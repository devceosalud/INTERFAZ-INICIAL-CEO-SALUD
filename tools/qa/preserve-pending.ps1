$ErrorActionPreference='Stop'
$rcRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
Set-Location -LiteralPath $rcRoot
$snapshot=Join-Path $rcRoot ('storage/app/qa-rc/recovery-'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $snapshot | Out-Null
& git diff --binary ('--output='+$snapshot+'/tracked.patch')
if($LASTEXITCODE -ne 0){throw 'Git patch failed'}
$files=@(& git diff --name-only)+@(& git ls-files --others --exclude-standard)
$files=@($files | Sort-Object -Unique)
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression
$zip=[IO.Compression.ZipFile]::Open(($snapshot+'/working-files.zip'),[IO.Compression.ZipArchiveMode]::Create)
$manifest=@()
try{foreach($relative in $files){
 $absolute=[IO.Path]::GetFullPath((Join-Path $rcRoot $relative))
 if(!$absolute.StartsWith($rcRoot+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){throw 'Outside recovery root'}
 if(Test-Path -LiteralPath $absolute -PathType Leaf){
  [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip,$absolute,$relative) | Out-Null
  $manifest+=@{path=$relative;bytes=(Get-Item -LiteralPath $absolute).Length;sha256=(Get-FileHash -LiteralPath $absolute -Algorithm SHA256).Hash}
 }
}}finally{$zip.Dispose()}
$manifest | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath ($snapshot+'/manifest.json') -Encoding UTF8
Write-Output ('Preserved '+$manifest.Count+' pending files in '+$snapshot)
