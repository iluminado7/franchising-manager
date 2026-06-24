<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualNote extends Model
{
    protected $table = 'manual_notes';

    protected $fillable = [
        'manual_id',
        'empresa_id',
        'manual_version_id',
        'user_id',
        'contenido',
        'estado',
    ];

    // Manual al que pertenece la nota
    public function manual(): BelongsTo
    {
        return $this->belongsTo(Manual::class, 'manual_id');
    }

    // Empresa que la escribió
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // Versión del manual sobre la que se dejó la nota
    public function version(): BelongsTo
    {
        return $this->belongsTo(ManualVersion::class, 'manual_version_id');
    }

    // Usuario franquiciante autor de la nota
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
