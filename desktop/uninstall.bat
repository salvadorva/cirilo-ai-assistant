@echo off
echo ===============================================
echo   DESINSTALADOR DE ASISTENTE INFANTIL CIRILO
echo ===============================================
echo.
echo Este archivo eliminara la configuracion y accesos directos
echo para permitir una reinstalacion limpia de la aplicacion.
echo.
echo Presiona cualquier tecla para continuar...
pause > nul

echo.
echo Eliminando archivos de configuracion...

:: Eliminar directorios de configuracion
if exist "%APPDATA%\AsistenteInfantil" (
    rmdir /S /Q "%APPDATA%\AsistenteInfantil"
    echo [OK] Configuracion eliminada: %APPDATA%\AsistenteInfantil
)

if exist "%APPDATA%\AsistenteMagico" (
    rmdir /S /Q "%APPDATA%\AsistenteMagico"
    echo [OK] Configuracion eliminada: %APPDATA%\AsistenteMagico
)

echo.
echo Eliminando accesos directos...

:: Eliminar accesos directos del escritorio
if exist "%USERPROFILE%\Desktop\Asistente Cirilo.lnk" (
    del "%USERPROFILE%\Desktop\Asistente Cirilo.lnk"
    echo [OK] Acceso directo eliminado del escritorio
)

if exist "%USERPROFILE%\Desktop\AsistenteMagico.lnk" (
    del "%USERPROFILE%\Desktop\AsistenteMagico.lnk"
    echo [OK] Acceso directo eliminado del escritorio
)

:: Intentar con la version en espanol del escritorio
if exist "%USERPROFILE%\Escritorio\Asistente Cirilo.lnk" (
    del "%USERPROFILE%\Escritorio\Asistente Cirilo.lnk"
    echo [OK] Acceso directo eliminado del escritorio
)

if exist "%USERPROFILE%\Escritorio\AsistenteMagico.lnk" (
    del "%USERPROFILE%\Escritorio\AsistenteMagico.lnk"
    echo [OK] Acceso directo eliminado del escritorio
)

echo.
echo ===============================================
echo Desinstalacion completada exitosamente.
echo Ahora puedes reinstalar la aplicacion.
echo ===============================================
echo.
echo Presiona cualquier tecla para salir...
pause > nul
