<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $table = 'invoices';

    public $timestamps = false;
    protected $dates = ['created_at', 'pagado_at'];

    protected $fillable = [
        'empresa_id',
        'plan_id',
        'periodo',
        'numero_factura',
        'franquicias_activas',
        'precio_por_franquicia',
        'precio_global_snapshot',
        'total',
        'estado',
        'pagado_at',
        'notas',
    ];

    protected $casts = [
        'franquicias_activas'   => 'integer',
        'precio_por_franquicia' => 'decimal:2',
        'precio_global_snapshot'=> 'decimal:2',
        'total'                 => 'decimal:2',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function estaPagada(): bool
    {
        return $this->estado === 'pagada';
    }

    public function estaVencida(): bool
    {
        return $this->estado === 'vencida';
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeVencidas($query)
    {
        return $query->where('estado', 'vencida');
    }
}
