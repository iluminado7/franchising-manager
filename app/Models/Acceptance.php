<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Acceptance extends Model
{
    protected $table = 'acceptances';

    public $timestamps = false;
    protected $dates = ['aceptado_at'];

    protected $fillable = [
        'manual_version_id',
        'user_id',
        'empresa_id',
        'aceptado_at',
        'ip_address',
        'user_agent',
        'pdf_sellado_url',
        'hash_verificacion',
        'pdf_generado',
    ];

    protected $casts = [
        'pdf_generado' => 'boolean',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function manualVersion(): BelongsTo
    {
        return $this->belongsTo(ManualVersion::class, 'manual_version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // empresa_id desnormalizado para performance del dashboard
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
