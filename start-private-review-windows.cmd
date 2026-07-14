@echo off
setlocal
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start-private-review-windows.ps1" -KeepOpenOnError %*
if errorlevel 1 pause
