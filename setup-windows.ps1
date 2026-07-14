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
        return $value.Trim()
    }
    return (Read-Host $Prompt).Trim()
}

function YesNo($Prompt, $Default = "y") {
    $value = Read-Host "$Prompt [$Default]"
    if ([string]::IsNullOrWhiteSpace($value)) { $value = $Default }
    return $value.ToLowerInvariant() -in @("y", "yes", "j", "ja", "1", "true")
}

function Ask-Secret($Prompt) {
    $secure = Read-Host $Prompt -AsSecureString
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
}

function New-Secret {
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { if ($rng) { $rng.Dispose() } }
    return "base64:" + [Convert]::ToBase64String($bytes)
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

function GitHub-Username($Value) {
    $candidate = $Value.Trim().TrimEnd("/")
    if ($candidate -match '^https?://') {
        try {
            $uri = [Uri]$candidate
            if ($uri.Host.ToLowerInvariant() -notin @("github.com", "www.github.com")) { return "" }
            $candidate = ($uri.AbsolutePath.Trim("/").Split("/") | Select-Object -First 1)
        } catch { return "" }
    }
    $candidate = $candidate.TrimStart("@")
    if ($candidate -notmatch '^[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?$') { return "" }
    return $candidate
}

function Resolve-GitHubUser($Default = "yourname") {
    while ($true) {
        $raw = Ask "GitHub username or profile URL" $Default
        $username = GitHub-Username $raw
        if (-not $username) {
            Write-Host "Please enter a GitHub username or a github.com profile URL."
            continue
        }
        try {
            $headers = @{ "User-Agent" = "InkWall-Setup"; "Accept" = "application/vnd.github+json" }
            $user = Invoke-RestMethod -Uri "https://api.github.com/users/$username" -Headers $headers -Method Get
            $displayName = if ([string]::IsNullOrWhiteSpace([string]$user.name)) { [string]$user.login } else { [string]$user.name }
            Write-Host ""
            Write-Host "Found GitHub account: $displayName (@$($user.login))"
            Write-Host "Profile: $($user.html_url)"
            if (YesNo "Is this the correct GitHub account?" "y") { return $user }
        } catch {
            Write-Host "GitHub account '$username' was not found or GitHub could not be reached."
        }
    }
}

function Resolve-GitHubRepo($User) {
    $headers = @{ "User-Agent" = "InkWall-Setup"; "Accept" = "application/vnd.github+json" }
    $defaultUrl = "https://github.com/$($User.login)/InkWall"
    try {
        $repo = Invoke-RestMethod -Uri "https://api.github.com/repos/$($User.login)/InkWall" -Headers $headers -Method Get
        Write-Host "Repository found: $($repo.html_url)"
        return [string]$repo.html_url
    } catch {
        Write-Host "No InkWall repository was found under @$($User.login)."
    }

    while ($true) {
        $repoUrl = Ask "InkWall repository URL" $defaultUrl
        if ($repoUrl -notmatch '^https://github\.com/([^/]+)/([^/]+)/?$') {
            Write-Host "Please enter a full github.com repository URL."
            continue
        }
        try {
            $repo = Invoke-RestMethod -Uri "https://api.github.com/repos/$($Matches[1])/$($Matches[2])" -Headers $headers -Method Get
            Write-Host "Repository found: $($repo.html_url)"
            return [string]$repo.html_url
        } catch {
            Write-Host "That GitHub repository does not exist or is not publicly reachable."
        }
    }
}

function Normalize-PublicUrl($Value) {
    $candidate = $Value.Trim().TrimEnd("/")
    if ($candidate -notmatch '^https?://') { $candidate = "https://$candidate" }
    try {
        $uri = [Uri]$candidate
        if (-not $uri.Host) { return "" }
        return $uri.AbsoluteUri.TrimEnd("/")
    } catch { return "" }
}

function Site-Label($Url) {
    $uri = [Uri]$Url
    return ($uri.Host + $uri.AbsolutePath).TrimEnd("/")
}

function Test-LocalPortFree($Port) {
    try {
        $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $Port)
        $listener.Start()
        $listener.Stop()
        return $true
    } catch { return $false }
}

function Find-FreeLocalPort($Start = 8787) {
    for ($port = $Start; $port -lt ($Start + 100); $port++) {
        if (Test-LocalPortFree $port) { return $port }
    }
    throw "No free local port found from $Start to $($Start + 99)."
}

