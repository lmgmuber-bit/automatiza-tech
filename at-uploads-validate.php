<?php
/**
 * AT Uploads Validate Helper
 *
 * Valida un archivo subido contra:
 *  - Whitelist de extensiones.
 *  - MIME real via finfo (no de cabecera del navegador).
 *  - Tamano maximo.
 *  - Rename a hash para evitar nombres maliciosos.
 *
 * Uso:
 *   require_once __DIR__ . '/at-uploads-validate.php';
 *   $res = at_validate_upload(
 *       $_FILES['archivo'],
 *       [ 'pdf', 'png', 'jpg', 'jpeg', 'webp' ],
 *       5 * MB_IN_BYTES
 *   );
 *   if ( is_wp_error( $res ) ) { ... }
 *   $safe_name = $res['safe_name'];
 *   $mime      = $res['mime'];
 *   move_uploaded_file( $res['tmp'], $dest_dir . $safe_name );
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'at_validate_upload' ) ) {
    function at_validate_upload( $file, array $allowed_ext, $max_bytes = 5242880 ) {
        if ( empty( $file ) || ! is_array( $file ) ) {
            return new WP_Error( 'no_file', 'No se recibio archivo' );
        }
        if ( ! isset( $file['error'] ) || $file['error'] !== UPLOAD_ERR_OK ) {
            return new WP_Error( 'upload_error', 'Error en la subida (codigo ' . (int) ( $file['error'] ?? -1 ) . ')' );
        }
        if ( ! isset( $file['size'] ) || (int) $file['size'] <= 0 || (int) $file['size'] > $max_bytes ) {
            return new WP_Error( 'too_big', 'Archivo vacio o supera el tamano maximo permitido' );
        }
        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return new WP_Error( 'not_uploaded', 'Archivo no proviene de upload HTTP valido' );
        }

        $orig = (string) ( $file['name'] ?? '' );
        $ext  = strtolower( pathinfo( $orig, PATHINFO_EXTENSION ) );
        $allowed_ext = array_map( 'strtolower', $allowed_ext );
        if ( ! in_array( $ext, $allowed_ext, true ) ) {
            return new WP_Error( 'bad_ext', 'Extension no permitida: ' . esc_html( $ext ) );
        }

        $mime = '';
        if ( function_exists( 'finfo_open' ) ) {
            $f = finfo_open( FILEINFO_MIME_TYPE );
            if ( $f ) {
                $mime = (string) finfo_file( $f, $file['tmp_name'] );
                finfo_close( $f );
            }
        }
        if ( $mime === '' && function_exists( 'mime_content_type' ) ) {
            $mime = (string) mime_content_type( $file['tmp_name'] );
        }

        $allowed_mimes = at_uploads_allowed_mimes();
        $valid_mimes_for_ext = $allowed_mimes[ $ext ] ?? [];
        if ( ! empty( $valid_mimes_for_ext ) && ! in_array( $mime, $valid_mimes_for_ext, true ) ) {
            return new WP_Error( 'bad_mime', 'El contenido del archivo no coincide con la extension declarada (' . esc_html( $mime ) . ')' );
        }

        if ( $ext === 'pdf' ) {
            $head = file_get_contents( $file['tmp_name'], false, null, 0, 5 );
            if ( $head !== '%PDF-' ) {
                return new WP_Error( 'bad_pdf', 'El archivo no es un PDF valido' );
            }
        }
        if ( in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ], true ) ) {
            $info = @getimagesize( $file['tmp_name'] );
            if ( $info === false ) {
                return new WP_Error( 'bad_image', 'El archivo no es una imagen valida' );
            }
        }

        $safe_name = bin2hex( random_bytes( 12 ) ) . '.' . $ext;

        return [
            'tmp'       => $file['tmp_name'],
            'safe_name' => $safe_name,
            'mime'      => $mime,
            'size'      => (int) $file['size'],
            'orig_name' => sanitize_file_name( $orig ),
        ];
    }
}

if ( ! function_exists( 'at_uploads_allowed_mimes' ) ) {
    function at_uploads_allowed_mimes() {
        return [
            'pdf'  => [ 'application/pdf' ],
            'png'  => [ 'image/png' ],
            'jpg'  => [ 'image/jpeg' ],
            'jpeg' => [ 'image/jpeg' ],
            'gif'  => [ 'image/gif' ],
            'webp' => [ 'image/webp' ],
            'svg'  => [ 'image/svg+xml' ],
            'csv'  => [ 'text/csv', 'text/plain', 'application/csv' ],
            'txt'  => [ 'text/plain' ],
            'xlsx' => [ 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip' ],
            'docx' => [ 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip' ],
        ];
    }
}
