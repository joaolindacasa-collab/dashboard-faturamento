<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mode', 20);                 // incremental | full
            $table->string('status', 20);               // ok | error
            $table->text('message')->nullable();
            $table->json('totals')->nullable();         // { bella: {...}, ... }
            $table->unsignedInteger('orders_seen')->default(0);
            $table->unsignedInteger('orders_upserted')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
