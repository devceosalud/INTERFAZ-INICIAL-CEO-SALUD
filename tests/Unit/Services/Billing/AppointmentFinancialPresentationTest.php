<?php
namespace Tests\Unit\Services\Billing;
use App\Models\Appointment;
use App\Services\Billing\AppointmentEconomicPosition as Economics;
use PHPUnit\Framework\TestCase;
class AppointmentFinancialPresentationTest extends TestCase
{
    /** @dataProvider cases */
    public function test_read_only_classification_never_equates_zero_with_payment(array $attrs,array $extra,bool $clinical,string $expected):void {
        $a=new Appointment($attrs);$before=$a->getAttributes();
        $position=$extra+['price_cents'=>10000,'balance_cents'=>10000,'allocation_required'=>false,'authority'=>'PAYMENTS','paid_cents'=>0,'effective_cents'=>0,'credit_cents'=>0];
        $this->assertSame($expected,Economics::financialStatus($a,$position,$clinical)['key']);$this->assertSame($before,$a->getAttributes());
    }
    public static function cases():array{return [
        'due'=>[[],[],false,'DUE'],
        'partial'=>[[],['paid_cents'=>3000,'effective_cents'=>3000,'balance_cents'=>7000],false,'DUE'],
        'paid'=>[[],['paid_cents'=>10000,'effective_cents'=>10000,'balance_cents'=>0],false,'PAID'],
        'exempt'=>[['es_exonerado'=>true,'autorizado_por'=>'Autorización ficticia'],['balance_cents'=>0],false,'EXEMPT'],
        'no authority'=>[['es_exonerado'=>true,'autorizado_por'=>'   '],['price_cents'=>0,'balance_cents'=>0],false,'VALIDATION'],
        'zero'=>[[],['price_cents'=>0,'balance_cents'=>0],false,'VALIDATION'],
        'clinical evidence'=>[[],['price_cents'=>0,'balance_cents'=>0],true,'CLINICAL_FREE'],
        'total historical authorized'=>[['es_exonerado'=>true,'autorizado_por'=>'Autorización ficticia','catalog_price'=>100,'discount_amount'=>100,'discount_actor_user_id'=>1,'discount_reason'=>'Histórico autorizado'],['price_cents'=>0,'balance_cents'=>0],false,'AUTHORIZED_DISCOUNT'],
        'total no authorization'=>[['catalog_price'=>100,'discount_amount'=>100,'discount_actor_user_id'=>1,'discount_reason'=>'Sin autorización'],['price_cents'=>0,'balance_cents'=>0],false,'VALIDATION'],
        'ambiguous'=>[['es_exonerado'=>true,'autorizado_por'=>'Ficticio'],['allocation_required'=>true],false,'VALIDATION'],
        'credit'=>[[],['credit_cents'=>10000,'balance_cents'=>0],false,'CREDIT'],
        'legacy full'=>[[],['authority'=>'LEGACY_SNAPSHOT','paid_cents'=>10000,'balance_cents'=>0],false,'VALIDATION']
    ];}
}
