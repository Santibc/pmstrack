<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos de la operación: seccionales, ciudades, zonas, causales,
 * parámetros y bodegas (una por seccional).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seccionales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 60)->unique();
            $table->string('nombre_normalizado', 60)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('ciudades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccional_id')->constrained('seccionales')->restrictOnDelete();
            $table->string('nombre', 80);
            $table->string('nombre_normalizado', 80);
            $table->string('departamento', 80);
            $table->string('departamento_normalizado', 80);
            $table->string('codigo_dane', 10)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['nombre_normalizado', 'seccional_id']);
        });

        Schema::create('zonas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccional_id')->constrained('seccionales')->restrictOnDelete();
            $table->string('codigo', 10);
            $table->string('nombre', 80);
            $table->string('color', 9)->default('#F07D2E');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['seccional_id', 'codigo']);
        });

        Schema::create('causales', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20)->index();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 120);
            // Marca los motivos que indican un dato errado en la base de Credibanco.
            $table->boolean('afecta_calidad_datos')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('parametros', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 60)->unique();
            $table->text('valor')->nullable();
            $table->string('tipo', 20)->default('texto');
            $table->string('grupo', 40)->default('general');
            $table->string('etiqueta', 120);
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('bodegas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccional_id')->unique()->constrained('seccionales')->restrictOnDelete();
            $table->string('nombre', 80);
            $table->unsignedInteger('stock_minimo')->default(0);
            // Saldo vigente. Solo lo modifica InventarioService, bajo bloqueo de fila.
            $table->integer('saldo')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bodegas');
        Schema::dropIfExists('parametros');
        Schema::dropIfExists('causales');
        Schema::dropIfExists('zonas');
        Schema::dropIfExists('ciudades');
        Schema::dropIfExists('seccionales');
    }
};
