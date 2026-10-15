$ErrorActionPreference='Stop'
$rcRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$files=@('tests/Feature/Scheduling/ScheduleConfigurationTest.php','tests/Feature/Scheduling/ScheduleCalendarRangeAndDurationTest.php','tests/Feature/Scheduling/LegacyScheduleContractTest.php','tests/Feature/Scheduling/DoctorScheduleWorkspaceTest.php','tests/Feature/Security/ScheduleAuthorizationTest.php')
foreach($relative in $files){
    $file=Join-Path $rcRoot $relative
    $text=[IO.File]::ReadAllText($file)
    $text=[regex]::Replace($text,"('doctor_schedule_id_edit'\s*=>\s*)([^,\r\n]+),",{param($m) $m.Value+"`n                'expected_version' => `$this->scheduleVersion((int)"+$m.Groups[2].Value+"),"})
    $text=[regex]::Replace($text,"\['id'\s*=>\s*(\`$[a-zA-Z_][a-zA-Z0-9_]*->id)\]",{param($m) "['id' => "+$m.Groups[1].Value+", 'expected_version' => `$this->scheduleVersion((int)"+$m.Groups[1].Value+")]"})
    [IO.File]::WriteAllText($file,$text)
}
