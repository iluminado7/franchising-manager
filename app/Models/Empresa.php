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
        'es_demo'                      => 'boolean',
        // Este cast SI es seguro, a diferencia de las fechas que escribe MySQL
        // (README §9): demo_vence_at la escribe PHP en UTC, asi que leerla
        // como UTC es correcto en los dos entornos.
        'demo_vence_at'                => 'datetime',
    ];

    // demo_vencida viaja en el JSON para que la pantalla no dependa del reloj
    // del navegador para decidir si una prueba termino.
    protected $appends = ['demo_vencida'];

    // ── Empresa demo ─────────────────────────────────────────────────
    //
    // es_demo y demo_vence_at NO estan en $fillable, y asi tienen que quedar:
    // con mass assignment se podria extender una prueba mandando el campo en
    // un request. Se asignan con setter directo desde
    // EmpresaController::store(), unico lugar que las escribe.

    // Duracion de la prueba. No se extiende: la empresa pasa a cliente solo
    // cuando contrata (flujo que todavia no existe).
    public const DEMO_DIAS = 30;

    // Tope de usuarios por rol de una empresa demo. Cuentan los NO eliminados,
    // activos o inactivos: si contaran solo los activos, desactivar a uno
    // liberaria un lugar y el tope no limitaria nada.
    //
    // Un rol que no figure aca tiene tope 0 en una demo (falla cerrado): si se
    // agrega un rol nuevo, su tope se decide a proposito.
    public const DEMO_TOPES = [
        'franquiciante' => 1,
        'franquiciado'  => 5,
        'empleado'      => 5,
    ];

    // Espacio de almacenamiento de una empresa demo: PDFs, documentos,
    // imagenes, firmas y el HTML de los manuales que suben sus usuarios.
    // La regla y lo que cuenta estan en App\Services\CupoDemo.
    public const DEMO_CUPO_BYTES = 500 * 1024 * 1024;

    // Mails por dia hacia los usuarios de una empresa demo. Publicar un manual a
    // sus 5 socios y 5 empleados son 10: una demo real no se acerca. Lo que
    // corta es el abuso. Ver App\Services\LimiteMailsDemo.
    public const DEMO_MAILS_POR_DIA = 100;

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
        // Excluye las dadas de baja: no deben sumar al total que se muestra en el
        // panel de empresas. Si en algún momento hace falta el conteo histórico
        // (incluyendo bajas), usar una relación aparte para no mezclar criterios.
        return $this->hasMany(Franquicia::class, 'empresa_id')
                    ->whereNull('deleted_at');
    }

    public function franquiciasActivas(): HasMany
    {
        return $this->hasMany(Franquicia::class, 'empresa_id')
                    ->where('activa', 1)
                    ->whereNull('deleted_at');
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

    // ¿Termino la prueba? Una demo sin fecha se trata como vencida: el CHECK
    // chk_empresa_demo impide ese estado, pero si apareciera, es mejor cortar
    // el acceso que regalar una prueba eterna.
    public function demoVencida(): bool
    {
        return (bool) $this->es_demo
            && ($this->demo_vence_at === null || $this->demo_vence_at->isPast());
    }

    public function getDemoVencidaAttribute(): bool
    {
        return $this->demoVencida();
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeActivas($query)
    {
        return $query->where('activa', 1);
    }

    // Excluir dados de baja (soft-delete). deleted_at / deleted_by se setean con
    // setter directo desde el controller; no van al $fillable.
    public function scopeNoEliminadas($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // Para jobs de facturación / suspensión por impago: nunca deben tocar a la
    // exenta, ni a una empresa demo. La demo no puede marcarse como exenta
    // (uq_unica_exenta admite una sola: Cerrajería Leonardo), así que queda con
    // facturable = 1, y este filtro es lo único que evita facturarle a quien
    // está probando la plataforma.
    public function scopeFacturables($query)
    {
        return $query->where('facturable', 1)
                     ->where('es_demo', 0);
    }
}