<?php

namespace Tests\Unit\Scheduling;

use App\Support\Scheduling\ManagementReportPaymentStatus;
use PHPUnit\Framework\TestCase;

class ManagementReportPaymentStatusTest extends TestCase
{
    public function test_authorized_exoneration_is_displayed_as_exonerado_without_synthesizing_payment(): void
    {
        $position = [
            'financial_status' => ['key' => 'EXEMPT'],
            'paid_cents' => 0,
            'balance_cents' => 0,
        ];
        $this->assertSame('EXONERADO', ManagementReportPaymentStatus::display('PENDIENTE', $position));
        $this->assertSame(0, $position['paid_cents']);
    }

    public function test_unverified_exoneration_or_ambiguous_allocation_is_not_marked_exonerado(): void
    {
        $this->assertSame('PENDIENTE', ManagementReportPaymentStatus::display('PENDIENTE', [
            'financial_status' => ['key' => 'VALIDATION'],
        ]));
        $this->assertSame('PENDIENTE', ManagementReportPaymentStatus::display('PENDIENTE', []));
    }

    public function test_other_authorized_no_charge_conditions_are_distinguished(): void
    {
        $this->assertSame('SIN SALDO POR DESCUENTO AUTORIZADO', ManagementReportPaymentStatus::display('PENDIENTE', [
            'financial_status' => ['key' => 'AUTHORIZED_DISCOUNT'],
        ]));
        $this->assertSame('SIN COSTO POR REGLA CLÍNICA', ManagementReportPaymentStatus::display('PENDIENTE', [
            'financial_status' => ['key' => 'CLINICAL_FREE'],
        ]));
    }

    public function test_actual_payment_states_are_preserved(): void
    {
        $this->assertSame('PARCIAL', ManagementReportPaymentStatus::display('PARCIAL', [
            'financial_status' => ['key' => 'DUE'],
        ]));
        $this->assertSame('PAGADO', ManagementReportPaymentStatus::display('PAGADO', [
            'financial_status' => ['key' => 'PAID'],
        ]));
    }
}
