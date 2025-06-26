@echo off
REM Script batch para deploy con mensaje personalizado
REM Uso: deploy-custom.bat "Mensaje del commit"

setlocal enabledelayedexpansion

echo.
echo 🚀 LexHoy Despachos - Deploy con Mensaje Personalizado
echo ====================================================
echo.

REM Verificar si PHP está disponible
php --version >nul 2>&1
if errorlevel 1 (
    echo ❌ ERROR: PHP no está instalado o no está en el PATH
    echo 💡 Instala PHP desde: https://www.php.net/downloads
    pause
    exit /b 1
)

REM Verificar si se proporcionó un mensaje
set "commit_message=%~1"
if "%commit_message%"=="" (
    echo 📝 Ingresa el mensaje para el commit:
    set /p commit_message="Mensaje: "
)

if "%commit_message%"=="" (
    echo ❌ ERROR: Se requiere un mensaje para el commit
    echo.
    echo Uso: deploy-custom.bat "Tu mensaje aquí"
    echo   o: deploy-custom.bat
    echo      (te pedirá el mensaje)
    echo.
    pause
    exit /b 1
)

echo.
echo 📝 Mensaje del commit: "%commit_message%"
echo.

REM Ejecutar deploy con mensaje personalizado
php sync-to-production.php custom "%commit_message%"

echo.
echo ✅ Deploy completado con mensaje personalizado
pause 