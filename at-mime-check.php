<?php
/**
 * Helper: verificación real de MIME type via finfo_file (magic bytes).
 *
 * Evita que un atacante bypasee el MIME check enviando un Content-Type
 * falso (p.ej. image/jpeg para un archivo .php).
 *
 * USO:
 *   $mime = at_verify_upload_mime( $_FILES['f']['tmp_name'], ['image/jpeg','image/png'] );
 *   if ( ! $mime ) { wp_send_json_error('Tipo no permitido'); }
 *   $ext  = at_mime_canonical_ext( $mime );   // 'jpg', 'png', ...
 */

if ( ! defined('ABSPATH') ) exit;

/**
 * Mapa canónico MIME → extensión.
 * La extensión del archivo guardado se DERIVA de aquí, NUNCA del nombre original.
 */
function at_mime_to_ext(): array {
    return [
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
        'image/gif'        => 'gif',
        'image/webp'       => 'webp',
        'video/mp4'        => 'mp4',
        'video/webm'       => 'webm',
        'application/pdf'  => 'pdf',
        'application/msword'                                                          => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'     => 'docx',
        'application/vnd.ms-powerpoint'                                               => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'   => 'pptx',
        'application/vnd.ms-excel'                                                    => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'           => 'xlsx',
        'text/plain'       => 'txt',
        'text/csv'         => 'csv',
        'text/x-csv'       => 'csv',
        'text/markdown'    => 'md',
    ];
}

/**
 * Lee el MIME real del archivo subido usando magic bytes (finfo_file).
 * Valida contra la lista blanca permitida.
 *
 * @param  string   $tmp_path  Ruta temporal del archivo ($_FILES['x']['tmp_name'])
 * @param  string[] $allowed   MIME types permitidos (ej. ['image/jpeg','application/pdf'])
 * @return string|false        MIME verificado, o false si no permitido / error
 */
function at_verify_upload_mime( string $tmp_path, array $allowed ) {
    // Garantizar que es un archivo subido legítimamente
    if ( ! is_uploaded_file( $tmp_path ) ) {
        return false;
    }

    // finfo: lee los magic bytes del archivo, ignora por completo el Content-Type del cliente
    if ( function_exists( 'finfo_open' ) ) {
        $finfo     = finfo_open( FILEINFO_MIME_TYPE );
        $real_mime = finfo_file( $finfo, $tmp_path );
        finfo_close( $finfo );
    } elseif ( function_exists( 'mime_content_type' ) ) {
        // Fallback (PECL mime_magic)
        $real_mime = mime_content_type( $tmp_path );
    } else {
        // Sin extensión disponible: rechazar por seguridad
        return false;
    }

    if ( $real_mime === false || $real_mime === '' ) {
        return false;
    }

    return in_array( $real_mime, $allowed, true ) ? $real_mime : false;
}

/**
 * Devuelve la extensión canónica para un MIME ya verificado.
 * Usar SIEMPRE en lugar de pathinfo($original_name, PATHINFO_EXTENSION).
 *
 * @param  string       $verified_mime  MIME devuelto por at_verify_upload_mime()
 * @return string|false                 Extensión sin punto (p.ej. 'jpg'), o false
 */
function at_mime_canonical_ext( string $verified_mime ) {
    $map = at_mime_to_ext();
    return $map[ $verified_mime ] ?? false;
}
