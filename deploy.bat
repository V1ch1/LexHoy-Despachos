@echo off
REM Script batch para deploy directo de LexHoy Despachos
REM Uso: deploy.bat [comando]

setlocal enabledelayedexpansion

echo.
echo 🚀 LexHoy Despachos - Deploy Directo
echo ===================================
echo.

REM Verificar si PHP está disponible
php --version >nul 2>&1
if errorlevel 1 (
    echo ❌ ERROR: PHP no está instalado o no está en el PATH
    echo 💡 Instala PHP desde: https://www.php.net/downloads
    pause
    exit /b 1
)

REM Verificar argumentos
set "command=%1"
if "%command%"=="" set "command=help"

REM Ejecutar comando correspondiente
if "%command%"=="setup" goto :setup
if "%command%"=="status" goto :status
if "%command%"=="push" goto :push
if "%command%"=="deploy" goto :deploy
if "%command%"=="full" goto :full
if "%command%"=="help" goto :help

:help
echo Comandos disponibles:
echo   deploy.bat setup   - Configurar Git por primera vez
echo   deploy.bat status  - Ver estado actual
echo   deploy.bat push    - Subir cambios a GitHub
echo   deploy.bat deploy  - Actualizar producción desde GitHub
echo   deploy.bat full    - Push + Deploy completo
echo   deploy.bat help    - Mostrar esta ayuda
echo.
echo Ejemplos:
echo   deploy.bat setup
echo   deploy.bat full
echo.
goto :end

:setup
echo 🔧 Configurando repositorio Git...
php setup-git.php
goto :end

:status
echo 📊 Verificando estado...
php sync-to-production.php status
goto :end

:push
echo 📤 Subiendo cambios a GitHub...
php sync-to-production.php push
goto :end

:deploy
echo 🎯 Actualizando producción...
php sync-to-production.php deploy
goto :end

:full
echo 🚀 Deploy completo...
php sync-to-production.php full
goto :end

:end
echo.
echo ✅ Comando completado
if "%command%"=="setup" (
    echo.
    echo 📋 Próximos pasos:
    echo   deploy.bat status  - Ver estado
    echo   deploy.bat full    - Deploy completo
    echo.
)
pause 