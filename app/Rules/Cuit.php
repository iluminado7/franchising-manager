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
 *   '20-12345678-6' y '20123456786' son válidos. La normalización a un formato
 *   único es tarea del controlador, no de la validación.
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

        $n = preg_replace('/\D/', '', (string) $value);

        if (strlen($n) !== 11) {
            $fail('El CUIT/CUIL debe tener 11 dígitos.');
            return;
        }

        // Multiplicadores fijos del algoritmo, sobre los primeros 10 dígitos.
        // El 11º es el verificador.
        $mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += (int) $n[$i] * $mult[$i];
        }

        $verif = 11 - ($suma % 11);

        if ($verif === 11) {
            $verif = 0;
        } elseif ($verif === 10) {
            // Un CUIT cuyo cálculo da 10 no se emite: el número es inválido.
            $fail('El CUIT/CUIL no es válido.');
            return;
        }

        if ($verif !== (int) $n[10]) {
            $fail('El CUIT/CUIL no es válido: el dígito verificador no coincide.');
        }
    }
}