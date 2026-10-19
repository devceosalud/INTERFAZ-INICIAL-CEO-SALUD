<?php

namespace Tests\Unit\Scheduling;

use App\Support\Scheduling\ManagementCsv;
use App\Support\Scheduling\ManagementPaymentOperationNumbers;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ManagementPaymentOperationNumbersTest extends TestCase
{
    public function test_collects_unique_numbers_from_attributed_payments_only(): void
    {
        $payments = new Collection([
            1 => (object) ['numero_operacion' => '000123'],
            2 => (object) ['numero_operacion' => ' 900456 '],
            3 => (object) ['numero_operacion' => '000123'],
            4 => (object) ['numero_operacion' => ''],
            5 => (object) ['numero_operacion' => null],
        ]);

        $numbers = ManagementPaymentOperationNumbers::forDetails([
            ['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5],
            ['id' => 999], // Unattributed/unavailable: never fabricate an operation.
        ], $payments);

        $this->assertSame('000123 | 900456', $numbers);
        $this->assertSame("'000123 | 900456", ManagementCsv::identifier($numbers));
    }

    public function test_leaves_empty_for_ambiguous_or_legacy_positions(): void
    {
        $payments = new Collection([1 => (object) ['numero_operacion' => '000123']]);
        $this->assertSame('', ManagementPaymentOperationNumbers::forDetails([], $payments));
        $this->assertSame('', ManagementPaymentOperationNumbers::forDetails([['id' => 99]], $payments));
        $this->assertSame('', ManagementCsv::identifier(''));
    }

    public function test_operation_numbers_keep_leading_zeros_in_xlsx_and_are_not_formulas(): void
    {
        $csv = fopen('php://temp', 'w+b');
        $xlsx = fopen('php://temp', 'w+b');
        ManagementCsv::begin($csv, ['Número de operación del pago', 'Número(s) de operación de pagos atribuidos']);
        ManagementCsv::row($csv, [ManagementCsv::identifier('000123'), ManagementCsv::identifier('000123 | 005678')]);

        try {
            \App\Support\Scheduling\ManagementXlsx::fromCsv($csv, $xlsx);
            rewind($xlsx);
            $content = stream_get_contents($xlsx);
        } finally {
            fclose($csv);
            fclose($xlsx);
        }

        $this->assertStringContainsString('>000123</t>', $content);
        $this->assertStringContainsString('>000123 | 005678</t>', $content);
        $this->assertStringNotContainsString('<f>', $content);
    }

    public function test_excel_formula_like_number_is_exported_as_text(): void
    {
        $payments = new Collection([7 => (object) ['numero_operacion' => '=HYPERLINK("bad")']]);
        $raw = ManagementPaymentOperationNumbers::forDetails([['id' => 7]], $payments);
        $this->assertSame('\'=HYPERLINK("bad")', ManagementCsv::identifier($raw));
    }
}
