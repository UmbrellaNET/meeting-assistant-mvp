# Daily local start for ServBay / native Windows (not Docker).
# From the repo root:  .\dev

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

function Test-Listening([int] $Port) {
    try {
        $client = [System.Net.Sockets.TcpClient]::new()
        $async = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        $ok = $async.AsyncWaitHandle.WaitOne(400)
        if ($ok) { $client.EndConnect($async) | Out-Null }
        $client.Close()
        return $ok
    } catch {
        return $false
    }
}

function Wait-Http([string] $Url, [int] $Seconds = 40) {
    $deadline = (Get-Date).AddSeconds($Seconds)
    while ((Get-Date) -lt $deadline) {
        try {
            $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 3
            if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 500) {
                return $true
            }
        } catch {}
        Start-Sleep -Milliseconds 500
    }
    return $false
}

function Start-AppWindow([string] $Title, [string] $WorkingDirectory, [string] $Command) {
    $script = @"
Set-Location -LiteralPath '$WorkingDirectory'
`$Host.UI.RawUI.WindowTitle = '$Title'
Write-Host '==> $Title' -ForegroundColor Cyan
$Command
"@
    $encoded = [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($script))
    Start-Process powershell.exe -ArgumentList @('-NoExit', '-EncodedCommand', $encoded)
}

if (-not (Test-Listening 5432)) {
    Write-Host 'Postgres is not listening on 5432. Start it from ServBay, then run .\dev again.' -ForegroundColor Red
    exit 1
}

if (-not (Test-Listening 6379)) {
    $redisServer = 'C:\ServBay\packages\redis\redis-server.exe'
    $redisConf = 'C:\ServBay\packages\redis\redis-local.conf'
    if (Test-Path $redisServer) {
        Write-Host 'Starting Redis...'
        if (Test-Path $redisConf) {
            Start-Process -FilePath $redisServer -ArgumentList 'redis-local.conf' -WorkingDirectory 'C:\ServBay\packages\redis' -WindowStyle Hidden
        } else {
            Start-Process -FilePath $redisServer -WorkingDirectory 'C:\ServBay\packages\redis' -WindowStyle Hidden
        }
        Start-Sleep 1
    }
    if (-not (Test-Listening 6379)) {
        Write-Host 'Redis is not listening on 6379. Start it from ServBay, then run .\dev again.' -ForegroundColor Red
        exit 1
    }
}

if (-not (Test-Listening 9000)) {
    $minio = 'C:\ServBay\db\minio\minio.exe'
    $mc = 'C:\ServBay\db\minio\mc.exe'
    if (-not (Test-Path $minio)) {
        Write-Host "MinIO not found at $minio" -ForegroundColor Red
        exit 1
    }
    Write-Host 'Starting MinIO...'
    $env:MINIO_ROOT_USER = 'minioadmin'
    $env:MINIO_ROOT_PASSWORD = 'minioadmin123'
    Start-Process -FilePath $minio -ArgumentList @('server', 'C:\ServBay\db\minio\data', '--address', ':9000', '--console-address', ':9001') -WorkingDirectory 'C:\ServBay\db\minio' -WindowStyle Hidden
    if (-not (Wait-Http 'http://127.0.0.1:9000/minio/health/live' 20)) {
        Write-Host 'MinIO did not become healthy on :9000.' -ForegroundColor Red
        exit 1
    }
    if (Test-Path $mc) {
        & $mc alias set local http://127.0.0.1:9000 minioadmin minioadmin123 | Out-Null
        & $mc mb --ignore-existing local/meeting-artifacts | Out-Null
    }
}

$apiDir = Join-Path $Root 'apps\api'
$webDir = Join-Path $Root 'apps\web'
$workerDir = Join-Path $Root 'services\worker'

if (-not (Test-Listening 8000)) {
    Start-AppWindow 'API' $apiDir 'php artisan serve --host=127.0.0.1 --port=8000'
} else {
    Write-Host 'API already running on :8000'
}

if (-not (Test-Listening 8100)) {
    Start-AppWindow 'AI worker' $workerDir 'python -m uvicorn app.main:app --host 127.0.0.1 --port 8100'
} else {
    Write-Host 'AI worker already running on :8100'
}

Start-AppWindow 'Queue' $apiDir 'php artisan queue:work --queue=default,meetings --tries=3 --timeout=900'

if (-not (Test-Listening 3000)) {
    Start-AppWindow 'Web' $webDir '$env:NEXT_PUBLIC_API_URL = "http://localhost:8000/api"; npm run dev -- --port 3000 --hostname 127.0.0.1'
} else {
    Write-Host 'Web already running on :3000'
}

Write-Host 'Waiting for API and web...'
$null = Wait-Http 'http://127.0.0.1:8000/up' 40
$null = Wait-Http 'http://127.0.0.1:3000' 60

Start-Process 'http://127.0.0.1:3000'

Write-Host ''
Write-Host 'Local app is starting in separate windows.' -ForegroundColor Green
Write-Host '  Web        http://127.0.0.1:3000'
Write-Host '  API        http://127.0.0.1:8000/up'
Write-Host '  AI worker  http://127.0.0.1:8100/health'
Write-Host '  MinIO      http://127.0.0.1:9001'
Write-Host ''
Write-Host 'Sign in: meetings@umbrellanet.com / password'
Write-Host 'Stop later with:  .\dev-stop'
