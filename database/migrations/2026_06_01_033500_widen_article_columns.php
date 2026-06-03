<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->text('image_url')->nullable()->change();
            $table->text('title_en')->change();
            $table->text('title_kk')->change();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('image_url')->nullable()->change();
            $table->string('title_en')->change();
            $table->string('title_kk')->change();
        });
    }
};
