<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $table = 'planes';

    public $timestamps = false;
    protected $dates = ['created_at'];

    protected $fillable = [
        'nombre',
        'tipo_plan',
        'precio_base_por_franquicia',
        'precio_global',
        'limite_franquicias',
        'manuales_ilimitados',
        'activo',
    ];

    protected $casts = [
        'precio_base_por_franquicia' => 'decimal:2',
        'precio_global'              => 'decimal:2',
        'manuales_ilimitados'        => 'boolean',
        'activo'                     => 'boolean',
        'limite_franquicias'         => 'integer',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class, 'plan_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'plan_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function esPorFranquicia(): bool
    {
        return $this->tipo_plan === 'por_franquicia';
    }

    public function esGlobal(): bool
    {
        return $this->tipo_plan === 'global';
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeActivos($query)
    {
        return $query->where('activo', 1);
    }
}
