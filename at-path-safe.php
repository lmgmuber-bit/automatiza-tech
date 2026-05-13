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

if ( ! function_exists( 'at_serve_protected_pdf' ) ) {
    /**
     * Sirve un archivo PDF al navegador tras validar que la ruta es segura.
     * — E2: helper unificado para evitar duplicación de lógica de file-serving —
     *
     * @param string $path       Ruta candidata al archivo (puede ser absoluta o relativa).
     * @param string $dir        Directorio base permitido (anti path-traversal).
     * @param string $filename   Nombre para Content-Disposition (vacío = basename de $path).
     * @param bool   $inline     true = 'inline' (vista en navegador), false = 'attachment' (descarga).
     * @param bool   $no_cache   true = envía Cache-Control: private, no-cache.
     */
    function at_serve_protected_pdf(
        string $path,
        string $dir,
        string $filename = '',
        bool   $inline    = false,
        bool   $no_cache  = true
    ): void {
        $safe_path = at_path_inside( $path, $dir );
        if ( ! $safe_path || ! file_exists( $safe_path ) || ! is_readable( $safe_path ) ) {
            status_header( 404 );
            exit( 'Archivo no encontrado.' );
        }
        if ( ob_get_level() ) {
            ob_end_clean();
        }
        $fname       = $filename ?: basename( $safe_path );
        $disposition = $inline ? 'inline' : 'attachment';
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: ' . $disposition . '; filename="' . addslashes( $fname ) . '"' );
        header( 'Content-Length: ' . filesize( $safe_path ) );
        if ( $no_cache ) {
            header( 'Cache-Control: private, no-cache, no-store, must-revalidate' );
            header( 'Pragma: no-cache' );
        }
        header( 'X-Frame-Options: SAMEORIGIN' );
        readfile( $safe_path );
        exit;
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