function Require-SshTools {
    foreach ($command in @("ssh", "scp", "ssh-keygen")) {
        if (-not (Get-Command $command -ErrorAction SilentlyContinue)) {
            throw "$command was not found. Install the Windows OpenSSH Client optional feature and run setup again."
        }
    }
}

function Validate-SshTarget($Target) {
    return $Target -match '^[A-Za-z0-9_.-]+@[A-Za-z0-9_.-]+$'
}

function Ensure-SshKey($Target) {
    $sshDir = Join-Path $HOME ".ssh"
    New-Item -ItemType Directory -Force -Path $sshDir | Out-Null
    $keyPath = Join-Path $sshDir "inkwall_review_ed25519"
    if (-not (Test-Path $keyPath)) {
        Write-Host "Creating a dedicated SSH identity for the encrypted InkWall transport."
        $keygenArgs = "-q -t ed25519 -f `"$keyPath`" -N `"`""
        $keygen = Start-Process -FilePath "ssh-keygen" -ArgumentList $keygenArgs -Wait -PassThru -NoNewWindow
        if ($keygen.ExitCode -ne 0) { throw "Could not create the InkWall SSH key." }
    }

    $ready = & ssh -i $keyPath -o BatchMode=yes -o ConnectTimeout=8 $Target "printf INKWALL_READY" 2>$null
    if (($ready -join "").Trim() -ne "INKWALL_READY") {
        Write-Host "One server login may be requested now to install the dedicated InkWall key."
        $publicKey = (Get-Content "$keyPath.pub" -Raw).Trim()
        $publicKey | & ssh -o ConnectTimeout=20 $Target 'umask 077; mkdir -p "$HOME/.ssh"; touch "$HOME/.ssh/authorized_keys"; read key; grep -qxF "$key" "$HOME/.ssh/authorized_keys" || printf "%s\n" "$key" >> "$HOME/.ssh/authorized_keys"'
        if ($LASTEXITCODE -ne 0) { throw "Could not install the InkWall SSH key on the server." }
    }

    $ready = & ssh -i $keyPath -o BatchMode=yes -o ConnectTimeout=8 $Target "printf INKWALL_READY" 2>$null
    if (($ready -join "").Trim() -ne "INKWALL_READY") { throw "Passwordless SSH verification failed." }
    return $keyPath
}

function Find-RemoteEnvPath($Target, $KeyPath, $ExistingPath = "") {
    if ($ExistingPath -and $ExistingPath -match '^/[A-Za-z0-9_./-]+$') {
        $exists = & ssh -i $KeyPath -o BatchMode=yes $Target "test -f '$ExistingPath' && printf FOUND" 2>$null
        if (($exists -join "").Trim() -eq "FOUND") { return $ExistingPath }
    }

    $scan = 'find "$HOME" /var/www /srv /opt -maxdepth 7 -type f -name .env 2>/dev/null | while IFS= read -r f; do grep -q "^INKWALL_" "$f" && printf "%s\n" "$f"; done | head -20'
    $found = @(& ssh -i $KeyPath -o BatchMode=yes $Target $scan 2>$null | Where-Object { $_ -match '^/[A-Za-z0-9_./-]+$' } | Select-Object -Unique)
    if ($found.Count -eq 1) {
        Write-Host "Found InkWall server config: $($found[0])"
        if (YesNo "Use this server config?" "y") { return [string]$found[0] }
    } elseif ($found.Count -gt 1) {
        Write-Host "Found multiple InkWall server configs:"
        for ($i = 0; $i -lt $found.Count; $i++) { Write-Host "  $($i + 1)) $($found[$i])" }
        $choice = Ask "Choose a config number" "1"
        if ($choice -match '^\d+$' -and [int]$choice -ge 1 -and [int]$choice -le $found.Count) {
            return [string]$found[[int]$choice - 1]
        }
    }

    while ($true) {
        $path = Ask "Absolute path to the server .env" "/var/www/inkwall/.env"
        if ($path -match '^/[A-Za-z0-9_./-]+$') { return $path }
        Write-Host "Please enter an absolute Linux path without spaces."
    }
}

