<?php
namespace Tests\Feature\Scheduling;

use App\Models\Cashier;
use App\Models\CashierShift;
use App\Models\Voucher;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Support\Scheduling\SchedulingCapability as C;
use Spatie\Permission\Models\Permission;

class ManagementExportsIntegrationTest extends \Tests\Support\AgendaV2TestCase
{
    private function url(string $type, string $format='csv', array $extra=[]): string
    {
        return '/scheduling-mvp/agenda/reportes/'.$type.'.csv?'.http_build_query($extra+[
            'from'=>$this->a->fecha_cita,'to'=>$this->a->fecha_cita,'doctor_id'=>$this->a->doctor_id,'format'=>$format]);
    }
    private function payments(): void
    {
        $this->admin->givePermissionTo(Permission::findOrCreate(C::VIEW_AUDIT,'web'));
        $box=Cashier::create(['nombre'=>'Caja reporte ficticia','estado'=>'ACTIVO']);
        $shift=CashierShift::create(['cashier_id'=>$box->id,'user_id'=>$this->admin->id,'monto_apertura'=>0,'estado'=>'ABIERTO','abierto_en'=>now()]);
        $v=Voucher::create(['tipo_comprobante'=>'TICKET','serie'=>'RQ01','correlativo'=>1,'patient_id'=>$this->a->patient_id,'subtotal'=>100,'total'=>100,'estado'=>'PENDIENTE','cashier_shift_id'=>$shift->id,'user_id'=>$this->admin->id]);
        $v->items()->create(['item_type'=>'cita','item_id'=>$this->a->id,'descripcion'=>'Consulta ficticia','cantidad'=>1,'precio_unitario'=>100,'total'=>100]);
        foreach([['12.34','000001234'],['7.66','000005678']] as [$amount,$op])$v->payments()->create(['metodo_pago'=>'TRANSFERENCIA','entidad_origen'=>'BCP','numero_operacion'=>$op,'monto'=>$amount,'user_id'=>$this->admin->id,'cashier_shift_id'=>$shift->id]);
    }
    public function test_csv_and_xlsx_export_both_bank_operations_without_altering_economics(): void
    {
        $this->payments();$before=app(AppointmentEconomicPosition::class)->forAppointment($this->a->fresh());
        foreach(['citas','pagos'] as $type){
            $csv=$this->get($this->url($type))->assertOk()->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF",$csv);
            $this->assertStringContainsString('000001234',$csv);$this->assertStringContainsString('000005678',$csv);
            $lines=array_values(array_filter(explode("\n",trim(substr($csv,3)))));$columns=count(str_getcsv($lines[0],';'));
            foreach(array_slice($lines,1) as $line)$this->assertCount($columns,str_getcsv($line,';'));
            $this->assertCount($type==='citas'?2:3,$lines);
            $xlsx=$this->get($this->url($type,'xlsx'))->assertOk()->assertHeader('content-type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->streamedContent();
            $this->assertStringStartsWith('PK',$xlsx);$this->assertStringContainsString('<autoFilter ref="A1:',$xlsx);
            $this->assertStringContainsString('ySplit="1"',$xlsx);
            $this->assertStringContainsString('000001234',$xlsx);$this->assertStringContainsString('000005678',$xlsx);
            // Identifier cells must remain inline strings, preserving leading zeroes in Excel.
            $this->assertMatchesRegularExpression('/t="inlineStr"[^>]*><is><t[^>]*>[^<]*000001234/s',$xlsx);
            $this->assertStringNotContainsString('<f>',$xlsx);
        }
        $this->assertSame($before,app(AppointmentEconomicPosition::class)->forAppointment($this->a->fresh()));
        $this->assertDatabaseCount('payments',2);$this->assertDatabaseCount('appointment_events',0);
    }
    public function test_reports_keep_all_sites_fixed_and_reject_site_injection_and_unauthorized_roles(): void
    {
        $this->payments();$this->get('/scheduling-mvp/agenda/reportes')->assertOk()->assertSee('Todas las sedes')->assertDontSee('name="site_id"',false);
        foreach(['csv','xlsx'] as $format)foreach(['citas','pagos','slots'] as $type){
            $this->getJson($this->url($type,$format,['site_id'=>1]))->assertUnprocessable();
            foreach([$this->admission,$this->commercial] as $user){$this->actingAs($user)->getJson($this->url($type,$format))->assertForbidden();}
            $this->actingAs($this->admin);
        }
        $this->admin->revokePermissionTo(C::VIEW_AUDIT);$this->getJson($this->url('citas'))->assertForbidden();
    }
    public function test_legacy_snapshot_exports_no_fabricated_payment_and_keeps_columns_aligned(): void
    {
        $this->admin->givePermissionTo(Permission::findOrCreate(C::VIEW_AUDIT,'web'));
        $this->a->update(['total_pagado'=>100,'numero_operacion'=>'00009999']);
        $csv=$this->get($this->url('pagos'))->assertOk()->streamedContent();
        $rows=array_map(fn($line)=>str_getcsv($line,';'),array_values(array_filter(explode("\n",trim(substr($csv,3))))));
        $this->assertCount(2,$rows);$this->assertCount(count($rows[0]),$rows[1]);
        $mapped=array_combine($rows[0],$rows[1]);
        $this->assertSame('',$mapped['ID pago']);$this->assertSame('',$mapped['Número de operación del pago']);
        $this->assertStringContainsString('00009999',$mapped['Número de operación legado de la cita']);
        $this->assertStringContainsString('Snapshot heredado',$mapped['Nota de conciliación']);
        $this->assertDatabaseCount('payments',0);
    }
}
