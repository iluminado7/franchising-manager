<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\CspReportController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\ManualImageController;
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
use App\Http\Controllers\FranchiseCategoryController;
use App\Http\Controllers\ManualCategoryAssignmentController;
use App\Http\Controllers\DocumentAssignmentController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\InvoiceController;
use App\Http\Middleware\EnsureActiveTenant;

// ── Rutas públicas ────────────────────────────────────────────────────
// H-014 fix: throttle compuesto (IP + email) — ver AppServiceProvider::boot().
// Reemplaza el throttle simple por IP que permitía credential stuffing con IPs
// rotadas.
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

// H-025 fix: endpoint público (sin auth) para recibir reportes de violaciones
// de Content-Security-Policy del navegador. Sin auth porque los navegadores no
// mandan cookies con report-uri. Con throttle agresivo para evitar spam.
Route::post('/csp-report', [CspReportController::class, 'receive'])
    ->middleware('throttle:60,1');

// ── Rutas protegidas ──────────────────────────────────────────────────
Route::middleware(['auth:sanctum', EnsureActiveTenant::class])->group(function () {

    // Auth — todos los roles
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
    // H-011 fix: throttle en cambio de credenciales.
    // 5 intentos por hora por IP+usuario. Suficiente para tipos humanos
    // (varios intentos con current_password mal) pero bloquea brute-force.
    Route::middleware('throttle:5,60')->group(function () {
        Route::put('/me/email',    [AuthController::class, 'updateEmail']);
        Route::put('/me/password', [AuthController::class, 'updatePassword']);
    });

    // Foto de perfil — cada usuario edita SOLO la suya (opera sobre el token).
    Route::post('/perfil/foto',   [ProfilePhotoController::class, 'subir']);
    Route::delete('/perfil/foto', [ProfilePhotoController::class, 'quitar']);
    // Ver el avatar de cualquier usuario (para logs, listados, topbar).
    Route::get('/perfil/foto/{userId}', [ProfilePhotoController::class, 'ver']);

    // Notificaciones — todos los roles
    Route::get('/notificaciones',             [NotificationController::class, 'index']);
    Route::post('/notificaciones/{id}/leer',  [NotificationController::class, 'marcarLeida']);
    Route::post('/notificaciones/leer-todas', [NotificationController::class, 'marcarTodasLeidas']);

    // Manuales — lectura para todos los roles
    Route::get('/manuales',      [ManualController::class, 'index']);
    Route::get('/manuales/{id}', [ManualController::class, 'show']);

    // Feature imágenes: descarga de imagen de manual. El controller valida
    // que el usuario tenga acceso al manual antes de servir la imagen.
    Route::get('/manuales-imagenes/{id}/descargar', [ManualImageController::class, 'descargar']);

    // Documentos — lectura para todos
    Route::get('/documentos',                [DocumentController::class, 'index']);
    Route::get('/documentos/{id}/descargar', [DocumentController::class, 'descargar']);
    Route::get('/documentos/{id}/preview',   [DocumentController::class, 'preview']);

    // Aceptaciones — franquiciado acepta
    Route::post('/versiones/{versionId}/aceptar', [AcceptanceController::class, 'aceptar']);

    // Asignaciones individuales de manuales — lectura para todos
    Route::get('/empleados/{userId}/asignaciones', [ManualAssignmentController::class, 'porEmpleado']);

    // Categorías — lectura para todos los roles (cada uno con su scope)
    Route::get('/categorias',                [FranchiseCategoryController::class, 'index']);
    Route::get('/categorias/{id}',           [FranchiseCategoryController::class, 'show']);
    Route::get('/usuarios/{id}/categorias',  [UserController::class, 'listarCategorias']);

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

    });

    // ── SUPER ADMIN + FRANQUICIANTE ───────────────────────────────────
    // Operaciones que sólo super_admin y franquiciante pueden hacer.
    // Franquiciado NO entra acá (ver bloque siguiente para las cosas que sí puede hacer).
    Route::middleware('role:super_admin,franquiciante')->group(function () {

        // Usuarios — CRUD (el franquiciado NO crea/elimina usuarios)
        Route::get('/usuarios',                     [UserController::class, 'index']);
        Route::post('/usuarios',                    [UserController::class, 'store']);
        Route::put('/usuarios/{id}',                [UserController::class, 'update']);
        Route::post('/usuarios/{id}/toggle-activo', [UserController::class, 'toggleActivo']);
        Route::delete('/usuarios/{id}',             [UserController::class, 'destroy']);
        Route::post('/usuarios/{id}/restore',       [UserController::class, 'restore']);

        // Franquicias
        Route::get('/franquicias',                [FranquiciaController::class, 'index']);
        Route::post('/franquicias',               [FranquiciaController::class, 'store']);
        Route::get('/franquicias/{id}',           [FranquiciaController::class, 'show']);
        Route::put('/franquicias/{id}',           [FranquiciaController::class, 'update']);
        Route::get('/franquicias/{id}/dashboard', [FranquiciaController::class, 'dashboard']);

        // Categorías del franquiciado — escritura
        Route::post('/categorias',                    [FranchiseCategoryController::class, 'store']);
        Route::put('/categorias/{id}',                [FranchiseCategoryController::class, 'update']);
        Route::post('/categorias/{id}/toggle-activa', [FranchiseCategoryController::class, 'toggleActiva']);
        Route::delete('/categorias/{id}',             [FranchiseCategoryController::class, 'destroy']);

        // Feature imágenes: upload de imagen a un manual. Solo super_admin y
        // franquiciante suben (validado también en el controller).
        Route::post('/manuales/{manualId}/imagenes', [ManualImageController::class, 'upload']);

        // Manuales — escritura
        Route::post('/manuales',                  [ManualController::class, 'store']);
        Route::put('/manuales/{id}',              [ManualController::class, 'update']);
        Route::delete('/manuales/{id}',           [ManualController::class, 'destroy']);
        Route::post('/manuales/{id}/restore',     [ManualController::class, 'restore']);
        Route::post('/manuales/{id}/publicar',    [ManualController::class, 'publicar']);
        Route::post('/manuales/{id}/archivar',    [ManualController::class, 'archivar']);
        Route::post('/manuales/{id}/borrador',    [ManualController::class, 'guardarBorrador']);
        Route::post('/manuales/{id}/desarchivar', [ManualController::class, 'desarchivar']);

        // Documentos — subir, editar, eliminar/restaurar
        Route::post('/documentos',              [DocumentController::class, 'store']);
        Route::put('/documentos/{id}',          [DocumentController::class, 'update']);
        Route::delete('/documentos/{id}',       [DocumentController::class, 'destroy']);
        Route::post('/documentos/{id}/restore', [DocumentController::class, 'restore']);

        // Documentos — versiones (subir, listar, editar nota, ver historial)
        Route::post('/documentos/{id}/version',                       [DocumentController::class, 'subirVersion']);
        Route::get('/documentos/{id}/versiones',                      [DocumentController::class, 'versiones']);
        Route::put('/documentos/{id}/versiones/{vid}/nota',           [DocumentController::class, 'updateNota']);
        Route::delete('/documentos/{id}/versiones/{versionId}',       [DocumentController::class, 'destroyVersion']);
        Route::post('/documentos/{id}/versiones/{versionId}/restore', [DocumentController::class, 'restoreVersion']);
        // Acceso a versiones específicas del historial (NO para franquiciado/empleado)
        Route::get('/documentos/{id}/versiones/{vid}/descargar', [DocumentController::class, 'descargarVersion']);
        Route::get('/documentos/{id}/versiones/{vid}/preview',   [DocumentController::class, 'previewVersion']);

        // Aceptaciones / firmas físicas — lectura
        Route::get('/versiones/{versionId}/aceptaciones',   [AcceptanceController::class, 'porVersion']);
        Route::get('/versiones/{versionId}/firmas-fisicas', [PhysicalSignatureController::class, 'porVersion']);

        // Feature "Aceptaciones" (pantalla nueva) — solo super_admin y franquiciante:
        //   POST /versiones/{id}/firma-fisica  → subir PDF de firma (con user_id del socio en el body)
        //   GET  /firmas-fisicas                → lista combinada de aceptaciones digitales + firmas
        //   GET  /firmas-fisicas/socios-para-manual → dropdown de socios para el modal de subida
        Route::post('/versiones/{versionId}/firma-fisica',      [PhysicalSignatureController::class, 'subir']);
        Route::get('/firmas-fisicas',                           [PhysicalSignatureController::class, 'index']);
        Route::get('/firmas-fisicas/socios-para-manual',        [PhysicalSignatureController::class, 'sociosParaManual']);

        // Versiones de manuales
        Route::get('/manuales/{id}/versiones', [ManualController::class, 'versiones']);

        // Activity Log
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);

        // Invoices — franquiciante solo lectura
        Route::get('/invoices',      [InvoiceController::class, 'index']);
        Route::get('/invoices/{id}', [InvoiceController::class, 'show']);

        // ── Asignación de manuales a categorías ─────────────────────
        Route::get('/manuales/{manualId}/categorias',                 [ManualCategoryAssignmentController::class, 'porManual']);
        Route::get('/categorias/{categoryId}/manuales',               [ManualCategoryAssignmentController::class, 'porCategoria']);
        Route::post('/manuales/{manualId}/categorias',                [ManualCategoryAssignmentController::class, 'asignar']);
        Route::put('/manuales/{manualId}/categorias',                 [ManualCategoryAssignmentController::class, 'sincronizar']);
        Route::delete('/manuales/{manualId}/categorias/{categoryId}', [ManualCategoryAssignmentController::class, 'desasignar']);

        // ── Asignación de manuales a usuarios individuales ──────────
        Route::get('/manuales/{manualId}/usuarios', [ManualAssignmentController::class, 'porManual']);
        Route::put('/manuales/{manualId}/usuarios', [ManualAssignmentController::class, 'sincronizarPorManual']);

        // ── Asignación de documentos a categorías ───────────────────
        Route::get('/documentos/{documentId}/categorias',                 [DocumentAssignmentController::class, 'listarCategorias']);
        Route::post('/documentos/{documentId}/categorias',                [DocumentAssignmentController::class, 'asignarCategoria']);
        Route::put('/documentos/{documentId}/categorias',                 [DocumentAssignmentController::class, 'sincronizarCategorias']);
        Route::delete('/documentos/{documentId}/categorias/{categoryId}', [DocumentAssignmentController::class, 'desasignarCategoria']);
        Route::get('/categorias/{categoryId}/documentos',                 [DocumentAssignmentController::class, 'porCategoria']);

        // ── Asignación de documentos a usuarios individuales ────────
        Route::get('/documentos/{documentId}/usuarios',             [DocumentAssignmentController::class, 'listarUsuarios']);
        Route::post('/documentos/{documentId}/usuarios',            [DocumentAssignmentController::class, 'asignarUsuario']);
        Route::put('/documentos/{documentId}/usuarios',             [DocumentAssignmentController::class, 'sincronizarUsuarios']);
        Route::delete('/documentos/{documentId}/usuarios/{userId}', [DocumentAssignmentController::class, 'desasignarUsuario']);
    });

    // ── SUPER ADMIN + FRANQUICIANTE + FRANQUICIADO ────────────────────
    // Operaciones que el franquiciado también puede hacer (sobre empleados de su
    // misma sucursal). El control fino (rol / empresa / franquicia) se aplica en
    // cada controller via su helper actorPuedeGestionar*.
    Route::middleware('role:super_admin,franquiciante,franquiciado')->group(function () {

        // Categorías de un usuario — escritura (franquiciado gestiona a sus empleados)
        Route::put('/usuarios/{id}/categorias',                 [UserController::class, 'sincronizarCategorias']);
        Route::post('/usuarios/{id}/categorias',                [UserController::class, 'agregarCategoria']);
        Route::delete('/usuarios/{id}/categorias/{categoryId}', [UserController::class, 'quitarCategoria']);

        // Asignaciones individuales de manuales (franquiciante o franquiciado asigna
        // un manual a un empleado/franquiciado específico de su scope)
        Route::post('/empleados/{userId}/asignaciones',              [ManualAssignmentController::class, 'asignar']);
        Route::delete('/empleados/{userId}/asignaciones/{manualId}', [ManualAssignmentController::class, 'desasignar']);

        // Notas de manuales — lectura
        // (super_admin ve todas, franquiciante las de su empresa, franquiciado las suyas)
        Route::get('/manuales/{manualId}/notas', [ManualNoteController::class, 'porManual']);

        // H-017 fix: descarga de firma física por endpoint autenticado.
        // El controller valida acceso al manual + restricción por franquicia
        // (super_admin: todas, franquiciante: de su empresa, franquiciado: solo la suya).
        // Empleado queda bloqueado por el grupo del middleware.
        Route::get('/firmas-fisicas/{id}/descargar', [PhysicalSignatureController::class, 'descargar']);
    });

    // ── SUPER ADMIN + FRANQUICIANTE (cambiar estado de notas) ─────────
    // El control fino (no permitir cambiar estado de notas propias / de otras
    // empresas) se aplica en ManualNoteController::updateEstado.
    Route::middleware('role:super_admin,franquiciante')->group(function () {
        Route::put('/notas/{id}/estado', [ManualNoteController::class, 'updateEstado']);
    });

    // ── FRANQUICIANTE + FRANQUICIADO ──────────────────────────────────
    // Escribir notas de manuales — el empleado no puede.
    Route::middleware('role:franquiciante,franquiciado')->group(function () {
        Route::post('/manuales/{manualId}/notas', [ManualNoteController::class, 'store']);
    });
});