function Test-RemotePortFree($Target, $KeyPath, $Port) {
    $command = "command -v php >/dev/null 2>&1 || { printf NO_PHP; exit 2; }; php -r '`$s=@stream_socket_server(`"tcp://127.0.0.1:$Port`",`$e,`$m); if (`$s) { fclose(`$s); echo `"FREE`"; }'"
    $result = & ssh -i $KeyPath -o BatchMode=yes $Target $command 2>$null
    $value = ($result -join "").Trim()
    if ($value -eq "NO_PHP") { throw "PHP CLI was not found on the InkWall server." }
    return ($value -eq "FREE")
}

function Configure-RemoteServer($Target, $KeyPath, $EnvFile, $ConfigText) {
    $id = [Guid]::NewGuid().ToString("N")
    $localPair = Join-Path ([IO.Path]::GetTempPath()) "inkwall-pair-$id.env"
    $remotePair = "/tmp/inkwall-pair-$id.env"
    $remoteHelper = "/tmp/inkwall-pair-$id.php"
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [IO.File]::WriteAllText($localPair, $ConfigText, $utf8)
    try {
        & scp -q -i $KeyPath $localPair "${Target}:$remotePair"
        if ($LASTEXITCODE -ne 0) { throw "Could not copy the private review configuration to the server." }
        & scp -q -i $KeyPath (Join-Path $Root "tools\apply-private-review-config.php") "${Target}:$remoteHelper"
        if ($LASTEXITCODE -ne 0) { throw "Could not copy the server configuration helper." }
        $command = "chmod 600 '$remotePair'; php '$remoteHelper' --env='$EnvFile' --input='$remotePair'; rc=`$?; rm -f '$remotePair' '$remoteHelper'; exit `$rc"
        & ssh -i $KeyPath -o BatchMode=yes $Target $command
        if ($LASTEXITCODE -ne 0) { throw "The server rejected the private review configuration." }
    } finally {
        Remove-Item $localPair -Force -ErrorAction SilentlyContinue
        & ssh -i $KeyPath -o BatchMode=yes $Target "rm -f '$remotePair' '$remoteHelper'" 2>$null | Out-Null
    }
}

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host "PHP CLI was not found in PATH. Install PHP for Windows first, then run this script again."
    exit 1
}

Write-Host "InkWall quick setup"
Write-Host "GitHub and connection details are verified before anything starts."
$existing = Read-EnvFile $EnvPath
$githubDefault = "yourname"
if ($existing.ContainsKey("INKWALL_BRANDING_JSON")) {
    try {
        $existingBrand = $existing["INKWALL_BRANDING_JSON"] | ConvertFrom-Json
        if ($existingBrand.profile_url) { $githubDefault = [string]$existingBrand.profile_url }
    } catch {}
}

$githubUser = Resolve-GitHubUser $githubDefault
$ownerName = if ([string]::IsNullOrWhiteSpace([string]$githubUser.name)) { [string]$githubUser.login } else { [string]$githubUser.name }
$githubProfile = [string]$githubUser.html_url
$repoUrl = Resolve-GitHubRepo $githubUser

while ($true) {
    $publicDefault = if ($existing.ContainsKey("INKWALL_PUBLIC_URL")) { $existing["INKWALL_PUBLIC_URL"] } else { "https://example.com/inkwall" }
    $publicUrl = Normalize-PublicUrl (Ask "Public InkWall URL" $publicDefault)
    if ($publicUrl) { break }
    Write-Host "Please enter a valid public URL."
}
$siteLabel = Site-Label $publicUrl
$emailDefault = if ($existing.ContainsKey("INKWALL_REVIEW_EMAIL")) { $existing["INKWALL_REVIEW_EMAIL"] } else { "admin@example.com" }
$reviewEmail = Ask "Review email" $emailDefault
$accent = "#d7422f"
if (YesNo "Customize branding now?" "n") {
    $ownerName = Ask "Owner display name" $ownerName
    $accent = Ask "Accent color" $accent
}

Write-Host ""
Write-Host "Setup summary"
Write-Host "  Owner: $ownerName (@$($githubUser.login))"
Write-Host "  Repository: $repoUrl"
Write-Host "  Public URL: $publicUrl"
Write-Host "  Review email: $reviewEmail"

Write-Host ""
Write-Host "Moderation:"
Write-Host "  1) Cloud review, private computer if cloud is unavailable"
Write-Host "  2) Private computer only"
Write-Host "  3) Local safety checks only"
$mode = Ask "Choose 1, 2, or 3" "1"
if ($mode -notin @("1", "2", "3")) { $mode = "1" }

