$ErrorActionPreference='Stop'
$rcRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$files=@('tests/Feature/Scheduling/ScheduleConfigurationTest.php','tests/Feature/Scheduling/ScheduleCalendarRangeAndDurationTest.php','tests/Feature/Scheduling/LegacyScheduleContractTest.php','tests/Feature/Scheduling/DoctorScheduleWorkspaceTest.php','tests/Feature/Security/ScheduleAuthorizationTest.php')
foreach($relative in $files){
    $file=Join-Path $rcRoot $relative
    $text=[IO.File]::ReadAllText($file)
    foreach($role in @('ADMISION','COMERCIAL')){$text=$text.Replace(('createUserWithRole('''+$role+''')'),('scheduleManager('''+$role+''')'))}
    [IO.File]::WriteAllText($file,$text)
}
# Only the positive mutation test uses explicit manager fixtures. Read-only cases remain ungranted.
$file=Join-Path $rcRoot 'tests/Feature/Security/ProvisionalRoleMatrixTest.php'
$text=[IO.File]::ReadAllText($file)
$start=$text.IndexOf('public function test_commercial_reaches_the_operational_write_routes')
$end=$text.IndexOf('    /**',$start)
$positive=$text.Substring($start,$end-$start).Replace('createUserWithRole(','scheduleManager(')
$text=$text.Substring(0,$start)+$positive+$text.Substring($end)
[IO.File]::WriteAllText($file,$text)
