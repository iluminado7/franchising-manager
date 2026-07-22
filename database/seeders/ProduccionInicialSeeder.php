<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Siembra inicial de PRODUCCION.
 *
 * Crea lo minimo para poder entrar y empezar a operar sobre una base vacia:
 * planes, Cerrajeria Leonardo con sus sucursales, y los usuarios super_admin +
 * franquiciante. NO migra manuales, documentos, aceptaciones ni logs: todo eso
 * era data de prueba y se arranca de cero.
 *
 * ES IDEMPOTENTE: se puede correr dos veces sin duplicar nada. La contraseña
 * SOLO se asigna al crear el usuario, asi que volver a correrlo no le pisa la
 * clave a nadie que ya la haya cambiado.
 *
 * CONTRASEÑA TEMPORAL
 * -------------------
 * Se toma de la variable de entorno SEED_PASSWORD_TEMPORAL. No se hardcodea a
 * proposito: un seeder vive en git y una contraseña ahi adentro queda en el
 * historial del repo para siempre, aunque despues se borre.
 *
 *     SEED_PASSWORD_TEMPORAL="algo-largo-y-temporal" php artisan db:seed
 *
 * Cada usuario debe cambiarla desde perfil.php en su primer ingreso. OJO: eso
 * es una CONVENCION, no una regla — la base no tiene un campo que obligue al
 * cambio, asi que si no la cambian, la temporal sigue valiendo.
 *
 * POR QUE DB::table Y NO LOS MODELOS
 * ----------------------------------
 * Para planes, empresas y franquicias se usa el query builder: evita depender
 * del $fillable de cada modelo y hace explicito que esto es carga de datos, no
 * logica de negocio. Para User SI se usa el modelo, pero con SETTERS DIRECTOS:
 * rol, empresa_id, activo y password_hash no estan en $fillable (fix H-015) y
 * un create() masivo los ignoraria en silencio.
 *
 * ORDEN (lo impone el esquema de FKs)
 * -----------------------------------
 *   1. planes
 *   2. super_admins  (empresa_id NULL: no dependen de empresas)
 *   3. empresa Leonardo
 *   4. franquicias
 *   5. usuario franquiciante (ya necesita empresa_id)
 *   6. filas de perfil (super_admins / system_admins)
 */
class ProduccionInicialSeeder extends Seeder
{
    public function run(): void
    {
        $passwordTemporal = (string) env('SEED_PASSWORD_TEMPORAL', '');

        if (trim($passwordTemporal) === '' || mb_strlen($passwordTemporal) < 10) {
            $this->command->error('Falta SEED_PASSWORD_TEMPORAL (minimo 10 caracteres).');
            $this->command->line('   Ejemplo:');
            $this->command->line('   SEED_PASSWORD_TEMPORAL="Cambiar.Esto.2026" php artisan db:seed');
            throw new \RuntimeException('SEED_PASSWORD_TEMPORAL no definida o demasiado corta.');
        }

        DB::transaction(function () use ($passwordTemporal) {
            $this->sembrarPlanes();
            $this->sembrarSuperAdmins($passwordTemporal);

            $empresaId = $this->sembrarEmpresaLeonardo();
            $this->sembrarFranquicias($empresaId);
            $this->sembrarFranquiciante($empresaId, $passwordTemporal);
        });

        $this->command->info('Siembra inicial completada.');
        $this->command->warn('Los usuarios creados deben cambiar su contraseña desde perfil.php.');
    }

