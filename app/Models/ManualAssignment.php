<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualAssignment extends Model
{
    protected $table = 'manual_assignments';

    public $incrementing = false;
    public $timestamps   = false;
    protected $dates     = ['assigned_at'];

    protected $fillable = [
        'manual_id',
        'user_id',
        'empresa_id',
        'assigned_by',
        'assigned_at',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function manual(): BelongsTo
    {
        return $this->belongsTo(Manual::class, 'manual_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
