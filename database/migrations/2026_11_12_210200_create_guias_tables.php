<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comercios, directorio de direcciones (memoria de zonas), cargas del Excel
 * de Credibanco, guías y su bitácora de trazabilidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comercios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_unico', 20)->unique();
            $table->string('nombre', 160);
            $table->string('cadena', 160)->nullable();
            // Memoria de zona por código único: primer criterio de zonificación.
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('direcciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciudad_id')->constrained('ciudades')->restrictOnDelete();
            $table->string('clave_normalizada', 191);
            $table->string('direccion', 191);
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->boolean('es_rural')->default(false);
            $table->foreignId('zonificada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('zonificada_at')->nullable();
            $table->timestamps();

            $table->unique(['ciudad_id', 'clave_normalizada']);
            $table->index('zona_id');
        });

        Schema::create('cargas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('nombre_archivo', 191);
            $table->string('ruta_archivo', 255);
            $table->char('hash_sha256', 64)->index();
            $table->string('hoja', 80)->nullable();
            $table->string('estado', 20)->default('pendiente')->index();
            $table->unsignedTinyInteger('columnas_reconocidas')->default(0);
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('nuevos')->default(0);
            $table->unsignedInteger('duplicados')->default(0);
            $table->unsignedInteger('errores')->default(0);
            $table->unsignedInteger('con_zona')->default(0);
            $table->unsignedInteger('por_zonificar')->default(0);
            $table->unsignedInteger('urgentes')->default(0);
            $table->unsignedInteger('con_alertas')->default(0);
            $table->unsignedInteger('total_rollos')->default(0);
            $table->timestamp('confirmada_at')->nullable();
            $table->timestamps();
        });

        Schema::create('guias', function (Blueprint $table) {
            $table->id();
            $table->string('servicio', 30)->unique();
            $table->string('numero_guia', 20)->unique();
            $table->foreignId('carga_id')->nullable()->constrained('cargas')->nullOnDelete();
            $table->foreignId('comercio_id')->constrained('comercios')->restrictOnDelete();
            $table->foreignId('direccion_id')->constrained('direcciones')->restrictOnDelete();
            $table->foreignId('ciudad_id')->constrained('ciudades')->restrictOnDelete();
            $table->foreignId('seccional_id')->constrained('seccionales')->restrictOnDelete();
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->string('estado', 30)->default('recibida');

            // Datos tal como llegaron en el Excel (registro histórico de la solicitud).
            $table->string('nombre_comercio', 160);
            $table->string('cadena', 160)->nullable();
            $table->string('direccion_original', 255);
            $table->string('direccion_normalizada', 191);
            $table->string('complemento', 191)->nullable();
            $table->string('ciudad_original', 80)->nullable();
            $table->string('departamento', 80)->nullable();
            $table->string('codigo_ciudad', 20)->nullable();
            $table->string('geolocalizacion', 40)->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('contacto_nombre', 120)->nullable();
            $table->string('contacto_telefono', 40)->nullable();
            $table->string('horario_comercio', 191)->nullable();
            $table->string('horario_atencion', 191)->nullable();
            $table->unsignedSmallInteger('rollos_solicitados');
            $table->text('observaciones')->nullable();
            $table->json('alertas')->nullable();

            // ANS: horas del campo HORARIO_DE_ATENCION contadas desde FECHA_REGISTRO.
            $table->dateTime('fecha_registro');
            $table->unsignedSmallInteger('ans_horas');
            $table->dateTime('ans_vence_at');
            $table->boolean('es_urgente')->default(false);

            // Operación.
            $table->foreignId('repartidor_id')->nullable()->constrained('repartidores')->nullOnDelete();
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->date('fecha_programada')->nullable();
            $table->dateTime('entregada_at')->nullable();
            $table->unsignedSmallInteger('rollos_entregados')->nullable();
            $table->dateTime('cerrada_at')->nullable();
            $table->string('ultimo_movimiento', 191)->nullable();
            $table->dateTime('ultimo_movimiento_at')->nullable();
            $table->timestamps();

            $table->index(['seccional_id', 'estado']);
            $table->index(['estado', 'zona_id']);
            $table->index('fecha_registro');
            $table->index('ans_vence_at');
            $table->index('entregada_at');
            $table->index('fecha_programada');
            $table->index('es_urgente');
        });

        Schema::create('carga_filas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_id')->constrained('cargas')->cascadeOnDelete();
            $table->unsignedInteger('numero_fila');
            $table->string('servicio', 30)->nullable()->index();
            $table->string('resultado', 20);
            $table->json('datos');
            $table->json('alertas')->nullable();
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->string('zona_origen', 20)->nullable();
            $table->foreignId('guia_id')->nullable()->constrained('guias')->nullOnDelete();
            $table->timestamps();

            $table->index(['carga_id', 'numero_fila']);
        });

        Schema::create('guia_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guia_id')->constrained('guias')->cascadeOnDelete();
            $table->string('tipo', 40)->index();
            $table->string('titulo', 191);
            $table->string('detalle', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('meta')->nullable();
            $table->dateTime('ocurrido_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['guia_id', 'ocurrido_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guia_eventos');
        Schema::dropIfExists('carga_filas');
        Schema::dropIfExists('guias');
        Schema::dropIfExists('cargas');
        Schema::dropIfExists('direcciones');
        Schema::dropIfExists('comercios');
    }
};
