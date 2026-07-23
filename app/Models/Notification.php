<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\NotificationObserver;

#[ObservedBy([NotificationObserver::class])]
class Notification extends Model
{
    protected $table = 'notifications';

    public $timestamps = false;
    protected $dates = ['created_at', 'leida_at'];

    // V2-H-020 (mass assignment): user_id NO esta en $fillable.
    //
    // Es el campo que decide A QUIEN le llega la notificacion. Si quedara en
    // $fillable, un Notification::create($request->all()) permitiria emitir
    // notificaciones a nombre de cualquier usuario — incluidas las alertas de
    // seguridad que recibe el super_admin.
    //
    // Se asigna con setter directo desde el codigo que las crea:
    //     $n = new Notification([...]);
    //     $n->user_id = $uid;
    //     $n->save();
    //
    // Los Notification::insert() masivos NO pasan por $fillable y siguen
    // funcionando sin cambios.
    protected $fillable = [
        'tipo',
        'manual_id',
        'manual_version_id',
        'document_id',
        'document_version_id',
        'category_id',
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

    // Cada FK puede ser NULL — qué combinación es válida lo controla
    // el CHECK constraint chk_notif_fk (ver migración v2.3 paso 17).
    //
    // Tipos y FKs requeridas:
    //   - nuevo_manual                  → manual_id
    //   - modificacion_manual           → manual_version_id
    //   - manual_asignado               → manual_version_id
    //   - nuevo_documento               → document_id
    //   - recordatorio_pendiente        → ninguna
    //   - manual_asignado_categoria     → manual_id + category_id
    //   - documento_asignado            → document_id
    //   - documento_asignado_categoria  → document_id + category_id
    //   - nueva_version_documento       → document_version_id

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

    // v2.3: nuevas FKs

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FranchiseCategory::class, 'category_id');
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