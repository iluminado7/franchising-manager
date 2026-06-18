<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Franquicia extends Model
{
    protected $table = 'franquicias';

    public $timestamps = false;
    protected $dates = ['created_at', 'updated_at'];

    protected $fillable = [
        'empresa_id',
        'nombre',
        'razon_social',
        'cuit',
        'direccion',
        'telefono',
        'email_contacto',
        'activa',
        'es_sede_central',
    ];

    protected $casts = [
        'activa'          => 'boolean',
        'es_sede_central' => 'boolean',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(FranchiseStaff::class, 'franquicia_id');
    }

    public function franquiciados(): HasMany
    {
        return $this->hasMany(FranchiseStaff::class, 'franquicia_id')
                    ->whereHas('user', fn($q) => $q->where('rol', 'franquiciado'));
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(FranchiseStaff::class, 'franquicia_id')
                    ->whereHas('user', fn($q) => $q->where('rol', 'empleado'));
    }

    public function firmasFisicas(): HasMany
    {
        return $this->hasMany(PhysicalSignature::class, 'franquicia_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Document::class, 'franquicia_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeActivas($query)
    {
        return $query->where('activa', 1);
    }
}