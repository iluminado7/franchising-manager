<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualEmpresaAssignment extends Model
{
    protected $table = 'manual_empresa_assignments';

    // PK compuesta
    public $incrementing = false;
    public $timestamps   = false;
    protected $dates     = ['asignado_at'];

    protected $fillable = [
        'manual_id',
        'empresa_id',
        'asignado_por',
        'asignado_at',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function manual(): BelongsTo
    {
        return $this->belongsTo(Manual::class, 'manual_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }
}
