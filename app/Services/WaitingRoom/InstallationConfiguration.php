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
    public function privacy(array $configuration, array $installation): array
    {
        $p = $configuration['privacy'] ?? [];
        abort_unless(($p['display_mode'] ?? null) === 'initial_two_surnames'
            && in_array($p['speech_mode'] ?? null, ['full_given_names','disabled'], true),503);
        if (($p['mode'] ?? null) === 'fixtures-only' && app()->environment(['testing','integration'])) return $p;
        abort_unless(($p['mode'] ?? null) === 'approved' && is_string($p['approval_reference']??null) && preg_match('/^[A-Za-z0-9_-]{6,100}$/D',$p['approval_reference'])
            && ($p['homonym_strategy'] ?? null) === 'suppress' && self::label($p['device_authority'] ?? null,100),503);
        foreach(['valid_from','valid_until'] as $field)abort_unless(is_string($p[$field]??null)&&preg_match('/^\d{4}-\d{2}-\d{2}T/',$p[$field]),503);
        try {$from=\Carbon\CarbonImmutable::parse($p['valid_from']??'invalid');$until=\Carbon\CarbonImmutable::parse($p['valid_until']??'invalid');}
        catch (\Throwable $e) {abort(503);}
        abort_unless($from->lessThanOrEqualTo(now()) && $until->greaterThan(now()) && $until->greaterThan($from) && $until->diffInDays($from)<=31,503);
        abort_unless(($p['authorized_events']??null)===['arrival','call','attention_start','attention_end'],503);
        $day=now('America/Lima')->toDateString();
        abort_unless(collect($p['scopes']??[])->contains(fn($r)=>is_array($r)
            && ($r['key_id']??null)===($installation['key_id']??null) && ($r['site_id']??null)===($installation['site_id']??null)
            && ($r['screen_ref']??null)===($installation['screen_ref']??null) && in_array($day,$r['days']??[],true)),503);
        return $p;
    }
    public function provenance(array $configuration): array
    {
        $p=$configuration['privacy']??[];
        return ['X-WR-Data-Class'=>($p['mode']??null)==='fixtures-only'?'synthetic':'clinical',
            'X-WR-Policy-Ref'=>hash('sha256',(string)($p['approval_reference']??'fixtures-only'))];
    }
    public static function label($value, int $max = 160): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value) <= $max && !preg_match('/[\x00-\x1F\x7F]/u', $value);
    }
}
