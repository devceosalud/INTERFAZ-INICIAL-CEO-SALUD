<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The historical CREATE migration declares these, but inherited databases may lack them.
        foreach (['entidad_origen', 'entidad_destino'] as $column) {
            if (!Schema::hasColumn('payments', $column)) {
                Schema::table('payments', fn (Blueprint $t) => $t->string($column)->nullable());
            }
        }
        Schema::table('payments', function (Blueprint $t) {
            // SHA256 hex: 64 characters; no long composite index or collation-dependent identity.
            // NULL retains cash and unclassified historical receipts without any backfill.
            $t->char('bank_identity_key', 64)->nullable();
            $t->unique('bank_identity_key', 'payments_bank_key_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('payments')->whereNotNull('bank_identity_key')->exists()) {
            throw new RuntimeException('Rollback bloqueado: existen operaciones bancarias protegidas.');
        }
        if (DB::getDriverName() === 'sqlite' && version_compare(DB::selectOne('select sqlite_version() as v')->v, '3.35', '<')) {
            throw new RuntimeException('Rollback requiere SQLite >= 3.35; no se modificó el schema.');
        }
        Schema::table('payments', function (Blueprint $t) {
            $t->dropUnique('payments_bank_key_unique');
            $t->dropColumn('bank_identity_key');
        });
        // Never erase bank metadata; these columns may predate this migration.
    }
};
