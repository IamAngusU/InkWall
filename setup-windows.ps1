param(
    [string]$EnvPath = ".env"
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root

function Ask($Prompt, $Default = "") {
    if ($Default) {
        $value = Read-Host "$Prompt [$Default]"
        if ([string]::IsNullOrWhiteSpace($value)) { return $Default }
        return $value
    }
    return Read-Host $Prompt
}

function YesNo($Prompt, $Default = "y") {
    $value = Read-Host "$Prompt [$Default]"
    if ([string]::IsNullOrWhiteSpace($value)) { $value = $Default }
    return $value.ToLowerInvariant() -in @("y", "yes", "j", "ja", "1", "true")
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

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host "PHP CLI was not found in PATH. Install PHP for Windows first, then run this script again."
    exit 1
}

Write-Host "InkWall Windows setup"
Write-Host "This creates .env for a Windows or server install."

if (Test-Path $EnvPath) {
    $backup = "$EnvPath.backup.$(Get-Date -Format yyyyMMdd-HHmmss)"
    Copy-Item $EnvPath $backup
    Write-Host "Existing .env backed up to $backup"
}

$ownerName = Ask "Owner name" "Your Name"
$githubProfile = Ask "GitHub profile URL" "https://github.com/yourname"
$repoUrl = Ask "InkWall repository URL" "https://github.com/yourname/InkWall"
$publicUrl = Ask "Public InkWall URL" "https://example.com/inkwall"
$siteLabel = Ask "Short site label" "example.com/inkwall"
$accent = Ask "Accent color" "#d7422f"
$reviewEmail = Ask "Review email" "admin@example.com"

Write-Host ""
Write-Host "Moderation mode:"
Write-Host "  1) Cloud AI with private fallback"
Write-Host "  2) Private computer only"
Write-Host "  3) Local checks only"
$mode = Ask "Choose 1, 2, or 3" "1"

$cloudEnabled = "1"
$textCloud = "1"
$imageCloud = "1"
$remoteMode = "fallback"
$remoteEndpoint = ""
$remoteSecret = New-Secret
$remoteEncryptKey = New-Secret

switch ($mode) {
    "2" {
        $cloudEnabled = "0"
        $textCloud = "0"
        $imageCloud = "0"
        $remoteMode = "fallback"
    }
    "3" {
        $cloudEnabled = "0"
        $textCloud = "0"
        $imageCloud = "0"
        $remoteMode = "off"
    }
    default {
        if (-not (YesNo "Use cloud AI for text review?" "y")) { $textCloud = "0" }
        if (-not (YesNo "Use cloud AI for image review?" "y")) { $imageCloud = "0" }
        if (-not (YesNo "Use private computer as fallback?" "y")) { $remoteMode = "off" }
    }
}

if ($remoteMode -ne "off") {
    $remoteEndpoint = Ask "Private review endpoint" "http://127.0.0.1:8787"
}

$deepseekKey = ""
$openaiKey = ""
if ($cloudEnabled -eq "1" -and $textCloud -eq "1") {
    $deepseekKey = Ask "DeepSeek API key, optional" ""
}
if ($cloudEnabled -eq "1" -and $imageCloud -eq "1") {
    $openaiKey = Ask "OpenAI API key, optional" ""
}

$brand = [ordered]@{
    accent = $accent
    paper_texture = "dots"
    theme = "light"
    ad_badge = $false
    review_badge = $false
    svg_ink_number = $true
    svg_latest_label = "latest_only"
    owner_name = $ownerName
    profile_url = $githubProfile
    repository_url = $repoUrl
    site_url = $publicUrl
    site_label = $siteLabel
    image_rendering = "ink"
} | ConvertTo-Json -Compress

$envContent = @"
INKWALL_PUBLIC_URL=$publicUrl
INKWALL_ADMIN_URL=$publicUrl/admin
INKWALL_REVIEW_EMAIL=$reviewEmail
INKWALL_BRANDING_JSON=$brand

INKWALL_AI_MODERATION=auto
INKWALL_AI_CLOUD_ENABLED=$cloudEnabled
INKWALL_AI_TEXT_CLOUD_ENABLED=$textCloud
INKWALL_AI_IMAGE_CLOUD_ENABLED=$imageCloud

INKWALL_AI_PROVIDER=deepseek
INKWALL_AI_TEXT_PROVIDER=deepseek
INKWALL_AI_TEXT_MODEL=deepseek-v4-flash
DEEPSEEK_API_KEY=$deepseekKey
DEEPSEEK_BASE_URL=https://api.deepseek.com
INKWALL_DEEPSEEK_MODEL=deepseek-v4-flash
INKWALL_DEEPSEEK_BALANCE_GUARD=1
INKWALL_DEEPSEEK_BALANCE_FAIL_CLOSED=0
INKWALL_DEEPSEEK_FAIL_OPEN=1
INKWALL_DEEPSEEK_DAILY_SPEND_LIMIT_USD=1.00
INKWALL_DEEPSEEK_ESTIMATED_CALL_USD=0.01
INKWALL_DEEPSEEK_SEND_IMAGES=0

INKWALL_AI_IMAGE_PROVIDER=openai_vision
INKWALL_AI_IMAGE_MODEL=gpt-4o-mini
OPENAI_API_KEY=$openaiKey
INKWALL_OPENAI_VISION_MODEL=gpt-4o-mini
INKWALL_OPENAI_VISION_DETAIL=low
INKWALL_OPENAI_VISION_FAIL_OPEN=1
INKWALL_OPENAI_DAILY_SPEND_LIMIT_USD=1.00
INKWALL_OPENAI_ESTIMATED_IMAGE_CALL_USD=0.01

INKWALL_REMOTE_REVIEW=$remoteMode
INKWALL_REMOTE_REVIEW_ENDPOINT=$remoteEndpoint
INKWALL_REMOTE_REVIEW_SECRET=$remoteSecret
INKWALL_REMOTE_REVIEW_ENCRYPT=1
INKWALL_REMOTE_REVIEW_ENCRYPTION_KEY=$remoteEncryptKey
INKWALL_REMOTE_REVIEW_FAIL_OPEN=1
INKWALL_REMOTE_REVIEW_SEND_TEXT=1
INKWALL_REMOTE_REVIEW_SEND_IMAGE=1
INKWALL_REMOTE_REVIEW_TIMEOUT_SECONDS=8

INKWALL_AI_ALLOW_REJECT=0
INKWALL_AI_FLAG_POLICY_JSON={"advertising":"allow","harassment":"hold","copyright":"hold","violence":"hold","nudity":"hold"}
INKWALL_AI_REVIEW_UNCHECKED_IMAGES=0
INKWALL_AI_ALLOW_UNCHECKED_IMAGES=0
INKWALL_SHARE_AI_METADATA=1
INKWALL_AI_METADATA_ENDPOINT=https://angusu.de/inkwall/telemetry.php
"@

Set-Content -Path $EnvPath -Value $envContent -Encoding UTF8
New-Item -ItemType Directory -Force -Path "data\inkwall" | Out-Null

Write-Host ""
Write-Host "Created $EnvPath"
Write-Host "Hard toggles:"
Write-Host "  INKWALL_AI_CLOUD_ENABLED=$cloudEnabled"
Write-Host "  INKWALL_AI_TEXT_CLOUD_ENABLED=$textCloud"
Write-Host "  INKWALL_AI_IMAGE_CLOUD_ENABLED=$imageCloud"
Write-Host "  INKWALL_REMOTE_REVIEW=$remoteMode"

if ($remoteMode -ne "off") {
    Write-Host ""
    Write-Host "Private receiver values for this Windows computer:"
    Write-Host "  `$env:INKWALL_PRIVATE_REVIEW_SECRET='$remoteSecret'"
    Write-Host "  `$env:INKWALL_PRIVATE_REVIEW_ENCRYPTION_KEY='$remoteEncryptKey'"
    Write-Host "  `$env:INKWALL_PRIVATE_REVIEW_DIR=`"`$HOME\InkWallReviewInbox`""
    Write-Host "  .\start-private-review-windows.ps1"

    Write-Host ""
    if (YesNo "Install private review autostart now?" "n") {
        $autostartMode = Ask "Autostart mode: hidden or window" "hidden"
        if ($autostartMode -notin @("hidden", "window")) { $autostartMode = "hidden" }
        & (Join-Path $Root "manage-private-review-windows.ps1") -Action install -WindowMode $autostartMode
        if (YesNo "Start private review receiver now?" "y") {
            & (Join-Path $Root "manage-private-review-windows.ps1") -Action start
        }
    } else {
        Write-Host "Autostart can be managed later with .\manage-private-review-windows.cmd"
        Write-Host "Start the receiver now with .\start-private-review-windows.cmd"
    }
}
