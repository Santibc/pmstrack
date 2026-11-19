<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventario de rollos: cada entrada, salida a ruta, devolución o ajuste
 * queda como un movimiento con el saldo resultante de la bodega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bodega_id')->constrained('bodegas')->restrictOnDelete();
            $table->string('tipo', 20)->index();
            // Con signo: positivo suma a la bodega, negativo descuenta.
            $table->integer('cantidad');
            $table->integer('saldo');
            $table->string('detalle', 191);
            $table->string('remision_numero', 40)->nullable();
            $table->date('remision_fecha')->nullable();
            $table->string('motivo', 255)->nullable();
            $table->string('origen', 20)->default('web');
            $table->foreignId('ruta_id')->nullable()->constrained('rutas')->nullOnDelete();
            $table->foreignId('repartidor_id')->nullable()->constrained('repartidores')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['bodega_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
