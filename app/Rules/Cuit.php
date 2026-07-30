<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida un CUIT/CUIL por su dígito verificador (módulo 11).
 *
 * QUÉ VALIDA Y QUÉ NO
 *   Verifica que el número sea INTERNAMENTE COHERENTE: 11 dígitos y el
 *   verificador correcto. Eso descarta los errores de tipeo, que son la
 *   inmensa mayoría de los casos reales.
 *
 *   NO consulta ARCA. Saber si ese CUIT EXISTE y a nombre de quién es otra
 *   cosa: necesita credenciales, certificado y homologación. Cuando se
 *   implemente, va como una validación adicional, no reemplaza a esta.
 *
 * CUIT Y CUIL SON EL MISMO FORMATO
 *   11 dígitos, mismo algoritmo. Lo que cambia es la semántica: CUIT para
 *   quien factura, CUIL para el empleado en relación de dependencia. Por eso
 *   una sola regla y una sola columna sirven para los dos.
 *
 * ACEPTA CON O SIN GUIONES
 *   '20-12345678-6' y '20123456786' son válidos. También '20.12345678.6' o
 *   con espacios: se descarta todo lo que no sea dígito antes de calcular.
 *
 * GUARDAR SIEMPRE IGUAL: normalizar()
 *   Validar y guardar son dos cosas distintas. Esta regla dice si el número
 *   sirve; normalizar() dice cómo se escribe.
 *
 *   Sin normalizar, la columna termina con '20-12345678-6' en unas filas y
 *   '20123456786' en otras: el mismo CUIT se ve distinto según quién lo cargó,
 *   y cualquier búsqueda por ese campo falla para la mitad de los casos.
 *
 *   Vive ACÁ y no en cada controller a propósito. Si la normalización quedara
 *   en updateCuit(), perfil.php guardaría con guiones y usuarios.php no —
 *   justo el tipo de inconsistencia que después nadie sabe de dónde salió.
 */
class Cuit implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // El campo es nullable: la obligatoriedad la decide la regla 'required'
        // por separado, no esta.
        if ($value === null || $value === '') {
            return;
        }

        $n = self::soloDigitos((string) $value);

        if (strlen($n) !== 11) {
            $fail('El CUIT/CUIL debe tener 11 dígitos.');
            return;
        }

        if (self::digitoVerificador($n) === null) {
            // Un CUIT cuyo cálculo da 10 no se emite: el número es inválido.
            $fail('El CUIT/CUIL no es válido.');
            return;
        }

        if (self::digitoVerificador($n) !== (int) $n[10]) {
            $fail('El CUIT/CUIL no es válido: el dígito verificador no coincide.');
        }
    }

    /**
     * Formato canónico: XX-XXXXXXXX-X.
     *
     * Se eligió CON guiones porque es lo que ya está cargado en la base y lo
     * que la gente reconoce a simple vista. Cambiar el criterio ahora obligaría
     * a migrar las filas existentes.
     *
     * SI NO SON 11 DÍGITOS, DEVUELVE EL VALOR TAL CUAL.
     * Normalizar algo que no es un CUIT sería inventarle una forma y esconder
     * el problema: quien decide si el número sirve es validate(), no esta
     * función. Se usan juntas — primero la regla rechaza, después esto formatea
     * lo que pasó.
     *
     * null y '' pasan derecho: la columna es nullable y no le corresponde a
     * esta función decidir qué significa un CUIT vacío.
     */
    public static function normalizar(?string $valor): ?string
    {
        if ($valor === null || trim($valor) === '') {
            return $valor;
        }

        $n = self::soloDigitos($valor);

        if (strlen($n) !== 11) {
            return $valor;
        }

        return substr($n, 0, 2) . '-' . substr($n, 2, 8) . '-' . substr($n, 10, 1);
    }

    /**
     * Deja solo los dígitos. Es lo que hace que '20-12345678-6',
     * '20123456786' y '20.12345678.6' sean el mismo número.
     */
    private static function soloDigitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    /**
     * Dígito verificador por módulo 11 sobre los primeros 10 dígitos.
     * Devuelve null cuando el cálculo da 10, que corresponde a un CUIT que no
     * se emite.
     *
     * Espera 11 dígitos ya limpios: quien llama valida el largo antes.
     */
    private static function digitoVerificador(string $n): ?int
    {
        // Multiplicadores fijos del algoritmo, sobre los primeros 10 dígitos.
        // El 11º es el verificador.
        $mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += (int) $n[$i] * $mult[$i];
        }

        $verif = 11 - ($suma % 11);

        if ($verif === 11) {
            return 0;
        }
        if ($verif === 10) {
            return null;
        }

        return $verif;
    }
}