$cloudEnabled = if ($mode -eq "1") { "1" } else { "0" }
$textCloud = $cloudEnabled
$imageCloud = $cloudEnabled
$remoteMode = if ($mode -eq "1") { "fallback" } elseif ($mode -eq "2") { "always" } else { "off" }
$deepseekKey = if ($existing.ContainsKey("DEEPSEEK_API_KEY")) { $existing["DEEPSEEK_API_KEY"] } else { "" }
$openaiKey = if ($existing.ContainsKey("OPENAI_API_KEY")) { $existing["OPENAI_API_KEY"] } else { "" }
$hadSavedCloudKeys = [bool]($deepseekKey -or $openaiKey)
if ($mode -eq "1") {
    if (-not $deepseekKey) { $deepseekKey = Ask-Secret "DeepSeek API key, optional" }
    if (-not $openaiKey) { $openaiKey = Ask-Secret "OpenAI API key, optional" }
    if ($hadSavedCloudKeys -and (YesNo "Replace saved cloud API keys?" "n")) {
        $deepseekKey = Ask-Secret "New DeepSeek API key, optional"
        $openaiKey = Ask-Secret "New OpenAI API key, optional"
    }
}

if (Get-Command Get-ScheduledTask -ErrorAction SilentlyContinue) {
    $oldTask = Get-ScheduledTask -TaskName "InkWall Private Review" -ErrorAction SilentlyContinue
    if ($oldTask -and $oldTask.State -eq "Running") {
        Stop-ScheduledTask -TaskName "InkWall Private Review"
        Start-Sleep -Milliseconds 800
    }
}

$remoteSecret = New-Secret
$remoteEncryptKey = New-Secret
$preferredPort = if ($existing.ContainsKey("INKWALL_PRIVATE_REVIEW_PORT") -and $existing["INKWALL_PRIVATE_REVIEW_PORT"] -match '^\d+$') { [int]$existing["INKWALL_PRIVATE_REVIEW_PORT"] } else { 8787 }
$privatePort = Find-FreeLocalPort $preferredPort
$remoteEndpoint = if ($remoteMode -eq "off") { "" } else { "http://127.0.0.1:$privatePort" }
$sshTarget = ""
$sshKey = ""
$serverEnvPath = ""
$serverPaired = $false

