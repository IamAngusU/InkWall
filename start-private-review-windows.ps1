param(
    [int]$Port = 0,
    [string]$HostName = "127.0.0.1",
    [string]$Server = ""
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root

function Read-EnvFile($Path) {
    $values = @{}
    if (-not (Test-Path $Path)) { return $values }
    foreach ($line in Get-Content $Path) {
        $trimmed = $line.Trim()
        if (-not $trimmed -or $trimmed.StartsWith("#") -or -not $trimmed.Contains("=")) { continue }
        $parts = $trimmed.Split("=", 2)
        $values[$parts[0].Trim()] = $parts[1].Trim().Trim('"').Trim("'")
    }
    return $values
}

function New-Secret {
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $rng.GetBytes($bytes)
    } finally {
        if ($rng) { $rng.Dispose() }
    }
    return "base64:" + [Convert]::ToBase64String($bytes)
}

function Write-EnvValue($Path, $Key, $Value) {
    $lines = @()
    if (Test-Path $Path) { $lines = @(Get-Content $Path) }
    $found = $false
    $updated = foreach ($line in $lines) {
        if ($line -match "^$([regex]::Escape($Key))=") {
            $found = $true
            "$Key=$Value"
        } else {
            $line
        }
    }
    if (-not $found) { $updated += "$Key=$Value" }
    Set-Content -Path $Path -Value $updated -Encoding UTF8
}

function Info($Text) {
    Write-Host $Text -ForegroundColor Cyan
}

function Good($Text) {
    Write-Host $Text -ForegroundColor Green
}

function Muted($Text) {
    Write-Host $Text -ForegroundColor DarkGray
}

function Ensure-EnvFile {
    if (Test-Path ".env") { return }
    Write-Host "No .env found. Creating a minimal private-review config."
    Copy-Item ".env.example" ".env"
    Write-EnvValue ".env" "INKWALL_AI_CLOUD_ENABLED" "0"
    Write-EnvValue ".env" "INKWALL_AI_TEXT_CLOUD_ENABLED" "0"
    Write-EnvValue ".env" "INKWALL_AI_IMAGE_CLOUD_ENABLED" "0"
    Write-EnvValue ".env" "INKWALL_REMOTE_REVIEW" "fallback"
    Write-EnvValue ".env" "INKWALL_REMOTE_REVIEW_ENCRYPT" "1"
    Write-EnvValue ".env" "INKWALL_REMOTE_REVIEW_FAIL_OPEN" "1"
}

function Test-PortFree($PortToCheck) {
    try {
        $address = [System.Net.Dns]::GetHostAddresses($HostName) | Where-Object { $_.AddressFamily -eq [System.Net.Sockets.AddressFamily]::InterNetwork } | Select-Object -First 1
        if (-not $address) { $address = [System.Net.IPAddress]::Parse("127.0.0.1") }
        $listener = [System.Net.Sockets.TcpListener]::new($address, $PortToCheck)
        $listener.Start()
        $listener.Stop()
        return $true
    } catch {
        return $false
    }
}

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host "PHP CLI was not found in PATH."
    exit 1
}

Ensure-EnvFile
$envValues = Read-EnvFile ".env"
$configuredPort = 0
if ($envValues.ContainsKey("INKWALL_PRIVATE_REVIEW_PORT") -and $envValues["INKWALL_PRIVATE_REVIEW_PORT"] -match '^\d+$') {
    $configuredPort = [int]$envValues["INKWALL_PRIVATE_REVIEW_PORT"]
}
if ($Port -le 0) { $Port = if ($configuredPort -gt 0) { $configuredPort } else { 8787 } }
if (-not $Server -and $envValues.ContainsKey("INKWALL_PRIVATE_REVIEW_SSH_TARGET")) {
    $Server = $envValues["INKWALL_PRIVATE_REVIEW_SSH_TARGET"]
}
$sshKey = if ($envValues.ContainsKey("INKWALL_PRIVATE_REVIEW_SSH_KEY")) { $envValues["INKWALL_PRIVATE_REVIEW_SSH_KEY"] } else { "" }
if (-not $env:INKWALL_PRIVATE_REVIEW_SECRET -and $envValues.ContainsKey("INKWALL_REMOTE_REVIEW_SECRET")) {
    $env:INKWALL_PRIVATE_REVIEW_SECRET = $envValues["INKWALL_REMOTE_REVIEW_SECRET"]
}
if (-not $env:INKWALL_PRIVATE_REVIEW_ENCRYPTION_KEY -and $envValues.ContainsKey("INKWALL_REMOTE_REVIEW_ENCRYPTION_KEY")) {
    $env:INKWALL_PRIVATE_REVIEW_ENCRYPTION_KEY = $envValues["INKWALL_REMOTE_REVIEW_ENCRYPTION_KEY"]
}
if (-not $env:INKWALL_PRIVATE_REVIEW_DIR) {
    $env:INKWALL_PRIVATE_REVIEW_DIR = Join-Path $HOME "InkWallReviewInbox"
}
if (-not $env:INKWALL_PRIVATE_REVIEW_DEFAULT) {
    $env:INKWALL_PRIVATE_REVIEW_DEFAULT = "review"
}
foreach ($key in @(
    "INKWALL_PRIVATE_REVIEW_COMMAND",
    "INKWALL_OLLAMA_URL",
    "INKWALL_OLLAMA_MODEL",
    "INKWALL_OLLAMA_TIMEOUT_SECONDS",
    "INKWALL_OLLAMA_SEND_IMAGES"
)) {
    if (-not [Environment]::GetEnvironmentVariable($key) -and $envValues.ContainsKey($key)) {
        [Environment]::SetEnvironmentVariable($key, $envValues[$key], "Process")
    }
}

