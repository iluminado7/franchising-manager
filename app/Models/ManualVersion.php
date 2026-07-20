<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualVersion extends Model
{
    protected $table = 'manual_versions';

    // Sin updated_at — una versión publicada es inmutable
    public $timestamps = false;
    protected $dates = ['created_at', 'publicado_at'];

    // V2-H-019 (mass assignment): es_activa NO esta en $fillable.
    //
    // Marcar una version como activa es la operacion mas sensible del modelo: es
    // lo que decide que contenido ven todos los usuarios y sobre que se firman las
    // aceptaciones. Si quedara en $fillable, un futuro
    // ManualVersion::create($request->all()) o ->update($request->all())
    // permitiria activar cualquier version salteando la logica de publicar()
    // (transaccion, desactivacion de la anterior, calculo de numero, notificaciones).
    //
    // Se asigna SIEMPRE con setter directo, igual que password_hash (H-015):
    //     $version->es_activa = 1;
    //     $version->save();
    //
    // Los Query Builder updates (ManualVersion::where(...)->update(['es_activa' => 0]))
    // no pasan por $fillable y siguen siendo validos.
    protected $fillable = [
        'manual_id',
        'version_number',
        'version_minor',
        'contenido_html',
        'contenido_hash',
        // Manuales tipo 'pdf': la version guarda el archivo en vez del HTML.
        // Son datos derivados del archivo subido (ruta, nombre, mime, tamano),
        // no privilegios, por eso si van en $fillable. La ruta la calcula
        // siempre el servidor a partir del hash: nunca viene del request.
        // El CHECK chk_mv_contenido impide que coexistan con contenido_html.
        'archivo_path',
        'archivo_nombre',
        'archivo_mime',
        'archivo_tamano',
        // Snapshot inmutable del encabezado/pie tal como estaban al publicar esta
        // version. La copia de trabajo (editable) sigue en manuals.
        'encabezado_html',
        'pie_pagina_html',
        // SHA-256 de encabezado+contenido+pie: es lo que el usuario realmente
        // firma. contenido_hash cubre SOLO contenido_html y se conserva por
        // compatibilidad con las aceptaciones ya emitidas.
        'documento_hash',
        'publicado_por',
        'publicado_at',
        'nota_publicacion',   // mensaje opcional del publicador al subir la versión
    ];

    protected $casts = [
        'es_activa'      => 'boolean',
        'archivo_tamano' => 'integer',
        'version_number' => 'integer',
        'version_minor'  => 'integer',
    ];

    // version_label ("3.1") disponible en las respuestas JSON (historial, version activa).
    protected $appends = ['version_label'];

    // ── Relaciones ───────────────────────────────────────────────────

    // El manual contenedor
    public function manual(): BelongsTo
    {
        return $this->belongsTo(Manual::class, 'manual_id');
    }

    // El franquiciante que publicó esta versión
    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por');
    }

    // Aceptaciones digitales de esta versión
    public function acceptances(): HasMany
    {
        return $this->hasMany(Acceptance::class, 'manual_version_id');
    }

    // Firmas físicas de esta versión
    public function firmasFisicas(): HasMany
    {
        return $this->hasMany(PhysicalSignature::class, 'manual_version_id');
    }

    // Notificaciones disparadas por esta versión
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'manual_version_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    // Versiones que tienen un mensaje del publicador (release note).
    // Se usa para armar el hilo de notas del manual junto con las ManualNote.
    public function scopeConNotaPublicacion($query)
    {
        return $query->whereNotNull('nota_publicacion')
                     ->where('nota_publicacion', '!=', '');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    // Verifica si un franquiciado ya aceptó esta versión
    public function fueAceptadaPor(int $userId): bool
    {
        return $this->acceptances()->where('user_id', $userId)->exists();
    }

    // Verifica si una franquicia ya tiene firma física para esta versión
    public function tieneFirmaFisicaDe(int $franquiciaId): bool
    {
        return $this->firmasFisicas()->where('franquicia_id', $franquiciaId)->exists();
    }

    public function tieneNotaPublicacion(): bool
    {
        return !empty($this->nota_publicacion);
    }

    // Version de un manual tipo 'pdf': su contenido es un archivo, no HTML.
    // (chk_mv_contenido garantiza que es uno u otro, nunca ambos.)
    public function esPdf(): bool
    {
        return !empty($this->archivo_path);
    }

    // Etiqueta de version en formato mayor.menor, p. ej. "3.1".
    public function getVersionLabelAttribute(): string
    {
        return $this->version_number . '.' . ($this->version_minor ?? 0);
    }
}