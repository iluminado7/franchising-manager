<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    public $timestamps = false;
    protected $dates = ['created_at'];

    protected $fillable = [
        'user_id',
        'empresa_id',
        'accion',
        'entidad_tipo',
        'entidad_id',
        'detalle',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'detalle' => 'array',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // NULL para acciones globales del super_admin
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // ── Helper estático para registrar acciones ───────────────────────
    public static function registrar(
        int $userId,
        string $accion,
        string $ip,
        ?int $empresaId = null,
        ?string $entidadTipo = null,
        ?int $entidadId = null,
        ?array $detalle = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'user_id'      => $userId,
            'empresa_id'   => $empresaId,
            'accion'       => $accion,
            'entidad_tipo' => $entidadTipo,
            'entidad_id'   => $entidadId,
            'detalle'      => $detalle,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
            'created_at'   => now(),
        ]);
    }
}
