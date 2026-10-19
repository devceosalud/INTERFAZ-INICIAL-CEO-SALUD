<?php

namespace App\Support\Scheduling;

/**
 * CSV compatible con Excel en configuración regional peruana.
 * No almacena el reporte en disco y neutraliza fórmulas inyectadas en campos de texto.
 */
final class ManagementCsv
{
    public static function begin($handle, array $headers): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        self::row($handle, $headers);
    }

    public static function row($handle, array $cells): void
    {
        fputcsv($handle, array_map([self::class, 'safeCell'], $cells), ';', '"', '');
    }

    public static function safeCell($value): string
    {
        if ($value === null) {
            return '';
        }

        $text = (string) $value;
        // A negative decimal is safe, and management should still be able to SUM it in Excel.
        if (preg_match('/^-?[0-9]+(?:[.,][0-9]+)?$/D', $text) === 1) {
            return $text;
        }
        // Excel/LibreOffice may evaluate a formula even after whitespace or control characters.
        if (preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $text) === 1) {
            return "'".$text;
        }

        return $text;
    }

    public static function decimal($value): string
    {
        return $value === null || $value === '' ? '' : number_format((float) $value, 2, ',', '');
    }

    public static function identifier($value): string
    {
        return $value === null || $value === '' ? '' : "'".(string) $value;
    }

    public static function soles($cents): string
    {
        return number_format(((int) $cents) / 100, 2, ',', '');
    }
}
