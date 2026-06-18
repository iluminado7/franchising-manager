<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuperAdmin extends Model
{
    protected $table = 'super_admins';

    public $timestamps = false;
    protected $dates = ['created_at'];

    protected $fillable = [
        'user_id',
        'nombre',
        'apellido',
        'dni',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function nombreCompleto(): string
    {
        return "{$this->nombre} {$this->apellido}";
    }
}
