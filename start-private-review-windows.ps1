param(
    [int]$Port = 0,
    [string]$HostName = "127.0.0.1",
    [string]$Server = "",
    [switch]$KeepOpenOnError
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root
$LogDir = Join-Path $Root "data\logs"
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
$LogFile = Join-Path $LogDir "private-review-windows.log"

function Log-Line($Text) {
    $line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') $Text"
    Add-Content -Path $LogFile -Value $line -Encoding UTF8
}

trap {
    $message = $_.Exception.Message
    Write-Host "InkWall private review stopped: $message" -ForegroundColor Red
    Log-Line "ERROR $message"
    Log-Line "ERROR $($_ | Out-String)"
    Write-Host "Log file: $LogFile"
    if ($KeepOpenOnError -or $env:INKWALL_KEEP_WINDOW_OPEN_ON_ERROR -eq "1") {
        Read-Host "Press Enter to close"
    }
    exit 1
}

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

function Warn($Text) {
    Write-Host $Text -ForegroundColor Yellow
}

function Quote-ProcessArg($Value) {
    $text = [string]$Value
    if ($text -notmatch '[\s"]') { return $text }
    return '"' + ($text -replace '"', '\"') + '"'
}

function Join-ProcessArgs($Values) {
    return (($Values | ForEach-Object { Quote-ProcessArg $_ }) -join " ")
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

function Test-ServerCanReachReceiver($Server, $SshKey, $PortToCheck) {
    if (-not (Get-Command ssh -ErrorAction SilentlyContinue)) { return $false }
    $remote = "if command -v curl >/dev/null 2>&1; then curl -s -X POST -H 'X-InkWall-Probe: 1' -o /dev/null -w '%{http_code}' --max-time 5 http://127.0.0.1:$PortToCheck/; else printf NO_CURL; fi"
    $args = @("-o", "BatchMode=yes", "-o", "ConnectTimeout=8")
    if ($SshKey) { $args += @("-i", $SshKey) }
    $args += @($Server, $remote)
    try {
        $result = (& ssh @args 2>$null)
        return (($result -join "").Trim() -eq "200")
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
    "INKWALL_OLLAMA_SEND_IMAGES",
    "INKWALL_BROWSER_REVIEW_OPEN",
    "INKWALL_BROWSER_REVIEW_TIMEOUT_SECONDS",
    "INKWALL_BROWSER_REVIEW_MODEL",
    "INKWALL_CONTEXTBRIDGE_EXE",
    "INKWALL_CONTEXTBRIDGE_CONFIG"
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
Log-Line "Starting private review receiver on http://${HostName}:$selectedPort"
Write-Host "Inbox: " -NoNewline
Good "$env:INKWALL_PRIVATE_REVIEW_DIR"
Write-Host "Local URL: " -NoNewline
Good "http://${HostName}:$selectedPort"
if ($env:INKWALL_PRIVATE_REVIEW_COMMAND) {
    Write-Host "Local review command: " -NoNewline
    Good "$env:INKWALL_PRIVATE_REVIEW_COMMAND"
    Log-Line "Review command: $env:INKWALL_PRIVATE_REVIEW_COMMAND"
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
Log-Line "Server tunnel target: $Server"
Write-Host "Transport: " -NoNewline
Good "SSH with encrypted InkWall payloads"
Muted "Waiting for review jobs. Received jobs will be logged here and saved in the inbox."
Write-Host ""

$bridgeProcess = $null
if ($env:INKWALL_CONTEXTBRIDGE_EXE -and (Test-Path $env:INKWALL_CONTEXTBRIDGE_EXE) -and $env:INKWALL_CONTEXTBRIDGE_CONFIG) {
    Write-Host "ContextBridge: " -NoNewline
    $health = & $env:INKWALL_CONTEXTBRIDGE_EXE health --config $env:INKWALL_CONTEXTBRIDGE_CONFIG 2>$null
    if ($LASTEXITCODE -eq 0) {
        Good "already running"
    } else {
        $bridgeArgs = @("serve", "--config", $env:INKWALL_CONTEXTBRIDGE_CONFIG)
        $bridgeProcess = Start-Process -FilePath $env:INKWALL_CONTEXTBRIDGE_EXE -ArgumentList (Join-ProcessArgs $bridgeArgs) -WorkingDirectory (Split-Path -Parent $env:INKWALL_CONTEXTBRIDGE_EXE) -NoNewWindow -PassThru
        Start-Sleep -Milliseconds 700
        if ($bridgeProcess.HasExited) { throw "ContextBridge could not start." }
        Good "started"
    }
}

$phpOutput = Join-Path $LogDir "private-review-php-output.log"
$phpServerLog = Join-Path $LogDir "private-review-php-server.log"
Set-Content -Path $phpOutput -Value "" -Encoding UTF8
Set-Content -Path $phpServerLog -Value "" -Encoding UTF8
$phpArgs = @("-S", "$HostName`:$selectedPort", "tools/private-review-receiver.php")
$phpProcess = Start-Process -FilePath "php" -ArgumentList $phpArgs -WorkingDirectory $Root -RedirectStandardOutput $phpOutput -RedirectStandardError $phpServerLog -PassThru
Start-Sleep -Milliseconds 500
if ($phpProcess.HasExited) {
    Write-Host "The private review receiver could not start."
    exit 1
}

try {
    $sshProcess = $null
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
        Log-Line "Connecting SSH tunnel to $Server on local port $selectedPort"
        $sshProcess = Start-Process -FilePath "ssh" -ArgumentList (Join-ProcessArgs $sshArgs) -NoNewWindow -PassThru
        Start-Sleep -Milliseconds 1200
        if ($sshProcess.HasExited) {
            Warn "Secure tunnel could not stay connected. Retrying in 5 seconds."
            Log-Line "SSH tunnel exited early with code $($sshProcess.ExitCode)"
            Start-Sleep -Seconds 5
            continue
        }
        Good "Secure tunnel connected."
        Log-Line "SSH tunnel connected"
        if (Test-ServerCanReachReceiver $Server $sshKey $selectedPort) {
            Good "Server can reach this receiver on 127.0.0.1:$selectedPort."
            Log-Line "Server reachability probe succeeded"
        } else {
            Warn "Tunnel is open, but the reachability probe did not confirm it yet."
            Log-Line "Server reachability probe did not confirm tunnel"
        }
        $seenReceiverLines = 0
        while (-not $sshProcess.HasExited -and -not $phpProcess.HasExited) {
            Start-Sleep -Milliseconds 500
            $receiverLines = @(Get-Content $phpServerLog -ErrorAction SilentlyContinue | Where-Object { $_ -match '\[\d{2}:\d{2}:\d{2}\]\s+(.+)$' })
            if ($receiverLines.Count -le $seenReceiverLines) { continue }
            foreach ($line in $receiverLines[$seenReceiverLines..($receiverLines.Count - 1)]) {
                if ($line -notmatch '\[\d{2}:\d{2}:\d{2}\]\s+(.+)$') { continue }
                $eventText = $Matches[1]
                if ($eventText -eq "Review request received from server.") { Info "Review job received." }
                elseif ($eventText -eq "Decrypting review payload.") { Muted "Decrypting the protected job payload..." }
                elseif ($eventText -like "Stored review job:*") { Good ($eventText -replace '^Stored review job:', 'Job stored:') }
                elseif ($eventText -eq "Running local review command.") { Info "Running the configured local review engine..." }
                elseif ($eventText -like "Decision sent:*") { Good $eventText }
                elseif ($eventText -like "Using default private review decision:*") { Warn $eventText }
                elseif ($eventText -like "Local review command did not return valid JSON.*") { Warn $eventText }
            }
            $seenReceiverLines = $receiverLines.Count
        }
        if ($phpProcess.HasExited) { break }
        Write-Host "Tunnel disconnected. " -NoNewline
        Muted "Reconnecting in 5 seconds."
        Log-Line "SSH tunnel disconnected with code $($sshProcess.ExitCode)"
        Start-Sleep -Seconds 5
    }
} finally {
    if ($sshProcess -and -not $sshProcess.HasExited) { Stop-Process -Id $sshProcess.Id -Force -ErrorAction SilentlyContinue }
    if (-not $phpProcess.HasExited) { Stop-Process -Id $phpProcess.Id -Force -ErrorAction SilentlyContinue }
    if ($bridgeProcess -and -not $bridgeProcess.HasExited) { Stop-Process -Id $bridgeProcess.Id -Force -ErrorAction SilentlyContinue }
}
