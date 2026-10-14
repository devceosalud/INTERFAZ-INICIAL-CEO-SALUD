<?php
namespace App\Console\Commands;

use App\Support\Scheduling\MagdalenaSiteSetup;
use Illuminate\Console\Command;

class SetupMagdalenaSite extends Command
{
    protected $signature = 'agenda:magdalena-site
        {--code= : Código estable aprobado, no un ID}
        {--apply : Crear o reutilizar exclusivamente Magdalena}
        {--confirm= : plan_hash revisado; rollback usa after.state_hash}
        {--actor-id= : ID de ADMINISTRADOR responsable}
        {--reference= : Referencia de aprobación sin datos personales}
        {--rollback= : UUID del recibo de alta nunca utilizada}';
    protected $description = 'Alta controlada de Magdalena: solo lectura por defecto, sin backfill ni permisos';

    public function handle(MagdalenaSiteSetup $setup): int
    {
        try {
            if ($this->option('apply') && $this->option('rollback')) throw new \InvalidArgumentException('No combines apply y rollback.');
            if ($this->option('apply') || $this->option('rollback')) {
                $actor = (string)$this->option('actor-id');
                if (!ctype_digit($actor) || (int)$actor<1) throw new \InvalidArgumentException('Indica el ID del responsable autorizado.');
                if ($this->option('rollback') && $this->option('code')) throw new \InvalidArgumentException('Rollback utiliza solo la sede del recibo.');
                $result = $this->option('rollback')
                    ? $setup->rollback((string)$this->option('rollback'),(string)$this->option('confirm'),(int)$actor,(string)$this->option('reference'))
                    : $setup->apply((string)$this->option('code'),(string)$this->option('confirm'),(int)$actor,(string)$this->option('reference'));
            } else {
                $result = $setup->plan((string)$this->option('code')) + ['read_only'=>true];
            }
            $this->line(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            return 0;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
