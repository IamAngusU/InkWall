param(
    [int]$Port = 8787,
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

$envValues = Read-EnvFile ".env"
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

if (-not $env:INKWALL_PRIVATE_REVIEW_SECRET) {
    Write-Host "Missing receiver secret. Run .\setup-windows.ps1 first or set INKWALL_PRIVATE_REVIEW_SECRET."
    exit 1
}

$selectedPort = $Port
while ($selectedPort -lt ($Port + 100)) {
    if (Test-PortFree $selectedPort) { break }
    $selectedPort++
}
if ($selectedPort -ge ($Port + 100)) {
    Write-Host "No free local port found from $Port to $($Port + 99)."
    exit 1
}

New-Item -ItemType Directory -Force -Path $env:INKWALL_PRIVATE_REVIEW_DIR | Out-Null

Write-Host "InkWall private review receiver"
Write-Host "Inbox: $env:INKWALL_PRIVATE_REVIEW_DIR"
Write-Host "Local URL: http://${HostName}:$selectedPort"
Write-Host ""
if ($Server) {
    Write-Host "SSH reverse tunnel:"
    Write-Host "ssh -N -R 127.0.0.1:$selectedPort`:$HostName`:$selectedPort $Server"
} else {
    Write-Host "SSH reverse tunnel example:"
    Write-Host "ssh -N -R 127.0.0.1:$selectedPort`:$HostName`:$selectedPort user@your-server"
}
Write-Host ""
Write-Host "Set the server endpoint to:"
Write-Host "INKWALL_REMOTE_REVIEW_ENDPOINT=http://127.0.0.1:$selectedPort"
Write-Host ""

php -S "$HostName`:$selectedPort" tools/private-review-receiver.php
