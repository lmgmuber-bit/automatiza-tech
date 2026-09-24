<?php
/**
 * El manual de la fiesta para los papás: qué va a pasar, qué necesitamos y sus enlaces.
 *
 * Nació el 2026-09-06 como un script de Python con los datos escritos a mano y un HTML por
 * fiesta. Acá se porta a la aplicación con dos cambios de fondo: los datos salen de la ficha
 * (nombre, edad, fecha, temática, contacto, hora y lugar del Resumen del Plan), y el
 * **contenido vive una sola vez**, en `cb_manual_datos()`, del que salen tanto la página que
 * se ve en el navegador como el PDF que va adjunto al correo. Si se escriben por separado
 * terminan diciendo cosas distintas, que es lo peor que le puede pasar a un manual.
 *
 * Lo que la ficha no sabe —el PIN de la galería, el enlace de la invitación, que va con un
 * token hasheado y no se puede reconstruir— entra como parámetro al generar, igual que en la
 * pantalla Mensajes.
 *
 * Los nombres de los personajes salen de `themes.json` en tiempo de ejecución. No van
 * escritos acá a propósito: el repositorio no lleva nombres de franquicias.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/lib.cliente.php';
require_once __DIR__ . '/lib.puntajes.php';
require_once __DIR__ . '/lib.ajustes.php';
require_once __DIR__ . '/lib.acceptance.php';
require_once __DIR__ . '/lib.documentos.php';

/** Firma corta para el enlace público del manual: sin ella la página no se sirve. */
function cb_manual_firma(string $slug): string
{
    return substr(cb_hmac($slug, 'manual-fiesta'), 0, 24);
}

function cb_manual_firma_valida(string $slug, string $firma): bool
{
    return $firma !== '' && hash_equals(cb_manual_firma($slug), $firma);
}

function cb_manual_url(string $slug, bool $pdf = false): string
{
    return rtrim((string) cb_public_base_url(), '/') . '/manual.php?p=' . rawurlencode($slug)
        . '&f=' . cb_manual_firma($slug) . ($pdf ? '&pdf=1' : '');
}

/**
 * La invitación publicada de una fiesta: de ahí salen la hora y la dirección que ya vieron
 * los invitados. Se prefiere la publicada; si no hay, la más reciente que no esté revocada.
 * Devuelve [] si la fiesta no tiene ninguna.
 */
function cb_manual_invitacion(string $slug): array
{
    // La consulta vive en `lib.cliente.php` desde que el formulario de Términos también
    // necesita la hora y la dirección de la invitación: una sola fuente, un solo criterio
    // sobre qué invitación manda cuando hay varias.
    return cb_party_invitacion_datos($slug);
}

/** Ruta de la cabecera JPEG de la temática, si existe. */
function cb_manual_banner(string $tema): ?string
{
    $ruta = __DIR__ . '/themes/' . basename($tema) . '/correo-cabecera.jpg';
    return is_file($ruta) ? $ruta : null;
}

/**
 * Compone la cabecera del manual: una franja horizontal con la foto de la temática,
 * oscurecida hacia abajo para que el título se lea encima. Devuelve la ruta de un JPEG
 * temporal, o null si no se pudo (y entonces el manual usa la foto tal cual).
 *
 * Los logos NO van acá: van en el pie del documento. Arriba manda la temática, que es lo que
 * el papá reconoce como "la fiesta de mi hijo".
 *
 * Se compone con GD y no por capas en el PDF porque el motor no sabe de transparencias: el
 * velo que hace legible el texto blanco tiene que venir ya aplicado en el píxel. De paso, el
 * mismo JPEG sirve para el PDF sin repetir el trabajo.
 */
