<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaEmail extends Model
{
    protected $table = 'empresa_emails';

    public $timestamps = true;
    const UPDATED_AT = 'updated_at';
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'empresa_id',
        'email',
        'tipo',
        'principal',
    ];

    protected $casts = [
        'principal' => 'boolean',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeFacturacion($query)
    {
        return $query->where('tipo', 'facturacion');
    }

    public function scopeContacto($query)
    {
        return $query->where('tipo', 'contacto');
    }

    public function scopePrincipales($query)
    {
        return $query->where('principal', 1);
    }
}