    // ── 1) Planes ────────────────────────────────────────────────────
    //
    // chk_tipo_precio exige que 'por_franquicia' traiga precio_base_por_franquicia
    // y precio_global NULL, y al reves para 'global'. Se respeta tal cual.
    private function sembrarPlanes(): void
    {
        $planes = [
            [
                'nombre'                     => 'Starter',
                'descripcion'                => null,
                'tipo_plan'                  => 'por_franquicia',
                'precio_base_por_franquicia' => 100.00,
                'precio_global'              => null,
            ],
            [
                'nombre'                     => 'Business',
                'descripcion'                => null,
                'tipo_plan'                  => 'por_franquicia',
                'precio_base_por_franquicia' => 200.00,
                'precio_global'              => null,
            ],
            [
                'nombre'                     => 'Global',
                'descripcion'                => null,
                'tipo_plan'                  => 'global',
                'precio_base_por_franquicia' => null,
                'precio_global'              => 250.00,
            ],
        ];

        foreach ($planes as $plan) {
            DB::table('planes')->updateOrInsert(
                ['nombre' => $plan['nombre']],
                $plan + [
                    'limite_franquicias'  => null,
                    'manuales_ilimitados' => 1,
                    'activo'              => 1,
                ]
            );
        }
    }

    // ── 2) Super admins ──────────────────────────────────────────────
    private function sembrarSuperAdmins(string $password): void
    {
        $cuentas = [
            ['email' => 'fdromero01@gmail.com',          'nombre' => 'Franco',    'apellido' => 'Romero',     'dni' => '46439087'],
            ['email' => 'cacciatorehoracio@gmail.com',   'nombre' => 'Horacio',   'apellido' => 'Cacciatore', 'dni' => '29094329'],
            ['email' => 'rosales.sebastian.m@gmail.com', 'nombre' => 'Sebastian', 'apellido' => 'Rosales',    'dni' => null],
        ];

        foreach ($cuentas as $datos) {
            $user = $this->crearUsuario($datos + [
                'rol'        => 'super_admin',
                'empresa_id' => null,   // el super_admin no pertenece a ninguna empresa
            ], $password);

            // Fila de perfil: marcador de rol. Sin esto el usuario existe pero
            // queda a medias para el codigo que resuelve el perfil.
            DB::table('super_admins')->updateOrInsert(
                ['user_id' => $user->id],
                ['user_id' => $user->id]
            );
        }
    }

    // ── 3) Empresa ───────────────────────────────────────────────────
    //
    // Leonardo es la unica empresa EXENTA de facturacion (facturable = 0). Dos
    // constraints la custodian:
    //   - uq_unica_exenta (columna virtual): no puede haber una segunda exenta.
    //   - chk_exenta_sin_plan: si facturable = 0, plan_id y los precios custom
    //     TIENEN que ser NULL. Por eso no se le asigna ningun plan.
    private function sembrarEmpresaLeonardo(): int
    {
        $nombre = 'Cerrajería Leonardo';

        DB::table('empresas')->updateOrInsert(
            ['nombre' => $nombre],
            [
                'razon_social'                 => 'Acceso Leonardo S.A.S',
                'cuit'                         => '30-71871070-3',
                'plan_id'                      => null,
                'precio_custom_por_franquicia' => null,
                'precio_custom_global'         => null,
                'facturable'                   => 0,
                'activa'                       => 1,
                'created_at'                   => now(),
                'updated_at'                   => now(),
            ]
        );

        return (int) DB::table('empresas')->where('nombre', $nombre)->value('id');
    }

