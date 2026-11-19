<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil de los usuarios, repartidores, dispositivos de la app y registro de accesos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('seccional_id')->nullable()->after('email')->constrained('seccionales')->nullOnDelete();
            $table->string('telefono', 20)->nullable()->after('seccional_id');
            $table->string('cargo', 80)->nullable()->after('telefono');
            $table->boolean('activo')->default(true)->after('cargo');
            $table->unsignedTinyInteger('intentos_fallidos')->default(0)->after('activo');
            $table->timestamp('bloqueado_hasta')->nullable()->after('intentos_fallidos');
            $table->timestamp('ultimo_acceso_at')->nullable()->after('bloqueado_hasta');
            $table->timestamp('password_cambiada_at')->nullable()->after('ultimo_acceso_at');
        });

        Schema::create('repartidores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('seccional_id')->constrained('seccionales')->restrictOnDelete();
            $table->string('cedula', 20)->unique();
            $table->string('celular', 20)->nullable();
            $table->string('vehiculo_tipo', 20)->nullable();
            $table->string('placa', 10)->nullable();
            $table->foreignId('zona_habitual_id')->nullable()->constrained('zonas')->nullOnDelete();
            // "No disponible hoy": lo excluye de la planeación sin desactivarlo.
            $table->boolean('disponible')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['seccional_id', 'activo']);
        });

        Schema::create('dispositivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('push_token', 255)->nullable();
            $table->string('plataforma', 20)->default('android');
            $table->string('modelo', 120)->nullable();
            $table->string('so_version', 40)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->timestamp('ultimo_uso_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'push_token']);
        });

        Schema::create('accesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('identificador', 150);
            $table->string('canal', 10)->default('web');
            $table->boolean('exitoso');
            $table->string('motivo', 60)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos');
        Schema::dropIfExists('dispositivos');
        Schema::dropIfExists('repartidores');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seccional_id');
            $table->dropColumn([
                'telefono', 'cargo', 'activo', 'intentos_fallidos',
                'bloqueado_hasta', 'ultimo_acceso_at', 'password_cambiada_at',
            ]);
        });
    }
};
