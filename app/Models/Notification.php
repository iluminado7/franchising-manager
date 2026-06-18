<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'notifications';

    public $timestamps = false;
    protected $dates = ['created_at', 'leida_at'];

    protected $fillable = [
        'user_id',
        'tipo',
        'manual_id',
        'manual_version_id',
        'document_id',
        'titulo',
        'mensaje',
        'leida',
        'leida_at',
    ];

    protected $casts = [
        'leida' => 'boolean',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Cada una es nullable — solo una tiene valor a la vez (CHECK CONSTRAINT en DB)
    public function manual(): BelongsTo
    {
        return $this->belongsTo(Manual::class, 'manual_id');
    }

    public function manualVersion(): BelongsTo
    {
        return $this->belongsTo(ManualVersion::class, 'manual_version_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    // Notification::noLeidas()->where('user_id', $id)->get()
    public function scopeNoLeidas($query)
    {
        return $query->where('leida', 0);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function marcarComoLeida(): void
    {
        $this->update([
            'leida'    => true,
            'leida_at' => now(),
        ]);
    }
}
