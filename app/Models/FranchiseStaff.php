<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FranchiseStaff extends Model
{
    protected $table = 'franchise_staff';

    public $timestamps = false;
    protected $dates = ['created_at', 'updated_at'];

    // Tras v2.3 nombre/apellido/dni viven en users. Esta tabla conserva
    // el vínculo con la franquicia, que es lo que la diferencia del resto
    // de tablas de perfil.
    protected $fillable = [
        'user_id',
        'franquicia_id',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    // La cuenta de autenticación
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // La franquicia a la que pertenece — NOT NULL por diseño
    public function franquicia(): BelongsTo
    {
        return $this->belongsTo(Franquicia::class, 'franquicia_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    // Delegado a User para no romper código existente que llamara
    // $franchiseStaff->nombreCompleto().
    public function nombreCompleto(): string
    {
        return $this->user?->nombreCompleto() ?? '';
    }
}