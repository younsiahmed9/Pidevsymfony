$ErrorActionPreference = 'Stop'

$projectDir = Split-Path -Parent $PSScriptRoot
$logDir = Join-Path $projectDir 'var\log'
$logFile = Join-Path $logDir 'scheduled-transfers.log'

if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir | Out-Null
}

$phpWin = 'C:\xampp\php\php-win.exe'
$phpExe = 'C:\xampp\php\php.exe'
$phpPath = if (Test-Path $phpWin) { $phpWin } else { $phpExe }

$timestamp = Get-Date -Format 'ddd MM/dd/yyyy HH:mm:ss.ff'
"[$timestamp] START app:transfers:run-scheduled" | Out-File -FilePath $logFile -Append -Encoding utf8

$stdoutFile = [System.IO.Path]::GetTempFileName()
$stderrFile = [System.IO.Path]::GetTempFileName()

try {
    $process = Start-Process -FilePath $phpPath `
        -ArgumentList @('bin/console', 'app:transfers:run-scheduled', '--env=prod', '--no-interaction') `
        -WorkingDirectory $projectDir `
        -WindowStyle Hidden `
        -Wait `
        -PassThru `
        -RedirectStandardOutput $stdoutFile `
        -RedirectStandardError $stderrFile

    if (Test-Path $stdoutFile) {
        Get-Content $stdoutFile | Out-File -FilePath $logFile -Append -Encoding utf8
    }
    if (Test-Path $stderrFile) {
        Get-Content $stderrFile | Out-File -FilePath $logFile -Append -Encoding utf8
    }

    $endTs = Get-Date -Format 'ddd MM/dd/yyyy HH:mm:ss.ff'
    "[$endTs] END code=$($process.ExitCode)" | Out-File -FilePath $logFile -Append -Encoding utf8
} finally {
    if (Test-Path $stdoutFile) { Remove-Item $stdoutFile -Force -ErrorAction SilentlyContinue }
    if (Test-Path $stderrFile) { Remove-Item $stderrFile -Force -ErrorAction SilentlyContinue }
}