function cb_manual_cabecera_compuesta(?string $rutaFoto, float $proporcion = 3.0): ?string
{
    if ($rutaFoto === null || !is_file($rutaFoto) || !function_exists('imagecreatefromjpeg')) {
        return null;
    }
    $foto = @imagecreatefromjpeg($rutaFoto);
    if (!$foto) { return null; }

    // El recorte se hace acá porque el motor de PDF solo escala: si se le pasa una foto más
    // alta de lo que cabe, o deja franjas blancas o aplasta la hoja. La proporción por
    // defecto (3.0) da una franja de unos 70 mm en A4: apaisada, con los personajes visibles
    // y dejando sitio a la primera sección en la misma hoja.
    $anchoOrig = imagesx($foto);
    $altoOrig = imagesy($foto);
    $ancho = $anchoOrig;
    $alto = (int) round($ancho / $proporcion);
    if ($alto > $altoOrig) {
        // La foto es más apaisada que el objetivo: se recorta a lo ancho.
        $alto = $altoOrig;
        $ancho = (int) round($alto * $proporcion);
    }
    // 0.30 desde arriba: al ser una franja más baja hay que subir el encuadre, o se cortan
    // las cabezas. Se sacrifica el suelo, que es donde menos pasa. Misma decisión que el
    // `object-position` de los carteles.
    $origenX = (int) round(($anchoOrig - $ancho) / 2);
    $origenY = (int) round(($altoOrig - $alto) * 0.30);

    $lienzo = imagecreatetruecolor($ancho, $alto);
    imagecopy($lienzo, $foto, 0, 0, $origenX, $origenY, $ancho, $alto);

    // Velo: casi limpio arriba —donde se ven los personajes— y firme abajo, que es donde va
    // el título. Se dibuja línea a línea porque GD no tiene degradados.
    for ($y = 0; $y < $alto; $y++) {
        $t = $y / max(1, $alto - 1);
        // Suave hasta la mitad, y de ahí sube rápido: el texto ocupa el tercio inferior.
        $op = $t < 0.45 ? 0.20 + $t * 0.15 : 0.27 + ($t - 0.45) * 1.05;
        $alpha = (int) round(min(0.80, $op) * 127);
        $color = imagecolorallocatealpha($lienzo, 12, 6, 24, 127 - $alpha);
        imagefilledrectangle($lienzo, 0, $y, $ancho, $y, $color);
    }

    $tmp = tempnam(sys_get_temp_dir(), 'cc-cab-') . '.jpg';
    $ok = imagejpeg($lienzo, $tmp, 88);
    return $ok ? $tmp : null;
}

/** "domingo 13 de septiembre de 2026" a partir de una fecha ISO. */
function cb_manual_fecha_larga(string $iso): string
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) { return $iso; }
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
              'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $ts = mktime(12, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
    return $dias[(int) date('w', $ts)] . ' ' . ((int) $m[3]) . ' de ' . $meses[(int) $m[2]] . ' de ' . $m[1];
}

/**
 * Todo lo que va en el manual, como datos. `null` si la fiesta no existe.
 *
 * @param array{pin?:string,invitacion?:string} $extra
 */
