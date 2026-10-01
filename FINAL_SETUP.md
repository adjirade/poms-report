# 🚀 LANGKAH TERAKHIR - Setup Database PostgreSQL

**Status Saat Ini:** PostgreSQL belum terinstall di Laragon  
**Waktu:** 2026-09-30 12:11 WIB  
**Progress:** 95% → 100% (tinggal 3 langkah)

---

## ⚡ OPTION A: Install PostgreSQL via Laragon (RECOMMENDED)

### **Langkah 1: Install PostgreSQL**

1. **Buka Laragon** (double-click icon Laragon di desktop atau system tray)

2. **Right-click pada Laragon icon** di system tray (pojok kanan bawah)

3. **Pilih:** `Tools` → `Quick add` → `PostgreSQL`

4. **Wait 2-3 minutes** untuk download dan instalasi

5. **Start PostgreSQL:**
   - Right-click Laragon icon
   - Pilih: `PostgreSQL` → `Start`

6. **Password default Laragon PostgreSQL:**
   - Username: `postgres`
   - Password: **kosong** (blank) atau `root`

### **Langkah 2: Update .env File**

Edit file `.env` di project root:

```env
# Line 15 - Database Password
DB_PASSWORD=          # ← Kosongkan untuk no password, atau isi 'root'
```

**Cara edit:**
- Buka dengan Notepad atau text editor
- Cari baris `DB_PASSWORD=`
- Biarkan kosong jika PostgreSQL tidak pakai password
- Atau isi `root` jika diminta password saat setup

### **Langkah 3: Create Database & Run Migrations**

Buka **Laragon Terminal** (klik tombol "Terminal" di Laragon):

```bash
# Masuk ke project directory
cd "D:\Project\Sawit APP\SawitApp"

# Create database (PostgreSQL tanpa password)
psql -U postgres -c "CREATE DATABASE poms_report;"

# Run migrations
php artisan migrate

# Seed validation rules
php artisan db:seed --class=ValidationRulesSeeder
```

**Jika PostgreSQL pakai password:**
```bash
# Akan prompt password, masukkan 'root' atau password yang Anda set
psql -U postgres
# Enter password when prompted
# Kemudian di psql prompt:
CREATE DATABASE poms_report;
\q
```

---

## ⚡ OPTION B: Skip PostgreSQL, Gunakan SQLite (Quick Test)

Jika ingin test sistem dulu tanpa install PostgreSQL:

### **1. Update .env untuk SQLite:**

```env
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=poms_report
# DB_USERNAME=postgres
# DB_PASSWORD=
```

### **2. Create SQLite Database:**

```bash
cd "D:\Project\Sawit APP\SawitApp"

# Create database file
type nul > database\database.sqlite

# Run migrations
php artisan migrate

# Seed validation rules
php artisan db:seed --class=ValidationRulesSeeder
```

---

## 📝 SETELAH DATABASE READY

### **Step 4: Setup Telegram Bot (5 menit)**

1. **Buka Telegram**, cari **@BotFather**

2. **Kirim:** `/newbot`

3. **Ikuti instruksi:**
   ```
   BotFather: Alright, a new bot. How are we going to call it?
   You: POMS Report Bot
   
   BotFather: Good. Now let's choose a username for your bot.
   You: poms_report_bot  (atau nama unik lain yang diakhiri _bot)
   
   BotFather: Done! Keep your token secure...
   Your bot token: 123456789:ABCdefGHIjklMNOpqrsTUVwxyz
   ```

4. **Copy token** yang diberikan

5. **Update .env:**
   ```env
   TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz
   TELEGRAM_BOT_USERNAME=poms_report_bot
   ```

### **Step 5: Create First User (2 menit)**

Buka Laragon Terminal:

```bash
cd "D:\Project\Sawit APP\SawitApp"
php artisan tinker
```

