# Stop app processes started by .\dev (leaves Postgres, Redis, and MinIO running).

$ErrorActionPreference = 'SilentlyContinue'

function Stop-Port([int] $Port) {
    Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
        ForEach-Object {
            Write-Host "Stopping PID $($_.OwningProcess) on :$Port"
            Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue
        }
}

foreach ($port in 3000, 8000, 8100) {
    Stop-Port $port
}

Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -match 'artisan queue:work' } |
    ForEach-Object {
        Write-Host "Stopping queue worker PID $($_.ProcessId)"
        Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
    }

Write-Host 'App processes stopped. Postgres, Redis, and MinIO were left running.'
