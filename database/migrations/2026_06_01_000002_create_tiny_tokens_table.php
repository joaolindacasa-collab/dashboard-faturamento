<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiny_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('company')->unique(); // bella | linda | gv
            $table->text('refresh_token');
            $table->text('access_token')->nullable();
            $table->timestamp('access_expires_at')->nullable();
            $table->string('scope')->nullable();
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiny_tokens');
    }
};
