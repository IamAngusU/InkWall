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

function Info($Text) {
    Write-Host $Text -ForegroundColor Cyan
}

function Good($Text) {
    Write-Host $Text -ForegroundColor Green
}

function Warn($Text) {
    Write-Host $Text -ForegroundColor Yellow
}

function Muted($Text) {
    Write-Host $Text -ForegroundColor DarkGray
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

function GitHub-DefaultFromRemote {
    if (-not (Get-Command git -ErrorAction SilentlyContinue)) { return "" }
    $remote = (& git remote get-url origin 2>$null)
    if (-not $remote) { return "" }
    $value = ($remote -join "").Trim()
    if ($value -match 'github\.com[:/]([^/]+)/[^/]+?(?:\.git)?$') {
        return "https://github.com/$($Matches[1])"
    }
    return ""
}

function Test-WebUrl($Url) {
    try {
        $response = Invoke-WebRequest -Uri $Url -Method Head -MaximumRedirection 5 -UseBasicParsing -ErrorAction Stop
        return ([int]$response.StatusCode -ge 200 -and [int]$response.StatusCode -lt 400)
    } catch {
        try {
            $response = Invoke-WebRequest -Uri $Url -Method Get -MaximumRedirection 5 -UseBasicParsing -ErrorAction Stop
            return ([int]$response.StatusCode -ge 200 -and [int]$response.StatusCode -lt 400)
        } catch {
            return $false
        }
    }
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
            Good "Found GitHub account: $displayName (@$($user.login))"
            Write-Host "Profile: " -NoNewline
            Write-Host "$($user.html_url)" -ForegroundColor Green
            if (YesNo "Is this the correct GitHub account?" "y") { return $user }
        } catch {
            $profileUrl = "https://github.com/$username"
            if (Test-WebUrl $profileUrl) {
                Good "GitHub profile is reachable: $profileUrl"
                if (YesNo "Use this GitHub account?" "y") {
                    return [pscustomobject]@{
                        login = $username
                        name = $username
                        html_url = $profileUrl
                    }
                }
            } else {
                Warn "GitHub account '$username' was not found or GitHub could not be reached."
                if (YesNo "Continue with this GitHub account without verification?" "n") {
                    return [pscustomobject]@{
                        login = $username
                        name = $username
                        html_url = $profileUrl
                    }
                }
            }
        }
    }
}

