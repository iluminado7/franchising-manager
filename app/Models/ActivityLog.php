<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    // Sin timestamps automaticos: solo hay created_at, y lo escribe registrar().
    //
    // Se saco `protected $dates = ['created_at']`: esa propiedad fue ELIMINADA
    // de Eloquent en Laravel 10+. No casteaba nada; solo hacia creer que si.
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'empresa_id',
        'accion',
        'entidad_tipo',
        'entidad_id',
        'detalle',
        'ip_address',
        'user_agent',
        // created_at va en $fillable A PROPOSITO.
        //
        // registrar() siempre paso 'created_at' => now(), pero sin estar aca
        // create() lo DESCARTABA en silencio y la columna la llenaba MySQL con
        // su DEFAULT CURRENT_TIMESTAMP. Eso hacia que el huso dependiera del
        // servidor de base y no de la aplicacion: RDS esta en UTC y el XAMPP
        // local en hora de Buenos Aires, asi que la misma app guardaba valores
        // distintos segun donde corriera.
        //
        // No es un campo privilegiado (§6): no otorga permisos ni define
        // identidad, y el unico camino que lo escribe es registrar(), que le
        // pasa now() y nunca algo del request.
        'created_at',
    ];

    protected $casts = [
        'detalle'    => 'array',
        // Sin este cast, created_at se serializa como el string crudo de MySQL
        // ("2026-07-28 15:31:38"), sin zona horaria — y JavaScript, ante un
        // string con espacio en vez de T, lo interpreta como hora LOCAL. Como
        // el valor es UTC, log.php mostraba todo tres horas adelantado.
        //
        // Con el cast, Laravel manda ISO-8601 con Z y cada navegador convierte
        // a su propia hora. Los datos se siguen guardando en UTC, que es lo
        // correcto: no hay que migrar nada ni tocar APP_TIMEZONE.
        'created_at' => 'datetime',
    ];

    // ── Relaciones ───────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // NULL para acciones globales del super_admin
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // ── Helper estático para registrar acciones ───────────────────────
    public static function registrar(
        ?int $userId,
        string $accion,
        string $ip,
        ?int $empresaId = null,
        ?string $entidadTipo = null,
        ?int $entidadId = null,
        ?array $detalle = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'user_id'      => $userId,
            'empresa_id'   => $empresaId,
            'accion'       => $accion,
            'entidad_tipo' => $entidadTipo,
            'entidad_id'   => $entidadId,
            'detalle'      => $detalle,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
            'created_at'   => now(),
        ]);
    }
}