if ($remoteMode -ne "off") {
    $connectDefault = if ($existing.ContainsKey("INKWALL_PRIVATE_REVIEW_SSH_TARGET")) { "y" } else { "y" }
    if (YesNo "Connect this review PC to an existing InkWall server now?" $connectDefault) {
        Require-SshTools
        $targetDefault = if ($existing.ContainsKey("INKWALL_PRIVATE_REVIEW_SSH_TARGET")) { $existing["INKWALL_PRIVATE_REVIEW_SSH_TARGET"] } else { "user@your-server" }
        while ($true) {
            $sshTarget = Ask "SSH server login (user@host)" $targetDefault
            if (Validate-SshTarget $sshTarget) { break }
            Write-Host "Please use the form user@host."
        }
        $sshKey = Ensure-SshKey $sshTarget
        $oldServerEnv = if ($existing.ContainsKey("INKWALL_PRIVATE_REVIEW_SERVER_ENV")) { $existing["INKWALL_PRIVATE_REVIEW_SERVER_ENV"] } else { "" }
        $serverEnvPath = Find-RemoteEnvPath $sshTarget $sshKey $oldServerEnv
        $portAttempts = 0
        while (-not (Test-RemotePortFree $sshTarget $sshKey $privatePort)) {
            $portAttempts++
            if ($portAttempts -ge 100) { throw "No shared free review port was found on this PC and server." }
            $privatePort = Find-FreeLocalPort ($privatePort + 1)
            $remoteEndpoint = "http://127.0.0.1:$privatePort"
        }

        $cloudSecretLines = @()
        if ($deepseekKey) { $cloudSecretLines += "DEEPSEEK_API_KEY=$deepseekKey" }
        if ($openaiKey) { $cloudSecretLines += "OPENAI_API_KEY=$openaiKey" }
        $cloudSecretConfig = $cloudSecretLines -join "`n"
        $pairConfig = @"
INKWALL_AI_MODERATION=auto
INKWALL_AI_CLOUD_ENABLED=$cloudEnabled
INKWALL_AI_TEXT_CLOUD_ENABLED=$textCloud
INKWALL_AI_IMAGE_CLOUD_ENABLED=$imageCloud
INKWALL_AI_PROVIDER=deepseek
INKWALL_AI_TEXT_PROVIDER=deepseek
INKWALL_AI_TEXT_MODEL=deepseek-v4-flash
$cloudSecretConfig
DEEPSEEK_BASE_URL=https://api.deepseek.com
INKWALL_DEEPSEEK_MODEL=deepseek-v4-flash
INKWALL_DEEPSEEK_BALANCE_GUARD=1
INKWALL_DEEPSEEK_BALANCE_FAIL_CLOSED=0
INKWALL_DEEPSEEK_FAIL_OPEN=1
INKWALL_DEEPSEEK_DAILY_SPEND_LIMIT_USD=1.00
INKWALL_AI_IMAGE_PROVIDER=openai_vision
INKWALL_AI_IMAGE_MODEL=gpt-4o-mini
INKWALL_OPENAI_VISION_MODEL=gpt-4o-mini
INKWALL_OPENAI_VISION_DETAIL=low
INKWALL_OPENAI_VISION_FAIL_OPEN=1
INKWALL_OPENAI_DAILY_SPEND_LIMIT_USD=1.00
INKWALL_REMOTE_REVIEW=$remoteMode
INKWALL_REMOTE_REVIEW_ENDPOINT=$remoteEndpoint
INKWALL_REMOTE_REVIEW_SECRET=$remoteSecret
INKWALL_REMOTE_REVIEW_ENCRYPT=1
INKWALL_REMOTE_REVIEW_ENCRYPTION_KEY=$remoteEncryptKey
INKWALL_REMOTE_REVIEW_FAIL_OPEN=1
INKWALL_REMOTE_REVIEW_SEND_TEXT=1
INKWALL_REMOTE_REVIEW_SEND_IMAGE=1
INKWALL_REMOTE_REVIEW_TIMEOUT_SECONDS=8
"@
        Configure-RemoteServer $sshTarget $sshKey $serverEnvPath $pairConfig
        $serverPaired = $true
    }
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

INKWALL_PRIVATE_REVIEW_PORT=$privatePort
INKWALL_PRIVATE_REVIEW_SSH_TARGET=$sshTarget
INKWALL_PRIVATE_REVIEW_SSH_KEY=$sshKey
INKWALL_PRIVATE_REVIEW_SERVER_ENV=$serverEnvPath

INKWALL_AI_ALLOW_REJECT=0
INKWALL_AI_FLAG_POLICY_JSON={"advertising":"allow","harassment":"hold","copyright":"hold","violence":"hold","nudity":"hold"}
INKWALL_AI_REVIEW_UNCHECKED_IMAGES=0
INKWALL_AI_ALLOW_UNCHECKED_IMAGES=0
INKWALL_SHARE_AI_METADATA=1
INKWALL_AI_METADATA_ENDPOINT=https://angusu.de/inkwall/telemetry.php
"@

if (Test-Path $EnvPath) {
    $backup = "$EnvPath.backup.$(Get-Date -Format yyyyMMdd-HHmmss)"
    Copy-Item $EnvPath $backup
    Write-Host "Existing .env backed up to $backup"
}
$utf8 = New-Object System.Text.UTF8Encoding($false)
[IO.File]::WriteAllText((Join-Path $Root $EnvPath), $envContent + "`n", $utf8)
New-Item -ItemType Directory -Force -Path "data\inkwall" | Out-Null

Write-Host ""
Write-Host "Setup complete. Private keys were rotated and saved without printing them."
if ($serverPaired) {
    Write-Host "Server paired: $sshTarget"
    Write-Host "The receiver and encrypted SSH tunnel can now start together."
} elseif ($remoteMode -ne "off") {
    Write-Host "The local receiver is configured, but no server is connected yet."
}

if ($remoteMode -ne "off") {
    if (YesNo "Start InkWall automatically after Windows sign-in?" "y") {
        $autostartMode = Ask "Autostart mode: hidden or window" "hidden"
        if ($autostartMode -notin @("hidden", "window")) { $autostartMode = "hidden" }
        & (Join-Path $Root "manage-private-review-windows.ps1") -Action install -WindowMode $autostartMode -Port $privatePort -Server $sshTarget
        if (YesNo "Start it now?" "y") {
            & (Join-Path $Root "manage-private-review-windows.ps1") -Action start
        }
    } else {
        Write-Host "Start later with .\start-private-review-windows.cmd"
    }
}
