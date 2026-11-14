@echo off
rem ---------------------------------------------------------------------------
rem Start the local mail catcher, if it is not already running.
rem
rem WHY THIS FILE EXISTS
rem Mailpit is the third service this box needs, alongside Apache and MySQL,
rem and Laragon does not start it. When it is down, Moodle does not fail the
rem action - it commits it and prints a stack trace over the top, so approving
rem a course or joining one looks broken when it worked. That happened twice.
rem
rem Safe to run twice: it checks first and does nothing if the port is taken.
rem
rem Read what was sent at http://127.0.0.1:8026/
rem ---------------------------------------------------------------------------

set MAILPIT=C:\laragon\bin\mailpit\1.22.3\mailpit.exe

if not exist "%MAILPIT%" (
    echo Mailpit introuvable: %MAILPIT%
    echo Verifiez le chemin, Laragon a peut-etre une autre version.
    exit /b 1
)

rem Ports: 1025 is held by a Windows service on this machine and 1026-1125 are
rem reserved by the system, which is why this is 2525.
netstat -an | find ":2525" | find "LISTENING" >nul
if %errorlevel%==0 (
    echo Mailpit tourne deja sur 127.0.0.1:2525
    exit /b 0
)

echo Demarrage de Mailpit...
start "" /b "%MAILPIT%" --smtp 127.0.0.1:2525 --listen 127.0.0.1:8026 --smtp-auth-accept-any --smtp-auth-allow-insecure

rem Not `timeout`: it refuses to run when stdin is redirected, which it is
rem whenever this is called from a script rather than typed. ping is the
rem portable wait in a .cmd file.
ping -n 4 127.0.0.1 >nul
netstat -an | find ":2525" | find "LISTENING" >nul
if %errorlevel%==0 (
    echo OK - boite de reception: http://127.0.0.1:8026/
    exit /b 0
)

echo ECHEC - Mailpit n'ecoute pas sur 2525.
echo Verifiez qu'aucun autre programme ne tient le port.
exit /b 1
