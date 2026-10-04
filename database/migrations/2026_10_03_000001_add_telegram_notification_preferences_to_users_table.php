<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preferensi notifikasi Telegram per-user (opt-out).
     *
     * - telegram_notif_enabled : saklar utama (matikan SEMUA notifikasi).
     * - telegram_notif_flagged : notifikasi saat record di-stasiun binaan ditandai flagged.
     * - telegram_notif_verified: notifikasi ke pengirim saat datanya diverifikasi.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('telegram_notif_enabled')->default(true)->after('telegram_user_id');
            $table->boolean('telegram_notif_flagged')->default(true)->after('telegram_notif_enabled');
            $table->boolean('telegram_notif_verified')->default(true)->after('telegram_notif_flagged');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_notif_enabled', 'telegram_notif_flagged', 'telegram_notif_verified']);
        });
    }
};
