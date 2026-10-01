# POMS Report - Palm Oil Mill Digital Reporting System

Sistem pelaporan digital berbasis Laravel 11 untuk Pabrik Kelapa Sawit dengan integrasi Telegram Bot menggunakan Long Polling, validasi anti-fraud, dan dashboard web responsif.

## 📋 Fitur Utama

### 1. **Telegram Bot Integration (Long Polling)**
- Operator input data via Telegram commands
- Sistem tetap berfungsi di area dengan blank spot (delayed send)
- Long polling menggunakan `php artisan telegram:poll`
- Queued processing dengan Redis untuk high concurrency

### 2. **8 Stasiun Monitoring**
- **Timbang** (Weightbridge)
- **Sortasi** (Grading Ramp)
- **Sterilizer** (Perebusan)
- **Press** (Screw Press)
- **Klarifikasi** (Clarification Tank)
- **Kernel** (Nut & Kernel)
- **Lab** (Quality Control)
- **Maintenance** (Workshop)

### 3. **Anti-Fraud System**
- **Double Timestamping**: Mencatat waktu kirim (dari Telegram) dan waktu server
- **Auto-flagging**: Data otomatis ditandai jika selisih > 4 jam
- **Dynamic Validation**: Validasi parameter berdasarkan rules per pabrik
- **Transaction Safety**: DB transactions untuk data integrity

### 4. **Role-Based Access Control (RBAC)**
- **Operator**: Input via Telegram only, no web access
- **Asisten**: View & verify department data
- **Askep**: Full dashboard access
- **Manager**: Approval & analytics
- **HQ Admin**: Multi-plant view
- **Developer**: System settings

### 5. **Web Dashboard**
- Responsive design with Tailwind CSS
- Livewire components untuk real-time filtering
- Flagged records highlighted (yellow background)
- Verification workflow
- Export to PDF/Excel

## 🚀 Requirements

- **PHP**: 8.3+
- **Laravel**: 11.x
- **PostgreSQL**: 16
- **Redis**: Stack
- **Composer**: 2.x
- **Node.js**: 18+ (untuk Vite)
- **Supervisor**: Untuk menjalankan queue worker dan polling command

## 📦 Installation

### 1. Clone & Install Dependencies

```bash
git clone <repository-url> poms-report
cd poms-report
composer install
npm install
```

### 2. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` dengan konfigurasi Anda:

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=poms_report
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
QUEUE_CONNECTION=redis

# Telegram Bot
TELEGRAM_BOT_TOKEN=your_bot_token_from_botfather
TELEGRAM_BOT_USERNAME=your_bot_username

# Plant Config
PLANT_ID=PKS_01
PLANT_NAME="Pabrik Kelapa Sawit 01"
```

### 3. Database Setup

```bash
# Create database
createdb poms_report

# Run migrations
php artisan migrate

# Seed validation rules
php artisan db:seed --class=ValidationRulesSeeder
```

### 4. Create Telegram Bot

1. Chat dengan [@BotFather](https://t.me/botfather) di Telegram
2. Kirim `/newbot`
3. Ikuti instruksi untuk membuat bot
4. Copy **token** yang diberikan ke `.env`

```env
TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz
TELEGRAM_BOT_USERNAME=your_poms_bot
```

### 5. Compile Assets

```bash
npm run build
```

## 🏃 Running the Application

### Development

```bash
# Start Laravel development server
php artisan serve

# In separate terminals:

# Start queue worker
php artisan queue:work redis --queue=telegram

