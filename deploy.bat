@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

REM ============================================================
REM  GAME PROJECT - One-Click Deploy (Local → GitHub → Live)
REM  Usage: Double-click deploy.bat or run from terminal
REM ============================================================

set "PROJECT_DIR=%~dp0"
set "DEPLOY_URL=https://game.ezycry.com/tools/game1_deploy.php?token=G1DEPLOY_2026_LIVE"
set "BRANCH=main"

cd /d "%PROJECT_DIR%"

echo.
echo ╔══════════════════════════════════════════════════╗
echo ║       GAME PROJECT - ONE-CLICK DEPLOY           ║
echo ║     Local → GitHub → Live (game.ezycry.com)     ║
echo ╚══════════════════════════════════════════════════╝
echo.

REM ── Step 1: Check git status ──────────────────────────────
echo [1/5] Checking Git status...
git status --short
if errorlevel 1 (
    echo ERROR: Git command failed. Make sure git is installed and this is a git repo.
    pause
    exit /b 1
)

REM ── Step 2: Stage all changes ─────────────────────────────
echo.
echo [2/5] Staging all changes...
git add -A
if errorlevel 1 (
    echo ERROR: Failed to stage changes.
    pause
    exit /b 1
)

REM ── Step 3: Commit ────────────────────────────────────────
echo.
set "COMMIT_MSG="
set /p "COMMIT_MSG=Enter commit message (or press Enter for auto): "

if "!COMMIT_MSG!"=="" (
    for /f "tokens=1-4 delims=/ " %%a in ('date /t') do set "TODAY=%%d-%%b-%%c"
    for /f "tokens=1-2 delims=: " %%a in ('time /t') do set "NOW=%%a:%%b"
    set "COMMIT_MSG=Deploy !TODAY! !NOW!"
)

echo Committing: !COMMIT_MSG!
git commit -m "!COMMIT_MSG!"
if errorlevel 1 (
    echo.
    echo INFO: Nothing to commit, working tree clean.
    echo Proceeding to push...
)

REM ── Step 4: Push to GitHub ────────────────────────────────
echo.
echo [3/5] Pushing to GitHub (branch: %BRANCH%)...
git push origin %BRANCH%
if errorlevel 1 (
    echo ERROR: Git push failed. Check your remote settings and credentials.
    pause
    exit /b 1
)
echo Push successful!

REM ── Step 5: Trigger live deploy ───────────────────────────
echo.
echo [4/5] Waiting 5 seconds for GitHub to process...
timeout /t 5 /nobreak >nul

echo.
echo [5/5] Triggering live deployment at game.ezycry.com...
echo URL: %DEPLOY_URL%

REM Try curl first, then PowerShell as fallback
where curl >nul 2>&1
if %errorlevel%==0 (
    curl -s -S --max-time 120 "%DEPLOY_URL%"
) else (
    powershell -Command "try { $r = Invoke-WebRequest -Uri '%DEPLOY_URL%' -TimeoutSec 120 -UseBasicParsing; Write-Host $r.Content } catch { Write-Host 'Deploy request failed:' $_.Exception.Message }"
)

echo.
echo.
echo ╔══════════════════════════════════════════════════╗
echo ║            DEPLOYMENT COMPLETE!                  ║
echo ║                                                  ║
echo ║  Local  → http://localhost/game/                 ║
echo ║  Live   → https://game.ezycry.com/              ║
echo ╚══════════════════════════════════════════════════╝
echo.
pause
