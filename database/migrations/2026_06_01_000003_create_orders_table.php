<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('company', 20);            // bella | linda | gv
            $table->string('tiny_order_id', 40);      // id do pedido no Tiny
            $table->date('order_date');               // dataPedido
            $table->decimal('value', 12, 2)->default(0);
            $table->string('status_code', 5)->nullable();   // código numérico v3
            $table->string('channel_raw')->nullable();      // ecommerce.nome cru
            $table->string('channel')->nullable();          // canal normalizado
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['company', 'tiny_order_id']);
            $table->index(['company', 'order_date']);
            $table->index('order_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
