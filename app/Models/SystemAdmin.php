<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class SystemAdmin extends Model
{
    protected $table = 'system_admins';

    public $timestamps = false;
    protected $dates = ['created_at'];

    protected $fillable = [
        'user_id',
        'nombre',
        'apellido',
        'dni',
        'cuit',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // empresa_id vive en users, no en system_admins (fuente única de verdad)
    // HasOneThrough: SystemAdmin → User → Empresa
    public function empresa(): HasOneThrough
    {
        return $this->hasOneThrough(
            Empresa::class,  // modelo final
            User::class,     // modelo intermedio
            'id',            // FK en users que apunta a system_admins.user_id
            'id',            // FK en empresas
            'user_id',       // FK en system_admins → users
            'empresa_id'     // FK en users → empresas
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function nombreCompleto(): string
    {
        return "{$this->nombre} {$this->apellido}";
    }

    // Acceso directo al empresa_id sin cargar la relación completa
    public function getEmpresaIdAttribute(): ?int
    {
        return $this->user?->empresa_id;
    }
}