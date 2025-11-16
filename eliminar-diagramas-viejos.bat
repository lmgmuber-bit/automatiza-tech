@echo off
echo Eliminando diagramas antiguos...
del "DIAGRAMA-USUARIO-FRONTEND.md" 2>nul
del "DIAGRAMA-ADMIN-BACKEND.md" 2>nul
del "DIAGRAMA-ADMINISTRADOR.md" 2>nul
del "crear-diagrama-admin.bat" 2>nul
echo.
echo Archivos eliminados. Ahora crea los nuevos archivos .md
echo.
pause
