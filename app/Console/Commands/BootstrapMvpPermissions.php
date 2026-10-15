<?php
namespace App\Console\Commands;

use App\Support\Scheduling\MvpPermissionBootstrap;
use Illuminate\Console\Command;

class BootstrapMvpPermissions extends Command
{
    protected $signature = 'agenda:mvp-permissions {--role=* : Nombres web aprobados; vacío incluye todos los existentes}
        {--apply : Añadir solamente los permisos ordinarios faltantes}
        {--confirm= : Huella exacta del plan revisado}
        {--reference= : Referencia de autorización, sin información personal}
        {--rollback= : UUID de recibo aplicado; no restaura datos financieros}';
    protected $description = 'Vista previa por defecto; asignación MVP aditiva, idempotente y auditada';

    public function handle(MvpPermissionBootstrap $bootstrap): int
    {
        try {
            $names = array_values(array_unique($this->option('role')));
            if ($this->option('apply') || $this->option('rollback')) {
                if (!preg_match('/\A[a-f0-9]{64}\z/', (string) $this->option('confirm'))
                    || !preg_match('/\A[A-Za-z0-9_.:-]{3,120}\z/', (string) $this->option('reference'))) {
                    throw new \InvalidArgumentException('Indica --confirm con la huella revisada y --reference con la autorización.');
                }
                if ($this->option('apply') && $this->option('rollback')) { throw new \InvalidArgumentException('No combines apply y rollback.'); }
                $result = $this->option('rollback')
                    ? $bootstrap->rollback($this->option('rollback'), $this->option('confirm'), $this->option('reference'))
                    : $bootstrap->apply($names, $this->option('confirm'), $this->option('reference'));
            } else { $result = $bootstrap->plan($names) + ['read_only' => true]; }
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        } catch (\Throwable $e) { $this->error($e->getMessage()); return 1; }
    }
}
