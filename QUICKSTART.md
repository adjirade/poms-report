# ⚡ Quick Start Guide - POMS Report

**Status Check Results (2026-09-29):**

```
✅ Node.js v22.23.1 - Installed
❌ PHP 8.3+ - NOT FOUND
❌ Composer - NOT FOUND
❌ PostgreSQL 16 - NOT FOUND
❌ Redis - NOT FOUND
❌ Dependencies - NOT INSTALLED
```

---

## 🎯 OPTION 1: Quick Install (Recommended)

### Install Semua Requirement Sekaligus

#### A. Install via Laragon (All-in-One)

**Download Laragon Full:**
https://laragon.org/download/

Laragon sudah include:
- ✅ PHP 8.3
- ✅ Composer
- ✅ Node.js
- ✅ PostgreSQL (optional saat install)
- ✅ Redis
- ✅ Apache/Nginx

**Langkah:**
1. Download Laragon Full (bukan Lite)
2. Install ke `C:\laragon`
3. Jalankan Laragon
4. Klik kanan icon Laragon > Tools > Quick add > PostgreSQL
5. Klik kanan icon Laragon > Tools > Quick add > Redis

#### B. Install Manual (Pilih Komponen)

##### 1. PHP 8.3 (5 menit)
```
1. Download: https://windows.php.net/downloads/releases/php-8.3.14-Win32-vs16-x64.zip
2. Extract ke: C:\php
3. Copy C:\php\php.ini-development ke C:\php\php.ini
4. Edit php.ini, uncomment extensions:
   - extension=curl
   - extension=fileinfo
   - extension=mbstring
   - extension=openssl
   - extension=pdo_pgsql
   - extension=pgsql
   - extension=redis
5. Add to PATH: C:\php
6. Restart Command Prompt
7. Test: php --version
```

##### 2. Composer (2 menit)
```
1. Download: https://getcomposer.org/Composer-Setup.exe
2. Run installer (akan auto-detect PHP)
3. Test: composer --version
```

##### 3. PostgreSQL 16 (5 menit)
```
1. Download: https://www.postgresql.org/download/windows/
2. Run installer
3. Set password untuk user 'postgres' (catat!)
4. Port default: 5432
5. Test: psql --version
```

##### 4. Redis (Windows)
```
Option A - Memurai (Native Windows):
1. Download: https://www.memurai.com/get-memurai
2. Install Memurai (Redis for Windows)
3. Start service via Services app
4. Test: memurai-cli ping (should return PONG)

Option B - WSL2:
1. Install WSL2: wsl --install
2. Di WSL: sudo apt-get install redis
3. Start: sudo service redis-server start
```

---

## 🚀 OPTION 2: Using Docker (Fastest!)

Jika Anda punya Docker Desktop:

### 1. Install Docker Desktop
https://www.docker.com/products/docker-desktop/

### 2. Create docker-compose.yml

Saya sudah buatkan file `docker-compose.yml` - check project directory.

### 3. Run dengan Docker

```cmd
# Start all services
docker-compose up -d

# Install dependencies
docker-compose exec app composer install
docker-compose exec app npm install

# Setup database
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed --class=ValidationRulesSeeder

# Access
http://localhost:8000
```

---

## 📋 CURRENT PROJECT STATUS

**Yang Sudah Siap:**
```
✅ Complete source code (Laravel 11)
✅ Database migrations (3 files)
✅ Models & Services (11 files)
✅ Telegram integration (Long Polling)
✅ Queue jobs & commands
✅ Web dashboard (Livewire)
✅ RBAC implementation
✅ Export functionality (PDF/Excel)
✅ Validation rules seeder
✅ Documentation (README, SETUP_GUIDE)
```

**Yang Perlu Diinstall:**
```
❌ PHP 8.3+ runtime
❌ Composer (dependency manager)
❌ PostgreSQL 16 database
❌ Redis (queue backend)
❌ Project dependencies (composer install, npm install)
```

---

## 🎬 NEXT STEPS

### Scenario 1: Anda Install Manual (1-2 jam)

```cmd
1. Install PHP, Composer, PostgreSQL, Redis (lihat instruksi di atas)
2. Restart terminal/command prompt
3. cd "D:\Project\Sawit APP\SawitApp"
4. composer install
5. npm install
6. copy .env.example .env
7. Edit .env (database password, bot token)
8. php artisan key:generate
9. php artisan migrate
10. php artisan db:seed --class=ValidationRulesSeeder
```

### Scenario 2: Anda Gunakan Laragon (30 menit)

```cmd
1. Install Laragon Full
2. Copy project ke C:\laragon\www\SawitApp
3. Laragon > Right click > Terminal
4. composer install
5. npm install  
6. copy .env.example .env
7. php artisan key:generate
8. php artisan migrate
9. php artisan db:seed
```

### Scenario 3: Anda Gunakan Docker (15 menit)

```cmd
1. Install Docker Desktop
2. cd "D:\Project\Sawit APP\SawitApp"
3. docker-compose up -d
4. docker-compose exec app composer install
5. docker-compose exec app php artisan migrate
6. docker-compose exec app php artisan db:seed
```

---

## 🆘 TROUBLESHOOTING CEPAT

### Error: "PHP not found"
```
- Install PHP atau add ke PATH
- Restart terminal
- Test: where php
```

### Error: "Class 'PDO' not found"
```
- Edit php.ini
- Uncomment: extension=pdo_pgsql
- Restart terminal/service
```

### Error: "Connection refused Redis"
```
- Check Redis running: redis-cli ping
- Or start: redis-server
- Windows: Start Memurai service
```

### Error: "Database doesn't exist"
```cmd
psql -U postgres
CREATE DATABASE poms_report;
\q
```

---

## 📞 Butuh Bantuan?

**File Bantuan:**
- `SETUP_GUIDE.md` - Panduan lengkap detail
- `README.md` - Overview sistem & fitur
- `check-setup.bat` - Script verifikasi otomatis

**Cek Status:**
```cmd
check-setup.bat
```

---

**Recommendation:** 

Untuk development cepat → **Gunakan Laragon** (all-in-one, mudah)  
Untuk production → **Install manual** (lebih kontrol)  
Untuk testing → **Gunakan Docker** (isolated environment)

---

**Updated:** 2026-09-29 20:59 WIB  
**Status:** Waiting for prerequisites installation
