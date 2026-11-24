$ErrorActionPreference = 'Stop'
$artisanScript = Join-Path $PSScriptRoot 'artisan'
$workers = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" |
    Where-Object { $_.CommandLine -and $_.CommandLine.Contains($artisanScript) -and $_.CommandLine.Contains('schedule:work') }
foreach ($worker in $workers) {
    Stop-Process -Id $worker.ProcessId
}
Write-Host 'Avelle scheduler stopped. Apache and MySQL were not changed.'
