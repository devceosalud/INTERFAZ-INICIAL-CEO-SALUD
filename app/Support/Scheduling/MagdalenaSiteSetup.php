<?php
namespace App\Support\Scheduling;

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Explicit catalogue setup only. Never assigns a site to existing appointments or schedules. */
final class MagdalenaSiteSetup
{
    public const NAME = 'Magdalena del Mar';

    public function plan(string $code, bool $lock = false): array
    {
        $this->validateCode($code);
        $this->schema($lock);
        $query = DB::table('sites')->orderBy('id');
        if ($lock) $query->lockForUpdate();
        $sites = $query->get(['id','codigo','nombre','estado','created_at','updated_at'])->map(fn ($s) => (array) $s)->all();
        $counts = [];
        foreach (['appointments','doctor_schedules'] as $table) {
            $counts[$table] = DB::table($table)->select('site_id', DB::raw('COUNT(*) AS total'))
                ->groupBy('site_id')->orderBy('site_id')->get()->map(fn ($r) => ['site_id'=>$r->site_id, 'total'=>(int)$r->total])->all();
        }
        $matches = array_values(array_filter($sites, fn ($s) =>
            $this->normalized($s['nombre']) === $this->normalized(self::NAME)
            || strtoupper(trim($s['codigo'])) === $code));
        $blockers = [];
        $target = count($matches) === 1 ? $matches[0] : null;
        if (count($matches) > 1) $blockers[] = 'MAGDALENA_AMBIGUA';
        if ($target && ($this->normalized($target['nombre']) !== $this->normalized(self::NAME)
            || $target['estado'] !== 'ACTIVO')) $blockers[] = 'SEDE_EXISTENTE_INCOMPATIBLE';
        if (collect($sites)->contains(fn ($s) => $s['estado'] === 'ACTIVO' && (!$target || $s['id'] !== $target['id']))) {
            $blockers[] = 'OTRA_SEDE_OPERATIVA_REQUIERE_REVISION';
        }
        $knownIds=array_map(fn($s)=>(int)$s['id'],$sites);
        foreach($counts as $rows)foreach($rows as $row){
            if($row['site_id']!==null && !in_array((int)$row['site_id'],$knownIds,true))$blockers[]='REFERENCIA_SEDE_HUERFANA';
        }
        $blockers=array_values(array_unique($blockers));
        $state = ['sites'=>$sites, 'relation_counts'=>$counts,
            'money_counts'=>['payments'=>DB::table('payments')->count(), 'vouchers'=>DB::table('vouchers')->count()],
            'database_fingerprint'=>$this->databaseFingerprint()];
        $plan = ['requested_code'=>$code, 'name'=>self::NAME, 'action'=>$target ? 'REUSE' : 'CREATE',
            'target_id'=>$target['id'] ?? null, 'catalogue'=>$sites, 'counts'=>$counts,'money_counts'=>$state['money_counts'],
            'blockers'=>$blockers, 'state_hash'=>$this->hash($state)];
        $plan['plan_hash'] = $this->hash($plan);
        return $plan;
    }

    public function apply(string $code, string $expected, int $actorId, string $reference): array
    {
        $this->approval($expected, $reference);
        $id = (string) Str::uuid();
        try {
            $receipt = DB::transaction(function () use ($code,$expected,$actorId,$reference,$id) {
                $this->actor($actorId);
                $before = $this->plan($code, true);
                if (!hash_equals($before['plan_hash'], $expected)) throw new \RuntimeException('El catálogo o sus conteos cambiaron; genera otra vista previa.');
                if ($before['blockers']) throw new \RuntimeException('La vista previa tiene incompatibilidades; no se modifica ninguna sede.');
                // Reuse a real matching site without changing its existing code, ID or timestamps.
                $created = $before['action'] === 'CREATE';
                $site = $created ? Site::create(['codigo'=>$code,'nombre'=>self::NAME,'estado'=>'ACTIVO']) : Site::findOrFail($before['target_id']);
                $receipt = ['id'=>$id, 'kind'=>'MAGDALENA_SITE_SETUP', 'status'=>'APPLIED', 'created'=>$created,
                    'actor_user_id'=>$actorId, 'approval_reference'=>$reference, 'occurred_at_utc'=>now('UTC')->toIso8601String(),
                    'database_fingerprint'=>$this->databaseFingerprint(), 'site_id'=>$site->id,
                    'site'=>$site->fresh()->getAttributes(), 'before'=>$before, 'after'=>$this->plan($code)];
                $this->save($receipt);
                return $receipt;
            }, 3);
        } catch (\Throwable $e) {
            // The UUID/path belongs only to this attempted setup. Never remove an existing receipt.
            Storage::disk('local')->delete($this->path($id));
            throw $e;
        }
        Log::notice('mvp.site.setup', ['receipt_id'=>$id,'actor_user_id'=>$actorId,'site_id'=>$receipt['site_id'],
            'created'=>$receipt['created'],'approval_reference'=>$reference]);
        return $receipt;
    }

