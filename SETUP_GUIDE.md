# 🚀 SETUP GUIDE - POMS Report System

## ⚠️ Prerequisites yang Harus Diinstall

Sebelum menjalankan sistem, pastikan sudah menginstall:

### 1. **PHP 8.3+**
Download dari: https://windows.php.net/download/
- Pilih **PHP 8.3 Thread Safe (x64)**
- Extract ke `C:\php`
- Tambahkan `C:\php` ke System Environment PATH

**Verifikasi:**
```cmd
php --version
```

### 2. **Composer 2.x**
Download dari: https://getcomposer.org/download/
- Install dengan installer Windows
- Restart terminal setelah install

**Verifikasi:**
```cmd
composer --version
```

### 3. **PostgreSQL 16**
Download dari: https://www.postgresql.org/download/windows/
- Install PostgreSQL 16
- Set password untuk user `postgres`
- Default port: 5432

**Verifikasi:**
```cmd
psql --version
```

### 4. **Redis Stack (Windows)**
Download dari: https://redis.io/docs/getting-started/install-stack/
- Install Memurai Redis (Redis for Windows) atau
- Install Redis via WSL2

**Verifikasi:**
```cmd
redis-cli ping
```
Harus return: `PONG`

### 5. **Node.js 18+ & NPM**
Download dari: https://nodejs.org/
- Install LTS version

**Verifikasi:**
```cmd
node --version
npm --version
```

---

## 📝 STEP-BY-STEP INSTALLATION

### **STEP 1: Clone/Setup Project**

```cmd
cd "D:\Project\Sawit APP\SawitApp"
```

### **STEP 2: Install PHP Extensions**

Edit `C:\php\php.ini` (atau `php.ini-development` rename ke `php.ini`):

Uncomment (remove `;`) dari extensions berikut:
```ini
extension=curl
extension=fileinfo
extension=gd
extension=mbstring
extension=openssl
extension=pdo_pgsql
extension=pgsql
extension=redis
extension=zip
```

**Restart terminal setelah edit php.ini**

### **STEP 3: Install Dependencies**

```cmd
# Install PHP dependencies
composer install

# Install Node dependencies
npm install
```

### **STEP 4: Create Database**

```cmd
# Login ke PostgreSQL
psql -U postgres

# Di PostgreSQL prompt:
CREATE DATABASE poms_report;
\q
```

### **STEP 5: Setup Environment**

```cmd
# Copy .env.example ke .env
copy .env.example .env

# Generate application key
php artisan key:generate
```

### **STEP 6: Configure .env File**

Edit file `.env` dengan Notepad atau editor favorit:

```env
APP_NAME="POMS Report"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=poms_report
DB_USERNAME=postgres
DB_PASSWORD=your_postgres_password_here

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis

# Dapatkan dari @BotFather di Telegram
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_BOT_USERNAME=your_bot_username

PLANT_ID=PKS_01
PLANT_NAME="Pabrik Kelapa Sawit 01"
```

### **STEP 7: Create Telegram Bot**

1. Buka Telegram dan cari `@BotFather`
2. Kirim command: `/newbot`
3. Ikuti instruksi:
   - Nama bot: `POMS Report Bot`
   - Username: `poms_report_bot` (harus unik dan diakhiri `_bot`)
4. Copy **token** yang diberikan
5. Paste ke `.env` di `TELEGRAM_BOT_TOKEN`

### **STEP 8: Run Migrations & Seeders**

```cmd
# Run migrations
php artisan migrate

# Seed validation rules
php artisan db:seed --class=ValidationRulesSeeder
```

### **STEP 9: Create First User (Developer)**

```cmd
php artisan tinker
```

Di Tinker prompt:
```php
\App\Models\User::create([
    'name' => 'Admin Developer',
    'phone_number' => '6281234567890',
    'telegram_user_id' => null,
    'password' => bcrypt('password123'),
    'role' => 'developer',
    'department' => null,
    'plant_id' => 'PKS_01',
    'status' => 'active',
]);

exit
```

### **STEP 10: Build Frontend Assets**

```cmd
npm run build
```

---

## 🏃 RUNNING THE APPLICATION

### **Development Mode (3 Terminal Windows)**

#### Terminal 1: Laravel Server
```cmd
cd "D:\Project\Sawit APP\SawitApp"
php artisan serve
```
Access web: http://localhost:8000

#### Terminal 2: Queue Worker
```cmd
cd "D:\Project\Sawit APP\SawitApp"
php artisan queue:work redis --queue=telegram --tries=3
```

#### Terminal 3: Telegram Polling
```cmd
cd "D:\Project\Sawit APP\SawitApp"
php artisan telegram:poll
```

---

## 🧪 TESTING

### **Test Telegram Bot Connection**

