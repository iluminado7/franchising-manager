<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $table = 'empresas';

    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'razon_social',
        'cuit',
        'plan_id',
        'precio_custom_por_franquicia',
        'precio_custom_global',
        'facturable',
        'activa',
    ];

    protected $casts = [
        'activa'                      => 'boolean',
        'facturable'                   => 'boolean',
        'precio_custom_por_franquicia' => 'decimal:2',
        'precio_custom_global'         => 'decimal:2',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(EmpresaEmail::class, 'empresa_id');
    }

    public function emailsFacturacion(): HasMany
    {
        return $this->hasMany(EmpresaEmail::class, 'empresa_id')
                    ->where('tipo', 'facturacion');
    }

    public function emailsContacto(): HasMany
    {
        return $this->hasMany(EmpresaEmail::class, 'empresa_id')
                    ->where('tipo', 'contacto');
    }

    public function franquicias(): HasMany
    {
        return $this->hasMany(Franquicia::class, 'empresa_id');
    }

    public function franquiciasActivas(): HasMany
    {
        return $this->hasMany(Franquicia::class, 'empresa_id')
                    ->where('activa', 1);
    }

    public function systemAdmins(): HasMany
    {
        return $this->hasMany(SystemAdmin::class, 'empresa_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'empresa_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Document::class, 'empresa_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'empresa_id');
    }

    public function manualEmpresaAssignments(): HasMany
    {
        return $this->hasMany(ManualEmpresaAssignment::class, 'empresa_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'empresa_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    // Precio efectivo según tipo de plan
    public function precioEfectivoporFranquicia(): ?float
    {
        if (!$this->facturable) return 0.0;

        return $this->precio_custom_por_franquicia
            ?? $this->plan?->precio_base_por_franquicia;
    }

    public function precioEfectivoGlobal(): ?float
    {
        if (!$this->facturable) return 0.0;

        return $this->precio_custom_global
            ?? $this->plan?->precio_global;
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeActivas($query)
    {
        return $query->where('activa', 1);
    }

    // Para jobs de facturación / suspensión por impago: nunca deben tocar a la exenta.
    public function scopeFacturables($query)
    {
        return $query->where('facturable', 1);
    }
}