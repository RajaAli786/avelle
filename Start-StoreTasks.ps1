$ErrorActionPreference = 'Stop'
$storeRoot = $PSScriptRoot
$artisanScript = Join-Path $storeRoot 'artisan'
$phpExecutable = Join-Path $storeRoot '.runtime/php/php.exe'
if (-not (Test-Path -LiteralPath $phpExecutable)) {
    $phpExecutable = (Get-Command php -ErrorAction Stop).Source
}
$existingWorker = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" |
    Where-Object { $_.CommandLine -and $_.CommandLine.Contains($artisanScript) -and $_.CommandLine.Contains('schedule:work') }
if ($existingWorker) {
    Write-Host 'Avelle background tasks are already running.'
    exit 0
}
$databaseConnection = [Net.Sockets.TcpClient]::new()
try {
    $connectionTask = $databaseConnection.ConnectAsync('127.0.0.1', 3306)
    if (-not $connectionTask.Wait(1000) -or -not $databaseConnection.Connected) {
        throw 'Start MySQL in XAMPP first, then run this helper again.'
    }
} finally {
    $databaseConnection.Dispose()
}
$logFolder = Join-Path $storeRoot 'storage/logs'
New-Item -ItemType Directory -Force -Path $logFolder | Out-Null
$worker = Start-Process -FilePath $phpExecutable -ArgumentList @(('"' + $artisanScript + '"'), 'schedule:work') `
    -WorkingDirectory $storeRoot -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput (Join-Path $logFolder 'store-tasks.log') `
    -RedirectStandardError (Join-Path $logFolder 'store-tasks-errors.log')
Write-Host "Avelle background tasks started (process $($worker.Id))."
Write-Host 'Email retries run every minute; referral rewards are checked every five minutes.'
Write-Host 'Run Stop-StoreTasks.ps1 before shutting down XAMPP.'
