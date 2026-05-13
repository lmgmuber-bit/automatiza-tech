<?php
/**
 * AT Path Safe Helper
 *
 * Resuelve una ruta y verifica que este contenida dentro de un
 * directorio base permitido. Anti path-traversal.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'at_path_inside' ) ) {
    /**
     * @param string $candidate Ruta a verificar (puede contener ..).
     * @param string $base_dir  Directorio raiz permitido.
     * @return string|false     Ruta absoluta resuelta si esta dentro del base, false si no.
     */
    function at_path_inside( $candidate, $base_dir ) {
        $base = realpath( $base_dir );
        if ( $base === false ) {
            return false;
        }
        $base = rtrim( $base, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

        $real = realpath( $candidate );
        if ( $real === false ) {
            return false;
        }

        if ( strncmp( $real, $base, strlen( $base ) ) !== 0 ) {
            return false;
        }

        return $real;
    }
}

if ( ! function_exists( 'at_safe_basename' ) ) {
    /**
     * Devuelve un basename estricto: solo [A-Za-z0-9._-]; sin separadores ni nulos.
     * Util cuando un nombre proviene de input externo y se usara para construir un path.
     */
    function at_safe_basename( $name ) {
        $name = (string) $name;
        $name = str_replace( [ "\0", '/', '\\' ], '', $name );
        $name = preg_replace( '/[^A-Za-z0-9._\-]/', '_', $name );
        $name = trim( $name, '.' );
        if ( $name === '' ) {
            $name = 'file';
        }
        return substr( $name, 0, 120 );
    }
}
