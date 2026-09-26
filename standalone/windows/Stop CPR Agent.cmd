@echo off
rem Thin wrapper, same pattern as the launcher: all the logic lives in the .ps1
rem so there is no cmd escaping to get wrong.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0stop.ps1"
