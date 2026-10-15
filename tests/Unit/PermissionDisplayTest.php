<?php

namespace Tests\Unit;

use App\Support\Authorization\PermissionDisplay;
use PHPUnit\Framework\TestCase;

class PermissionDisplayTest extends TestCase
{
    /** @dataProvider businessPermissions */
    public function test_business_facing_labels_are_clear_without_renaming_permissions(
        string $key, string $label
    ): void {
        $this->assertSame($label, PermissionDisplay::label($key));
        $this->assertNotSame($key, PermissionDisplay::label($key));
        $this->assertNotEmpty(PermissionDisplay::description($key));
    }

    public static function businessPermissions(): iterable
    {
        yield ['appointment.hold.create', 'Crear retención temporal de horario'];
        yield ['appointment.hold.extend', 'Ampliar retención temporal de horario'];
        yield ['appointment.payment.submit', 'Registrar adelantos y pagos'];
        yield ['appointment.payment.verify', 'Verificar pagos'];
        yield ['appointment.down_payment.override', 'Autorizar excepción al adelanto mínimo'];
        yield ['appointment.zero_cost.request', 'Solicitar cita sin costo'];
        yield ['appointment.zero_cost.approve', 'Aprobar cita sin costo'];
        yield ['doctor.schedule.manage', 'Gestionar horarios médicos'];
    }

    public function test_unknown_permission_remains_distinguishable_and_does_not_claim_business_authority(): void
    {
        $this->assertSame('Future feature manage', PermissionDisplay::label('future.feature.manage'));
        $this->assertStringContainsString(
            'pendiente de descripción funcional',
            PermissionDisplay::description('future.feature.manage')
        );
    }
}