if (-not $env:INKWALL_PRIVATE_REVIEW_SECRET) {
    $env:INKWALL_PRIVATE_REVIEW_SECRET = New-Secret
    Write-EnvValue ".env" "INKWALL_REMOTE_REVIEW_SECRET" $env:INKWALL_PRIVATE_REVIEW_SECRET
    Write-Host "Generated INKWALL_REMOTE_REVIEW_SECRET and saved it to .env."
}
if (-not $env:INKWALL_PRIVATE_REVIEW_ENCRYPTION_KEY) {
    $env:INKWALL_PRIVATE_REVIEW_ENCRYPTION_KEY = New-Secret
    Write-EnvValue ".env" "INKWALL_REMOTE_REVIEW_ENCRYPTION_KEY" $env:INKWALL_PRIVATE_REVIEW_ENCRYPTION_KEY
    Write-Host "Generated INKWALL_REMOTE_REVIEW_ENCRYPTION_KEY and saved it to .env."
}

$selectedPort = $Port
if (-not (Test-PortFree $selectedPort)) {
    Write-Host "Port $selectedPort is already in use. InkWall may already be running."
    Write-Host "Check with .\manage-private-review-windows.cmd status"
    exit 1
}

New-Item -ItemType Directory -Force -Path $env:INKWALL_PRIVATE_REVIEW_DIR | Out-Null
$serverEndpoint = "http://127.0.0.1:$selectedPort"
Write-EnvValue ".env" "INKWALL_REMOTE_REVIEW_ENDPOINT" $serverEndpoint
if (-not $envValues.ContainsKey("INKWALL_REMOTE_REVIEW") -or -not $envValues["INKWALL_REMOTE_REVIEW"]) {
    Write-EnvValue ".env" "INKWALL_REMOTE_REVIEW" "fallback"
}
Write-EnvValue ".env" "INKWALL_REMOTE_REVIEW_ENCRYPT" "1"
Write-EnvValue ".env" "INKWALL_PRIVATE_REVIEW_PORT" $selectedPort

Info "InkWall private review receiver"
Write-Host "Inbox: " -NoNewline
Good "$env:INKWALL_PRIVATE_REVIEW_DIR"
Write-Host "Local URL: " -NoNewline
Good "http://${HostName}:$selectedPort"
if ($env:INKWALL_PRIVATE_REVIEW_COMMAND) {
    Write-Host "Local review command: " -NoNewline
    Good "$env:INKWALL_PRIVATE_REVIEW_COMMAND"
} else {
    Write-Host "Local review command: " -NoNewline
    Muted "none, default decision is $env:INKWALL_PRIVATE_REVIEW_DEFAULT"
}
Write-Host ""
if (-not $Server) {
    Write-Host "Server tunnel: " -NoNewline
    Muted "not configured"
    Write-Host "Run .\setup-windows.cmd to pair this receiver with an InkWall server."
    Write-Host ""
    php -S "$HostName`:$selectedPort" tools/private-review-receiver.php
    exit $LASTEXITCODE
}

if (-not (Get-Command ssh -ErrorAction SilentlyContinue)) {
    Write-Host "Windows OpenSSH Client was not found."
    exit 1
}

Write-Host "Server tunnel: " -NoNewline
Good "$Server"
Write-Host "Transport: " -NoNewline
Good "SSH with encrypted InkWall payloads"
Muted "Waiting for review jobs. Received jobs will be logged here and saved in the inbox."
Write-Host ""

$phpArgs = @("-S", "$HostName`:$selectedPort", "tools/private-review-receiver.php")
$phpProcess = Start-Process -FilePath "php" -ArgumentList $phpArgs -WorkingDirectory $Root -NoNewWindow -PassThru
Start-Sleep -Milliseconds 500
if ($phpProcess.HasExited) {
    Write-Host "The private review receiver could not start."
    exit 1
}

try {
    while (-not $phpProcess.HasExited) {
        $sshArgs = @(
            "-N",
            "-o", "BatchMode=yes",
            "-o", "ExitOnForwardFailure=yes",
            "-o", "ServerAliveInterval=30",
            "-o", "ServerAliveCountMax=3",
            "-R", "127.0.0.1:$selectedPort`:$HostName`:$selectedPort"
        )
        if ($sshKey) { $sshArgs += @("-i", $sshKey) }
        $sshArgs += $Server
        Info "Connecting secure tunnel..."
        & ssh @sshArgs
        if ($phpProcess.HasExited) { break }
        Write-Host "Tunnel disconnected. " -NoNewline
        Muted "Reconnecting in 5 seconds."
        Start-Sleep -Seconds 5
    }
} finally {
    if (-not $phpProcess.HasExited) { Stop-Process -Id $phpProcess.Id -Force -ErrorAction SilentlyContinue }
}
