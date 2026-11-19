<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Parámetros de operación editables desde Configuración.
 * Se leen siempre a través de App\Services\Parametros.
 */
#[Table('parametros')]
#[Fillable(['clave', 'valor', 'tipo', 'grupo', 'etiqueta', 'descripcion'])]
class Parametro extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('configuracion')->logOnly(['clave', 'valor'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
