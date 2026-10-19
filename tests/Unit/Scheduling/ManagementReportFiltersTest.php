<?php

namespace Tests\Unit\Scheduling;

use PHPUnit\Framework\TestCase;

class ManagementReportFiltersTest extends TestCase
{
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