# Start Telegram polling
php artisan telegram:poll
```

### Production with Supervisor

#### File: `/etc/supervisor/conf.d/poms-report.conf`

```ini
# Queue Worker
[program:poms-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/poms-report/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --queue=telegram
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/poms-queue-worker.log
stopwaitsecs=3600

# Telegram Polling
[program:poms-telegram-poll]
command=php /path/to/poms-report/artisan telegram:poll
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/poms-telegram-poll.log
```

Load konfigurasi Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start poms-queue-worker:*
sudo supervisorctl start poms-telegram-poll
```

## 📱 Telegram Command Usage

### Format Perintah

Semua perintah dimulai dengan `/` dan menggunakan spasi sebagai pemisah parameter.

### Contoh Penggunaan

#### 1. Timbang (Weightbridge)
```
/timbang SPB10293 25300 9500 4.5
```
- SPB10293: Nomor SPB
- 25300: Tonase Bruto (kg)
- 9500: Tonase Tarra (kg)
- 4.5: Potongan (%)

#### 2. Sortasi (Grading)
```
/sortasi SPB10293 2.5 85.0 0.5 1.5
```
- SPB10293: Nomor SPB
- 2.5: Buah Mentah (%)
- 85.0: Buah Matang (%)
- 0.5: Jankos (%)
- 1.5: Tangkai Panjang (%)

#### 3. Sterilizer
```
/sterilizer 02 3.0 130 90
```
- 02: Nomor Rebusan
- 3.0: Tekanan (Bar)
- 130: Suhu (°C)
- 90: Durasi (Menit)

#### 4. Press
```
/press 04 65 40 7
```
- 04: Nomor Press
- 65: Tekanan Hidrolik (Kg/cm²)
- 40: Ampere Motor
- 7: Tambah Air (%)

#### 5. Klarifikasi
```
/klarifikasi 01 92 180 0.25
```
- 01: Nomor Tangki
- 92: Suhu Tangki (°C)
- 180: Level Minyak (cm)
- 0.25: Kadar Air (%)

#### 6. Kernel
```
/kernel 75 1.2 5.5
```
- 75: Suhu Silo (°C)
- 1.2: Losses Inti (%)
- 5.5: Kadar Kotoran (%)

#### 7. Lab
```
/lab 3.5 4.2 0.8
```
- 3.5: Kadar ALB/FFA CPO (%)
- 4.2: Losses Fiber (%)
- 0.8: Losses Jankos (%)

#### 8. Maintenance
```
/maintenance GENSET_02 4850 normal Aman_tidak_ada_kendala
```
- GENSET_02: Kode Mesin
- 4850: Jam Jalan Hour Meter
- normal: Status (normal/breakdown/maintenance)
- Aman_tidak_ada_kendala: Keterangan (gunakan _ untuk spasi)

## 👥 User Management

### Membuat User Pertama

```php
php artisan tinker

User::create([
    'name' => 'Admin Pabrik',
    'phone_number' => '6281234567890',
    'telegram_user_id' => null, // Will be auto-filled on first message
    'password' => bcrypt('password'),
    'role' => 'developer',
    'department' => null,
    'plant_id' => 'PKS_01',
    'status' => 'active',
]);
```

### Menambah Operator via Tinker

```php
User::create([
    'name' => 'Operator Sterilizer',
    'phone_number' => '6281234567891',
    'telegram_user_id' => '123456789', // Dari Telegram User ID
    'password' => bcrypt('password'),
    'role' => 'operator',
    'department' => 'proses',
    'plant_id' => 'PKS_01',
    'status' => 'active',
]);
```

## 🔒 Security Features

1. **Unauthorized Access**: User tidak terdaftar akan mendapat pesan "Akses Ditolak"
2. **Parameter Validation**: Semua input divalidasi terhadap `validation_rules` table
3. **Transaction Safety**: Menggunakan DB transactions untuk mencegah partial writes
4. **Time Discrepancy Detection**: Auto-flag jika selisih waktu > 4 jam
5. **RBAC**: Strict role-based access di web dashboard

## 📊 Validation Rules Management

Validation rules dapat diubah via web dashboard oleh **Manager** atau **Developer**.

### Via Database

```sql
-- Update validation rule
UPDATE validation_rules 
SET min_value = 1.2, max_value = 3.5 
WHERE plant_id = 'PKS_01' 
  AND station_name = 'sterilizer' 
  AND parameter_name = 'tekanan_bar';
```

### Via Web Dashboard

1. Login sebagai Manager/Developer
2. Buka menu **Pengaturan** > **Validation Rules**
3. Edit nilai min/max untuk parameter yang diinginkan
4. Simpan perubahan

## 📈 Export & Reporting

### Export Per Stasiun

```
GET /export/pdf/sterilizer?date_from=2026-09-01&date_to=2026-09-30
GET /export/excel/sterilizer?date_from=2026-09-01&date_to=2026-09-30
```

### Daily Production Report

```
GET /export/daily-report?date=2026-09-29
```

Laporan akan berisi:
- Ringkasan penerimaan buah
- Rata-rata parameter sterilizer
- Rata-rata parameter press
- QC Lab & Losses
- Kolom tanda tangan untuk operator, asisten, dan manager

## 🐛 Troubleshooting

### Telegram Bot Tidak Merespon

```bash
# Check bot connection
php artisan tinker
>>> app(\App\Services\TelegramService::class)->getMe();

# Check polling command
php artisan telegram:poll --once

# Check logs
tail -f storage/logs/laravel.log
```

### Queue Tidak Berjalan

```bash
# Check Redis connection
redis-cli ping

# Restart queue worker
sudo supervisorctl restart poms-queue-worker:*

# Check failed jobs
php artisan queue:failed
```

### Validasi Selalu Gagal

```bash
# Check validation rules for station
php artisan tinker
>>> \App\Models\ValidationRule::where('plant_id', 'PKS_01')
      ->where('station_name', 'sterilizer')
      ->get();
```

## 📝 License

Proprietary - All Rights Reserved

## 🤝 Support

Untuk bantuan teknis, hubungi tim IT Pabrik atau developer sistem.

---

**Version**: 1.0.0  
**Last Updated**: September 2026  
**Developed with**: Laravel 11, PostgreSQL 16, Redis Stack, Livewire v3
