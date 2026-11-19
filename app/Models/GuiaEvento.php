<?php

namespace App\Models;

use App\Enums\TipoEvento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora de trazabilidad de la guía: quién hizo qué y cuándo.
 */
#[Table('guia_eventos', timestamps: false)]
#[Fillable(['guia_id', 'tipo', 'titulo', 'detalle', 'user_id', 'meta', 'ocurrido_at'])]
class GuiaEvento extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoEvento::class,
            'meta' => 'array',
            'ocurrido_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Guia, $this>
     */
    public function guia(): BelongsTo
    {
        return $this->belongsTo(Guia::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
