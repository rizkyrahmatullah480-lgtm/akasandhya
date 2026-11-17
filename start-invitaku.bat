@echo off
setlocal EnableExtensions EnableDelayedExpansion
title Invitaku - Digital Invitation Platform Launcher
color 0D

cd /D "%~dp0"

echo =====================================================================
echo           INVITAKU - DIGITAL INVITATION + QR ATTENDANCE
echo   "Setiap Momen Punya Cerita, Setiap Cerita Punya Undangan."
echo =====================================================================
echo.

set "RESET_DB=0"
if /I "%~1"=="--reset" set "RESET_DB=1"

set "PHP_EXE=php"
if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"

if "%RESET_DB%"=="1" (
    echo [MODE RESET] Database akan dikosongkan lalu diisi ulang dengan data demo.
    echo.
)

:: ---------------------------------------------------------------------
:: 1. MySQL
:: ---------------------------------------------------------------------
echo [1/5] Memeriksa MySQL XAMPP...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I "mysqld.exe" >NUL
if errorlevel 1 (
    if exist "C:\xampp\mysql\bin\mysqld.exe" (
        echo   - Menjalankan MySQL XAMPP...
        start "" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
    ) else (
        echo   [ERROR] MySQL XAMPP tidak ditemukan.
        echo           Buka XAMPP Control Panel lalu nyalakan MySQL secara manual.
        pause
        exit /b 1
    )
) else (
    echo   - MySQL sudah aktif.
)

set /a MYSQL_TRIES=0
:wait_mysql
"C:\xampp\mysql\bin\mysqladmin.exe" -u root ping >NUL 2>&1
if not errorlevel 1 goto mysql_ready
set /a MYSQL_TRIES+=1
if !MYSQL_TRIES! GEQ 20 (
    echo   [ERROR] MySQL tidak merespons setelah 20 detik.
    echo           Periksa XAMPP Control Panel dan konfigurasi C:\xampp\mysql\bin\my.ini.
    pause
    exit /b 1
)
timeout /t 1 /nobreak >NUL
goto wait_mysql
:mysql_ready
echo   - MySQL siap digunakan.
echo.

:: ---------------------------------------------------------------------
:: 2. Dependency backend
:: ---------------------------------------------------------------------
echo [2/5] Menyiapkan backend Laravel...
if not exist "backend\vendor\autoload.php" (
    echo   - Memasang dependensi PHP dengan Composer...
    pushd backend
    call composer install --no-interaction
    if errorlevel 1 (
        popd
        echo   [ERROR] Composer install gagal. Periksa koneksi internet dan instalasi Composer.
        pause
        exit /b 1
    )
    popd
)

if not exist "backend\.env" (
    echo   - Membuat backend\.env dari contoh...
    copy /Y "backend\.env.example" "backend\.env" >NUL
    pushd backend
    %PHP_EXE% artisan key:generate --force >NUL
    popd
)

"C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS db_invitaku CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >NUL 2>&1
if errorlevel 1 (
    echo   [PERINGATAN] Database tidak dapat dibuat otomatis.
    echo                Pastikan MySQL berjalan dan kredensial di backend\.env benar.
)

pushd backend

if "%RESET_DB%"=="1" (
    echo   - Menjalankan migrate:fresh --seed ^(data lama dihapus^)...
    %PHP_EXE% artisan migrate:fresh --seed --force
    if errorlevel 1 (
        popd
        echo   [ERROR] Migrasi database gagal.
        pause
        exit /b 1
    )
) else (
    echo   - Menjalankan migrasi database...
    %PHP_EXE% artisan migrate --force
    if errorlevel 1 (
        popd
        echo   [ERROR] Migrasi database gagal. Periksa koneksi database di backend\.env.
        pause
        exit /b 1
    )

    "C:\xampp\mysql\bin\mysql.exe" -u root -N -B -D db_invitaku -e "SELECT COUNT(*) FROM users;" > "%TEMP%\invitaku_user_count.txt" 2>NUL
    set "USER_COUNT="
    set /p USER_COUNT=<"%TEMP%\invitaku_user_count.txt"
    del "%TEMP%\invitaku_user_count.txt" >NUL 2>&1
    if not defined USER_COUNT set "USER_COUNT=0"

    if "%USER_COUNT%"=="0" (
        echo   - Mengisi data demo awal...
        %PHP_EXE% artisan db:seed --force
        if errorlevel 1 (
            popd
            echo   [ERROR] Pengisian data demo gagal.
            pause
            exit /b 1
        )
    ) else (
        echo   - Data pengguna sudah ada, seeding dilewati.
    )
)

%PHP_EXE% artisan storage:link >NUL 2>&1
popd
echo   - Backend siap.
echo.

:: ---------------------------------------------------------------------
:: 3. Dependency frontend
:: ---------------------------------------------------------------------
echo [3/5] Menyiapkan frontend SvelteKit...
if not exist "frontend\node_modules" (
    echo   - Memasang dependensi Node.js...
    pushd frontend
    call npm install
    if errorlevel 1 (
        popd
        echo   [ERROR] npm install gagal. Periksa koneksi internet dan instalasi Node.js.
        pause
        exit /b 1
    )
    popd
)

if not exist "frontend\.env" (
    copy /Y "frontend\.env.example" "frontend\.env" >NUL
)
echo   - Frontend siap.
echo.

:: ---------------------------------------------------------------------
:: 4. Backend server
:: ---------------------------------------------------------------------
echo [4/5] Menjalankan Backend REST API Laravel ^(Port 8000^)...
start "Invitaku Backend (Laravel API :8000)" cmd /k "cd /D %~dp0backend && %PHP_EXE% artisan serve --host=127.0.0.1 --port=8000"
timeout /t 2 /nobreak >NUL
echo   - Backend: http://127.0.0.1:8000/api
echo.

:: ---------------------------------------------------------------------
:: 5. Frontend server
:: ---------------------------------------------------------------------
echo [5/5] Menjalankan Frontend SvelteKit ^(Port 5173^)...
start "Invitaku Frontend (SvelteKit :5173)" cmd /k "cd /D %~dp0frontend && npm run dev -- --open"

echo.
echo =====================================================================
echo  Invitaku siap digunakan! Browser akan terbuka otomatis.
echo.
echo  Website : http://localhost:5173
echo  API     : http://127.0.0.1:8000/api
echo.
echo  Akun Demo:
echo    Admin   : admin@invitaku.com     / admin123
echo    Scanner : scanner@invitaku.com   / scanner123
echo    Scanner : petugas2@invitaku.com  / scanner123
echo.
echo  Tips:
echo    - Jalankan "start-invitaku.bat --reset" untuk mengulang data demo.
echo    - Jangan gunakan --reset jika sudah ada data penting.
echo =====================================================================
echo.
pause
