<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('season')->nullable();
            $table->string('player_name');
            $table->string('player_url')->nullable();
            $table->string('position')->nullable();
            $table->string('from_club')->nullable();
            $table->string('from_club_url')->nullable();
            $table->string('to_club')->nullable();
            $table->string('to_club_url')->nullable();
            $table->date('transfer_date')->nullable();
            $table->string('date_text')->nullable();
            $table->string('fee')->nullable();
            $table->string('country')->nullable();
            $table->string('source_url')->nullable();
            $table->string('fingerprint', 64)->unique();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamps();

            $table->index(['season', 'transfer_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};

