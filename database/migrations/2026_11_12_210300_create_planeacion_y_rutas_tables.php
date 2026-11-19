<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planeación del día, rutas por repartidor, paradas, visitas con evidencia,
 * novedades y memoria del orden de ruta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planeaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccional_id')->constrained('seccionales')->restrictOnDelete();
            $table->date('fecha');
            $table->string('estado', 20)->default('borrador');
            $table->timestamp('publicada_at')->nullable();
            $table->foreignId('publicada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['seccional_id', 'fecha']);
        });

        Schema::create('planeacion_zonas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planeacion_id')->constrained('planeaciones')->cascadeOnDelete();
            $table->foreignId('zona_id')->constrained('zonas')->cascadeOnDelete();
            $table->foreignId('repartidor_id')->nullable()->constrained('repartidores')->nullOnDelete();
            $table->timestamps();

            $table->unique(['planeacion_id', 'zona_id']);
        });

        Schema::create('rutas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planeacion_id')->constrained('planeaciones')->restrictOnDelete();
            $table->foreignId('repartidor_id')->constrained('repartidores')->restrictOnDelete();
            $table->foreignId('seccional_id')->constrained('seccionales')->restrictOnDelete();
            $table->date('fecha');
            $table->string('estado', 20)->default('asignada');
            $table->dateTime('iniciada_at')->nullable();
            $table->dateTime('cerrada_at')->nullable();
            $table->dateTime('orden_guardado_at')->nullable();
            // Rollos que salieron de bodega con el repartidor (suma de sus paradas).
            $table->unsignedInteger('rollos_salida')->default(0);
            $table->unsignedInteger('rollos_devueltos_declarados')->nullable();
            $table->unsignedInteger('rollos_devueltos_esperados')->nullable();
            $table->boolean('cuadre_ok')->nullable();
            $table->string('observacion_cierre', 255)->nullable();
            $table->timestamps();

            $table->unique(['repartidor_id', 'fecha']);
            $table->index(['seccional_id', 'fecha']);
        });

        Schema::create('paradas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruta_id')->constrained('rutas')->cascadeOnDelete();
            $table->foreignId('guia_id')->constrained('guias')->restrictOnDelete();
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->unsignedSmallInteger('orden')->nullable();
            // Sin posición en la memoria del repartidor: aparece arriba como "sin ordenar".
            $table->boolean('es_nueva')->default(false);
            $table->string('estado', 20)->default('pendiente');
            $table->dateTime('visitada_at')->nullable();
            $table->timestamps();

            $table->unique(['ruta_id', 'guia_id']);
            $table->index(['ruta_id', 'orden']);
        });

        Schema::create('visitas', function (Blueprint $table) {
            $table->id();
            // Generado en el celular: evita duplicados al sincronizar sin conexión.
            $table->uuid('uuid')->unique();
            $table->foreignId('parada_id')->constrained('paradas')->restrictOnDelete();
            $table->foreignId('guia_id')->constrained('guias')->restrictOnDelete();
            $table->foreignId('ruta_id')->constrained('rutas')->restrictOnDelete();
            $table->foreignId('repartidor_id')->constrained('repartidores')->restrictOnDelete();
            $table->string('resultado', 20)->index();
            $table->foreignId('causal_id')->nullable()->constrained('causales')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->unsignedTinyInteger('numero_intento')->default(1);
            $table->unsignedSmallInteger('rollos_solicitados');
            $table->unsignedSmallInteger('rollos_entregados')->default(0);

            // Quien recibe (datos personales, Ley 1581 de 2012).
            $table->string('recibe_nombre', 120)->nullable();
            $table->string('recibe_cedula', 20)->nullable();
            $table->string('recibe_telefono', 20)->nullable();
            $table->boolean('autoriza_datos')->default(false);

            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->decimal('precision_m', 8, 2)->nullable();
            // Hora del celular y hora en que llegó al servidor.
            $table->dateTime('registrada_at');
            $table->dateTime('sincronizada_at');
            $table->string('dispositivo', 120)->nullable();
            $table->string('app_version', 20)->nullable();

            // Una visita no se edita: solo se anula con justificación.
            $table->dateTime('anulada_at')->nullable();
            $table->foreignId('anulada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('anulacion_motivo', 255)->nullable();
            $table->timestamps();

            $table->index(['guia_id', 'registrada_at']);
            $table->index(['repartidor_id', 'registrada_at']);
            $table->index('registrada_at');
        });

        Schema::create('evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visita_id')->constrained('visitas')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('disk', 30);
            $table->string('ruta', 255);
            $table->string('mime', 60);
            $table->unsignedInteger('tamano');
            $table->unsignedSmallInteger('ancho')->nullable();
            $table->unsignedSmallInteger('alto')->nullable();
            // Huella de integridad del archivo guardado.
            $table->char('sha256', 64);
            $table->timestamps();

            $table->index(['visita_id', 'tipo']);
        });

        Schema::create('novedades', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('repartidor_id')->constrained('repartidores')->restrictOnDelete();
            $table->foreignId('ruta_id')->nullable()->constrained('rutas')->nullOnDelete();
            $table->foreignId('guia_id')->nullable()->constrained('guias')->nullOnDelete();
            $table->string('tipo', 40);
            $table->text('descripcion');
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->dateTime('reportada_at')->index();
            $table->string('estado', 20)->default('abierta')->index();
            $table->foreignId('atendida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('atendida_at')->nullable();
            $table->string('respuesta', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('orden_memoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repartidor_id')->constrained('repartidores')->cascadeOnDelete();
            $table->foreignId('zona_id')->constrained('zonas')->cascadeOnDelete();
            $table->foreignId('direccion_id')->constrained('direcciones')->cascadeOnDelete();
            $table->unsignedSmallInteger('posicion');
            $table->timestamps();

            $table->unique(['repartidor_id', 'zona_id', 'direccion_id'], 'orden_memoria_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_memoria');
        Schema::dropIfExists('novedades');
        Schema::dropIfExists('evidencias');
        Schema::dropIfExists('visitas');
        Schema::dropIfExists('paradas');
        Schema::dropIfExists('rutas');
        Schema::dropIfExists('planeacion_zonas');
        Schema::dropIfExists('planeaciones');
    }
};