Di Tinker prompt, copy-paste:

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
```

Press Enter, tunggu "Created", lalu ketik:
```php
exit
```

---

## 🚀 MENJALANKAN SISTEM (3 Terminal)

### **Terminal 1: Laravel Server**
```bash
cd "D:\Project\Sawit APP\SawitApp"
php artisan serve
```
**Access:** http://localhost:8000

### **Terminal 2: Queue Worker**
```bash
cd "D:\Project\Sawit APP\SawitApp"
php artisan queue:work redis --queue=telegram --tries=3
```

### **Terminal 3: Telegram Bot Polling**
```bash
cd "D:\Project\Sawit APP\SawitApp"
php artisan telegram:poll
```

---

## 🧪 TEST SISTEM

### **1. Test Web Dashboard:**
```
URL: http://localhost:8000
Phone: 6281234567890
Password: password123
```

### **2. Test Telegram Bot:**

**A. Register Your Telegram ID:**

1. Buka Telegram, cari **@userinfobot**
2. Send: `/start`
3. Bot akan reply dengan **Your ID:** `123456789`
4. Copy ID tersebut

**B. Create Operator User:**

```bash
php artisan tinker
```

```php
\App\Models\User::create([
    'name' => 'Operator Test',
    'phone_number' => '6281234567891',
    'telegram_user_id' => '123456789', // ← Paste ID dari userinfobot
    'password' => bcrypt('password'),
    'role' => 'operator',
    'department' => 'proses',
    'plant_id' => 'PKS_01',
    'status' => 'active',
]);

exit
```

**C. Test Command:**

Di Telegram, cari bot Anda (`@poms_report_bot`):
```
/start
/sterilizer 02 3.0 130 90
```

Harus reply: **"✅ Data Berhasil Disimpan"**

---

## 📊 STATUS CHECKLIST

```
✅ PHP 8.3.33 + Extensions
✅ Composer Dependencies (127 packages)
✅ NPM Dependencies (116 packages)
✅ Laravel 11.56.1
✅ Application Key Generated
✅ All Source Code (40 files)
✅ Migrations Ready (10 tables)
✅ Seeders Ready (27 validation rules)
✅ .env Configuration

⏳ PostgreSQL Installation → STEP 1
⏳ Database Creation → STEP 2
⏳ Migrations Executed → STEP 3
⏳ Telegram Bot Setup → STEP 4
⏳ First User Created → STEP 5
```

---

## 🆘 TROUBLESHOOTING

### **Error: "PostgreSQL not found"**
```
→ Restart Laragon setelah install PostgreSQL
→ Start PostgreSQL service via Laragon
```

### **Error: "no password supplied"**
```
→ Edit .env, set DB_PASSWORD=root atau kosongkan
→ Atau gunakan SQLite (Option B di atas)
```

### **Error: "Connection refused"**
```
→ Pastikan PostgreSQL service running
→ Check: Right-click Laragon > PostgreSQL > Start
```

### **Error: "Class not found" saat migrate**
```bash
composer dump-autoload
php artisan optimize:clear
php artisan migrate
```

---

## 📞 QUICK HELP COMMANDS

```bash
# Check Laravel version
php artisan --version

# Check database connection
php artisan db:show

# List all migrations
php artisan migrate:status

# Rollback last migration
php artisan migrate:rollback

# Clear all caches
php artisan optimize:clear

# List all routes
php artisan route:list

# Check queue jobs
php artisan queue:failed

# Restart queue
php artisan queue:restart
```

---

## ✨ REKOMENDASI SAYA

**Untuk Development/Testing Cepat:**
→ Gunakan **SQLite** (Option B) - no installation needed, langsung jalan

**Untuk Production/Enterprise:**
→ Install **PostgreSQL** (Option A) - more robust, production-ready

**Pilih mana yang Anda mau:**
1. **Option A (PostgreSQL):** More powerful, recommended untuk production
2. **Option B (SQLite):** Faster setup, perfect untuk testing

Kedua-duanya bisa, tinggal ikuti langkah-langkah di atas! 🚀

---

**Next Action:** Pilih Option A atau B, lalu lanjutkan ke Step 4-5  
**Time to Complete:** 5-10 menit  
**Status After:** ✅ 100% FULLY OPERATIONAL

---

**Generated:** 2026-09-30 12:11:38 WIB  
**Ready to Deploy!** 🎉
