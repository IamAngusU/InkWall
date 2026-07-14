@echo off
setlocal
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start-private-review-windows.ps1" %*
