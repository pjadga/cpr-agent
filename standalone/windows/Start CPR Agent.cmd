@echo off
rem Double click target. Kept as a .cmd so the shortcut works even where
rem PowerShell scripts are blocked by policy: -ExecutionPolicy Bypass applies
rem to this invocation only and does not change any machine setting.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0launcher.ps1"
