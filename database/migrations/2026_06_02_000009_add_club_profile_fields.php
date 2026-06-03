<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
            $table->text('description')->nullable()->after('name_en');
            $table->json('profile_data')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'description', 'profile_data']);
        });
    }
};
