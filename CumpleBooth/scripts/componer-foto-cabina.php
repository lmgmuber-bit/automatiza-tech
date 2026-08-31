<?php
/**
 * componer-foto-cabina.php — arma una foto igual a las que produce el kiosco,
 * para poblar álbumes de demo sin usar fotos de prueba reales.
 *
 * SE USÓ PARA: reemplazar las 13 fotos de prueba de Luis en el álbum de
 * `demo-carreras` (un adulto en penumbra dentro de un marco de Rayo McQueen)
 * por 8 fotos con los niños de IA de `design/explicativo/ia/`. Esos niños NO
 * son reales: están generados y aprobados, y el README del explicativo lo deja
 * por escrito.
 *
 * AL REGISTRARLAS EN EL ÁLBUM, VAN COMO `guest`, NO COMO `booth`.
 * `album-api.php` descarta EN SILENCIO cualquier pieza `booth` que no tenga
 * fila enlazada en `cc_photos` —de ahí sale su token—, y esas filas solo las
 * crea el kiosco de verdad. Registradas como `booth` entran a la base y no
 * aparecen nunca: el ejecutor decía "8 registradas" y el álbum seguía vacío.
 *
 * Y NO hace falta emitir tokens de lectura nuevos: viven en
 * `cc_event_album_tokens` atados al álbum, no a las fotos, así que agregar o
 * quitar piezas no rompe los enlaces de demo de la landing.
 */
/**
 * La geometría NO se inventa: sale de src/frameGeometry.js, que es lo que usa
 * el kiosco de verdad. El marco dorado ya viene pintado dentro de
 * `fondo-sala.jpg`, y `frameBox` dice dónde va la foto dentro de ese marco. El
 * área útil es un CUADRADO centrado en esa caja, con un inset del 8,5% para no
 * pisar el borde dorado. Copiar mal ese cálculo se nota: la foto queda
 * ligeramente corrida y el marco se ve mal recortado.
 */

const INSET_RATIO = 0.085;

function geometriaFoto(array $frameBox, int $ancho, int $alto): array
{
    $ox = $frameBox['x'] * $ancho;
    $oy = $frameBox['y'] * $alto;
    $ow = $frameBox['w'] * $ancho;
    $oh = $frameBox['h'] * $alto;
    $lado = min($ow, $oh);
    $left = $ox + ($ow - $lado) / 2;
    $top  = $oy + ($oh - $lado) / 2;
    $inset = $lado * INSET_RATIO;
    return [
        'left'  => (int) round($left + $inset),
        'top'   => (int) round($top + $inset),
        'lado'  => (int) round($lado - $inset * 2),
    ];
}

/** Recorta al centro y escala, sin deformar: una cara estirada canta. */
function cuadrarYPegar($lienzo, $src, int $dx, int $dy, int $lado): void
{
    $sw = imagesx($src); $sh = imagesy($src);
    $corte = min($sw, $sh);
    // Se toma del tercio superior: en una foto de cámara la cara está arriba,
    // y un recorte centrado deja media frente fuera.
    $sx = (int) (($sw - $corte) / 2);
    $sy = (int) (($sh - $corte) * 0.18);
    imagecopyresampled($lienzo, $src, $dx, $dy, $sx, $sy, $lado, $lado, $corte, $corte);
}

