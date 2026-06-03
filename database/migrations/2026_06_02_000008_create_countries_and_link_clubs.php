<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_country_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('flag_url')->nullable();
            $table->timestamps();
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('logo_url')->constrained()->nullOnDelete();
        });

        Schema::table('players', function (Blueprint $table) {
            $table->string('nationality_flag_url')->nullable()->after('nationality');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('nationality_flag_url');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
        });

        Schema::dropIfExists('countries');
    }
};
