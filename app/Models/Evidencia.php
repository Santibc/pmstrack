<?php

namespace App\Models;

use App\Enums\TipoEvidencia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Archivo de evidencia (foto, firma o fachada). Se guarda en un disco privado:
 * solo se entrega a usuarios autorizados o por enlace temporal firmado.
 */
#[Table('evidencias')]
#[Fillable(['visita_id', 'tipo', 'disk', 'ruta', 'mime', 'tamano', 'ancho', 'alto', 'sha256'])]
class Evidencia extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['tipo' => TipoEvidencia::class];
    }

    /**
     * @return BelongsTo<Visita, $this>
     */
    public function visita(): BelongsTo
    {
        return $this->belongsTo(Visita::class);
    }

    public function existe(): bool
    {
        return Storage::disk($this->disk)->exists($this->ruta);
    }

    public function contenido(): ?string
    {
        return $this->existe() ? Storage::disk($this->disk)->get($this->ruta) : null;
    }

    /**
     * URL para usuarios autenticados y autorizados (pasa por la política de acceso).
     */
    public function url(): string
    {
        return route('evidencias.show', $this);
    }

    /**
     * Enlace temporal firmado, para compartir o incrustar sin sesión.
     */
    public function urlTemporal(int $minutos = 30): string
    {
        return URL::temporarySignedRoute('evidencias.firmada', now()->addMinutes($minutos), ['evidencia' => $this->id]);
    }

    /**
     * La huella guardada coincide con el archivo en disco.
     */
    public function integridadVerificada(): bool
    {
        $contenido = $this->contenido();

        return $contenido !== null && hash_equals($this->sha256, hash('sha256', $contenido));
    }
}
