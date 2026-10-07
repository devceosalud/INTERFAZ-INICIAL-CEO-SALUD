<?php

namespace App\Support\Patients;

use App\Models\Channel;

/**
 * Idempotent local/demo names for the capture-channel catalog.
 * Existing rows are left untouched, including inactive ones.
 */
class DemoChannelCatalog
{
    public const NAMES = [
        'Facebook',
        'TikTok',
        'Redes del Dr. Julio Quiroz',
    ];

    /**
     * @return list<string> names inserted on this call
     */
    public static function ensure(bool $force = false): array
    {
        if (!$force && !app()->environment('local')) {
            return [];
        }

        $created = [];

        foreach (self::NAMES as $name) {
            $channel = Channel::query()->firstOrCreate(
                ['nombre' => $name],
                ['estado' => 'ACTIVO']
            );

            if ($channel->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        return $created;
    }
}