```cmd
php artisan tinker
```

```php
$telegram = app(\App\Services\TelegramService::class);
$botInfo = $telegram->getMe();
print_r($botInfo);
exit
```

Harus return informasi bot Anda.

### **Test dengan Telegram**

1. Buka Telegram
2. Cari bot Anda (berdasarkan username)
3. Start chat: `/start`
4. Kirim test command: `/sterilizer 02 3.0 130 90`
5. Check di terminal "Telegram Polling" - harus ada log
6. Check di terminal "Queue Worker" - harus ada processing
7. Bot harus reply dengan error (karena user belum terdaftar)

### **Register Your Telegram User**

Dapatkan Telegram User ID Anda dari log, atau gunakan bot [@userinfobot](https://t.me/userinfobot)

```cmd
php artisan tinker
```

```php
$user = \App\Models\User::create([
    'name' => 'Operator Test',
    'phone_number' => '6281234567891',
    'telegram_user_id' => 'YOUR_TELEGRAM_USER_ID',
    'password' => bcrypt('password'),
    'role' => 'operator',
    'department' => 'proses',
    'plant_id' => 'PKS_01',
    'status' => 'active',
]);

exit
```

### **Test Command Lagi**

Kirim lagi: `/sterilizer 02 3.0 130 90`

Sekarang harus:
- ✅ Diterima dan divalidasi
- ✅ Disimpan ke database
- ✅ Bot reply: "✅ Data Berhasil Disimpan"

---

## 🌐 ACCESS WEB DASHBOARD

1. Buka browser: http://localhost:8000
2. Login dengan:
   - Email/Phone: `6281234567890` (developer account)
   - Password: `password123`
3. Navigate dashboard

---

## 📊 PRODUCTION DEPLOYMENT (Windows Service/Task Scheduler)

### **Option 1: NSSM (Non-Sucking Service Manager)**

Download NSSM: https://nssm.cc/download

```cmd
# Install Queue Worker as Service
nssm install PomsQueueWorker "C:\php\php.exe" "D:\Project\Sawit APP\SawitApp\artisan" "queue:work redis --queue=telegram"

# Install Telegram Polling as Service
nssm install PomsTelegramPoll "C:\php\php.exe" "D:\Project\Sawit APP\SawitApp\artisan" "telegram:poll"

# Start services
nssm start PomsQueueWorker
nssm start PomsTelegramPoll
```

### **Option 2: Task Scheduler**

Create 2 tasks yang run at startup:

**Task 1: Queue Worker**
- Program: `C:\php\php.exe`
- Arguments: `"D:\Project\Sawit APP\SawitApp\artisan" queue:work redis --queue=telegram`
- Start in: `D:\Project\Sawit APP\SawitApp`

**Task 2: Telegram Poll**
- Program: `C:\php\php.exe`
- Arguments: `"D:\Project\Sawit APP\SawitApp\artisan" telegram:poll`
- Start in: `D:\Project\Sawit APP\SawitApp`

---

## 🔧 TROUBLESHOOTING

### **Error: "could not find driver" (PDO)**
- Install PHP extension: `pdo_pgsql`
- Uncomment di `php.ini`: `extension=pdo_pgsql`
- Restart terminal

### **Error: "Class 'Redis' not found"**
- Install PHP extension: `redis`
- Atau install via PECL: `pecl install redis`

### **Error: "Connection refused [tcp://127.0.0.1:6379]"**
- Redis tidak running
- Start Redis: `redis-server`

### **Error: Telegram tidak merespon**
- Check bot token valid
- Check `telegram:poll` command running
- Check logs: `storage/logs/laravel.log`

### **Validation Selalu Gagal**
```cmd
php artisan tinker
```
```php
\App\Models\ValidationRule::where('station_name', 'sterilizer')->get();
```
Pastikan rules ada.

---

## 📱 USER ROLES & ACCESS

| Role | Telegram Input | Web Dashboard | Features |
|------|---------------|---------------|----------|
| **operator** | ✅ Yes | ❌ No | Input data only via Telegram |
| **asisten** | ✅ Yes | ✅ View & Verify | Department data, verification |
| **askep** | ✅ View Summary | ✅ Full Dashboard | All stations, analytics, export |
| **manager** | ✅ View Summary | ✅ Full + Approval | Reports approval, analytics |
| **hq_admin** | ❌ No | ✅ Multi-plant View | Read-only all plants |
| **developer** | ❌ No | ✅ System Settings | Validation rules, full access |

---

## 📞 SUPPORT

Jika ada issue:
1. Check logs: `storage/logs/laravel.log`
2. Check queue failed jobs: `php artisan queue:failed`
3. Restart services jika perlu

---

**Setup Complete! 🎉**

System sudah siap digunakan untuk production Palm Oil Mill reporting.
