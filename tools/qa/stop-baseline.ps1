$ErrorActionPreference='Stop'
$owned=@(Get-CimInstance Win32_Process | Where-Object {$_.Name -eq 'php.exe' -and $_.CommandLine -like '*rc-nicole-benchmark.php baseline*'})
if($owned.Count -ne 1){throw 'Expected exactly one owned baseline process; none stopped'}
$evidence=Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) 'storage/app/qa-rc'
$record=@{process_id=$owned[0].ProcessId;created_at=$owned[0].CreationDate;stopped_at=(Get-Date).ToUniversalTime();reason='Baseline remained in 500-row warmup/sampling after 100-row results. Static inspection confirms quadratic voucher scans. Preserve partial evidence; do not infer 500/2000 p50/p95.'}
$record | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $evidence 'baseline-interrupted.json') -Encoding UTF8
Stop-Process -Id $owned[0].ProcessId
Write-Output 'Only the owned read-benchmark PHP process stopped; server and other runtimes retained.'
