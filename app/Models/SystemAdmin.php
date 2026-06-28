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

    // Tras v2.3 nombre/apellido/dni viven en users. Esta tabla queda como
    // marcador de rol (id, user_id, created_at).
    // 'cuit' fue removido del fillable porque la columna no existe en la
    // base; si llegase a necesitarse, conviene agregarlo a users.
    protected $fillable = [
        'user_id',
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

    // Delegado a User para no romper código existente que llamara
    // $systemAdmin->nombreCompleto().
    public function nombreCompleto(): string
    {
        return $this->user?->nombreCompleto() ?? '';
    }

    // Acceso directo al empresa_id sin cargar la relación completa
    public function getEmpresaIdAttribute(): ?int
    {
        return $this->user?->empresa_id;
    }
}