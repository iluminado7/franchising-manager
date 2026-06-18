<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FranchiseStaff extends Model
{
    protected $table = 'franchise_staff';

    public $timestamps = false;
    protected $dates = ['created_at', 'updated_at'];

    protected $fillable = [
        'user_id',
        'franquicia_id',
        'nombre',
        'apellido',
        'dni',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    // La cuenta de autenticación
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // La franquicia a la que pertenece — NOT NULL por diseño
    public function franquicia(): BelongsTo
    {
        return $this->belongsTo(Franquicia::class, 'franquicia_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function nombreCompleto(): string
    {
        return "{$this->nombre} {$this->apellido}";
    }
}
