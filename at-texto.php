<?php
/**
 * AT Texto - convierte un valor de entrada a texto sin corromper el dato.
 *
 * (string) sobre un arreglo devuelve "Array" (con aviso); si ese resultado se
 * guarda, el dato queda corrupto. at_texto() devuelve $omision para arreglos,
 * objetos y null, y el texto del valor para escalares.
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('at_texto')) {
    /**
     * @param mixed  $valor   Dato recibido (cuerpo JSON, POST, parametro REST).
     * @param string $omision Texto devuelto cuando el valor no es un escalar.
     */
    function at_texto($valor, string $omision = ''): string {
        if (is_string($valor)) {
            return $valor;
        }
        if (is_int($valor) || is_float($valor) || is_bool($valor)) {
            return (string) $valor;
        }
        return $omision;
    }
}
