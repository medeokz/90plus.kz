<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title_en');
            $table->string('title_kk');
            $table->text('summary_en')->nullable();
            $table->text('summary_kk')->nullable();
            $table->longText('content_en')->nullable();
            $table->longText('content_kk')->nullable();
            $table->string('source_url')->unique();
            $table->string('source_name');
            $table->string('image_url')->nullable();
            $table->string('slug')->unique();
            $table->timestamp('published_at')->nullable();
            $table->string('status')->default('published');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index('source_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
