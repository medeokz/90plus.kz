<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->string('player_icon')->nullable()->after('player_url');
            $table->string('from_club_icon')->nullable()->after('from_club_url');
            $table->string('to_club_icon')->nullable()->after('to_club_url');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn(['player_icon', 'from_club_icon', 'to_club_icon']);
        });
    }
};

