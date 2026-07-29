<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * users.dni → users.cuit
 *
 * UNA SOLA COLUMNA PARA CUIT Y CUIL
 *   Tienen formato idéntico: 11 dígitos con el mismo dígito verificador. Lo que
 *   cambia es la semántica — CUIT para quien factura (franquiciante, socio
 *   comercial), CUIL para el empleado en relación de dependencia. La etiqueta
 *   varía en la UI según el rol; el dato es el mismo.
 *
 *   Dos columnas dejarían siempre una vacía, y habría que recordar cuál mirar
 *   en cada consulta.
 *
 * QUÉ PASA CON LOS DNI QUE YA ESTÁN
 *   Un DNI NO es un CUIT: '20068467' son 8 dígitos, sin prefijo de tipo de
 *   persona ni dígito verificador. No se puede convertir — el prefijo depende
 *   del sexo o de si es persona jurídica, y el verificador se calcula sobre el
 *   número completo.
 *
 *   Renombrar y ya dejaría registros con un dato INVÁLIDO haciéndose pasar por
 *   CUIT. En un sistema de cumplimiento eso es peor que tenerlo vacío: alguien
 *   podría exportarlo, facturarlo o presentarlo creyendo que es correcto.
 *
 *   Por eso: los valores que NO pasan la validación de CUIT se mueven a
 *   `dni_legacy` y `cuit` queda en NULL. Nadie pierde el dato, y nadie lo
 *   confunde con un CUIT.
 *
 *   `dni_legacy` es TEMPORAL. Cuando todos los usuarios tengan su CUIT
 *   cargado, se borra con una migración de una línea.
 *
 * POR QUÉ NULLABLE Y NO NOT NULL
 *   Hay usuarios reales sin CUIT: un NOT NULL haría fallar esta migración. El
 *   camino es nullable ahora, obligatorio en el formulario para las altas
 *   nuevas, y NOT NULL recién cuando los existentes estén completos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Columna temporal para lo que no se pueda convertir.
        Schema::table('users', function (Blueprint $table) {
            $table->string('dni_legacy', 15)->nullable()->after('dni')
                  ->comment('TEMPORAL: DNI viejo que no era un CUIT válido. Borrar cuando todos tengan CUIT.');
        });

        // 2. Renombrar dni → cuit.
        DB::statement("
            ALTER TABLE users
            CHANGE COLUMN `dni` `cuit` VARCHAR(15) NULL
            COMMENT 'CUIT. Para rol empleado es el CUIL: mismo formato y mismo dígito verificador.'
        ");

        // 3. Separar lo válido de lo que no.
        //
        // Se recorre en PHP y no en SQL porque el dígito verificador es un
        // módulo 11 con multiplicadores fijos: expresarlo en SQL sería
        // ilegible y difícil de auditar.
        $filas = DB::table('users')->whereNotNull('cuit')->get(['id', 'cuit']);

        foreach ($filas as $fila) {
            if (self::cuitValido($fila->cuit)) {
                continue;   // ya era un CUIT: se queda donde está
            }

            DB::table('users')->where('id', $fila->id)->update([
                'dni_legacy' => $fila->cuit,
                'cuit'       => null,
            ]);
        }
    }

    public function down(): void
    {
        // Devolver los legacy a su lugar antes de renombrar: si no, se perderían.
        DB::statement("UPDATE users SET cuit = dni_legacy WHERE cuit IS NULL AND dni_legacy IS NOT NULL");

        DB::statement("ALTER TABLE users CHANGE COLUMN `cuit` `dni` VARCHAR(15) NULL");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dni_legacy');
        });
    }

    /**
     * Valida un CUIT/CUIL por su dígito verificador (módulo 11).
     *
     * Acepta con o sin guiones. NO consulta ARCA: esto solo verifica que el
     * número sea internamente coherente, que es lo que descarta los errores de
     * tipeo. Verificar que el CUIT EXISTA y a nombre de quién es otra cosa, y
     * necesita credenciales y homologación.
     */
    private static function cuitValido(?string $valor): bool
    {
        $n = preg_replace('/\D/', '', (string) $valor);

        if (strlen($n) !== 11) {
            return false;
        }

        // Multiplicadores fijos del algoritmo, aplicados a los primeros 10
        // dígitos. El 11º es el verificador.
        $mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += (int) $n[$i] * $mult[$i];
        }

        $resto = $suma % 11;
        $verif = 11 - $resto;

        if ($verif === 11) {
            $verif = 0;
        } elseif ($verif === 10) {
            // Un CUIT que daría 10 no se emite: el número es inválido.
            return false;
        }

        return $verif === (int) $n[10];
    }
};
