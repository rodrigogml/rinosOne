@echo off
setlocal EnableExtensions EnableDelayedExpansion
set "EXIT_CODE=0"

if "%~1"=="" (
    echo Arraste uma ou mais imagens sobre este arquivo.
    set "EXIT_CODE=1"
) else (
    py -3 "%~dp0icon_treatment.py" %*
    set "EXIT_CODE=!ERRORLEVEL!"
)

echo.
pause
exit /b %EXIT_CODE%
