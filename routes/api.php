<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\AcceptanceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\FranquiciaController;
use App\Http\Controllers\ManualAssignmentController;
use App\Http\Controllers\ManualEmpresaAssignmentController;
use App\Http\Controllers\PhysicalSignatureController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ManualNoteController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\EmpresaEmailController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\InvoiceController;
use App\Http\Middleware\EnsureActiveTenant;

// ── Rutas públicas ────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

// ── Rutas protegidas ──────────────────────────────────────────────────
Route::middleware(['auth:sanctum', EnsureActiveTenant::class])->group(function () {

    // Auth — todos los roles
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
    Route::put('/me/email',    [AuthController::class, 'updateEmail']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);

    // Notificaciones — todos los roles
    Route::get('/notificaciones',             [NotificationController::class, 'index']);
    Route::post('/notificaciones/{id}/leer',  [NotificationController::class, 'marcarLeida']);
    Route::post('/notificaciones/leer-todas', [NotificationController::class, 'marcarTodasLeidas']);

    // Manuales — lectura para todos los roles
    Route::get('/manuales',      [ManualController::class, 'index']);
    Route::get('/manuales/{id}', [ManualController::class, 'show']);

    // Documentos — lectura para todos
    Route::get('/documentos', [DocumentController::class, 'index']);

    // Aceptaciones — franquiciado acepta
    Route::post('/versiones/{versionId}/aceptar', [AcceptanceController::class, 'aceptar']);

    // Firmas físicas — franquiciado sube
    Route::post('/versiones/{versionId}/firma-fisica', [PhysicalSignatureController::class, 'subir']);

    // Asignaciones de empleados — lectura para todos
    Route::get('/empleados/{userId}/asignaciones', [ManualAssignmentController::class, 'porEmpleado']);

    // ── SOLO SUPER ADMIN ──────────────────────────────────────────────
    Route::middleware('role:super_admin')->group(function () {

        // Empresas
        Route::get('/empresas',                   [EmpresaController::class, 'index']);
        Route::post('/empresas',                  [EmpresaController::class, 'store']);
        Route::get('/empresas/{id}',              [EmpresaController::class, 'show']);
        Route::put('/empresas/{id}',              [EmpresaController::class, 'update']);
        Route::get('/empresas/{id}/dashboard',    [EmpresaController::class, 'dashboard']);

        // Emails de empresa
        Route::get('/empresas/{empresaId}/emails',         [EmpresaEmailController::class, 'index']);
        Route::post('/empresas/{empresaId}/emails',        [EmpresaEmailController::class, 'store']);
        Route::put('/empresas/{empresaId}/emails/{id}',    [EmpresaEmailController::class, 'update']);
        Route::delete('/empresas/{empresaId}/emails/{id}', [EmpresaEmailController::class, 'destroy']);

        // Planes
        Route::get('/planes',      [PlanController::class, 'index']);
        Route::post('/planes',     [PlanController::class, 'store']);
        Route::get('/planes/{id}', [PlanController::class, 'show']);
        Route::put('/planes/{id}', [PlanController::class, 'update']);

        // Asignación de manuales a empresas
        Route::get('/manuales/{manualId}/empresas',                [ManualEmpresaAssignmentController::class, 'porManual']);
        Route::post('/manuales/{manualId}/empresas',               [ManualEmpresaAssignmentController::class, 'asignar']);
        Route::delete('/manuales/{manualId}/empresas/{empresaId}', [ManualEmpresaAssignmentController::class, 'desasignar']);
        Route::get('/empresas/{empresaId}/manuales',               [ManualEmpresaAssignmentController::class, 'porEmpresa']);

        // Invoices
        Route::get('/invoices',          [InvoiceController::class, 'index']);
        Route::get('/invoices/{id}',     [InvoiceController::class, 'show']);
        Route::put('/invoices/{id}',     [InvoiceController::class, 'update']);
        Route::post('/invoices/generar', [InvoiceController::class, 'generar']);

        // Notas de manuales — super_admin marca el estado (pendiente/leida/resuelta)
        Route::put('/notas/{id}/estado', [ManualNoteController::class, 'updateEstado']);
    });

    // ── USUARIOS — super_admin, franquiciante y franquiciado ──────────
    // El franquiciado solo puede gestionar empleados de su propia sucursal;
    // el control fino (rol / empresa / franquicia) se aplica en UserController.
    Route::middleware('role:super_admin,franquiciante,franquiciado')->group(function () {
        Route::get('/usuarios',                     [UserController::class, 'index']);
        Route::post('/usuarios',                    [UserController::class, 'store']);
        Route::put('/usuarios/{id}',                [UserController::class, 'update']);
        Route::post('/usuarios/{id}/toggle-activo', [UserController::class, 'toggleActivo']);
        Route::delete('/usuarios/{id}',             [UserController::class, 'destroy']);
        Route::post('/usuarios/{id}/restore',       [UserController::class, 'restore']);
    });

    // ── SUPER ADMIN + FRANQUICIANTE ───────────────────────────────────
    Route::middleware('role:super_admin,franquiciante')->group(function () {

        // Franquicias
        Route::get('/franquicias',                [FranquiciaController::class, 'index']);
        Route::post('/franquicias',               [FranquiciaController::class, 'store']);
        Route::get('/franquicias/{id}',           [FranquiciaController::class, 'show']);
        Route::put('/franquicias/{id}',           [FranquiciaController::class, 'update']);
        Route::get('/franquicias/{id}/dashboard', [FranquiciaController::class, 'dashboard']);

        // Manuales — escritura
        Route::post('/manuales',               [ManualController::class, 'store']);
        Route::put('/manuales/{id}',           [ManualController::class, 'update']);
        Route::delete('/manuales/{id}',         [ManualController::class, 'destroy']);
        Route::post('/manuales/{id}/restore',   [ManualController::class, 'restore']);
        Route::post('/manuales/{id}/publicar', [ManualController::class, 'publicar']);
        Route::post('/manuales/{id}/archivar', [ManualController::class, 'archivar']);
        Route::post('/manuales/{id}/borrador', [ManualController::class, 'guardarBorrador']);
        Route::post('/manuales/{id}/desarchivar', [ManualController::class, 'desarchivar']);

        // Documentos — subir, editar, eliminar/restaurar
        Route::post('/documentos',                              [DocumentController::class, 'store']);
        Route::put('/documentos/{id}',                          [DocumentController::class, 'update']);
        Route::delete('/documentos/{id}',                       [DocumentController::class, 'destroy']);
        Route::post('/documentos/{id}/restore',                 [DocumentController::class, 'restore']);

        // Documentos — versiones (subir, listar, editar nota, ver historial)
        Route::post('/documentos/{id}/version',                 [DocumentController::class, 'subirVersion']);
        Route::get('/documentos/{id}/versiones',                [DocumentController::class, 'versiones']);
        Route::put('/documentos/{id}/versiones/{vid}/nota',     [DocumentController::class, 'updateNota']);
        // Acceso a versiones específicas del historial (NO para franquiciado/empleado)
        Route::get('/documentos/{id}/versiones/{vid}/descargar', [DocumentController::class, 'descargarVersion']);
        Route::get('/documentos/{id}/versiones/{vid}/preview',   [DocumentController::class, 'previewVersion']);

        // Aceptaciones — ver
        Route::get('/versiones/{versionId}/aceptaciones',  [AcceptanceController::class, 'porVersion']);

        // Firmas físicas — ver
        Route::get('/versiones/{versionId}/firmas-fisicas', [PhysicalSignatureController::class, 'porVersion']);

        // Versiones de manuales
        Route::get('/manuales/{id}/versiones', [ManualController::class, 'versiones']);

        // Activity Log
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);

        // Invoices — franquiciante solo lectura
        Route::get('/invoices',      [InvoiceController::class, 'index']);
        Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    });

    // ── FRANQUICIANTE + FRANQUICIADO ──────────────────────────────────
    Route::middleware('role:franquiciante,franquiciado')->group(function () {
        Route::post('/empleados/{userId}/asignaciones',              [ManualAssignmentController::class, 'asignar']);
        Route::delete('/empleados/{userId}/asignaciones/{manualId}', [ManualAssignmentController::class, 'desasignar']);
    });

    Route::get('/documentos/{id}/descargar', [DocumentController::class, 'descargar']);
    Route::get('/documentos/{id}/preview',   [DocumentController::class, 'preview']);

    // Notas de manuales
    // Leer el hilo: super_admin (todas), franquiciante (su empresa), franquiciado (las suyas).
    Route::get('/manuales/{manualId}/notas', [ManualNoteController::class, 'porManual'])
        ->middleware('role:super_admin,franquiciante,franquiciado');
    // Escribir: franquiciante y franquiciado (el empleado no).
    Route::post('/manuales/{manualId}/notas', [ManualNoteController::class, 'store'])
        ->middleware('role:franquiciante,franquiciado');
});