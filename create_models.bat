@echo off
echo Creating all station log models...

REM LogSortasi
(
echo ^<?php
echo namespace App\Models;
echo use Illuminate\Database\Eloquent\Factories\HasFactory;
echo use Illuminate\Database\Eloquent\Model;
echo class LogSortasi extends Model {
echo     use HasFactory, StationLogTrait;
echo     protected $table = 'log_sortasi';
echo     protected $fillable = ['user_id', 'plant_id', 'no_spb', 'buah_mentah_persen', 'buah_matang_persen', 'jankos_persen', 'tangkai_panjang_persen', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes'];
echo     protected $casts = ['buah_mentah_persen' =^> 'decimal:2', 'buah_matang_persen' =^> 'decimal:2', 'jankos_persen' =^> 'decimal:2', 'tangkai_panjang_persen' =^> 'decimal:2', 'timestamp_kirim' =^> 'datetime', 'timestamp_server' =^> 'datetime', 'is_flagged' =^> 'boolean', 'is_verified' =^> 'boolean'];
echo }
) > app\Models\LogSortasi.php

REM LogSterilizer
(
echo ^<?php
echo namespace App\Models;
echo use Illuminate\Database\Eloquent\Factories\HasFactory;
echo use Illuminate\Database\Eloquent\Model;
echo class LogSterilizer extends Model {
echo     use HasFactory, StationLogTrait;
echo     protected $table = 'log_sterilizer';
echo     protected $fillable = ['user_id', 'plant_id', 'no_rebusan', 'tekanan_bar', 'suhu_celcius', 'durasi_menit', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes'];
echo     protected $casts = ['no_rebusan' =^> 'integer', 'tekanan_bar' =^> 'decimal:2', 'suhu_celcius' =^> 'integer', 'durasi_menit' =^> 'integer', 'timestamp_kirim' =^> 'datetime', 'timestamp_server' =^> 'datetime', 'is_flagged' =^> 'boolean', 'is_verified' =^> 'boolean'];
echo }
) > app\Models\LogSterilizer.php

REM LogPress
(
echo ^<?php
echo namespace App\Models;
echo use Illuminate\Database\Eloquent\Factories\HasFactory;
echo use Illuminate\Database\Eloquent\Model;
echo class LogPress extends Model {
echo     use HasFactory, StationLogTrait;
echo     protected $table = 'log_press';
echo     protected $fillable = ['user_id', 'plant_id', 'no_press', 'tekanan_hidrolik', 'ampere_motor', 'tambah_air_persen', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes'];
echo     protected $casts = ['no_press' =^> 'integer', 'tekanan_hidrolik' =^> 'decimal:2', 'ampere_motor' =^> 'decimal:2', 'tambah_air_persen' =^> 'decimal:2', 'timestamp_kirim' =^> 'datetime', 'timestamp_server' =^> 'datetime', 'is_flagged' =^> 'boolean', 'is_verified' =^> 'boolean'];
echo }
) > app\Models\LogPress.php

REM LogKlarifikasi
(
echo ^<?php
echo namespace App\Models;
echo use Illuminate\Database\Eloquent\Factories\HasFactory;
echo use Illuminate\Database\Eloquent\Model;
echo class LogKlarifikasi extends Model {
echo     use HasFactory, StationLogTrait;
echo     protected $table = 'log_klarifikasi';
echo     protected $fillable = ['user_id', 'plant_id', 'no_tangki', 'suhu_tangki_celcius', 'level_minyak_cm', 'kadar_air_persen', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes'];
echo     protected $casts = ['no_tangki' =^> 'integer', 'suhu_tangki_celcius' =^> 'integer', 'level_minyak_cm' =^> 'decimal:2', 'kadar_air_persen' =^> 'decimal:3', 'timestamp_kirim' =^> 'datetime', 'timestamp_server' =^> 'datetime', 'is_flagged' =^> 'boolean', 'is_verified' =^> 'boolean'];
echo }
) > app\Models\LogKlarifikasi.php

REM LogKernel
(
echo ^<?php
echo namespace App\Models;
echo use Illuminate\Database\Eloquent\Factories\HasFactory;
echo use Illuminate\Database\Eloquent\Model;
echo class LogKernel extends Model {
echo     use HasFactory, StationLogTrait;
echo     protected $table = 'log_kernel';
echo     protected $fillable = ['user_id', 'plant_id', 'suhu_silo_celcius', 'losses_inti_persen', 'kadar_kotoran_persen', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes'];
echo     protected $casts = ['suhu_silo_celcius' =^> 'integer', 'losses_inti_persen' =^> 'decimal:2', 'kadar_kotoran_persen' =^> 'decimal:2', 'timestamp_kirim' =^> 'datetime', 'timestamp_server' =^> 'datetime', 'is_flagged' =^> 'boolean', 'is_verified' =^> 'boolean'];
echo }
) > app\Models\LogKernel.php

REM LogLab
(
echo ^<?php
echo namespace App\Models;
echo use Illuminate\Database\Eloquent\Factories\HasFactory;
echo use Illuminate\Database\Eloquent\Model;
echo class LogLab extends Model {
echo     use HasFactory, StationLogTrait;
echo     protected $table = 'log_lab';
echo     protected $fillable = ['user_id', 'plant_id', 'kadar_alb_cpo', 'losses_fiber_persen', 'losses_jankos_persen', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes'];
echo     protected $casts = ['kadar_alb_cpo' =^> 'decimal:2', 'losses_fiber_persen' =^> 'decimal:2', 'losses_jankos_persen' =^> 'decimal:2', 'timestamp_kirim' =^> 'datetime', 'timestamp_server' =^> 'datetime', 'is_flagged' =^> 'boolean', 'is_verified' =^> 'boolean'];
echo }
) > app\Models\LogLab.php

REM LogMaintenance
(
echo ^<?php
echo namespace App\Models;
echo use Illuminate\Database\Eloquent\Factories\HasFactory;
echo use Illuminate\Database\Eloquent\Model;
echo class LogMaintenance extends Model {
echo     use HasFactory, StationLogTrait;
echo     protected $table = 'log_maintenance';
echo     protected $fillable = ['user_id', 'plant_id', 'kode_mesin', 'jam_jalan_hm', 'status_kondisi', 'keterangan_perbaikan', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes'];
echo     protected $casts = ['jam_jalan_hm' =^> 'decimal:2', 'timestamp_kirim' =^> 'datetime', 'timestamp_server' =^> 'datetime', 'is_flagged' =^> 'boolean', 'is_verified' =^> 'boolean'];
echo }
) > app\Models\LogMaintenance.php

echo Done! All model files created.
