<?php
namespace Tests\Feature\Sales;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BankPaymentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_additive_migration_handles_inherited_missing_bank_columns_without_backfill(): void
    {
        Schema::drop('payments');
        Schema::create('payments', function (Blueprint $t) { $t->id(); $t->decimal('monto', 10, 2); });
        DB::table('payments')->insert([['monto' => 10], ['monto' => 20]]);
        $migration = require database_path('migrations/2026_10_07_140000_add_bank_identity_to_payments_table.php');
        $migration->up();
        $this->assertTrue(Schema::hasColumns('payments', ['entidad_origen', 'entidad_destino', 'bank_identity_key']));
        $this->assertEquals(30, DB::table('payments')->sum('monto'));
        $this->assertSame(2, DB::table('payments')->whereNull('bank_identity_key')->whereNull('entidad_origen')->count());
        DB::table('payments')->where('id', 1)->update(['bank_identity_key' => str_repeat('a', 64)]);
        try { $migration->down(); $this->fail('Rollback must retain protected receipts.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Rollback bloqueado', $e->getMessage()); }
        $this->assertSame(2, DB::table('payments')->count());
        $this->assertTrue(Schema::hasColumn('payments', 'bank_identity_key'));
    }
}
