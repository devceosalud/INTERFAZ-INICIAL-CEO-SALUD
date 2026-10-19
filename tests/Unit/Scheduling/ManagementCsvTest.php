<?php

namespace Tests\Unit\Scheduling;

use App\Support\Scheduling\ManagementCsv;
use PHPUnit\Framework\TestCase;

class ManagementCsvTest extends TestCase
{
    public function test_it_neutralizes_formulas_even_after_whitespace(): void
    {
        foreach (['=SUM(1,2)', '+cmd', '-cmd', '@HYPERLINK', " \t=1+2", "\n=1"] as $unsafe) {
            $this->assertSame("'".$unsafe, ManagementCsv::safeCell($unsafe));
        }
        $this->assertSame('DNI 12345678', ManagementCsv::safeCell('DNI 12345678'));
        $this->assertSame('', ManagementCsv::safeCell(null));
    }

    public function test_it_writes_utf8_semicolon_csv_with_quotes(): void
    {
        $stream = fopen('php://temp', 'w+');
        ManagementCsv::begin($stream, ['Paciente', 'Motivo']);
        ManagementCsv::row($stream, ['Muñoz; Pérez', '=2+2']);
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('"Muñoz; Pérez";', $content);
        $this->assertStringContainsString("'=2+2", $content);
        $this->assertSame('123,45', ManagementCsv::soles(12345));
    }
}