function cb_manual_datos(string $slug, array $extra = []): ?array
{
    $fiesta = cb_load_party_raw($slug);
    if (!$fiesta) { return null; }

    $temaSlug = (string) ($fiesta['tema'] ?? '');
    $temas = cb_load_themes();
    $tema = is_array($temas['themes'][$temaSlug] ?? null) ? $temas['themes'][$temaSlug] : [];
    $personajes = [];
    foreach ((array) ($tema['personajes'] ?? []) as $p) {
        $n = is_array($p) ? (string) ($p['name'] ?? $p['nombre'] ?? '') : (string) $p;
        if ($n !== '') { $personajes[] = $n; }
    }

    $contactos = cb_party_contacts($slug);
    $principal = $contactos[0] ?? null;

    // Hora y lugar salen de dos sitios y en este orden: primero el Resumen del Plan que el
    // admin escribe al emitir el enlace de Términos (es lo acordado por escrito), y si no
    // está, **la invitación publicada**, que es la hora y la dirección que ya leyeron los
    // invitados. Antes solo se miraba el Resumen, así que en una fiesta sin Términos emitidos
    // el manual salía sin hora ni lugar teniéndolos la invitación a un JOIN de distancia.
    $estado = cb_party_acceptance_state($slug);
    $resumen = is_array($estado['row']['plan_summary'] ?? null) ? $estado['row']['plan_summary'] : [];
    $invitacionFiesta = cb_manual_invitacion($slug);
    $hora = (string) ($resumen['event_time'] ?? '') ?: (string) ($invitacionFiesta['event_time'] ?? '');
    $lugar = (string) ($resumen['event_address'] ?? '') ?: (string) ($invitacionFiesta['address'] ?? '');
    // "Por confirmar" en la invitación es un marcador de posición, no una dirección: si es
    // eso, el manual prefiere no mostrar la fila a mostrar algo que no sirve.
    if (preg_match('/^\s*por confirmar\s*$/iu', $lugar) === 1) { $lugar = ''; }

    $ajustes = cb_ajustes();
    $anticipacion = (int) ($ajustes['manual_anticipacion_min'] ?? 0);
    $diasLista = (int) ($ajustes['manual_dias_lista'] ?? 0);

    $base = rtrim((string) cb_public_base_url(), '/');
    $pin = preg_replace('/\D/', '', (string) ($extra['pin'] ?? '1234'));
    $invitacion = trim((string) ($extra['invitacion'] ?? ''));
    if ($invitacion !== '' && !preg_match('#^https?://#i', $invitacion)) { $invitacion = ''; }

    $juegos = [];
    foreach (cb_juegos_de_tema($temaSlug) as $id => $j) { $juegos[] = $j['nombre']; }

    $nombre = (string) ($fiesta['nombre'] ?? '');
    $edad = isset($fiesta['edad']) && (int) $fiesta['edad'] > 0 ? (int) $fiesta['edad'] : null;
    $temaNombre = cb_theme_public_name($temaSlug) ?: (string) ($tema['nombre'] ?? $temaSlug);

    return [
        'slug' => $slug,
        'nombre' => $nombre,
        'edad' => $edad,
        'fecha' => (string) ($fiesta['fecha'] ?? ''),
        'fecha_larga' => cb_manual_fecha_larga((string) ($fiesta['fecha'] ?? '')),
        'hora' => $hora,
        'lugar' => $lugar,
        'plan' => (string) ($resumen['plan_name'] ?? ''),
        'duracion' => (string) ($resumen['service_hours'] ?? ''),
        'tema' => $temaSlug,
        'tema_nombre' => $temaNombre,
        'personajes' => $personajes,
        'banner' => cb_manual_banner($temaSlug),
        'contacto' => $principal ? [
            'nombre' => (string) ($principal['name'] ?? ''),
            'email' => (string) ($principal['email'] ?? ''),
        ] : null,
        'invitados' => count((array) ($fiesta['invitados'] ?? [])),
        'juegos' => $juegos,
        'pin' => $pin,
        'anticipacion_min' => $anticipacion,
        'dias_lista' => $diasLista,
        'enlaces' => [
            'galeria' => $base . '/galeria.php?p=' . rawurlencode($slug),
            'album' => $base . '/album.html?p=' . rawurlencode($slug),
            'juegos' => $base . '/juego/?p=' . rawurlencode($slug),
            'invitacion' => $invitacion,
            'manual' => cb_manual_url($slug),
            'manual_pdf' => cb_manual_url($slug, true),
        ],
        'whatsapp' => cb_manual_marca()['whatsapp'],
        'correo' => cb_manual_marca()['correo'],
        'instagram' => cb_manual_marca()['instagram'],
        'instagram_url' => cb_manual_marca()['instagram_url'],
    ];
}

/**
 * El glifo de Instagram como SVG en línea: el mismo dibujo que en el PDF —cuadrado
 * redondeado, lente y punto—, para que la página y el adjunto se vean iguales. Va incrustado
 * y no como archivo suelto: así el manual se sigue viendo completo si alguien lo guarda.
 */
function cb_manual_icono_instagram(): string
{
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">'
        . '<rect x="3" y="3" width="18" height="18" rx="5.4"/>'
        . '<circle cx="12" cy="12" r="4.1"/>'
        . '<circle cx="17.3" cy="6.7" r="1.2" fill="currentColor" stroke="none"/>'
        . '</svg>';
}

/**
 * El contacto de CumpleClick sale de `data/marca.json`, que es donde el admin lo edita.
 * Con valores de respaldo por si el archivo falta: un manual sin teléfono es peor que uno
 * con el teléfono conocido.
 */
function cb_manual_marca(): array
{
    static $marca = null;
    if ($marca === null) {
        $crudo = json_decode((string) @file_get_contents(__DIR__ . '/data/marca.json'), true);
        $crudo = is_array($crudo) ? $crudo : [];
        $instagram = trim((string) ($crudo['instagram'] ?? '')) ?: '@Cumple_Click';
        $enlaceIg = trim((string) ($crudo['instagram_url'] ?? ''));
        $marca = [
            'whatsapp' => trim((string) ($crudo['whatsapp'] ?? '')) ?: '+56 9 7494 0070',
            'correo' => trim((string) ($crudo['correo'] ?? '')) ?: 'contacto@cumpleclick.com',
            'instagram' => $instagram,
            // Si el admin no escribió el enlace, se arma con la cuenta: es la misma regla que
            // ya usa el cartel QR, para que las dos piezas nunca apunten a lugares distintos.
            'instagram_url' => $enlaceIg !== '' ? $enlaceIg : 'https://instagram.com/' . ltrim($instagram, '@'),
        ];
    }
    return $marca;
}

