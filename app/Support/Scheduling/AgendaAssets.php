<?php

namespace App\Support\Scheduling;

/** Content versions are evaluated from deployed files, independent of timestamps/build tooling. */
final class AgendaAssets
{
    public static function url(string $path): string
    {
        $file = public_path($path);
        if (!is_file($file)) { throw new \RuntimeException('Missing Agenda asset: '.$path); }
        $digest = hash_file('sha256', $file);
        if ($digest === false) { throw new \RuntimeException('Missing Agenda asset: '.$path); }
        return asset($path).'?v='.substr($digest, 0, 20);
    }
}
