<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de accesos (exitosos y fallidos) a la plataforma y a la app.
 */
#[Table('accesos', timestamps: false)]
#[Fillable(['user_id', 'identificador', 'canal', 'exitoso', 'motivo', 'ip', 'user_agent', 'created_at'])]
class Acceso extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exitoso' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