/**
 * El contenido, sección por sección, ya con los datos puestos. Es lo único que saben leer
 * la página y el PDF: si algo cambia en el manual, cambia acá y sale en los dos.
 *
 * @return array<int,array{titulo:string,tipo:string,cuerpo:mixed}>
 */
function cb_manual_secciones(array $d): array
{
    $n = $d['nombre'];
    $t = $d['tema_nombre'];
    $conPersonajes = $d['personajes'] ? ': ' . implode(', ', $d['personajes']) : '';
    $llegamos = $d['anticipacion_min'] > 0
        ? "Llegamos {$d['anticipacion_min']} minutos antes de la hora de inicio."
        : 'Llegamos con anticipación a la hora de inicio.';
    $plazoLista = $d['dias_lista'] > 0
        ? "hasta {$d['dias_lista']} días antes de la fiesta"
        : 'antes de la fiesta';
    $juegosTexto = count($d['juegos']) > 1
        ? 'Esta temática tiene ' . count($d['juegos']) . ' juegos: ' . implode(' y ', $d['juegos']) . '.'
        : ($d['juegos'] ? 'El juego de esta temática es ' . $d['juegos'][0] . '.' : '');

    $datos = [['Cumpleañero/a', $n . ($d['edad'] ? " · cumple {$d['edad']} años" : '')]];
    if ($d['fecha'] !== '') { $datos[] = ['Fecha', $d['fecha_larga']]; }
    if ($d['hora'] !== '') { $datos[] = ['Hora de inicio', $d['hora']]; }
    if ($d['lugar'] !== '') { $datos[] = ['Lugar', $d['lugar']]; }
    $datos[] = ['Temática', $t . $conPersonajes];
    if ($d['plan'] !== '') { $datos[] = ['Plan contratado', $d['plan'] . ($d['duracion'] !== '' ? ' · ' . $d['duracion'] : '')]; }
    if ($d['contacto']) { $datos[] = ['Contacto de la fiesta', $d['contacto']['nombre'] . ($d['contacto']['email'] ? ' · ' . $d['contacto']['email'] : '')]; }
    $datos[] = ['Contacto de CumpleClick', 'WhatsApp ' . $d['whatsapp'] . ' · ' . $d['correo']];

    $enlaces = [
        ['Galería de fotos', $d['enlaces']['galeria'] . '  (PIN ' . $d['pin'] . ')'],
        ['Álbum Recuerdo', $d['enlaces']['album']],
        ['Juegos 3D', $d['enlaces']['juegos']],
    ];
    if ($d['enlaces']['invitacion'] !== '') { array_unshift($enlaces, ['Invitación digital', $d['enlaces']['invitacion']]); }
    $enlaces[] = ['Este manual', $d['enlaces']['manual']];

    return [
        ['titulo' => 'Los datos de tu fiesta', 'tipo' => 'tabla', 'cuerpo' => $datos],

        ['titulo' => 'Antes de la fiesta', 'tipo' => 'pasos', 'cuerpo' => [
            ['Firma los Términos y Condiciones',
             'Te llega un enlace personal por correo. Se revisa el resumen del plan, se marcan tres casillas y se firma con el dedo en la pantalla. Toma dos minutos, y sin esa firma no podemos activar la fiesta.'],
            ['Revisa la lista de invitados',
             "Los nombres de los invitados aparecen dentro de la cabina, en los juegos y en la tabla de posiciones. Mándanos la lista actualizada de los niños de $n por WhatsApp o a {$d['correo']}, $plazoLista." . ($d['invitados'] > 0 ? " Hoy tenemos {$d['invitados']} nombres cargados." : '')],
            ['Comparte la invitación',
             'Te enviamos el enlace de la invitación digital para que la mandes por WhatsApp. Se abre en el celular, con música y la historia de la fiesta. Cada invitado puede confirmar desde ahí.'],
        ]],

        ['titulo' => 'Qué necesitamos el día del evento', 'tipo' => 'lista', 'cuerpo' => [
            'Un enchufe cerca de donde va la cabina: la impresora de fotos funciona con corriente.',
            'Una mesa o superficie firme para la tablet, la impresora y los carteles con los códigos QR.',
            'Conexión a internet: WiFi de la casa o datos. Los juegos y la galería la necesitan.',
            'Un rincón con buena luz para la cabina de fotos, con espacio para que los niños se muevan.',
        ]],

        ['titulo' => 'Cómo funciona, paso a paso', 'tipo' => 'pasos', 'cuerpo' => [
            ['Llegamos y armamos', "$llegamos Necesitamos unos 15 minutos para instalar la cabina y probar todo."],
            ['Cada niño pasa por la cabina', "El niño toca la pantalla, elige o le toca uno de los personajes de $t, ve su video de saludo, juega un momento con él y se toma la foto."],
            ['La foto sale con la temática', "La foto se compone al instante con el marco y el personaje de $t, y queda guardada en la galería de la fiesta."],
            ['Se llevan su recuerdo', 'Cada invitado recibe su diploma en pantalla y su foto impresa con imán para el refrigerador, lista para llevarse a casa.'],
        ]],

        ['titulo' => 'Los juegos 3D', 'tipo' => 'texto', 'cuerpo' => trim(
            "Desde el cartel con el código QR o desde la tablet se abre el menú de juegos de la fiesta. $juegosTexto "
            . "Antes de jugar, cada niño toca su nombre en la lista para que su puntaje quede registrado.\n\n"
            . "Los puntajes se juntan en la tabla de Posiciones, con medallas de oro, plata y bronce por juego y un podio general de la fiesta. "
            . "Nadie queda eliminado: todos juegan cuantas veces quieran y cuenta su mejor intento.\n\n"
            . "Los juegos se ven mucho mejor con la tablet o el celular apaisados. Al abrirlos aparece el aviso de girar el aparato."
        )],

        ['titulo' => 'El Álbum Recuerdo', 'tipo' => 'texto', 'cuerpo' =>
            "Los invitados también pueden subir sus propias fotos y videos de la fiesta desde el celular, escaneando el cartel del Álbum. "
            . "Todo queda junto con las fotos de la cabina, en un solo lugar, para que después de la fiesta tengas el recuerdo completo y no solo lo que alcanzaste a sacar tú."],

        ['titulo' => 'Las fotos, después de la fiesta', 'tipo' => 'texto', 'cuerpo' =>
            "Todas las fotos quedan en la galería privada de la fiesta, protegida con el PIN {$d['pin']}. "
            . "Desde ahí se ven, se descargan en alta calidad y se comparten con quien quieras. El enlace está más abajo y también en el cartel de la galería."],

        ['titulo' => 'Tus enlaces', 'tipo' => 'tabla', 'cuerpo' => $enlaces],

        ['titulo' => 'Preguntas frecuentes', 'tipo' => 'faq', 'cuerpo' => [
            ['¿Tengo que instalar algo?', 'No. Todo funciona desde el navegador del celular o la tablet, sin descargar aplicaciones.'],
            ['¿Los invitados necesitan una cuenta?', 'No. Con escanear el código QR del cartel entran directo.'],
            ['¿Qué pasa si se corta el internet?', 'La cabina de fotos sigue funcionando. Los juegos y el álbum se retoman apenas vuelve la conexión.'],
            ['¿Puedo pedir más fotos impresas?', 'Sí. Cada invitado recibe una; las adicionales se pueden pedir el mismo día de la fiesta.'],
            ['¿Cuánto tiempo quedan las fotos disponibles?', 'La galería queda abierta después de la fiesta para que descargues todo con calma. Te avisamos antes de cerrarla.'],
        ]],
    ];
}

