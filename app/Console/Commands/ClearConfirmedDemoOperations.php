<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * One-shot cleanup of an explicitly confirmed fictitious ERP dataset.
 *
 * Critical: fiscal/SUNAT documents and their upstream references are excluded.
 * No schema, catalog, role, professional, cashier or voucher sequence is changed.
 */
class ClearConfirmedDemoOperations extends Command
{
    protected $signature = 'ceo:clear-confirmed-demo
        {--execute : Apply the plan in a single database transaction}
        {--confirm= : Must equal LIMPIAR-DATOS-FICTICIOS-CEO}';
    protected $description = 'Remove confirmed fictitious patient/appointment operations; preserve fiscal documents and configuration.';

    private const TABLES = [
        'appointment_admission_task_events', 'appointment_admission_tasks',
        'appointment_contingencies', 'appointment_credit_applications',
        'appointment_refund_requests', 'appointment_reevaluation_indications',
        'appointment_documents', 'appointment_events', 'appointment_operations',
        'voucher_items', 'payments', 'vouchers', 'appointments', 'responsibles',
        'patients', 'appointment_pilot_cash_contexts', 'cash_movements', 'cashier_shifts',
    ];

    private function ids(string $table, callable $modify = null): array
    {
        if (!Schema::hasTable($table)) return [];
        $q = DB::table($table);
        if ($modify) $modify($q);
        return $q->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function except(array $all, array $protected): array
    {
        return array_values(array_diff($all, $protected));
    }

    private function bound(string $name, int $size, int $maximum): void
    {
        if ($size > $maximum) throw new RuntimeException(
            "Alcance cambiado ($name=$size; máximo previsto $maximum). No se borró nada."
        );
    }

    private function plan(): array
    {
        foreach (['patients', 'appointments', 'vouchers', 'payments', 'cashier_shifts'] as $table) {
            if (!Schema::hasTable($table)) throw new RuntimeException("Falta tabla $table; sin cambios.");
        }

        $vouchers = DB::table('vouchers')->get([
            'id', 'parent_voucher_id', 'patient_id', 'paga_patient_id', 'cashier_shift_id',
            'tipo_comprobante', 'requiere_sunat', 'estado_sunat', 'sunat_hash',
            'sunat_respuesta', 'xml_path', 'cdr_path', 'sunat_enviado_en',
        ]);
        $this->bound('comprobantes', $vouchers->count(), 20);
        $protected = [];
        foreach ($vouchers as $v) {
            if ($v->tipo_comprobante !== 'TICKET' || (bool) $v->requiere_sunat
                || $v->estado_sunat !== 'NO_APLICA' || $v->sunat_hash !== null
                || $v->sunat_respuesta !== null || $v->xml_path !== null
                || $v->cdr_path !== null || $v->sunat_enviado_en !== null) {
                $protected[] = (int) $v->id;
            }
        }
        // A fiscal voucher's ticket/credit family must not be partially purged.
        do {
            $size = count($protected);
            foreach ($vouchers as $v) {
                if (in_array((int) $v->id, $protected, true)
                    && $v->parent_voucher_id !== null) $protected[] = (int) $v->parent_voucher_id;
                if ($v->parent_voucher_id !== null
                    && in_array((int) $v->parent_voucher_id, $protected, true)) $protected[] = (int) $v->id;
            }
            $protected = array_values(array_unique($protected));
        } while (count($protected) !== $size);

        $protectedAppointments = [];
        if ($protected) {
            $protectedAppointments = DB::table('voucher_items')
                ->whereIn('voucher_id', $protected)
                ->whereIn('item_type', ['cita', Appointment::class])
                ->pluck('item_id')->map(fn ($id) => (int) $id)->unique()->all();
        }
        $protectedPatients = [];
        foreach ($vouchers as $v) if (in_array((int) $v->id, $protected, true)) {
            if ($v->patient_id) $protectedPatients[] = (int) $v->patient_id;
            if ($v->paga_patient_id) $protectedPatients[] = (int) $v->paga_patient_id;
        }
        if ($protectedAppointments) {
            $protectedPatients = array_merge($protectedPatients, DB::table('appointments')
                ->whereIn('id', $protectedAppointments)->pluck('patient_id')
                ->map(fn ($id) => (int) $id)->all());
        }
        $protectedPatients = array_values(array_unique($protectedPatients));

        $allAppointments = $this->ids('appointments');
        $allPatients = $this->ids('patients');
        $allShifts = $this->ids('cashier_shifts');
        $this->bound('pacientes', count($allPatients), 5000);
        $this->bound('citas', count($allAppointments), 80);
        $this->bound('turnos de caja', count($allShifts), 100);

        $a = $this->except($allAppointments, $protectedAppointments);
        $p = $this->except($allPatients, $protectedPatients);
        $v = $this->except($vouchers->pluck('id')->map(fn ($id) => (int) $id)->all(), $protected);
        $protectedShifts = $vouchers->filter(fn ($i) => in_array((int) $i->id, $protected, true))
            ->pluck('cashier_shift_id')->map(fn ($id) => (int) $id)->unique()->all();
        $shifts = $this->except($allShifts, $protectedShifts);

        $plans = [];
        $plans['patients'] = $p;
        $plans['appointments'] = $a;
        $plans['vouchers'] = $v;
        $plans['payments'] = $this->ids('payments', fn ($q) => $q->whereIn('voucher_id', $v));
        $plans['voucher_items'] = $this->ids('voucher_items', fn ($q) => $q->whereIn('voucher_id', $v));
        $plans['appointment_documents'] = $this->ids('appointment_documents', fn ($q) => $q->whereIn('appointment_id', $a));
        $plans['appointment_events'] = $this->ids('appointment_events', fn ($q) => $q->whereIn('appointment_id', $a));
        $plans['appointment_operations'] = $this->ids('appointment_operations',
            fn ($q) => $q->whereIn('appointment_id', $a));
        $plans['appointment_admission_tasks'] = $this->ids('appointment_admission_tasks',
            fn ($q) => $q->whereIn('appointment_id', $a));
        $plans['appointment_admission_task_events'] = $this->ids('appointment_admission_task_events',
            fn ($q) => $q->whereIn('task_id', $plans['appointment_admission_tasks']));
        $plans['appointment_reevaluation_indications'] = $this->ids('appointment_reevaluation_indications',
            fn ($q) => $q->whereIn('source_appointment_id', $a)->orWhereIn('bound_appointment_id', $a));
        $plans['appointment_credit_applications'] = $this->ids('appointment_credit_applications',
            fn ($q) => $q->whereIn('source_appointment_id', $a)->orWhereIn('destination_appointment_id', $a)->orWhereIn('voucher_id', $v));
        $plans['appointment_refund_requests'] = $this->ids('appointment_refund_requests',
            fn ($q) => $q->whereIn('appointment_id', $a)->orWhereIn('voucher_id', $v));
        $plans['appointment_contingencies'] = $this->ids('appointment_contingencies',
            fn ($q) => $q->whereIn('appointment_id', $a)->orWhereIn('event_id', $plans['appointment_events']));
        $plans['responsibles'] = $this->ids('responsibles', fn ($q) => $q->whereIn('patient_id', $p));
        $plans['cashier_shifts'] = $shifts;
        $plans['cash_movements'] = $this->ids('cash_movements', fn ($q) => $q->whereIn('cashier_shift_id', $shifts));
        $plans['appointment_pilot_cash_contexts'] = $this->ids('appointment_pilot_cash_contexts',
            fn ($q) => $q->whereIn('cashier_shift_id', $shifts));

        $this->bound('pagos', count($plans['payments']), 50);
        foreach (self::TABLES as $table) $plans[$table] ??= [];
        return [$plans, ['vouchers' => count($protected), 'appointments' => count($protectedAppointments),
            'patients' => count($protectedPatients), 'cashier_shifts' => count($protectedShifts)]];
    }

    /**
     * No cascading or nulling of retained rows is permitted.
     * FKs without a selected child are treated as a blocker.
     */
    private function deletionOrder(array $planned): array
    {
        $edges = [];
        if (DB::connection()->getDriverName() === 'sqlite') {
            foreach (DB::select("SELECT name FROM sqlite_master WHERE type='table'") as $row) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $row->name)) continue;
                foreach (DB::select('PRAGMA foreign_key_list("'.$row->name.'")') as $foreign) {
                    $edges[] = (object) [
                        'child_table' => $row->name,
                        'child_column' => $foreign->from,
                        'parent_table' => $foreign->table,
                        'parent_column' => $foreign->to,
                    ];
                }
            }
        } else {
            $edges = DB::select("SELECT TABLE_NAME child_table, COLUMN_NAME child_column,
                    REFERENCED_TABLE_NAME parent_table, REFERENCED_COLUMN_NAME parent_column
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL");
        }

        $parents = [];
        foreach ($edges as $fk) {
            $parent = $fk->parent_table;
            if (!isset($planned[$parent]) || !$planned[$parent]) continue;
            if ($fk->parent_column !== 'id') {
                throw new RuntimeException("FK no estándar en $parent; deteniendo sin borrar.");
            }
            $child = $fk->child_table;
            $query = DB::table($child)->whereIn($fk->child_column, $planned[$parent]);
            if (isset($planned[$child]) && Schema::hasColumn($child, 'id')) {
                $query->whereNotIn('id', $planned[$child]);
            }
            if ($query->exists()) {
                throw new RuntimeException("Referencia retenida en $child → $parent; sin borrado. "
                    . "Se debe preservar primero la cadena vinculada.");
            }
            if (isset($planned[$child]) && $child !== $parent) $parents[$child][] = $parent;
        }

        $order = [];
        $state = [];
        $visit = function (string $table) use (&$visit, &$state, &$order, $parents): void {
            if (($state[$table] ?? 0) === 2) return;
            if (($state[$table] ?? 0) === 1) throw new RuntimeException('Relación circular: sin cambios.');
            $state[$table] = 1;
            foreach ($parents[$table] ?? [] as $parent) $visit($parent);
            $state[$table] = 2;
            $order[] = $table;
        };
        foreach (array_keys($planned) as $table) $visit($table);
        // Reverse dependency order: dependent children first.
        return array_reverse($order);
    }

    public function handle(): int
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql'], true)
            && !(app()->environment('testing') && DB::connection()->getDriverName() === 'sqlite')) {
            $this->error('Solo MySQL/MariaDB productivo está soportado. Sin cambios.');
            return self::FAILURE;
        }
        if (!$this->option('execute') || $this->option('confirm') !== 'LIMPIAR-DATOS-FICTICIOS-CEO') {
            $this->error('Este comando solo opera con --execute --confirm=LIMPIAR-DATOS-FICTICIOS-CEO.');
            return self::FAILURE;
        }

        try {
            $report = DB::transaction(function () {
                [$planned, $protected] = $this->plan();
                // Ensure that the fiscal exception is preserved, not silently nulled or cascaded.
                $order = $this->deletionOrder($planned);
                foreach ($order as $table) if ($planned[$table]) {
                    $deleted = DB::table($table)->whereIn('id', $planned[$table])->delete();
                    if ($deleted !== count($planned[$table])) {
                        throw new RuntimeException("Conteo inesperado en $table. Transacción revertida.");
                    }
                }
                return [$planned, $protected];
            }, 1);

            [$planned, $protected] = $report;
            $this->info('LIMPIEZA CONFIRMADA. La configuración, series y documentos SUNAT se conservaron.');
            foreach ($planned as $table => $ids) if ($ids) $this->line($table . ': ' . count($ids));
            $this->warn('Referencias fiscales conservadas: ' . json_encode($protected));
            $this->warn('Archivos privados y registros externos del llamador NO se borraron.');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('SIN LIMPIEZA: ' . $error->getMessage());
            $this->warn('La transacción se revirtió o no llegó a iniciar. Ningún borrado se da por confirmado.');
            return self::FAILURE;
        }
    }
}