function Resolve-GitHubRepo($User) {
    $headers = @{ "User-Agent" = "InkWall-Setup"; "Accept" = "application/vnd.github+json" }
    $defaultUrl = "https://github.com/$($User.login)/InkWall"
    try {
        $repo = Invoke-RestMethod -Uri "https://api.github.com/repos/$($User.login)/InkWall" -Headers $headers -Method Get
        Write-Host "Repository found: " -NoNewline
        Write-Host "$($repo.html_url)" -ForegroundColor Green
        return [string]$repo.html_url
    } catch {
        if (Test-WebUrl $defaultUrl) {
            Write-Host "Repository found: " -NoNewline
            Write-Host $defaultUrl -ForegroundColor Green
            return $defaultUrl
        }
        Warn "No InkWall repository was found under @$($User.login)."
    }

    while ($true) {
        $repoUrl = Ask "InkWall repository URL" $defaultUrl
        if ($repoUrl -notmatch '^https://github\.com/([^/]+)/([^/]+)/?$') {
            Write-Host "Please enter a full github.com repository URL."
            continue
        }
        try {
            $repo = Invoke-RestMethod -Uri "https://api.github.com/repos/$($Matches[1])/$($Matches[2])" -Headers $headers -Method Get
            Write-Host "Repository found: " -NoNewline
            Write-Host "$($repo.html_url)" -ForegroundColor Green
            return [string]$repo.html_url
        } catch {
            $webRepoUrl = "https://github.com/$($Matches[1])/$($Matches[2])"
            if (Test-WebUrl $webRepoUrl) {
                Write-Host "Repository found: " -NoNewline
                Write-Host $webRepoUrl -ForegroundColor Green
                return $webRepoUrl
            }
            Warn "That GitHub repository does not exist or is not publicly reachable."
            if (YesNo "Continue with this repository URL without verification?" "n") { return $repoUrl.TrimEnd("/") }
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

function Env-CandidatePaths($PublicUrl) {
    $paths = @()
    try {
        $uri = [Uri]$PublicUrl
        $host = $uri.Host.ToLowerInvariant()
        $siteDir = $host -replace '[^a-z0-9]+', '_'
        $paths += "/var/www/html/AlleProjekte/$siteDir/.env"
        $paths += "/var/www/html/$siteDir/.env"
        $paths += "/var/www/$siteDir/.env"
        $paths += "/var/www/html/$host/.env"
        $paths += "/var/www/$host/.env"
    } catch {}
    $paths += "/var/www/html/AlleProjekte/angusu_de/.env"
    return @($paths | Where-Object { $_ -match '^/[A-Za-z0-9_./-]+$' } | Select-Object -Unique)
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

function Invoke-QuietSsh($Target, $KeyPath, $RemoteCommand, $Timeout = 8) {
    $errFile = [IO.Path]::GetTempFileName()
    $args = @("-i", $KeyPath, "-o", "BatchMode=yes", "-o", "ConnectTimeout=$Timeout", $Target, $RemoteCommand)
    try {
        $rawOutput = & ssh @args 2>$errFile
        $exitCode = if ($null -eq $LASTEXITCODE) { 1 } else { [int]$LASTEXITCODE }
        $output = if ($null -eq $rawOutput) { "" } else { [string]::Join("`n", @($rawOutput)) }
        return @{ ExitCode = $exitCode; Output = ([string]$output).Trim() }
    } catch {
        return @{ ExitCode = 1; Output = "" }
    } finally {
        Remove-Item $errFile -Force -ErrorAction SilentlyContinue
    }
}

function Test-SshKey($Target, $KeyPath) {
    if (-not $KeyPath -or -not (Test-Path $KeyPath)) { return $false }
    $result = Invoke-QuietSsh $Target $KeyPath "printf INKWALL_READY"
    return ($result.ExitCode -eq 0 -and $result.Output -eq "INKWALL_READY")
}

function Find-SshKeyCandidates {
    $sshDir = Join-Path $HOME ".ssh"
    New-Item -ItemType Directory -Force -Path $sshDir | Out-Null
    $known = @(
        "inkwall_review_ed25519",
        "id_ed25519",
        "id_ecdsa",
        "id_rsa"
    ) | ForEach-Object { Join-Path $sshDir $_ }

    $extra = @()
    if (Test-Path $sshDir) {
        $extra = @(Get-ChildItem -Path $sshDir -File -ErrorAction SilentlyContinue |
            Where-Object {
                $_.Name -notmatch '\.pub$' -and
                $_.Name -notmatch '^known_hosts' -and
                $_.Name -notmatch '^config$'
            } |
            Select-Object -ExpandProperty FullName)
    }
    return @($known + $extra | Where-Object { Test-Path $_ } | Select-Object -Unique)
}

function Install-DedicatedSshKey($Target) {
    $sshDir = Join-Path $HOME ".ssh"
    New-Item -ItemType Directory -Force -Path $sshDir | Out-Null
    $keyPath = Join-Path $sshDir "inkwall_review_ed25519"
    if (-not (Test-Path $keyPath)) {
        Info "Creating a dedicated SSH identity for InkWall."
        $keygenArgs = "-q -t ed25519 -f `"$keyPath`" -N `"`""
        $keygen = Start-Process -FilePath "ssh-keygen" -ArgumentList $keygenArgs -Wait -PassThru -NoNewWindow
        if ($keygen.ExitCode -ne 0) { throw "Could not create the InkWall SSH key." }
    }

    Info "One server login may be requested now to install the InkWall key."
    $publicKey = (Get-Content "$keyPath.pub" -Raw).Trim()
    $publicKey | & ssh -o ConnectTimeout=20 $Target 'umask 077; mkdir -p "$HOME/.ssh"; touch "$HOME/.ssh/authorized_keys"; read key; grep -qxF "$key" "$HOME/.ssh/authorized_keys" || printf "%s\n" "$key" >> "$HOME/.ssh/authorized_keys"'
    if ($LASTEXITCODE -ne 0) { throw "Could not install the InkWall SSH key on the server. If password login is disabled, choose an existing SSH key instead." }

    if (-not (Test-SshKey $Target $keyPath)) { throw "Passwordless SSH verification failed." }
    return $keyPath
}

function Select-SshKey($Target, $ExistingKey = "") {
    $candidates = @()
    if ($ExistingKey -and (Test-Path $ExistingKey)) { $candidates += $ExistingKey }
    $candidates += Find-SshKeyCandidates
    $candidates = @($candidates | Select-Object -Unique)

    $working = @()
    if ($candidates.Count -gt 0) {
        Info "Checking local SSH keys for $Target..."
        foreach ($candidate in $candidates) {
            if (Test-SshKey $Target $candidate) { $working += $candidate }
        }
    }

    if ($working.Count -eq 1) {
        Good "SSH key works: $($working[0])"
        return [string]$working[0]
    }
    if ($working.Count -gt 1) {
        Good "Working SSH keys found:"
        for ($i = 0; $i -lt $working.Count; $i++) { Write-Host "  $($i + 1)) $($working[$i])" -ForegroundColor Green }
        $choice = Ask "Choose SSH key number" "1"
        if ($choice -match '^\d+$' -and [int]$choice -ge 1 -and [int]$choice -le $working.Count) {
            return [string]$working[[int]$choice - 1]
        }
        return [string]$working[0]
    }

    Warn "No working SSH key was found automatically."
    if ($candidates.Count -gt 0) {
        Muted "Keys found locally, but the server did not accept them:"
        for ($i = 0; $i -lt $candidates.Count; $i++) { Muted "  $($i + 1)) $($candidates[$i])" }
    }

    while ($true) {
        Write-Host ""
        Write-Host "SSH connection options:" -ForegroundColor Cyan
        Write-Host "  1) Choose another private key file"
        Write-Host "  2) Create and install a dedicated InkWall key with one server password login"
        Write-Host "  3) Skip server pairing for now"
        $choice = Ask "Choose 1, 2, or 3" "1"
        if ($choice -eq "1") {
            $manual = Ask "Private key path" (Join-Path $HOME ".ssh\id_ed25519")
            $manual = $manual.Trim('"')
            if (Test-SshKey $Target $manual) {
                Good "SSH key works: $manual"
                return $manual
            }
            Warn "That key did not work for $Target."
        } elseif ($choice -eq "2") {
            return (Install-DedicatedSshKey $Target)
        } elseif ($choice -eq "3") {
            return ""
        }
    }
}

function Find-RemoteEnvPath($Target, $KeyPath, $ExistingPath = "", $CandidatePaths = @()) {
    if ($ExistingPath -and $ExistingPath -match '^/[A-Za-z0-9_./-]+$') {
        $existing = Invoke-QuietSsh $Target $KeyPath "test -f '$ExistingPath' && printf FOUND"
        if ($existing.Output -eq "FOUND") { return $ExistingPath }
    }

    foreach ($candidate in $CandidatePaths) {
        $candidateResult = Invoke-QuietSsh $Target $KeyPath "test -f '$candidate' && grep -Iqs '^INKWALL_' '$candidate' && printf FOUND"
        if ($candidateResult.Output -eq "FOUND") {
            Write-Host "Found InkWall server config: $candidate"
            if (YesNo "Use this server config?" "y") { return $candidate }
        }
    }

    $scan = @'
for d in "$HOME" /var/www /srv /opt; do
  [ -d "$d" ] && find "$d" -maxdepth 7 -type f -name .env -exec sh -c 'for f do grep -Iqs "^INKWALL_" "$f" && printf "%s\n" "$f"; done' sh {} + 2>/dev/null
done | head -20
'@
    $scanResult = Invoke-QuietSsh $Target $KeyPath $scan 20
    $found = @($scanResult.Output -split "`n" | Where-Object { $_ -match '^/[A-Za-z0-9_./-]+$' } | Select-Object -Unique)
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
        $fallbackPath = if ($CandidatePaths.Count -gt 0) { [string]$CandidatePaths[0] } else { "/var/www/inkwall/.env" }
        $path = Ask "Absolute path to the server .env" $fallbackPath
        if ($path -match '^/[A-Za-z0-9_./-]+$') { return $path }
        Write-Host "Please enter an absolute Linux path without spaces."
    }
}

function Test-RemotePortFree($Target, $KeyPath, $Port) {
    $script = @"
if command -v ss >/dev/null 2>&1; then
  if ss -ltnH "sport = :$Port" 2>/dev/null | grep -q .; then
    printf BUSY
  else
    printf FREE
  fi
  exit 0
fi
if command -v netstat >/dev/null 2>&1; then
  if netstat -ltn 2>/dev/null | awk '{print `$4}' | grep -Eq '(^|[.:])$Port`$'; then
    printf BUSY
  else
    printf FREE
  fi
  exit 0
fi
printf UNKNOWN
"@
    $encoded = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($script))
    $command = "printf '%s' '$encoded' | base64 -d | sh"
    $result = Invoke-QuietSsh $Target $KeyPath $command 15
    $value = $result.Output.Trim()
    if ($value -eq "BUSY") { return $false }
    return $true
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

Write-Host "InkWall quick setup" -ForegroundColor Cyan
Muted "GitHub, repository, SSH, and server config are checked before anything starts."
$existing = Read-EnvFile $EnvPath
$githubDefault = GitHub-DefaultFromRemote
if (-not $githubDefault) { $githubDefault = "yourname" }
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
Write-Host "Setup summary" -ForegroundColor Cyan
Write-Host "  Owner: " -NoNewline
Good "$ownerName (@$($githubUser.login))"
Write-Host "  Repository: " -NoNewline
Write-Host $repoUrl -ForegroundColor Green
Write-Host "  Public URL: " -NoNewline
Write-Host $publicUrl -ForegroundColor Green
Write-Host "  Review email: " -NoNewline
Good $reviewEmail

Write-Host ""
Write-Host "Moderation:" -ForegroundColor Cyan
Write-Host "  1) Cloud review plus this PC as fallback"
Muted "     Text/image APIs can review first. If they are off, down, or out of budget, this PC receives the review."
Write-Host "  2) This PC reviews everything"
Muted "     No cloud review calls. The server sends reviews to this computer through the SSH tunnel."
Write-Host "  3) Local safety checks only"
Muted "     No cloud calls and no private computer review. Only length, Unicode, rate limit, and similar checks."
$mode = Ask "Choose 1, 2, or 3" "1"
if ($mode -notin @("1", "2", "3")) { $mode = "1" }

$cloudEnabled = if ($mode -eq "1") { "1" } else { "0" }
$textCloud = $cloudEnabled
$imageCloud = $cloudEnabled
$remoteMode = if ($mode -eq "1") { "fallback" } elseif ($mode -eq "2") { "always" } else { "off" }
$deepseekKey = if ($existing.ContainsKey("DEEPSEEK_API_KEY")) { $existing["DEEPSEEK_API_KEY"] } else { "" }
$openaiKey = if ($existing.ContainsKey("OPENAI_API_KEY")) { $existing["OPENAI_API_KEY"] } else { "" }
$hadSavedCloudKeys = [bool]($deepseekKey -or $openaiKey)
$privateReviewCommand = if ($existing.ContainsKey("INKWALL_PRIVATE_REVIEW_COMMAND")) { $existing["INKWALL_PRIVATE_REVIEW_COMMAND"] } else { "" }
$ollamaUrl = if ($existing.ContainsKey("INKWALL_OLLAMA_URL")) { $existing["INKWALL_OLLAMA_URL"] } else { "http://127.0.0.1:11434" }
$ollamaModel = if ($existing.ContainsKey("INKWALL_OLLAMA_MODEL")) { $existing["INKWALL_OLLAMA_MODEL"] } else { "gemma3:4b" }
$ollamaSendImages = if ($existing.ContainsKey("INKWALL_OLLAMA_SEND_IMAGES")) { $existing["INKWALL_OLLAMA_SEND_IMAGES"] } else { "0" }
$browserReviewTimeout = if ($existing.ContainsKey("INKWALL_BROWSER_REVIEW_TIMEOUT_SECONDS")) { $existing["INKWALL_BROWSER_REVIEW_TIMEOUT_SECONDS"] } else { "180" }
$browserReviewOpen = if ($existing.ContainsKey("INKWALL_BROWSER_REVIEW_OPEN")) { $existing["INKWALL_BROWSER_REVIEW_OPEN"] } else { "1" }

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
        $oldSshKey = if ($existing.ContainsKey("INKWALL_PRIVATE_REVIEW_SSH_KEY")) { $existing["INKWALL_PRIVATE_REVIEW_SSH_KEY"] } else { "" }
        $sshKey = Select-SshKey $sshTarget $oldSshKey
        if (-not $sshKey) {
            Warn "Server pairing was skipped. You can run setup again after SSH access is ready."
            $sshTarget = ""
            $remoteEndpoint = "http://127.0.0.1:$privatePort"
        } else {
            $oldServerEnv = if ($existing.ContainsKey("INKWALL_PRIVATE_REVIEW_SERVER_ENV")) { $existing["INKWALL_PRIVATE_REVIEW_SERVER_ENV"] } else { "" }
            $serverEnvPath = Find-RemoteEnvPath $sshTarget $sshKey $oldServerEnv (Env-CandidatePaths $publicUrl)
            Info "Finding a local SSH tunnel port. No public firewall port is opened."
            $portAttempts = 0
            while (-not (Test-RemotePortFree $sshTarget $sshKey $privatePort)) {
                $portAttempts++
                if ($portAttempts -ge 20) {
                    Warn "Several nearby ports look busy on the server."
                    $manualPort = Ask "Review tunnel port" ([string]$privatePort)
                    if ($manualPort -notmatch '^\d+$') { throw "Invalid review tunnel port." }
                    $privatePort = [int]$manualPort
                    if (-not (Test-LocalPortFree $privatePort)) { throw "Port $privatePort is not free on this PC." }
                    break
                }
                Muted "Port $privatePort is not available. Trying the next port."
                $privatePort = Find-FreeLocalPort ($privatePort + 1)
                $remoteEndpoint = "http://127.0.0.1:$privatePort"
            }
            $remoteEndpoint = "http://127.0.0.1:$privatePort"
            Good "Review port selected: $privatePort"

            if ($mode -eq "1") {
                Muted "Existing cloud API keys on the server are kept unless you replace them here."
                if (YesNo "Update DeepSeek/OpenAI API keys on the server now?" "n") {
                    $deepseekKey = Ask-Secret "New DeepSeek API key, optional"
                    $openaiKey = Ask-Secret "New OpenAI API key, optional"
                } else {
                    $deepseekKey = ""
                    $openaiKey = ""
                }
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
            Info "Configuring the InkWall server connection..."
            Configure-RemoteServer $sshTarget $sshKey $serverEnvPath $pairConfig
            $serverPaired = $true
            Good "Server connection configured."
        }
    }
}

if ($mode -eq "1" -and -not $serverPaired) {
    if ($hadSavedCloudKeys) {
        Muted "Saved local cloud API keys will be kept."
        if (YesNo "Replace saved local cloud API keys?" "n") {
            $deepseekKey = Ask-Secret "New DeepSeek API key, optional"
            $openaiKey = Ask-Secret "New OpenAI API key, optional"
        }
    } else {
        Muted "No server was paired, so cloud keys can only be saved in this local .env."
        if (YesNo "Add local DeepSeek/OpenAI API keys now?" "n") {
            $deepseekKey = Ask-Secret "DeepSeek API key, optional"
            $openaiKey = Ask-Secret "OpenAI API key, optional"
        }
    }
}

if ($remoteMode -ne "off") {
    Write-Host ""
    Write-Host "Private computer review engine:" -ForegroundColor Cyan
    Write-Host "  1) Manual/default: save the job and return the default decision"
    Write-Host "  2) Ollama on this PC: run tools/private-review-ollama.php"
    Write-Host "  3) Custom command: run your own local script or app"
    Write-Host "  4) Browser bridge: open a local review page for a browser AI workflow"
    $engineDefault = if ($privateReviewCommand -match 'private-review-ollama\.php') { "2" } elseif ($privateReviewCommand -match 'private-review-browser\.php') { "4" } elseif ($privateReviewCommand) { "3" } else { "1" }
    $engine = Ask "Choose 1, 2, 3, or 4" $engineDefault
    if ($engine -eq "2") {
        $privateReviewCommand = "php tools/private-review-ollama.php"
        $ollamaUrl = Ask "Ollama URL" $ollamaUrl
        $ollamaModel = Ask "Ollama model" $ollamaModel
        Write-Host "Ollama image sending:" -ForegroundColor Cyan
        Write-Host "  0) Text only, image jobs can still be held by policy"
        Write-Host "  1) Send image bytes to Ollama for multimodal models"
        $ollamaSendImages = Ask "Choose 0 or 1" $ollamaSendImages
        if ($ollamaSendImages -notin @("0", "1")) { $ollamaSendImages = "0" }
    } elseif ($engine -eq "3") {
        $privateReviewCommand = Ask "Custom review command" $privateReviewCommand
    } elseif ($engine -eq "4") {
        $privateReviewCommand = "php tools/private-review-browser.php"
        Write-Host "Browser bridge mode:" -ForegroundColor Cyan
        Write-Host "  1) Open the local review page automatically"
        Write-Host "  0) Only save the files in the inbox"
        $browserReviewOpen = Ask "Choose 0 or 1" $browserReviewOpen
        if ($browserReviewOpen -notin @("0", "1")) { $browserReviewOpen = "1" }
        $browserReviewTimeout = Ask "Seconds to wait for browser-answer.json" $browserReviewTimeout
        if ($browserReviewTimeout -notmatch '^\d+$') { $browserReviewTimeout = "180" }
    } else {
        $privateReviewCommand = ""
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
INKWALL_PRIVATE_REVIEW_COMMAND=$privateReviewCommand
INKWALL_OLLAMA_URL=$ollamaUrl
INKWALL_OLLAMA_MODEL=$ollamaModel
INKWALL_OLLAMA_SEND_IMAGES=$ollamaSendImages
INKWALL_OLLAMA_TIMEOUT_SECONDS=25
INKWALL_BROWSER_REVIEW_OPEN=$browserReviewOpen
INKWALL_BROWSER_REVIEW_TIMEOUT_SECONDS=$browserReviewTimeout
INKWALL_BROWSER_REVIEW_MODEL=browser-bridge

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
    Write-Host ""
    Write-Host "Windows autostart:" -ForegroundColor Cyan
    Write-Host "  y) Start the private receiver and SSH tunnel after Windows sign-in"
    Write-Host "  n) Start it manually later"
    if (YesNo "Install autostart?" "y") {
        Write-Host ""
        Write-Host "Autostart window mode:" -ForegroundColor Cyan
        Write-Host "  hidden) Run quietly in the background"
        Write-Host "  window) Open a visible window, useful while testing"
        $autostartMode = Ask "Choose hidden or window" "hidden"
        if ($autostartMode -notin @("hidden", "window")) { $autostartMode = "hidden" }
        & (Join-Path $Root "manage-private-review-windows.ps1") -Action install -WindowMode $autostartMode -Port $privatePort -Server $sshTarget
        Write-Host ""
        Write-Host "Start now:" -ForegroundColor Cyan
        Write-Host "  y) Start receiver and SSH tunnel now"
        Write-Host "  n) Leave it installed, but start later"
        if (YesNo "Start now?" "y") {
            & (Join-Path $Root "manage-private-review-windows.ps1") -Action start
        }
    } else {
        Write-Host "Start later with .\start-private-review-windows.cmd"
    }
}
