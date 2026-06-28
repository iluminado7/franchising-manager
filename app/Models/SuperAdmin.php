<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuperAdmin extends Model
{
    protected $table = 'super_admins';

    public $timestamps = false;
    protected $dates = ['created_at'];

    // Tras v2.3 nombre/apellido/dni viven en users. Esta tabla queda como
    // marcador de rol (id, user_id, created_at).
    protected $fillable = [
        'user_id',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    // Delegado a User para no romper código existente que llamara
    // $superAdmin->nombreCompleto().
    public function nombreCompleto(): string
    {
        return $this->user?->nombreCompleto() ?? '';
    }
}