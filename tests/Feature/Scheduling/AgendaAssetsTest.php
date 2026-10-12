<?php
namespace Tests\Feature\Scheduling;

use App\Support\Scheduling\AgendaAssets;
use Tests\TestCase;

class AgendaAssetsTest extends TestCase
{
    public function test_asset_version_changes_with_content_even_when_timestamp_and_size_are_preserved(): void
    {
        $relative = 'qa-asset-'.bin2hex(random_bytes(8)).'.js'; $file = public_path($relative);
        try {
            file_put_contents($file, 'version-one'); touch($file, 1700000000);
            $one = AgendaAssets::url($relative);
            $this->assertSame($one, AgendaAssets::url($relative));
            file_put_contents($file, 'version-two'); touch($file, 1700000000); clearstatcache(true, $file);
            $this->assertNotSame($one, AgendaAssets::url($relative));
            $this->assertSame(1700000000, filemtime($file));
        } finally { if (is_file($file)) { unlink($file); } }
    }
    public function test_missing_assets_fail_explicitly_instead_of_shipping_an_unversioned_url(): void
    {
        $this->expectException(\RuntimeException::class);
        AgendaAssets::url('qa-does-not-exist-'.bin2hex(random_bytes(8)).'.js');
    }
}
