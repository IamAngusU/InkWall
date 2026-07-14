param(
    [ValidateSet("install", "remove", "status", "start", "stop", "ask")]
    [string]$Action = "ask",
    [ValidateSet("hidden", "window")]
    [string]$WindowMode = "hidden",
    [int]$Port = 8787,
    [string]$Server = "",
    [string]$TaskName = "InkWall Private Review"
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root

function Require-ScheduledTasks {
    if (-not (Get-Command Get-ScheduledTask -ErrorAction SilentlyContinue)) {
        Write-Host "Windows Scheduled Tasks are not available in this shell."
        Write-Host "Run this script in Windows PowerShell or use manage-private-review-linux.sh on Linux."
        exit 1
    }
}

function Ask-Choice($Prompt, $Choices, $Default) {
    Write-Host $Prompt
    foreach ($choice in $Choices) { Write-Host "  $choice" }
    $value = Read-Host "Choose [$Default]"
    if (-not $value) { return $Default }
    return $value
}

function Task-Exists {
    $task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    return $null -ne $task
}

function Install-Task {
    if (Task-Exists) {
        Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false | Out-Null
    }

    $script = Join-Path $Root "start-private-review-windows.ps1"
    $args = "-NoProfile -ExecutionPolicy Bypass"
    if ($WindowMode -eq "hidden") { $args += " -WindowStyle Hidden" }
    $args += " -File `"$script`" -Port $Port"
    if ($Server) { $args += " -Server `"$Server`"" }

    $action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument $args -WorkingDirectory $Root
    $trigger = New-ScheduledTaskTrigger -AtLogOn
    $settings = New-ScheduledTaskSettingsSet `
        -AllowStartIfOnBatteries `
        -DontStopIfGoingOnBatteries `
        -ExecutionTimeLimit (New-TimeSpan -Days 3650) `
        -RestartCount 3 `
        -RestartInterval (New-TimeSpan -Minutes 1)

    Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings -Description "Runs the InkWall private review receiver after sign-in." | Out-Null
    Write-Host "Installed autostart task: $TaskName"
    Write-Host "Mode: $WindowMode"
    Write-Host "Start now with: .\manage-private-review-windows.cmd start"
}

function Show-Status {
    $task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    if (-not $task) {
        Write-Host "Autostart is not installed."
        return
    }
    $info = Get-ScheduledTaskInfo -TaskName $TaskName
    Write-Host "Autostart is installed."
    Write-Host "State: $($task.State)"
    Write-Host "Last run: $($info.LastRunTime)"
    Write-Host "Last result: $($info.LastTaskResult)"
    Write-Host "Next run: $($info.NextRunTime)"
}

if ($Action -eq "ask") {
    $choice = Ask-Choice "InkWall private review autostart" @("install", "remove", "status", "start", "stop") "status"
    if (@("install", "remove", "status", "start", "stop") -notcontains $choice) {
        Write-Host "Unknown choice: $choice"
        exit 1
    }
    $Action = $choice
    if ($Action -eq "install") {
        $mode = Ask-Choice "Start with a visible window or hidden?" @("hidden", "window") $WindowMode
        if (@("hidden", "window") -contains $mode) { $WindowMode = $mode }
    }
}

switch ($Action) {
    "install" {
        Require-ScheduledTasks
        Install-Task
    }
    "remove" {
        Require-ScheduledTasks
        if (Task-Exists) {
            Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false | Out-Null
            Write-Host "Removed autostart task: $TaskName"
        } else {
            Write-Host "Autostart is not installed."
        }
    }
    "status" {
        Require-ScheduledTasks
        Show-Status
    }
    "start" {
        Require-ScheduledTasks
        if (-not (Task-Exists)) { Install-Task }
        Start-ScheduledTask -TaskName $TaskName
        Write-Host "Started: $TaskName"
    }
    "stop" {
        Require-ScheduledTasks
        if (Task-Exists) {
            Stop-ScheduledTask -TaskName $TaskName
            Write-Host "Stopped: $TaskName"
        } else {
            Write-Host "Autostart is not installed."
        }
    }
}