function componerCabina(
    string $fondoSala, string $cutPersonaje, string $fotoNino,
    string $etiqueta, string $agradece, string $subtexto, string $nombrePersonaje,
    string $fuenteBold, array $frameBox, string $salida
): bool {
    $fondo = @imagecreatefromjpeg($fondoSala);
    if (!$fondo) { return false; }
    $ancho = imagesx($fondo); $alto = imagesy($fondo);

    $lienzo = imagecreatetruecolor($ancho, $alto);
    imagecopy($lienzo, $fondo, 0, 0, 0, 0, $ancho, $alto);
    imagedestroy($fondo);
    imagealphablending($lienzo, true);

    // 1. La foto del niño, dentro del marco que ya trae el fondo.
    $nino = @imagecreatefrompng($fotoNino);
    if ($nino) {
        $g = geometriaFoto($frameBox, $ancho, $alto);
        cuadrarYPegar($lienzo, $nino, $g['left'], $g['top'], $g['lado']);
        imagedestroy($nino);
    }

    // 2. El personaje, apoyado abajo.
    $cut = @imagecreatefrompng($cutPersonaje);
    if ($cut) {
        $cw = imagesx($cut); $ch = imagesy($cut);
        $destAlto = (int) ($alto * 0.30);
        $destAncho = (int) ($cw * ($destAlto / $ch));
        $cx = (int) (($ancho - $destAncho) / 2);
        $cy = $alto - $destAlto - (int) ($alto * 0.075);
        imagecopyresampled($lienzo, $cut, $cx, $cy, 0, 0, $destAncho, $destAlto, $cw, $ch);
        imagedestroy($cut);
    }

    $blanco = imagecolorallocate($lienzo, 255, 255, 255);
    $g = geometriaFoto($frameBox, $ancho, $alto);

    // 3. Textos. Se centran midiendo la caja real de cada línea; calcular el
    //    centro por caracteres deja el texto corrido con tildes y mayúsculas.
    /* El texto va con sombra. Sin ella el blanco sobre la pared clara del fondo
       queda ilegible: se probó plano y no se leía nada. La sombra se dibuja
       desplazada 3px y en dos pasadas, que es lo que hace el kiosco. */
    $sombra = imagecolorallocatealpha($lienzo, 0, 0, 0, 45);
    $centrar = function (int $tam, string $txt, int $y, $color) use ($lienzo, $ancho, $fuenteBold, $sombra) {
        $caja = imagettfbbox($tam, 0, $fuenteBold, $txt);
        $w = $caja[2] - $caja[0];
        $x = (int) (($ancho - $w) / 2);
        imagettftext($lienzo, $tam, 0, $x + 3, $y + 3, $sombra, $fuenteBold, $txt);
        imagettftext($lienzo, $tam, 0, $x, $y, $color, $fuenteBold, $txt);
    };

    /* Debajo del MARCO, no del área de foto: el marco dorado sobresale, y
       tomando el borde de la foto el texto quedaba montado encima de él. */
    $yBase = $g['top'] + $g['lado'] + (int) ($alto * 0.085);
    $centrar(40, $agradece, $yBase, $blanco);
    $centrar(26, $subtexto, $yBase + 46, $blanco);

    // 4. Píldora con el nombre del personaje, abajo.
    $tamPill = 30;
    $cajaPill = imagettfbbox($tamPill, 0, $fuenteBold, $nombrePersonaje);
    $wPill = $cajaPill[2] - $cajaPill[0];
    $px = (int) (($ancho - $wPill) / 2);
    $py = $alto - (int) ($alto * 0.028);
    $rojo = imagecolorallocate($lienzo, 214, 48, 60);
    imagefilledrectangle($lienzo, $px - 34, $py - 40, $px + $wPill + 34, $py + 14, $rojo);
    imagettftext($lienzo, $tamPill, 0, $px, $py, $blanco, $fuenteBold, $nombrePersonaje);

    // 5. Etiqueta de la temática, sobre el marco.
    $tamEt = 20;
    $cajaEt = imagettfbbox($tamEt, 0, $fuenteBold, $etiqueta);
    $wEt = $cajaEt[2] - $cajaEt[0];
    $ex = (int) (($ancho - $wEt) / 2);
    $ey = $g['top'] - (int) ($alto * 0.012);
    imagefilledrectangle($lienzo, $ex - 22, $ey - 28, $ex + $wEt + 22, $ey + 10, $rojo);
    imagettftext($lienzo, $tamEt, 0, $ex, $ey, $blanco, $fuenteBold, $etiqueta);

    $ok = imagejpeg($lienzo, $salida, 88);
    imagedestroy($lienzo);
    return $ok;
}
