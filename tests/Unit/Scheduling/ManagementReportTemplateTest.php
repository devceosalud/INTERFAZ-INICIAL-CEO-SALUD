<?php

namespace Tests\Unit\Scheduling;

use PHPUnit\Framework\TestCase;

class ManagementReportTemplateTest extends TestCase
{
    public function test_management_report_loads_the_legacy_shell_preloader_initializer(): void
    {
        $view = file_get_contents(__DIR__.'/../../../resources/views/scheduling/agenda/management-report.blade.php');

        $this->assertStringContainsString("@include('templates.preloader')", $view);
        $this->assertStringContainsString("@section('script_data')", $view);

        $assets = [
            'assets/vendor/global/global.min.js',
            'assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js',
            'assets/js/custom.min.js',
            'assets/js/deznav-init.js',
        ];

        $last = -1;
        foreach ($assets as $asset) {
            $position = strpos($view, $asset);
            $this->assertNotFalse($position, 'Falta asset requerido: '.$asset);
            $this->assertGreaterThan($last, $position, 'El orden de carga debe mantenerse');
            $last = $position;
        }
    }
}
