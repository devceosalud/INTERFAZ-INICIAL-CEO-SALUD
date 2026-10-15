$ErrorActionPreference='Stop'
$rcRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
Set-Location -LiteralPath $rcRoot
$php=(Resolve-Path '..\entrega-urgente-1\storage\app\qa-final\equivalent\php-8.3.35\php.exe').Path
$folder=Join-Path $rcRoot ('storage/app/qa-rc/backup-restore-'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $folder | Out-Null
& $php tests/Support/rc-restore-rehearsal.php prepare $folder
if($LASTEXITCODE -ne 0){throw 'Identity validation/restore preparation failed'}
$bin=(Resolve-Path '..\.qa-mariadb1189\bin').Path
$dump=Join-Path $folder 'database.sql'
$sourceCnf=Join-Path $folder 'private-source.cnf'
[IO.File]::WriteAllText($sourceCnf,"[client]`nhost=127.0.0.1`nport=33322`nprotocol=tcp`nuser=rc_qa`npassword=rc-local-only`n")
& (Join-Path $bin 'mariadb-dump.exe') ('--defaults-extra-file='+$sourceCnf) --single-transaction --routines --triggers --no-tablespaces ('--result-file='+$dump) ceosalud_qa_rc
if($LASTEXITCODE -ne 0){throw 'Dump failed'}
$sql=[IO.File]::ReadAllText($dump)
if($sql -match '(?im)^\s*(USE\s|CREATE\s+DATABASE)' -or $sql.Contains('`ceosalud_qa_rc`.')){throw 'Dump must not address the source schema explicitly'}
$restore=Get-Content -LiteralPath (Join-Path $folder 'private-restore.json') -Raw | ConvertFrom-Json
$import=Start-Process -FilePath (Join-Path $bin 'mariadb.exe') -ArgumentList @(('--defaults-extra-file='+(Join-Path $folder 'private-restore.cnf')),$restore.schema) -WorkingDirectory $rcRoot -WindowStyle Hidden -PassThru -Wait -RedirectStandardInput $dump -RedirectStandardOutput (Join-Path $folder 'import-out.log') -RedirectStandardError (Join-Path $folder 'import-error.log')
if($import.ExitCode -ne 0){throw 'Restore import failed; original runtime unchanged'}
& $php tests/Support/rc-restore-rehearsal.php verify $folder
if($LASTEXITCODE -ne 0){throw 'Restore fingerprint validation failed'}
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$documentRoot=Join-Path $rcRoot 'storage/app/appointment-documents'
$manifest=@()
$zip=[IO.Compression.ZipFile]::Open((Join-Path $folder 'private-documents.zip'),[IO.Compression.ZipArchiveMode]::Create)
try{foreach($file in (Get-ChildItem -LiteralPath $documentRoot -File)){
 [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip,$file.FullName,$file.Name) | Out-Null
 $manifest+=@{name=$file.Name;sha256=(Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash}
}}finally{$zip.Dispose()}
$restored=Join-Path $folder 'restored-private-documents'
New-Item -ItemType Directory -Path $restored | Out-Null
[IO.Compression.ZipFile]::ExtractToDirectory((Join-Path $folder 'private-documents.zip'),$restored)
foreach($entry in $manifest){if((Get-FileHash -LiteralPath (Join-Path $restored $entry.name) -Algorithm SHA256).Hash -ne $entry.sha256){throw 'Restored private file hash differs'}}
$manifest | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath (Join-Path $folder 'private-file-manifest.json') -Encoding UTF8
@{passed=$true;files_verified=$manifest.Count;dump_sha256=(Get-FileHash -LiteralPath $dump -Algorithm SHA256).Hash;scope='Fictitious owned local data only; not a production backup'} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $folder 'backup-result.json') -Encoding UTF8
& git bundle create (Join-Path $folder 'rc-commits.bundle') codex/rc-integracion-preproductiva
if($LASTEXITCODE -ne 0){throw 'Git bundle failed'}
& git diff --binary ('--output='+$folder+'/pending-source.patch')
if($LASTEXITCODE -ne 0){throw 'Pending source patch failed'}
Write-Output ('Backup and independent restoration verified: '+$folder)
