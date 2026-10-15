<?php
namespace Tests\Feature\Scheduling;

use App\Models\User;
use App\Support\Scheduling\MvpGuardNormalization;
use Tests\Concerns\BuildsLegacyMvpGuards;

class LegacyGuardsMvpFlowTest extends MvpAllRolesFlowTest
{
    use BuildsLegacyMvpGuards;
    protected function setUp(): void
    {
        parent::setUp(); \Illuminate\Support\Facades\Storage::fake('local');
        $this->buildLegacyMvpGuards();
        $this->assertSame(5,\Illuminate\Support\Facades\DB::table('roles')->where('guard_name','')->count());
        $n=app(MvpGuardNormalization::class);$plan=$n->plan($this->legacyRoleNames());
        $n->apply($this->legacyRoleNames(),[],$plan['plan_hash'],'QA-LEGACY-FLOW');
    }
    public function rolesAndEntries(): array
    {
        $cases=[];
        foreach($this->legacyRoleNames() as $role){foreach(['legacy','operational'] as $entry){$cases[]=[$role,$entry];}}
        return $cases;
    }
    protected function createUserWithRole(string $roleName,array $attributes=[]): User
    {
        return $this->legacyActors[$roleName] ?? parent::createUserWithRole($roleName,$attributes);
    }
}
