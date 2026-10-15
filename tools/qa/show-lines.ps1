param([Parameter(Mandatory=$true)][string]$File,[int]$Start=1,[int]$Count=120)
$stream=[IO.File]::Open((Join-Path (Get-Location) $File),[IO.FileMode]::Open,[IO.FileAccess]::Read,[IO.FileShare]::ReadWrite)
$reader=New-Object IO.StreamReader($stream)
try{$text=$reader.ReadToEnd()}finally{$reader.Dispose();$stream.Dispose()}
$lines=$text -split "`r?`n"
for($i=[Math]::Max(0,$Start-1);$i -lt [Math]::Min($lines.Length,$Start-1+$Count);$i++){Write-Output (([string]($i+1))+': '+$lines[$i])}