    // ── 4) Franquicias (sucursales) ──────────────────────────────────
    //
    // NO se incluye "Sucursal Boedo": estaba eliminada (deleted_at) e inactiva.
    // Arrancando de cero no tiene sentido crear una sucursal dada de baja.
    private function sembrarFranquicias(int $empresaId): void
    {
        $sucursales = [
            [
                'nombre'         => 'Sucursal Caballito',
                'razon_social'   => 'AccesoLeonardo S.A.S',
                'cuit'           => '30-71871070-3',
                'direccion'      => 'Senillosa 235',
                'telefono'       => '1161736925',
                'email_contacto' => 'info@cerrajerialeonardo.com.ar',
                'es_sede_central' => 1,
            ],
            [
                'nombre'         => 'Sucursal Colegiales',
                'razon_social'   => 'Leonardo Colegiales S.A.S',
                'cuit'           => '30-71871070-3',
                'direccion'      => 'Av. Álvarez Thomas 390, CABA',
                'telefono'       => '1161736925',
                'email_contacto' => 'info@cerrajerialeonardo.com.ar',
                'es_sede_central' => 0,
            ],
            [
                'nombre'         => 'Sucursal Palermo',
                'razon_social'   => 'José Ángel Cerrada',
                'cuit'           => '30-71871070-3',
                'direccion'      => 'Aráoz 2486',
                'telefono'       => '1161736925',
                'email_contacto' => 'info@cerrajerialeonardo.com.ar',
                'es_sede_central' => 0,
            ],
            [
                'nombre'         => 'Sucursal Recoleta',
                'razon_social'   => 'Recoleta SAS',
                'cuit'           => '30-71871070-3',
                'direccion'      => 'Paraguay 1967 Recoleta, CABA',
                'telefono'       => '+541133791102',
                'email_contacto' => 'recoleta@cerrajerialeonardo.com.ar',
                'es_sede_central' => 0,
            ],
            [
                'nombre'         => 'Sucursal Retiro',
                'razon_social'   => 'CL Retiro SRL',
                'cuit'           => '30591633984',
                'direccion'      => 'Alfredo Lasalle 621',
                'telefono'       => '1122434981',
                'email_contacto' => 'rosales.sebastian.m@gmail.com',
                'es_sede_central' => 0,
            ],
        ];

        foreach ($sucursales as $s) {
            DB::table('franquicias')->updateOrInsert(
                ['empresa_id' => $empresaId, 'nombre' => $s['nombre']],
                $s + [
                    'empresa_id' => $empresaId,
                    'activa'     => 1,
                    'deleted_at' => null,
                    'deleted_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    // ── 5) Franquiciante ─────────────────────────────────────────────
    private function sembrarFranquiciante(int $empresaId, string $password): void
    {
        $user = $this->crearUsuario([
            'email'      => 'rovitoedualb@hotmail.com',
            'nombre'     => 'Edu',
            'apellido'   => 'Rovito',
            'dni'        => '20068467',
            'rol'        => 'franquiciante',
            'empresa_id' => $empresaId,
        ], $password);

        // El franquiciante marca su rol en system_admins. franchise_staff es
        // para franquiciado y empleado (ver las relaciones del modelo User), y
        // como no se migra ninguno, esa tabla queda vacia.
        DB::table('system_admins')->updateOrInsert(
            ['user_id' => $user->id],
            ['user_id' => $user->id]
        );
    }

    /**
     * Crea o actualiza un usuario.
     *
     * rol, empresa_id, activo y password_hash NO estan en $fillable (fix H-015):
     * se asignan con SETTER DIRECTO. Un User::create() masivo los descartaria en
     * silencio y la insercion fallaria contra el NOT NULL de rol.
     *
     * La contraseña se setea SOLO al crear: si el seeder se vuelve a correr, no
     * le pisa la clave a quien ya la cambio.
     */
    private function crearUsuario(array $datos, string $password): User
    {
        $user = User::where('email', $datos['email'])->first() ?? new User();
        $esNuevo = !$user->exists;

        $user->email    = $datos['email'];
        $user->nombre   = $datos['nombre'];
        $user->apellido = $datos['apellido'];
        $user->dni      = $datos['dni'] ?? null;
        $user->celular  = $datos['celular'] ?? null;

        // Campos privilegiados: setter directo, nunca mass assignment.
        $user->rol        = $datos['rol'];
        $user->empresa_id = $datos['empresa_id'] ?? null;
        $user->activo     = 1;

        if ($esNuevo) {
            $user->password_hash = Hash::make($password);
        }

        $user->save();

        $this->command->line(
            ($esNuevo ? '  + creado:     ' : '  = ya existia: ') . $user->email . '  (' . $user->rol . ')'
        );

        return $user;
    }
}
