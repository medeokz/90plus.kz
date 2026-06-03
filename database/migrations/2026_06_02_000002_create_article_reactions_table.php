<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('reaction', 20);
            $table->string('session_id', 128);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'session_id']);
            $table->index(['article_id', 'reaction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_reactions');
    }
};

