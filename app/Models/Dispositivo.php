<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('dispositivos')]
#[Fillable(['user_id', 'push_token', 'plataforma', 'modelo', 'so_version', 'app_version', 'ultimo_uso_at'])]
class Dispositivo extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ultimo_uso_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
