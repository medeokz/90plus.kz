<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('from_club_source_id')->nullable()->after('from_club');
            $table->unsignedBigInteger('to_club_source_id')->nullable()->after('to_club');

            $table->index('from_club_source_id');
            $table->index('to_club_source_id');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropIndex(['from_club_source_id']);
            $table->dropIndex(['to_club_source_id']);
            $table->dropColumn(['from_club_source_id', 'to_club_source_id']);
        });
    }
};
