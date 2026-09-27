@echo off
setlocal EnableExtensions EnableDelayedExpansion
set "EXIT_CODE=0"

if "%~2"=="" (
    echo Arraste exatamente duas imagens sobre este arquivo.
    set "EXIT_CODE=1"
) else if not "%~3"=="" (
    echo Arraste exatamente duas imagens sobre este arquivo.
    set "EXIT_CODE=1"
) else (
    py -3 "%~dp0compose_icons.py" "%~1" "%~2"
    set "EXIT_CODE=!ERRORLEVEL!"
)

echo.
pause
exit /b %EXIT_CODE%