// ── Página ────────────────────────────────────────────────────────────────

function cb_manual_html(array $d): string
{
    $h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $vars = cb_theme_css_vars($d['tema']);
    $banner = $d['banner'] ? 'themes/' . rawurlencode($d['tema']) . '/correo-cabecera.jpg' : '';

    $cuerpo = '';
    foreach (cb_manual_secciones($d) as $s) {
        $cuerpo .= '<section class="bloque"><h2>' . $h($s['titulo']) . '</h2>';
        switch ($s['tipo']) {
            case 'tabla':
                $cuerpo .= '<table class="datos">';
                foreach ($s['cuerpo'] as [$k, $v]) {
                    $esUrl = preg_match('#^https?://\S+#', $v, $m) === 1;
                    $valor = $esUrl
                        ? '<a href="' . $h($m[0]) . '">' . $h($m[0]) . '</a>' . $h(substr($v, strlen($m[0])))
                        : $h($v);
                    $cuerpo .= '<tr><th>' . $h($k) . '</th><td>' . $valor . '</td></tr>';
                }
                $cuerpo .= '</table>';
                break;
            case 'pasos':
                $cuerpo .= '<ol class="pasos">';
                foreach ($s['cuerpo'] as [$tt, $tx]) {
                    $cuerpo .= '<li><b>' . $h($tt) . '</b><span>' . $h($tx) . '</span></li>';
                }
                $cuerpo .= '</ol>';
                break;
            case 'lista':
                $cuerpo .= '<ul class="lista">';
                foreach ($s['cuerpo'] as $li) { $cuerpo .= '<li>' . $h($li) . '</li>'; }
                $cuerpo .= '</ul>';
                break;
            case 'faq':
                $cuerpo .= '<dl class="faq">';
                foreach ($s['cuerpo'] as [$q, $a]) { $cuerpo .= '<dt>' . $h($q) . '</dt><dd>' . $h($a) . '</dd>'; }
                $cuerpo .= '</dl>';
                break;
            default:
                foreach (preg_split('/\n{2,}/', (string) $s['cuerpo']) as $p) {
                    $cuerpo .= '<p>' . $h($p) . '</p>';
                }
        }
        $cuerpo .= '</section>';
    }

    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>Manual de la fiesta de ' . $h($d['nombre']) . ' · CumpleClick</title>'
        . '<style>' . $vars . '
:root{color-scheme:light only}
*{box-sizing:border-box}
body{margin:0;background:#f6f3fb;color:#221436;font-family:"Segoe UI",Roboto,Helvetica,Arial,sans-serif;line-height:1.55}
.hoja{max-width:820px;margin:0 auto;background:#fff}
/* Cabecera al estilo de los carteles: la foto de la temática a todo el ancho, un velo que se
   cierra hacia abajo, y encima los dos logos y el título en blanco. El velo es lo que hace
   legible el texto sin tapar a los personajes, que es la gracia del "marco de agua". */
/* Franja horizontal con la temática, como los carteles: la foto a todo el ancho y un velo
   que se cierra hacia abajo. Los logos NO van acá sino en el pie; arriba manda la temática,
   que es lo que el papá reconoce como la fiesta de su hijo. `aspect-ratio` mantiene la franja
   apaisada en cualquier pantalla, que es la misma proporción del PDF. */
.cab{position:relative;aspect-ratio:3/1;min-height:170px;display:flex;flex-direction:column;justify-content:flex-end;padding:24px 34px 20px;overflow:hidden;background:#132a4a}
.cab .hero{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center 30%}
.cab .velo{position:absolute;inset:0;background:linear-gradient(180deg,rgba(12,6,24,.20) 0%,rgba(12,6,24,.26) 45%,rgba(12,6,24,.80) 100%)}
.cab>*{position:relative}
.cab .sello{font-size:.72rem;letter-spacing:.18em;text-transform:uppercase;color:#fff;font-weight:700;margin:0 0 6px;opacity:.85;text-shadow:0 1px 6px rgba(0,0,0,.6)}
h1{margin:0;font-size:2rem;line-height:1.15;color:#fff;text-shadow:0 2px 10px rgba(0,0,0,.75),0 0 3px rgba(0,0,0,.5)}
.cab p{margin:6px 0 0;color:#eae4fb;text-shadow:0 1px 8px rgba(0,0,0,.7)}
@media (max-width:560px){.cab{padding:20px 20px 16px}h1{font-size:1.5rem}}
.bloque{padding:10px 34px 4px}
h2{font-size:1.18rem;margin:18px 0 8px;padding-bottom:6px;border-bottom:2px solid #e9d8fd}
p{margin:0 0 10px}
table.datos{width:100%;border-collapse:collapse;font-size:.95rem}
table.datos th,table.datos td{text-align:left;vertical-align:top;padding:7px 10px;border-bottom:1px solid #eee7f7}
table.datos th{width:34%;color:#4c2882;font-weight:700;background:#f8f5fd}
table.datos a{color:#6d3fd4;word-break:break-all}
ol.pasos{list-style:none;padding:0;margin:0;counter-reset:p}
ol.pasos li{position:relative;padding:0 0 12px 40px;counter-increment:p}
ol.pasos li::before{content:counter(p);position:absolute;left:0;top:1px;width:28px;height:28px;border-radius:50%;background:#8b5cf6;color:#fff;font-weight:800;display:grid;place-items:center;font-size:.9rem}
ol.pasos b{display:block;margin-bottom:2px}
ul.lista{margin:0;padding-left:20px}
ul.lista li{margin:0 0 6px}
dl.faq dt{font-weight:700;margin:10px 0 2px}
dl.faq dd{margin:0 0 6px;color:#3b3355}
/* Pie con los dos logos: cierran el documento como una firma. Uno a cada lado, alineados
   por su base, con el contacto debajo. */
.pie{padding:22px 34px 30px;color:#6e6786;font-size:.85rem;border-top:1px solid #eee7f7;margin-top:14px}
.pie__logos{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;flex-wrap:wrap;margin:0 0 14px}
.pie__logos .cc{max-width:170px;width:100%;height:auto;flex:0 1 auto}
.pie__logos .at{max-width:130px;width:100%;height:auto;flex:0 1 auto}
.pie p{margin:0 0 4px}
.pie .chico{font-size:.78rem;opacity:.8}
/* La cuenta de Instagram con su ícono, como en el correo y en el PDF. */
.pie .ig{display:inline-flex;align-items:center;gap:6px;color:#d6307f;text-decoration:none;font-weight:600}
.pie .ig:hover{text-decoration:underline}
.pie .ig svg{width:16px;height:16px;flex:none}
.acciones{padding:14px 34px 0;display:flex;gap:10px;flex-wrap:wrap}
.btn{display:inline-block;padding:11px 18px;border-radius:999px;background:#7c3aed;color:#fff;text-decoration:none;font-weight:700}
.btn.claro{background:#efe8fb;color:#4c2882}
@media print{body{background:#fff}.acciones{display:none}.hoja{max-width:none}.bloque{break-inside:avoid}}
@page{size:A4;margin:12mm}
</style></head><body><div class="hoja">'
        . '<div class="cab">'
        . ($banner !== '' ? '<img class="hero" src="' . $h($banner) . '" alt=""><span class="velo"></span>' : '')
        . '<p class="sello">Manual de la fiesta</p>'
        . '<h1>La fiesta de ' . $h($d['nombre']) . ($d['edad'] ? ' · ' . (int) $d['edad'] . ' años' : '') . '</h1>'
        . '<p>' . $h($d['tema_nombre']) . ($d['fecha'] !== '' ? ' · ' . $h($d['fecha_larga']) : '') . '</p></div>'
        . '<div class="acciones"><a class="btn" href="' . $h($d['enlaces']['manual_pdf']) . '">Descargar en PDF</a>'
        . '<a class="btn claro" href="javascript:window.print()">Imprimir</a></div>'
        . $cuerpo
        . '<div class="pie">'
        . '<div class="pie__logos">'
        . '<img class="cc" src="brand/cumpleclick-lockup.svg" alt="CumpleClick">'
        // El mismo archivo que usan el pie del PDF y el comprobante. `logo-automatizatech.png`
        // es la versión sobre fondo oscuro: en el pie blanco de la página se veía como un
        // recuadro negro, distinto del logo que sale en los documentos.
        . '<img class="at" src="brand/pdf-automatizatech.jpg" alt="AutomatizaTech">'
        . '</div>'
        . '<p>WhatsApp ' . $h($d['whatsapp']) . ' · ' . $h($d['correo']) . '</p>'
        . '<p><a class="ig" href="' . $h($d['instagram_url']) . '" target="_blank" rel="noopener">'
        . cb_manual_icono_instagram() . $h($d['instagram']) . '</a></p>'
        . '<p class="chico">CumpleClick es un servicio de AUTOMATIZATECH SpA</p>'
        . '</div></div></body></html>';
}

// ── PDF ───────────────────────────────────────────────────────────────────

function cb_manual_pdf(array $d): string
{
    $doc = new CcDocumento();
    $doc->pie('CumpleClick · Manual de la fiesta de ' . $d['nombre']);

    // La cabecera lleva la foto de la temática con los dos logos y el velo ya aplicados; el
    // título va encima en texto vectorial, para que se lea nítido y no quemado en el JPEG.
    $cabecera = cb_manual_cabecera_compuesta($d['banner']);
    $altoCab = 0.0;
    if ($cabecera !== null) {
        $altoCab = $doc->banner($cabecera);
        @unlink($cabecera);   // el motor ya copió los bytes al documento
    } elseif ($d['banner']) {
        $altoCab = $doc->banner($d['banner']);
    }

    $titulo = 'La fiesta de ' . $d['nombre'] . ($d['edad'] ? ' · ' . $d['edad'] . ' años' : '');
    $bajada = $d['tema_nombre'] . ($d['fecha'] !== '' ? ' · ' . $d['fecha_larga'] : '');
    if ($altoCab > 0) {
        // Sobre la parte oscura de la cabecera, en blanco.
        $doc->sobreBanner($titulo, $altoCab, 19.0, 19.0);
        $doc->sobreBanner($bajada, $altoCab, 7.0, 10.5, false, [235, 230, 250]);
    } else {
        $doc->titulo($titulo);
        $doc->texto($bajada, 11.0, false, [110, 103, 134]);
    }

    foreach (cb_manual_secciones($d) as $s) {
        $doc->subtitulo($s['titulo']);
        switch ($s['tipo']) {
            case 'tabla': $doc->tabla($s['cuerpo']); break;
            case 'pasos': $doc->pasos($s['cuerpo']); break;
            case 'lista': $doc->lista($s['cuerpo']); break;
            case 'faq':
                foreach ($s['cuerpo'] as [$q, $a]) {
                    $doc->texto($q, 10.0, true);
                    $doc->texto($a);
                }
                break;
            default:
                foreach (preg_split('/\n{2,}/', (string) $s['cuerpo']) as $p) { $doc->texto($p); }
        }
    }
    // Pie de cierre con los dos logos. Va una sola vez al final y no en cada hoja: repetirlos
    // en las tres páginas los convierte en ruido, y acá cierran el documento como una firma.
    $doc->cierreConLogos(
        __DIR__ . '/brand/pdf-cumpleclick.jpg',
        __DIR__ . '/brand/pdf-automatizatech.jpg',
        'WhatsApp ' . $d['whatsapp'] . '  ·  ' . $d['correo'],
        'CumpleClick es un servicio de AUTOMATIZATECH SpA',
        instagram: ['texto' => $d['instagram'], 'url' => $d['instagram_url']]
    );
    return $doc->salida();
}

// ── Correo ────────────────────────────────────────────────────────────────

/** Asunto, texto y HTML del correo que lleva el manual. El PDF lo adjunta quien envía. */
function cb_manual_correo(array $d): array
{
    require_once __DIR__ . '/lib.mail-templates.php';
    $nombre = $d['contacto']['nombre'] ?? '';
    $saludo = $nombre !== '' ? 'Hola ' . cc_mail_h(explode(' ', trim($nombre))[0]) : 'Hola';
    $url = $d['enlaces']['manual'];

    $filas = cc_mail_fila('Fiesta', cc_mail_h($d['nombre'] . ' · ' . $d['tema_nombre']));
    if ($d['fecha'] !== '') { $filas .= cc_mail_fila('Fecha', cc_mail_h($d['fecha_larga'])); }
    if ($d['hora'] !== '') { $filas .= cc_mail_fila('Hora', cc_mail_h($d['hora'])); }
    if ($d['lugar'] !== '') { $filas .= cc_mail_fila('Lugar', cc_mail_h($d['lugar'])); }

    $html = '<p style="margin:0 0 16px">' . $saludo . ', te dejamos el manual de la fiesta de <strong>'
        . cc_mail_h($d['nombre']) . '</strong>: qué va a pasar, qué necesitamos el día del evento y tus enlaces. '
        . 'Va adjunto en PDF y también lo puedes abrir desde el botón.</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 18px">' . $filas . '</table>'
        . '<p style="margin:0 0 8px"><a href="' . cc_mail_h($url) . '" style="display:inline-block;background:#7C3AED;color:#ffffff;'
        . 'text-decoration:none;padding:13px 26px;border-radius:999px;font-weight:700;font-size:15px">Ver el manual</a></p>'
        . '<p style="margin:0 0 16px;font-size:13px;color:#6B6280">Guarda este correo: el enlace es de tu fiesta y no vence.</p>'
        . '<p style="margin:0">Cualquier duda, respóndenos por acá o por WhatsApp ' . cc_mail_h($d['whatsapp']) . '.</p>';

    $texto = "$saludo, te dejamos el manual de la fiesta de {$d['nombre']}: qué va a pasar, qué necesitamos el día del evento y tus enlaces.\n\n"
        . "Va adjunto en PDF y también lo puedes abrir aquí:\n$url\n\n"
        . "Cualquier duda, respóndenos por este correo o por WhatsApp {$d['whatsapp']}.\n";

    return [
        'subject' => 'El manual de la fiesta de ' . $d['nombre'] . ' · CumpleClick',
        'text' => $texto,
        'html' => cc_mail_shell('Manual de la fiesta', $html),
        'filename' => 'manual-fiesta-' . preg_replace('/[^a-z0-9-]/', '', strtolower($d['slug'])) . '.pdf',
    ];
}
