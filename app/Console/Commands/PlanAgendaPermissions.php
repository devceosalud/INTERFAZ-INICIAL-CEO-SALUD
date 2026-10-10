<?php
namespace App\Console\Commands;

use App\Models\User;
use App\Support\Scheduling\AgendaPermissionPlan;
use Illuminate\Console\Command;

class PlanAgendaPermissions extends Command
{
    protected $signature = 'agenda:permission-plan {user : ID del operador nominado} {--profile=reserve : reserve o advance}';
    protected $description = 'Plan nominativo de solo lectura para Agenda; no cambia permisos ni roles';
    public function handle(AgendaPermissionPlan $plans): int
    {
        $id = (string) $this->argument('user');
        if (!ctype_digit($id) || (int) $id < 1) { $this->error('Indica un ID de usuario positivo.'); return 1; }
        $user = User::find($id);
        if (!$user) { $this->error('Usuario no encontrado.'); return 1; }
        try { $plan = $plans->forUser($user, (string) $this->option('profile')); }
        catch (\InvalidArgumentException $e) { $this->error($e->getMessage()); return 1; }
        $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return 0;
    }
}
