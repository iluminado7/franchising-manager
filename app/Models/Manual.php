<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Manual extends Model
{
    protected $table = 'manuals';

    public $timestamps = false;
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'titulo',
        'categoria',
        'created_by',
        'estado',
        'orden',
        'deleted_by',
        'deleted_at',
        // OJO: `tipo` (editable|pdf) NO va aca a proposito.
        //
        // El tipo define QUE clase de contenido tienen las versiones del
        // manual: 'editable' -> contenido_html, 'pdf' -> archivo_path. Si se
        // pudiera cambiar por mass assignment (update($request->all())), un
        // manual publicado quedaria incoherente con sus propias versiones y
        // con las aceptaciones ya firmadas.
        //
        // La base garantiza "HTML o archivo, nunca ambos" (chk_mv_contenido),
        // pero un CHECK no puede referenciar otra tabla, asi que el vinculo
        // tipo <-> contenido se sostiene desde el codigo.
        //
        // Se setea con setter directo UNA sola vez, en ManualController::store().
        // Mismo criterio que es_activa (V2-H-019) y los campos privilegiados
        // de User (H-015).
        // OJO: `public_id` tampoco va aca (igual que `tipo`).
        // Es el identificador con el que el manual se conoce publicamente en
        // las URLs. Cambiarlo por mass assignment romperia todos los enlaces
        // guardados y los deep-links de notificaciones ya emitidas.
        // Lo asigna el hook creating() de abajo, una sola vez.
    ];

    // ── Ciclo de vida ────────────────────────────────────────────────

    protected static function booted(): void
    {
        // Todo manual nace con su identificador publico. Va en el MODELO y no en
        // el controlador para cubrir cualquier camino de creacion (store, seeders,
        // tinker, endpoints futuros) sin depender de que alguien se acuerde.
        // La columna es NOT NULL: sin esto, un create() por fuera de store()
        // fallaria en la base.
        static::creating(function (Manual $manual) {
            if (empty($manual->public_id)) {
                $manual->public_id = (string) Str::ulid();
            }
        });
    }

    // ── Relaciones ───────────────────────────────────────────────────

    // Super Admin que creó el manual
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Usuario que eliminó el manual (puede ser super_admin o franquiciante)
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(ManualVersion::class, 'manual_id');
    }

    public function versionActiva(): HasMany
    {
        return $this->hasMany(ManualVersion::class, 'manual_id')
                    ->where('es_activa', 1);
    }

    // Versiones con nota del publicador — usadas para armar el hilo de notas
    // (se mergean con ManualNote en ManualNoteController::porManual).
    public function versionesConNota(): HasMany
    {
        return $this->hasMany(ManualVersion::class, 'manual_id')
                    ->whereNotNull('nota_publicacion')
                    ->where('nota_publicacion', '!=', '');
    }

    // Empresas a las que fue asignado
    public function empresasAsignadas(): BelongsToMany
    {
        return $this->belongsToMany(
            Empresa::class,
            'manual_empresa_assignments',
            'manual_id',
            'empresa_id'
        )->withPivot('asignado_por', 'asignado_at');
    }

    // ── Asignaciones v2.3 ────────────────────────────────────────────

    // Usuarios con acceso individual al manual (vía manual_user_assignments).
    // Antes vivía en manual_assignments — la tabla fue migrada en v2.3.
    public function usuariosAsignados(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'manual_user_assignments',
            'manual_id',
            'user_id'
        )->withPivot('assigned_by', 'assigned_at', 'empresa_id');
    }

    // Alias para mantener compatibilidad con código viejo
    public function empleadosAsignados(): BelongsToMany
    {
        return $this->usuariosAsignados();
    }

    // Categorías a las que el manual está dirigido. Cualquier usuario que
    // tenga una de estas categorías (activa) verá el manual.
    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(
            FranchiseCategory::class,
            'manual_category_assignments',
            'manual_id',
            'category_id'
        )->withPivot('empresa_id', 'assigned_by', 'assigned_at');
    }

    // Filas pivote directas — útiles cuando se necesita assigned_by, fecha, etc.
    public function categoryAssignments(): HasMany
    {
        return $this->hasMany(ManualCategoryAssignment::class, 'manual_id');
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(ManualUserAssignment::class, 'manual_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'manual_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopePublicados($query)
    {
        return $query->where('estado', 'publicado');
    }

    public function scopeBorradores($query)
    {
        return $query->where('estado', 'borrador');
    }

    // Manuales asignados a una empresa específica
    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->whereHas('empresasAsignadas', fn($q) =>
            $q->where('empresa_id', $empresaId)
        );
    }

    // Excluir manuales marcados como eliminados
    public function scopeNoEliminados($query)
    {
        return $query->where('estado', '!=', 'eliminado');
    }

    // Visibilidad para super_admin: ve todos los no-eliminados + los eliminados por franquiciantes
    // (porque para super_admin el borrado del franquiciante no es definitivo).
    // Los eliminados por super_admin sí quedan ocultos por defecto.
    public function scopeVisiblesParaSuperAdmin($query)
    {
        return $query->where(function ($q) {
            $q->where('estado', '!=', 'eliminado')
              ->orWhereHas('deletedBy', fn($du) => $du->where('rol', 'franquiciante'));
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────


    public function estaEliminado(): bool
    {
        return $this->estado === 'eliminado';
    }

    public function estaPublicado(): bool
    {
        return $this->estado === 'publicado';
    }

    // ── Tipo de manual ───────────────────────────────────────────────

    // Manual subido como archivo PDF: no se edita, no se publica desde el
    // editor. Sus versiones guardan archivo_path en vez de contenido_html.
    public function esPdf(): bool
    {
        return $this->tipo === 'pdf';
    }

    // Manual redactado en el editor (comportamiento historico y default).
    public function esEditable(): bool
    {
        return $this->tipo !== 'pdf';
    }
}