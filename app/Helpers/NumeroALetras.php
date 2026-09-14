<?php
// app/Helpers/NumeroALetras.php

namespace App\Helpers;

class NumeroALetras
{
    private static array $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
    private static array $decenas = ['DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
    private static array $decenasCompletas = ['', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    private static array $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    public static function convertir(float $numero, string $moneda = 'SOLES'): string
    {
        $entero = (int) floor($numero);
        $decimal = (int) round(($numero - $entero) * 100);

        // CERO es un caso especial que las reglas normales no cubren bien
        $texto = $entero === 0 ? 'CERO' : self::convertirEntero($entero);

        return sprintf('%s Y %02d/100 %s', trim($texto), $decimal, $moneda);
    }

    private static function convertirEntero(int $n): string
    {
        if ($n < 10) return self::$unidades[$n];
        if ($n < 20) return self::$decenas[$n - 10];
        if ($n < 30) return $n === 20 ? 'VEINTE' : 'VEINTI' . strtolower(self::$unidades[$n - 20]);
        if ($n < 100) {
            $d = intdiv($n, 10);
            $u = $n % 10;
            return self::$decenasCompletas[$d] . ($u > 0 ? ' Y ' . self::$unidades[$u] : '');
        }
        if ($n === 100) return 'CIEN';
        if ($n < 1000) {
            $c = intdiv($n, 100);
            $resto = $n % 100;
            return self::$centenas[$c] . ($resto > 0 ? ' ' . self::convertirEntero($resto) : '');
        }
        if ($n < 1000000) {
            $miles = intdiv($n, 1000);
            $resto = $n % 1000;
            $prefijoMiles = $miles === 1 ? 'MIL' : self::convertirEntero($miles) . ' MIL';
            return $prefijoMiles . ($resto > 0 ? ' ' . self::convertirEntero($resto) : '');
        }

        // Para montos de clínica esto es más que suficiente —
        // millones no debería aparecer nunca en una boleta médica.
        return (string) $n;
    }
}