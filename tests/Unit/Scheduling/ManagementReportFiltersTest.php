<?php

namespace Tests\Unit\Scheduling;

use PHPUnit\Framework\TestCase;

class ManagementReportFiltersTest extends TestCase
{
    public function test_long_periods_are_not_blocked_by_the_old_31_day_rule(): void
    {
        $view = file_get_contents(__DIR__.'/../../../resources/views/scheduling/agenda/management-report.blade.php');
        $controller = file_get_contents(__DIR__.'/../../../app/Http/Controllers/Scheduling/ManagementExportController.php');

        $this->assertStringNotContainsString('hasta 31 días consecutivos', $view);
        $this->assertStringNotContainsString('El rango máximo es de 31 días', $controller);
        $this->assertStringContainsString("for ($windowFrom = $from->copy();", $controller);
    }

    public function test_all_sites_is_fixed_and_native_doctor_control_is_used(): void
    {
        $view = file_get_contents(__DIR__.'/../../../resources/views/scheduling/agenda/management-report.blade.php');

        $this->assertStringContainsString('Todas las sedes</div>', $view);
        $this->assertStringNotContainsString('name="site_id"', $view);
        $this->assertStringContainsString('id="management-doctor" name="doctor_id"', $view);
        $this->assertStringContainsString("control.selectpicker('destroy')", $view);
        $this->assertStringContainsString('name="format" value="xlsx"', $view);
        $this->assertStringContainsString('name="format" value="csv"', $view);
    }
}
