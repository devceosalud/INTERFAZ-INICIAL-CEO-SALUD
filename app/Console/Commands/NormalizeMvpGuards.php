<?php
namespace App\Console\Commands;

use App\Support\Scheduling\MvpGuardNormalization;
use Illuminate\Console\Command;

class NormalizeMvpGuards extends Command
{
    protected $signature = 'agenda:mvp-guards {--role=* : Nombre exacto de rol aprobado}
        {--permission-id=* : ID explícito de permiso vacío aprobado}
        {--apply : Normalizar solamente los guards vacíos seleccionados}
        {--confirm= : SHA256 de plan; para rollback, after.state_hash}
        {--reference= : Referencia de aprobación, sin datos personales}
        {--rollback= : UUID del recibo de normalización}';
    protected $description = 'Audita guards; previa por defecto y reparación web explícita, reversible y sin grants';

    public function handle(MvpGuardNormalization $normalization): int
    {
        try {
            $names = $this->option('role'); $ids = $this->option('permission-id');
            foreach ($ids as $id) {
                if (!ctype_digit((string) $id) || (int) $id < 1) { throw new \InvalidArgumentException('Usa IDs positivos de permisos existentes.'); }
            }
            if ($this->option('apply') || $this->option('rollback')) {
                if (!preg_match('/\A[a-f0-9]{64}\z/', (string) $this->option('confirm'))
                    || !preg_match('/\A[A-Za-z0-9_.:-]{3,120}\z/', (string) $this->option('reference'))) {
                    throw new \InvalidArgumentException('Indica huella revisada y referencia de autorización.');
                }
                if ($this->option('apply') && $this->option('rollback')) { throw new \InvalidArgumentException('No combines apply y rollback.'); }
                if ($this->option('rollback') && ($names || $ids)) { throw new \InvalidArgumentException('Rollback utiliza únicamente los objetivos del recibo.'); }
                $result = $this->option('rollback')
                    ? $normalization->rollback($this->option('rollback'), $this->option('confirm'), $this->option('reference'))
                    : $normalization->apply($names, $ids, $this->option('confirm'), $this->option('reference'));
            } else {
                $result = $normalization->plan($names, $ids) + ['read_only' => true];
            }
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        } catch (\Throwable $e) {
            $this->error($e->getMessage()); return 1;
        }
    }
}
