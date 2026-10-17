<?php
namespace App\Support\Scheduling;

/** Limits fit the existing TEXT columns, including four-byte UTF-8 characters. */
final class OperationalText
{
    public const REASON_MAX = 2000;
    public const OBSERVATION_MAX = 16000;

    public static function trim(string $text): string
    {
        return preg_replace('/\A[\s\p{Z}\x{200B}\x{FEFF}]+|[\s\p{Z}\x{200B}\x{FEFF}]+\z/u', '', $text) ?? trim($text);
    }
}