    public function rollback(string $id, string $expected, int $actorId, string $reference): array
    {
        $this->approval($expected, $reference);
        $receipt = $this->read($id);
        if (!hash_equals($receipt['database_fingerprint'], $this->databaseFingerprint())) throw new \RuntimeException('Recibo de otro entorno.');
        $result = DB::transaction(function () use ($receipt,$expected,$actorId,$reference) {
            $this->actor($actorId);
            if ($receipt['status'] === 'ROLLED_BACK') return $receipt;
            if ($receipt['status'] !== 'APPLIED' || !$receipt['created']) throw new \RuntimeException('No se elimina una sede preexistente o un recibo no aplicado.');
            $current = $this->plan($receipt['before']['requested_code'], true);
            if (!hash_equals($current['state_hash'], $expected)
                || !hash_equals($receipt['after']['state_hash'], $current['state_hash'])) {
                throw new \RuntimeException('Catálogo, citas, horarios o conteos financieros cambiaron; conserva la sede y revisa sin forzar.');
            }
            $site = Site::whereKey($receipt['site_id'])->lockForUpdate()->firstOrFail();
            if ($site->appointments()->exists() || $site->doctorSchedules()->exists()) throw new \RuntimeException('La sede tiene relaciones; no se elimina.');
            if ($site->getAttributes() !== $receipt['site']) throw new \RuntimeException('La sede cambió después del alta.');
            $site->delete(); // Only the created, unchanged, entirely unused row; foreign keys also prevent a competing assignment.
            $receipt['status'] = 'ROLLED_BACK';
            $receipt['rollback_actor_user_id'] = $actorId;
            $receipt['rollback_reference'] = $reference;
            $receipt['rolled_back_at_utc'] = now('UTC')->toIso8601String();
            $this->save($receipt);
            return $receipt;
        }, 3);
        Log::notice('mvp.site.rollback', ['receipt_id'=>$id,'actor_user_id'=>$actorId,'site_id'=>$receipt['site_id'],
            'approval_reference'=>$reference]);
        return $result;
    }

    private function actor(int $id): void
    {
        $actor = User::whereKey($id)->lockForUpdate()->first();
        if (!$actor || !$actor->hasRole('ADMINISTRADOR','web')) throw new \RuntimeException('Se requiere un responsable ADMINISTRADOR con guard web normalizado.');
        // Stable lock exists even when sites is empty, including READ COMMITTED and distinct administrators.
        $roles=DB::table('roles')->where('name','ADMINISTRADOR')->where('guard_name','web')->lockForUpdate()->get();
        if($roles->count()!==1)throw new \RuntimeException('Rol ADMINISTRADOR web ambiguo; no se modifica el catálogo.');
    }

    private function schema(bool $lock): void
    {
        if (!$lock) return;
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite' && app()->environment('testing')) return;
        if ($driver !== 'mysql') throw new \RuntimeException('Motor no verificado para esta operación.');
        $tables = DB::select("SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('sites','appointments','doctor_schedules')");
        if (count($tables)!==3 || collect($tables)->contains(fn($r)=>strtoupper($r->engine??'')!=='INNODB')) {
            throw new \RuntimeException('Las tablas de sede y relaciones requieren InnoDB.');
        }
        $indexes = DB::select("SELECT INDEX_NAME AS idx, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
            FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sites' AND NON_UNIQUE=0 GROUP BY INDEX_NAME");
        if (!collect($indexes)->contains(fn($r)=>$r->cols==='codigo')) throw new \RuntimeException('Falta UNIQUE(codigo); no se altera el esquema.');
        $links = DB::select("SELECT TABLE_NAME AS child, COLUMN_NAME AS col, REFERENCED_COLUMN_NAME AS parent_col
            FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='sites'");
        $expected = ['appointments.site_id.id','doctor_schedules.site_id.id'];
        $actual = collect($links)->map(fn($r)=>$r->child.'.'.$r->col.'.'.$r->parent_col)->sort()->values()->all();
        sort($expected);
        if ($actual !== $expected) throw new \RuntimeException('Relaciones de sede distintas de las verificadas; revisar sin modificar.');
    }

    private function approval(string $hash, string $reference): void
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/',$hash) || !preg_match('/\A[A-Za-z0-9_.:-]{3,120}\z/',$reference)) {
            throw new \InvalidArgumentException('Indica huella revisada y referencia de autorización sin datos personales.');
        }
        if (!config('app.key')) throw new \RuntimeException('La clave de aplicación es necesaria para firmar el recibo privado.');
    }

    private function validateCode(string $code): void
    {
        if (!preg_match('/\A[A-Z0-9][A-Z0-9_-]{2,31}\z/',$code)) throw new \InvalidArgumentException('Indica un código estable aprobado de 3–32 caracteres en mayúsculas.');
    }

    private function normalized(string $value): string { return mb_strtoupper(trim(preg_replace('/\s+/u',' ',$value)),'UTF-8'); }
    private function hash(array $data): string { return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)); }
    private function databaseFingerprint(): string
    {
        $c = DB::connection()->getConfig();
        return $this->hash(['driver'=>DB::connection()->getDriverName(),'database'=>$c['database']??null,'host'=>$c['host']??null,'port'=>$c['port']??null]);
    }
    private function path(string $id): string
    {
        if (!Str::isUuid($id)) throw new \InvalidArgumentException('Recibo inválido.');
        return 'private/magdalena-site-receipts/'.$id.'.json';
    }
    private function save(array $receipt): void
    {
        $receipt['signature'] = hash_hmac('sha256', json_encode($receipt,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),config('app.key'));
        if (!Storage::disk('local')->put($this->path($receipt['id']),json_encode($receipt,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))) {
            throw new \RuntimeException('No se conservó el recibo privado; se revierte la transacción.');
        }
    }
    private function read(string $id): array
    {
        $receipt = json_decode(Storage::disk('local')->get($this->path($id)),true,512,JSON_THROW_ON_ERROR);
        $signature = $receipt['signature'] ?? ''; unset($receipt['signature']);
        if (!is_string($signature) || !hash_equals(hash_hmac('sha256',json_encode($receipt,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),config('app.key')),$signature)
            || ($receipt['kind']??'')!=='MAGDALENA_SITE_SETUP' || ($receipt['id']??'')!==$id) throw new \RuntimeException('Recibo no válido o modificado.');
        return $receipt;
    }
}
