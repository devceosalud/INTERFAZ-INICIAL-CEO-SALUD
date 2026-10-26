<?php
namespace App\Services\WaitingRoom;

final class InstallationConfiguration
{
    public function read(): array
    {
        $path = config('waiting_room.configuration_file');
        if (!is_string($path) || !is_file($path)) abort(503, 'Integration unavailable');
        $resolved=strtolower(str_replace(chr(92),'/',(string)realpath($path)));$webRoot=strtolower(str_replace(chr(92),'/',(string)realpath(public_path()))).'/';abort_if(str_starts_with($resolved,$webRoot),503);
        try { $value = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { abort(503, 'Integration unavailable'); }
        if (!is_array($value)) abort(503, 'Integration unavailable');
        return $value;
    }
    public function privacy(array $configuration): array
    {
        $policy = $configuration['privacy'] ?? [];
        $fixture = ($policy['mode'] ?? null) === 'fixtures-only' && app()->environment(['testing', 'integration']);
        $approved = ($policy['mode'] ?? null) === 'approved' && !empty($policy['approval_reference']);
        abort_unless(($fixture || $approved) && ($policy['display_mode'] ?? null) === 'initial_two_surnames', 503, 'Integration unavailable');
        return $policy;
    }
    public static function label($value, int $max = 160): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value) <= $max && !preg_match('/[\x00-\x1F\x7F]/u', $value);
    }
}
