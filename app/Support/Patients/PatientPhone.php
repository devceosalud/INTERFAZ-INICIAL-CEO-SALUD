<?php

namespace App\Support\Patients;

/**
 * Compact dial prefixes for the patient phone field.
 * The stored value stays in patients.telefono. No extra column.
 */
class PatientPhone
{
    public const DEFAULT_PREFIX = '+51';

    public const PREFIXES = [
        '+51' => 'Perú',
        '+54' => 'Argentina',
        '+591' => 'Bolivia',
        '+55' => 'Brasil',
        '+56' => 'Chile',
        '+57' => 'Colombia',
        '+593' => 'Ecuador',
        '+34' => 'España',
        '+1' => 'Estados Unidos / Canadá',
        '+52' => 'México',
        '+595' => 'Paraguay',
        '+598' => 'Uruguay',
        '+58' => 'Venezuela',
    ];

    /**
     * @return array{parsed: bool, prefijo: string, numero: string, raw: string}
     */
    public static function split(?string $stored): array
    {
        $stored = trim((string) $stored);

        if ($stored === '') {
            return [
                'parsed' => true,
                'prefijo' => self::DEFAULT_PREFIX,
                'numero' => '',
                'raw' => '',
            ];
        }

        $compact = preg_replace('/[\s\-]+/', '', $stored) ?? $stored;

        foreach (self::prefixesByLength() as $prefix) {
            if (!str_starts_with($compact, $prefix)) {
                continue;
            }

            $number = substr($compact, strlen($prefix));

            if (preg_match('/^\d{6,15}$/', $number) === 1) {
                return [
                    'parsed' => true,
                    'prefijo' => $prefix,
                    'numero' => $number,
                    'raw' => '',
                ];
            }
        }

        return [
            'parsed' => false,
            'prefijo' => self::DEFAULT_PREFIX,
            'numero' => '',
            'raw' => $stored,
        ];
    }

    public static function compose(?string $prefix, ?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number) ?? '';

        if ($digits === '') {
            return null;
        }

        $prefix = self::normalizePrefix($prefix) ?? self::DEFAULT_PREFIX;

        return $prefix.$digits;
    }

    public static function normalizePrefix(?string $prefix): ?string
    {
        $prefix = trim((string) $prefix);

        if ($prefix === '') {
            return null;
        }

        if ($prefix[0] !== '+') {
            $prefix = '+'.$prefix;
        }

        return array_key_exists($prefix, self::PREFIXES) ? $prefix : null;
    }

    /**
     * @return list<string>
     */
    private static function prefixesByLength(): array
    {
        $prefixes = array_keys(self::PREFIXES);
        usort($prefixes, function (string $left, string $right): int {
            return strlen($right) <=> strlen($left);
        });

        return $prefixes;
    }
}
