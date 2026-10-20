<?php

namespace Tests\Unit\Scheduling;

use App\Support\Scheduling\ManagementCsv;
use App\Support\Scheduling\ManagementXlsx;
use PHPUnit\Framework\TestCase;

class ManagementXlsxTest extends TestCase
{
    public function test_generates_filterable_workbook_with_frozen_headers_and_safe_values(): void
    {
        $csv = fopen('php://temp', 'w+b');
        $xlsx = fopen('php://temp', 'w+b');

        ManagementCsv::begin($csv, [
            'ID cita', 'Motivo consulta', 'Pago acreditado o snapshot S/',
            'Documento paciente', 'Porcentaje ocupación del día',
        ]);
        ManagementCsv::row($csv, [
            9, '=HYPERLINK("https://invalid.test","x")', '32,50',
            ManagementCsv::identifier('00123456'), '66,67',
        ]);

        try {
            ManagementXlsx::fromCsv($csv, $xlsx, 'Reporte de prueba');
            rewind($xlsx);
            $data = stream_get_contents($xlsx);
        } finally {
            fclose($csv);
            fclose($xlsx);
        }

        $this->assertStringStartsWith("PK\x03\x04", $data);
        // ZIP is deliberately stored; sheet markup is directly inspectable in this test.
        $this->assertStringContainsString('<autoFilter ref="A1:E2"/>', $data);
        $this->assertStringContainsString('ySplit="1"', $data);
        $this->assertStringContainsString('sheet name="Reporte de prueba"', $data);
        $this->assertStringContainsString('<v>32.50</v>', $data);
        $this->assertStringContainsString('<v>66.67</v>', $data);
        $this->assertStringContainsString('>00123456</t>', $data);
        $this->assertStringContainsString('>=HYPERLINK', $data);
        $this->assertStringNotContainsString('<f>', $data);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ManagementXlsx::contentType()
        );
    }

    public function test_allows_header_only_export(): void
    {
        $csv = fopen('php://temp', 'w+b');
        $xlsx = fopen('php://temp', 'w+b');
        ManagementCsv::begin($csv, ['ID cita', 'Motivo consulta']);

        ManagementXlsx::fromCsv($csv, $xlsx);
        rewind($xlsx);
        $contents = stream_get_contents($xlsx);
        fclose($csv);
        fclose($xlsx);

        $this->assertStringContainsString('<autoFilter ref="A1:B1"/>', $contents);
    }
}
