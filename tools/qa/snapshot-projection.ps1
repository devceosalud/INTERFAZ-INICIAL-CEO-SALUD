$ErrorActionPreference='Stop'
$rcRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$source=Join-Path $rcRoot 'app/Services/Billing/AppointmentEconomicPosition.php'
$destination=Join-Path $rcRoot 'storage/app/qa-rc/EconomicPosition-indexed.php'
if(Test-Path -LiteralPath $destination){throw 'Prior snapshot retained; no overwrite'}
Copy-Item -LiteralPath $source -Destination $destination
Get-FileHash -LiteralPath $destination -Algorithm SHA256 | Format-List
