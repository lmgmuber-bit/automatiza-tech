<?php
/**
 * invitacion.php — página pública mínima de una invitación publicada.
 * Requiere token opaco vía GET ?t=<token>. Solo muestra outputs aprobados de
 * una invitación en estado `published` y no expirada. No expone IDs internos,
 * rutas físicas, prompts ni ninguna información administrativa.
 */
require __DIR__ . '/lib.php';

function cb_invitation_page_error(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex, nofollow">'
        . '<title>CumpleClick</title></head>'
        . '<body style="font-family:system-ui,sans-serif;text-align:center;padding:3rem;background:#1a1a1a;color:#fff">'
        . '<h1>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h1></body></html>';
    exit;
}

header('X-Robots-Tag: noindex, nofollow');

$token = (string) ($_GET['t'] ?? '');
if (!cb_invitation_public_token_is_valid($token)) {
    cb_invitation_page_error(400, 'Enlace de invitación inválido.');
}

try {
    $invitation = cb_load_invitation_by_public_token($token);
} catch (Throwable $e) {
    error_log('CumpleClick invitacion.php: ' . $e->getMessage());
    cb_invitation_page_error(503, 'Servicio no disponible por el momento.');
}

if (!$invitation) {
    cb_invitation_page_error(404, 'Invitación no encontrada.');
}
if ((string) $invitation['status'] !== 'published') {
    cb_invitation_page_error(404, 'Esta invitación todavía no está disponible.');
}
if (!empty($invitation['expires_at']) && strtotime((string) $invitation['expires_at']) < time()) {
    cb_invitation_page_error(410, 'Este enlace de invitación ya expiró.');
}

$imageOutputs = cb_invitation_approved_outputs((int) $invitation['id'], 'personalized_image');
if (!$imageOutputs) {
    // Publicada sin imagen aprobada no debería ocurrir (cb_publish_invitation lo exige),
    // pero se valida de nuevo acá por defensa en profundidad antes de mostrar nada.
    cb_invitation_page_error(404, 'Esta invitación todavía no está disponible.');
}
$hasVideo = (bool) cb_invitation_approved_outputs((int) $invitation['id'], 'personalized_video');
// Narración de Alice: el INICIO es dinámico (nombre/fecha/lugar de ESTA
// invitación) y se sube aprobado por invitación, como la imagen. El resto
// del audio es texto fijo: se genera una sola vez y se reutiliza siempre.
$narrationIntroOutputs = cb_invitation_approved_outputs((int) $invitation['id'], 'personalized_narration_intro');
$narrationIntroUrl = $narrationIntroOutputs ? cb_invitation_download_url($token, 'narracion_inicio') : '';

$themesData = cb_load_themes();
$themeSlug = (string) ($invitation['theme_slug'] ?? '');

// Música de fondo del kiosco, reutilizada tal cual (mismo archivo, mismo
// volumen base) — no se genera nada nuevo para esto.
$musicUrl = '';
if (preg_match('/^[a-z0-9-]+$/', $themeSlug) && is_file(__DIR__ . '/themes/' . $themeSlug . '/musica-fondo.mp3')) {
    $musicUrl = 'themes/' . rawurlencode($themeSlug) . '/musica-fondo.mp3';
}
// Narración de despedida: texto fijo, igual para cualquier temática, así que
// son archivos compartidos (no por tema, no por invitación) en vez de pedir
// que se generen una y otra vez. Tres variantes: neutra (sin género elegido,
// compatible con invitaciones creadas antes de este campo), niño y niña —
// "cumpleañero"/"cumpleañera" en vez de un texto genérico que no calzaba con
// el botón "Conoce al cumpleañero/a" (pedido de Luis 2026-08-12).
$narrationOutroFile = 'narracion-final.mp3';
$birthdayGender = (string) ($invitation['birthday_person_gender'] ?? '');
if ($birthdayGender === 'm') {
    $narrationOutroFile = 'narracion-final-nino.mp3';
} elseif ($birthdayGender === 'f') {
    $narrationOutroFile = 'narracion-final-nina.mp3';
}
$narrationOutroUrl = '';
$narrationOutroPath = __DIR__ . '/assets/audio/' . $narrationOutroFile;
if (!is_file($narrationOutroPath)) {
    // Variante de género aún no generada: cae a la neutra en vez de quedar mudo.
    $narrationOutroFile = 'narracion-final.mp3';
    $narrationOutroPath = __DIR__ . '/assets/audio/narracion-final.mp3';
}
if (is_file($narrationOutroPath)) {
    // El archivo compartido conserva su nombre, así que la versión evita que
    // una invitación ya abierta conserve en caché una despedida anterior.
    $narrationOutroUrl = 'assets/audio/' . rawurlencode($narrationOutroFile) . '?v=' . rawurlencode((string) filemtime($narrationOutroPath));
}

// Cierre del RECORRIDO de personajes (modo Automática): es un momento
// distinto al de arriba, aunque compartían el mismo audio por error (Luis
// 2026-08-12: "se pisan" no era esto, pero el texto de "conoce al
// cumpleañero/a" quedaba sonando ANTES de que el botón exista en pantalla,
// justo al terminar el último video de personaje — el CTA real recién
// aparece más abajo, en la sección "Guarda y comparte"). Este audio invita a
// seguir bajando; el de arriba (`$narrationOutroUrl`) queda solo para esa
// sección final, disparado por `data-inv-narration-outro-trigger`.
$narrationPlaylistEndUrl = '';
$narrationPlaylistEndPath = __DIR__ . '/assets/audio/narracion-playlist-final.mp3';
if (is_file($narrationPlaylistEndPath)) {
    $narrationPlaylistEndUrl = 'assets/audio/narracion-playlist-final.mp3?v=' . rawurlencode((string) filemtime($narrationPlaylistEndPath));
}

$themeData = is_array($themesData['themes'][$themeSlug] ?? null) ? $themesData['themes'][$themeSlug] : [];
$colors = is_array($themeData['colors'] ?? null) ? $themeData['colors'] : [];
$accent = (string) ($colors['accent'] ?? '#7C3AED');
$yellow = (string) ($colors['yellow'] ?? '#FBBF24');
$dark1 = (string) ($colors['dark1'] ?? '#1a1a1a');
$dark2 = (string) ($colors['dark2'] ?? '#312e81');
$ink = (string) ($colors['ink'] ?? '#1a1a1a');
$bgLight1 = (string) ($colors['bgLight1'] ?? '#fff');
$bgLight2 = (string) ($colors['bgLight2'] ?? '#fff');

$birthdayName = (string) ($invitation['birthday_person_name'] ?? '');
$eventDate = (string) ($invitation['event_date'] ?? '');
$eventTime = (string) ($invitation['event_time'] ?? '');
$address = (string) ($invitation['address'] ?? '');
$message = (string) ($invitation['message'] ?? '');

// Como se nombra el evento en todo lo que lee el invitado. Un baby shower
// no es "el cumpleaños de Valentina": Valentina todavía no nace. Se
// resuelve acá, una sola vez, porque lo consumen el hero, la plantilla, el
// calendario, el .ics, la tarjeta de WhatsApp y el <title>; repetir el
// ternario en cada punto es exactamente como se quedan sin cambiar los que
// nadie recuerda. Las invitaciones de cumpleaños no se mueven: `event_type`
// viene con 'child_birthday' por defecto.
$esBabyShower = (string) ($invitation['event_type'] ?? 'child_birthday') === 'baby_shower';
$eventoNombre = $esBabyShower ? 'baby shower' : 'cumpleaños';
// El artículo va incluido: en español "al baby shower" y "a la fiesta" no
// se componen con una preposición suelta, y separarlos ya produjo un
// "en al baby shower" en el kiosco.
$eventoDeQuien = $birthdayName !== ''
    ? 'al ' . $eventoNombre . ' de ' . $birthdayName
    : ($esBabyShower ? 'a nuestro baby shower' : 'a nuestra fiesta');
// Sin nombre esto decía "Baby shower de la fiesta".
$eventoTitulo = $birthdayName !== ''
    ? ($esBabyShower ? 'Baby shower de ' : 'Cumpleaños de ') . $birthdayName
    : ($esBabyShower ? 'Baby shower' : 'Nuestra fiesta');

/* Los dos datos que un baby shower puede no tener todavía: el nombre y el
   sexo. Las cuatro combinaciones son corrientes —hay familias que hacen la
   fiesta justamente para revelar uno de los dos— así que hay que servirlas
   a las cuatro y no tratar las incompletas como un formulario a medio
   llenar:

     nombre + sexo  ->  "Valentina"      · "conocer a Valentina"
     solo sexo      ->  "Nuestra bebé"   · "conocer a nuestra bebé"
     solo nombre    ->  "Valentina"      · "conocer a Valentina"
     ninguno        ->  "Nuestro bebé"   · "conocer al bebé"

   No hace falta ninguna columna nueva: los dos ya se expresan vacíos. */
$hayNombreBebe = $birthdayName !== '';
$sexoBebe = in_array($birthdayGender, ['m', 'f'], true) ? $birthdayGender : '';
// Cómo se le dice cuando todavía no tiene nombre.
$bebeGenerico = $sexoBebe === 'f' ? 'nuestra bebé' : 'nuestro bebé';
// Lo que va grande en el hero y en la lámina.
$bebeTitulo = $hayNombreBebe
    ? $birthdayName
    // Solo la primera letra: MB_CASE_TITLE capitaliza cada palabra y dejaba
    // "Nuestra Bebé", que se lee como un nombre propio.
    : ($esBabyShower
        ? mb_strtoupper(mb_substr($bebeGenerico, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($bebeGenerico, 1, null, 'UTF-8')
        : '');
// "conocer __": el complemento entero, con preposición, porque "conocer a
// el bebé" y "conocer la" no se componen pegando partes sueltas.
$conocerAlBebe = $hayNombreBebe
    ? 'a ' . $birthdayName
    : ($sexoBebe !== '' ? 'a ' . $bebeGenerico : 'al bebé');
$eventoSinNombre = $esBabyShower ? $bebeTitulo : 'Nuestra fiesta';

/* La lista de regalos. Solo baby shower: en un cumpleaños infantil las
   ideas de regalo viven dentro de la ficha del protagonista y no se
   reservan. Falla cerrado — si la tabla no existe todavía (la migración
   010 no está aplicada en producción) la sección simplemente no aparece,
   en vez de tumbar la invitación entera. */
$regalos = ['items' => [], 'total' => 0, 'tomados' => 0];
if ($esBabyShower && function_exists('cb_gift_list_public')) {
    try {
        $regalos = cb_gift_list_public((int) $invitation['id']);
    } catch (Throwable $e) {
        error_log('CumpleClick lista de regalos: ' . $e->getMessage());
    }
}
// El gancho nombra el problema del invitado, no la sección. Con el nombre
// del bebé cuando existe; sin él sigue funcionando, que es el caso de las
// familias que todavía no lo eligieron.
$regalosGancho = $hayNombreBebe
    ? '¿No sabes qué regalarle a ' . $birthdayName . '?'
    : '¿No sabes qué regalarle?';
$eventoArchivo = ($esBabyShower ? 'baby-shower-' : 'cumpleanos-')
    . ($birthdayName !== '' ? $birthdayName : 'cumpleclick');

// El perfil es estrictamente opcional. El helper ya entrega solo contenido
// visible de un perfil activo asociado al mismo evento de esta invitacion.
$eventProfile = null;
if (function_exists('cb_event_profile_public_for_invitation')) {
    try {
        $candidateProfile = cb_event_profile_public_for_invitation($invitation, $token);
        if (is_array($candidateProfile) && !empty($candidateProfile['has_public_content'])) {
            $eventProfile = $candidateProfile;
        }
    } catch (Throwable $e) {
        // El modulo opcional falla cerrado y nunca bloquea la invitacion actual.
        error_log('CumpleClick invitacion perfil publico: ' . $e->getMessage());
    }
}

$profilePeople = [];
$profileSections = [];
$profileTitle = '';
$profileCta = '';
$profileIntroPhrase = '';
$profileVideoUrl = '';
$profilePosterUrl = '';
$profileThemeStyle = '';
$profileLayout = 'event-profile';
$profileIntroStyle = '';
if ($eventProfile !== null) {
    $profilePeople = is_array($eventProfile['featured_people'] ?? null)
        ? $eventProfile['featured_people']
        : (is_array($eventProfile['people'] ?? null) ? $eventProfile['people'] : []);
    $profilePeople = array_values(array_filter($profilePeople, static function ($person): bool {
        return is_array($person) && trim((string) ($person['display_name'] ?? $person['name'] ?? '')) !== '';
    }));
    usort($profilePeople, static function (array $a, array $b): int {
        return ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
    });

    $profileSections = is_array($eventProfile['sections'] ?? null) ? array_values($eventProfile['sections']) : [];
    $profileSections = array_values(array_filter($profileSections, static function ($section): bool {
        return is_array($section) && trim((string) ($section['key'] ?? '')) !== '';
    }));
    usort($profileSections, static function (array $a, array $b): int {
        return ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
    });

    $profileTitle = trim((string) ($eventProfile['public_title'] ?? $eventProfile['title'] ?? ''));
    $profileCta = trim((string) ($eventProfile['cta_label'] ?? $eventProfile['public_cta'] ?? ''));
    $profileIntroPhrase = trim((string) ($eventProfile['intro_phrase'] ?? ''));
    $profilePreset = is_array($eventProfile['preset'] ?? null) ? $eventProfile['preset'] : [];
    $profileLayout = trim((string) ($eventProfile['layout'] ?? $profilePreset['layout'] ?? 'event-profile'));
    $profileIntroStyle = trim((string) ($eventProfile['intro_style'] ?? $profilePreset['intro_style'] ?? ''));
    if ($profileLayout === '') {
        $profileLayout = 'event-profile';
    }
    if ($profileTitle === '') {
        $profileTitle = 'Conoce a los protagonistas';
    }
    if ($profileCta === '') {
        $profileCta = $profileTitle;
    }

    $profileMediaUrl = static function ($media) use ($token): string {
        if (!is_array($media) || !$media) {
            return '';
        }
        // El shape público ya trae la URL firmada resuelta. Antes se ignoraba y
        // se llamaba a cb_event_profile_media_url(), que exige `access_token`
        // mientras que el shape expone esa clave como `token`: la excepción se
        // tragaba en el catch y la foto del protagonista NUNCA se mostraba,
        // igual que habría pasado con el video y el póster de intro.
        $direct = trim((string) ($media['url'] ?? ''));
        if ($direct !== '') {
            return $direct;
        }
        if (!function_exists('cb_event_profile_media_url')) {
            return '';
        }
        try {
            return (string) cb_event_profile_media_url($token, $media);
        } catch (Throwable $e) {
            error_log('CumpleClick invitacion URL media de perfil: ' . $e->getMessage());
            return '';
        }
    };

    $introMedia = is_array($eventProfile['intro_media'] ?? null) ? $eventProfile['intro_media'] : [];
    $profileVideoUrl = $profileMediaUrl($introMedia['video'] ?? null);
    $profilePosterUrl = $profileMediaUrl($introMedia['poster'] ?? null);

    $profileThemeStyle = cb_theme_css_vars($themeSlug);
    if (preg_match('/^[a-z0-9-]+$/', $themeSlug)) {
        $bannerPath = __DIR__ . '/themes/' . $themeSlug . '/fondo-banner.jpg';
        if (is_file($bannerPath)) {
            // La variable se consume dentro de assets/event-profile.css; los URL
            // relativos de custom properties se resuelven contra ese stylesheet.
            $profileThemeStyle .= '--ep-background:url("../themes/' . rawurlencode($themeSlug) . '/fondo-banner.jpg");';
        }
    }

    // Tokens de legibilidad por temática. El fondo de la invitación es una
    // ilustración clara y con personajes, así que la ficha necesita su propio
    // velo y superficies opacas; cada preset ajusta la intensidad sin CSS nuevo.
    $themeSurface = is_array($profilePreset['theme']['surface'] ?? null)
        ? $profilePreset['theme']['surface']
        : [];
    $clampNum = static function ($value, float $min, float $max, float $fallback): float {
        $number = is_numeric($value) ? (float) $value : $fallback;
        return max($min, min($max, $number));
    };
    $profileThemeStyle .= '--ep-scrim:' . $clampNum($themeSurface['scrim'] ?? null, 0.4, 0.98, 0.9) . ';';
    $profileThemeStyle .= '--ep-bg-blur:' . $clampNum($themeSurface['blur'] ?? null, 0, 40, 20) . 'px;';
    $profileThemeStyle .= '--ep-bg-saturate:' . $clampNum($themeSurface['saturate'] ?? null, 0, 1.5, 0.6) . ';';
    $profileThemeStyle .= '--ep-surface-mix:' . $clampNum($themeSurface['surface_mix'] ?? null, 50, 96, 88) . '%;';
    $surfaceTitle = (string) ($themeSurface['title'] ?? '');
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $surfaceTitle)) {
        $profileThemeStyle .= '--ep-title:' . $surfaceTitle . ';';
    }

    $presetsRoot = function_exists('cb_event_profile_presets') ? cb_event_profile_presets() : [];
    $sectionAccents = is_array($presetsRoot['section_accents'] ?? null) ? $presetsRoot['section_accents'] : [];
    $personAccents = is_array($presetsRoot['person_accents'] ?? null)
        ? array_values(array_filter($presetsRoot['person_accents'], static function ($tone): bool {
            return is_string($tone) && preg_match('/^#[0-9a-fA-F]{6}$/', $tone) === 1;
        }))
        : [];

    // La paleta se ordena por distancia de tono respecto al acento del tema:
    // en K-Pop (fucsia) el segundo protagonista no puede salir también rosa.
    // Es determinista y depende del tema, nunca de quién sea la persona.
    $hueOf = static function (string $hex): float {
        $r = hexdec(substr($hex, 1, 2)) / 255;
        $g = hexdec(substr($hex, 3, 2)) / 255;
        $b = hexdec(substr($hex, 5, 2)) / 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;
        if ($delta <= 0.0) {
            return 0.0;
        }
        if ($max === $r) {
            $hue = fmod((($g - $b) / $delta), 6.0);
        } elseif ($max === $g) {
            $hue = (($b - $r) / $delta) + 2.0;
        } else {
            $hue = (($r - $g) / $delta) + 4.0;
        }
        $hue *= 60.0;
        return $hue < 0 ? $hue + 360.0 : $hue;
    };
    if ($personAccents && preg_match('/^#[0-9a-fA-F]{6}$/', $accent) === 1) {
        $themeHue = $hueOf($accent);
        usort($personAccents, static function (string $a, string $b) use ($hueOf, $themeHue): int {
            $distance = static function (float $hue) use ($themeHue): float {
                $diff = abs($hue - $themeHue);
                return $diff > 180.0 ? 360.0 - $diff : $diff;
            };
            return $distance($hueOf($b)) <=> $distance($hueOf($a));
        });
    }

    // Un perfil sin protagonista renderizable no agrega controles a la pagina.
    if (!$profilePeople) {
        $eventProfile = null;
    }
}


$imageUrl = cb_invitation_download_url($token, 'image');
$videoUrl = $hasVideo ? cb_invitation_download_url($token, 'video') : null;

$esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

// Intro cinematográfico opcional por temática. La convención evita amarrar la
// invitación a una lista fija: cualquier tema presente o futuro puede activarlo
// agregando el mismo par de archivos dentro de su carpeta `invitation/`.
// Si el MP4 no existe, el flujo del sobre permanece exactamente como antes.
$themeIntroUrl = '';
$themeIntroPosterUrl = '';
if (preg_match('/^[a-z0-9-]+$/', $themeSlug)) {
    $themeIntroRelative = 'invitation/intro-invitacion-wow-v1.mp4';
    $themeIntroPath = __DIR__ . '/themes/' . $themeSlug . '/' . $themeIntroRelative;
    if (is_file($themeIntroPath)) {
        $themeIntroUrl = 'themes/' . rawurlencode($themeSlug) . '/' . $themeIntroRelative . '?v=' . rawurlencode((string) filemtime($themeIntroPath));
        $themeIntroPosterRelative = 'invitation/intro-invitacion-wow-v1-poster.jpg';
        $themeIntroPosterPath = __DIR__ . '/themes/' . $themeSlug . '/' . $themeIntroPosterRelative;
        if (is_file($themeIntroPosterPath)) {
            $themeIntroPosterUrl = 'themes/' . rawurlencode($themeSlug) . '/' . $themeIntroPosterRelative . '?v=' . rawurlencode((string) filemtime($themeIntroPosterPath));
        }
    }
}

// Fecha legible sin depender del locale del servidor, que en Hostinger y en
// Windows no coinciden y dejaban la fecha en inglés o en formato ISO.
$formatEventDate = static function (string $isoDate): array {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($isoDate), $parts)) {
        return ['long' => trim($isoDate), 'day' => '', 'month' => ''];
    }
    $timestamp = mktime(12, 0, 0, (int) $parts[2], (int) $parts[3], (int) $parts[1]);
    if ($timestamp === false) {
        return ['long' => trim($isoDate), 'day' => '', 'month' => ''];
    }
    $weekdays = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
        'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $weekday = $weekdays[(int) date('w', $timestamp)] ?? '';
    $month = $months[((int) $parts[2]) - 1] ?? '';
    $day = (string) ((int) $parts[3]);
    return [
        'long' => trim($weekday . ' ' . $day . ' de ' . $month),
        'day' => $day,
        'month' => $month,
    ];
};
$dateParts = $eventDate !== '' ? $formatEventDate($eventDate) : ['long' => '', 'day' => '', 'month' => ''];

// Cuenta regresiva, solo para baby shower. Un baby shower ES una espera:
// "faltan 39 días para conocer a Valentina" es lo primero que el invitado
// busca, y es un dato que ninguna invitación de cumpleaños necesita.
//
// La zona horaria va explícita. PROD corre en UTC, así que a las 21:00 de
// un martes en Santiago el servidor ya cree que es miércoles y el conteo
// saldría corrido un día entero — el mismo error que se corrigió en el
// tablero de predicciones.
// El bloque del hero son DOS contadores. `$cuentaNumero`/`$cuentaTexto`
// cuentan lo que sí se sabe —los días hasta el baby shower— y son el dato
// que el invitado necesita. `$incognitaTexto` es el otro: ocupa el mismo
// lugar donde iría un número y no lleva ninguno, porque cuántos días
// faltan para conocer al bebé no lo sabe nadie. Ahí el signo no es
// adorno, es el dato.
//
// `$cuentaRegresiva` es la versión de una línea para la lámina dentro del
// marco, que no tiene espacio para dos numerales sin invadir el paspartú.
$cuentaNumero = '';
$cuentaTexto = '';
$incognitaTexto = '';
$cuentaRegresiva = '';
if ($esBabyShower && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $eventDate)) {
    try {
        $zonaChile = new DateTimeZone('America/Santiago');
        $hoy = new DateTimeImmutable('today', $zonaChile);
        $dia = new DateTimeImmutable($eventDate, $zonaChile);
        $faltan = (int) $hoy->diff($dia)->format('%r%a');
        if ($faltan > 0) {
            $cuentaNumero = (string) $faltan;
            $cuentaTexto = $faltan === 1 ? 'día para el baby shower' : 'días para el baby shower';
            // Dentro del marco NO se repite el nombre: está en la línea de
            // arriba, y repetirlo agrega una segunda línea que se desborda
            // por el borde de madera.
            $cuentaRegresiva = $faltan === 1 ? 'Falta 1 día' : 'Faltan ' . $faltan . ' días';
        } elseif ($faltan === 0) {
            $cuentaTexto = '¡El baby shower es hoy!';
            $cuentaRegresiva = '¡Es hoy!';
        }
        // El segundo contador va siempre que la fecha no haya pasado, con
        // número o sin él: la pregunta sigue abierta igual.
        if ($faltan >= 0) {
            $incognitaTexto = 'días para conocer ' . $conocerAlBebe;
        }
        // Fecha pasada: no se muestra nada. Un "faltan -12 días" en la
        // invitación que la familia guarda de recuerdo es peor que el vacío.
    } catch (Throwable $e) {
        error_log('CumpleClick cuenta regresiva: ' . $e->getMessage());
    }
}
// Cada temática puede traer su propia entrada. Las dos de baby shower
// hablan de recibir al bebé, no de celebrar un cumpleaños que aún no pasa.
if ($themeSlug === 'hielo') {
    $heroKicker = 'El Reino de Hielo te invita a celebrar a:';
} elseif ($themeSlug === 'baby-nube') {
    $heroKicker = 'Entre nubes y estrellas, te esperamos para recibir a:';
} elseif ($themeSlug === 'baby-safari') {
    $heroKicker = 'La manada se prepara para recibir a:';
} elseif ($esBabyShower) {
    $heroKicker = 'Tenemos el agrado de invitarte al baby shower de:';
} else {
    $heroKicker = 'Tenemos el agrado de invitarte a celebrar el cumpleaños de:';
}

// Fondo en movimiento opcional por temática (contrato §5.1: 720x1280, sin
// audio, loopeable). Si no existe, el hero cae al fondo estático del tema.
$heroVideoUrl = '';
$heroImageUrl = '';
$heroScrubUrl = '';
if (preg_match('/^[a-z0-9-]+$/', $themeSlug)) {
    // Video de "entrada": avanza con el scroll en vez de reproducirse solo.
    // Va codificado con un keyframe por cuadro para que el salto sea inmediato.
    $scrubPath = __DIR__ . '/themes/' . $themeSlug . '/invitation/invitation-scroll-v1.mp4';
    if (is_file($scrubPath)) {
        $heroScrubUrl = 'themes/' . rawurlencode($themeSlug) . '/invitation/invitation-scroll-v1.mp4';
    }
    $motionPath = __DIR__ . '/themes/' . $themeSlug . '/invitation/invitation-motion-v1.mp4';
    if (is_file($motionPath)) {
        $heroVideoUrl = 'themes/' . rawurlencode($themeSlug) . '/invitation/invitation-motion-v1.mp4';
    }
    // El poster es lo que se ve antes de arrancar el scroll, y Luis pidió
    // explícitamente que sea "la imagen de la invitación" (la plantilla con
    // el elenco, los trofeos y los regalos) en vez de un cuadro del video: es
    // reconocible de inmediato, mientras que un frame del video puede quedar
    // ambiguo detrás del velo oscuro del primer instante. Cae al poster
    // propio del scroll si la plantilla no existe, y a `fondo-banner.jpg`
    // como último recurso.
    $baseImagePosterPath = __DIR__ . '/themes/' . $themeSlug . '/invitation/invitation-base-v1.jpg';
    $scrubPosterPath = __DIR__ . '/themes/' . $themeSlug . '/invitation/invitation-scroll-poster.jpg';
    if (is_file($baseImagePosterPath)) {
        $heroImageUrl = 'themes/' . rawurlencode($themeSlug) . '/invitation/invitation-base-v1.jpg';
    } elseif ($heroScrubUrl !== '' && is_file($scrubPosterPath)) {
        $heroImageUrl = 'themes/' . rawurlencode($themeSlug) . '/invitation/invitation-scroll-poster.jpg';
    } else {
        $bannerPath = __DIR__ . '/themes/' . $themeSlug . '/fondo-banner.jpg';
        if (is_file($bannerPath)) {
            $heroImageUrl = 'themes/' . rawurlencode($themeSlug) . '/fondo-banner.jpg';
        }
    }
}

// Playlist automática: usa exclusivamente videos ya existentes del kiosco.
// El orden es explícito para que cada temática cuente su propia llegada a la
// fiesta y no herede la historia ni los personajes de otra. El nombre de la
// cumpleañera sigue siendo dinámico y vive en HTML, nunca en estos assets.
$hieloCelebrant = $birthdayName !== '' ? $birthdayName : 'nuestra cumpleañera';
// Neutro a propósito: los temas nuevos sirven a niños y niñas y la invitación
// no siempre trae el género cargado. "quien cumple años" no obliga a elegir.
$celebrante = $birthdayName !== '' ? $birthdayName : 'quien cumple años';
// Nombre del bebé para los capítulos. `$celebrante` no sirve acá: su
// respaldo es "quien cumple años", y en un baby shower nadie los cumple.
/* El capítulo del nombre tiene dos versiones y el video es el MISMO en las
   dos —un gorrito de lana— porque lo que cambia es lo que dice Alice, no lo
   que se ve. Que no haya nombre no es un hueco que disimular: es la otra
   mitad de la misma idea, y se dice en voz alta. */
$capituloNombre = $hayNombreBebe
    ? [
        'caption' => 'El nombre de ' . $birthdayName . ' ya se dice en voz alta',
        'narracion' => 'capitulo-4-su-nombre',
    ]
    : [
        'caption' => 'Todavía no tiene nombre, y ya tiene quien lo espere',
        'narracion' => 'capitulo-4-sin-nombre',
    ];
$playlistOrdersByTheme = [
    /* Baby shower: el recorrido NO son personajes saludando, porque no hay
       personajes a quienes hacer saludar.

       La primera versión contaba la sala preparándose —los globos, la cuna,
       las hojas— y Luis lo corrigió: eso es la decoración, no lo que importa.
       Un baby shower trata de la espera, de lo que significa traer un hijo al
       mundo y del nacimiento que se está esperando. Los capítulos cuentan eso.

       El arco es el mismo en las dos temáticas a propósito: lo que se cuenta
       es del bebé, no del decorado, y el decorado es lo único que cambia. Por
       eso los textos se repiten y los archivos no.

       Los MP4 todavía no existen. Cada capítulo pasa por is_file(), así que
       esto queda inerte hasta que llegue el primero, y después van entrando
       de a uno sin tocar código. */
    'baby-nube' => [
        'invitation/capitulo-1-la-espera.mp4' => 'Hay esperas que se sienten distintas',
        'invitation/capitulo-2-antes-de-nacer.mp4' => 'Todo empieza mucho antes de nacer',
        'invitation/capitulo-3-manos-que-esperan.mp4' => 'Manos que ya aprendieron a esperar',
        'invitation/capitulo-4-su-nombre.mp4' => $capituloNombre,
        'invitation/capitulo-5-el-mundo-se-acomoda.mp4' => 'El mundo se acomoda para recibirte',
        'despedida-baby-nube.mp4' => 'Ven a esperar con nosotros',
    ],
    'baby-safari' => [
        'invitation/capitulo-1-la-espera.mp4' => 'Hay esperas que se sienten distintas',
        'invitation/capitulo-2-antes-de-nacer.mp4' => 'Todo empieza mucho antes de nacer',
        'invitation/capitulo-3-manos-que-esperan.mp4' => 'Manos que ya aprendieron a esperar',
        'invitation/capitulo-4-su-nombre.mp4' => $capituloNombre,
        'invitation/capitulo-5-el-mundo-se-acomoda.mp4' => 'El mundo se acomoda para recibirte',
        'despedida-baby-safari.mp4' => 'Ven a esperar con nosotros',
    ],
    'carreras' => [
        'saludo-mate.mp4' => 'Mate llega primero',
        'saludo-sally.mp4' => 'Sally viene en camino',
        'saludo-cruz.mp4' => 'Cruz calienta motores',
        'saludo-luigi.mp4' => 'Luigi no se queda atrás',
        'saludo-el-rey.mp4' => 'El Rey no se lo pierde',
        'rayo-mcqueen-estrella.mp4' => 'Rayo se prepara',
        'saludo-rayo-mcqueen-v3.mp4' => 'Rayo cruza la meta',
        'despedida-carreras.mp4' => '¡Te esperamos!',
    ],
    // 2026-08-23: Luis aprobó promover las tomas de Higgsfield a definitivas.
    // Antes esta lista apuntaba a los saludos originales de la raíz del tema
    // (saludo-elsa.mp4 y compañía) y las nuevas vivían aisladas detrás de
    // `?capitulos=candidatos` + firma QA. Los archivos antiguos siguen en su
    // sitio y $playlistCandidateOrdersByTheme sigue existiendo, así que volver
    // atrás es cambiar estas seis rutas y nada más.
    'hielo' => [
        'invitation/candidates/saludo-elsa-v2.mp4' => 'La magia de ' . $hieloCelebrant . ' se enciende',
        'invitation/candidates/saludo-anna-v3.mp4' => 'La celebración de ' . $hieloCelebrant . ' ya está lista',
        'invitation/candidates/saludo-olaf-v2.mp4' => 'Una sorpresa nevada viene en camino',
        'invitation/candidates/saludo-kristoff-v2.mp4' => 'Todos llegan para celebrar a ' . $hieloCelebrant,
        'invitation/candidates/saludo-sven-v3.mp4' => 'La aventura de ' . $hieloCelebrant . ' está por comenzar',
        'invitation/candidates/saludo-bruni-v3.mp4' => 'El reino completo celebra a ' . $hieloCelebrant,
        'despedida-hielo.mp4' => '¡Te esperamos!',
    ],
    // 2026-08-23: los tres temas restantes entran con la misma regla que Hielo.
    // Los MP4 ya estaban en la raíz de cada tema desde el kiosco; acá solo se
    // define el orden y el texto de cada capítulo. Un archivo que falte se
    // omite solo (is_file), así que la lista no se rompe si algo no está.
    'kpop' => [
        'saludo-rumi.mp4' => 'Rumi abre el escenario',
        'saludo-mira.mp4' => 'Mira toma el micrófono',
        'saludo-zoey.mp4' => 'Zoey suma su voz',
        'saludo-luna.mp4' => 'Luna enciende las luces',
        'saludo-sussie.mp4' => 'Sussie llega con la coreografía',
        'saludo-derpy.mp4' => 'El escenario espera a ' . $celebrante,
        'despedida-kpop.mp4' => '¡Te esperamos!',
    ],
    'tropical' => [
        'saludo-hawaiana.mp4' => 'La isla se prepara para ' . $celebrante,
        'saludo-surfista.mp4' => 'El surfista ya viene en camino',
        'saludo-loro.mp4' => 'El loro reparte la noticia',
        'saludo-tortugamar.mp4' => 'La tortuga llega sin apuro',
        'saludo-alienrosa.mp4' => 'Una visita inesperada aterriza',
        'saludo-alienazul.mp4' => 'Toda la playa celebra a ' . $celebrante,
        'despedida-tropical.mp4' => '¡Te esperamos!',
    ],
    'familia-canina' => [
        'saludo-muffin.mp4' => 'Muffin da la primera señal',
        'saludo-chloe.mp4' => 'Chloe se suma al plan',
        'saludo-chispa.mp4' => 'Chispa no se queda quieta',
        'saludo-manchita.mp4' => 'Manchita llega corriendo',
        'saludo-azulita.mp4' => 'Azulita trae la sorpresa',
        'saludo-nube.mp4' => 'Nube prepara todo para ' . $celebrante,
        'saludo-mama-coral.mp4' => 'Mamá Coral ordena la casa',
        'saludo-papa-marino.mp4' => 'Papá Marino enciende la música',
        'despedida-familia-canina.mp4' => '¡Te esperamos!',
    ],
];

// Revision aislada de los saludos nuevos de Hielo. Solo se usa con
// el modo capitulos=candidatos, conserva intactos los MP4 vigentes y termina
// con la despedida ya aprobada para probar el recorrido completo.
$playlistCandidateOrdersByTheme = [
    'hielo' => [
        'invitation/candidates/saludo-elsa-v2.mp4' => 'La magia de ' . $hieloCelebrant . ' se enciende',
        'invitation/candidates/saludo-anna-v3.mp4' => 'La celebración de ' . $hieloCelebrant . ' ya está lista',
        'invitation/candidates/saludo-olaf-v2.mp4' => 'Una sorpresa nevada viene en camino',
        'invitation/candidates/saludo-kristoff-v2.mp4' => 'Todos llegan para celebrar a ' . $hieloCelebrant,
        'invitation/candidates/saludo-sven-v3.mp4' => 'La aventura de ' . $hieloCelebrant . ' está por comenzar',
        'invitation/candidates/saludo-bruni-v3.mp4' => 'El reino completo celebra a ' . $hieloCelebrant,
        'despedida-hielo.mp4' => '¡Te esperamos!',
    ],
];
// Candidatos de entrada: solo se activan al pedir `?hero=auto`, por lo que
// ningún asset aprobado se reemplaza ni cambia el comportamiento por defecto.
// Carreras conserva su candidato aprobado para comparar; Hielo podrá sumar el
// suyo recién después de la revisión visual de Luis.
$heroAutoCandidatesByTheme = [
    'carreras' => 'invitation/candidate-wan27-auto.mp4',
    'hielo' => 'invitation/candidate-hielo-auto.mp4',
];

// Candidatos scroll: cada tema puede exponer una versión con keyframe por
// cuadro, separada del video auto para que el dedo avance sin saltos.
$heroScrollCandidatesByTheme = [
    'carreras' => 'invitation/candidate-wan27-scroll.mp4',
    'hielo' => 'invitation/candidate-hielo-scroll.mp4',
];
// GATE DE PLAN. Regla comercial canónica:
// docs/CAMPANA-INVITACIONES-BASICO-FULL-2026-08-11.md
//   service_plan=booth → Plan Básico → Scroll     (hero=scroll, capitulos=1)
//   service_plan=full  → Plan Full   → Automática (hero=auto,   capitulos=auto)
//
// `hero` y `capitulos` ya NO son parámetros públicos: antes, cualquiera con un
// enlace de Básico podía escribir hero=auto y llevarse el Full. Ahora la
// variante sale del plan de la fiesta y los parámetros solo se respetan con la
// firma `qa` que emite el admin en sus botones de vista previa.
$servicePlan = cb_invitation_service_plan(
    isset($invitation['party_id']) && $invitation['party_id'] !== null ? (int) $invitation['party_id'] : null
);
$requestedHero     = (string) ($_GET['hero'] ?? '');
$requestedChapters = (string) ($_GET['capitulos'] ?? '');
$isPreview = $requestedHero !== ''
    && cb_invitation_preview_ok(
        (int) $invitation['id'],
        $requestedHero,
        $requestedChapters,
        (string) ($_GET['qa'] ?? '')
    );
if ($isPreview) {
    $planHero     = $requestedHero;
    $planChapters = $requestedChapters;
} elseif ($servicePlan === 'full') {
    $planHero     = 'auto';
    $planChapters = 'auto';
} else {
    $planHero = 'scroll';
    // El Básico avanza por capítulos: imágenes fijas en invitation/chapters/.
    // Carreras las tiene (9 archivos) y esa lógica está aprobada, no se toca.
    // Hielo no tiene esa carpeta, y sin ella el recorrido saltaba directo a la
    // lámina, sin personajes. Cuando el tema no trae capítulos, el Básico usa
    // la playlist de videos; lo que lo sigue separando del Full es el hero, que
    // el invitado tiene que avanzar con el dedo en vez de reproducirse solo.
    $tieneCapitulos = preg_match('/^[a-z0-9-]+$/', $themeSlug) === 1
        && is_dir(__DIR__ . '/themes/' . $themeSlug . '/invitation/chapters');
    $planChapters = $tieneCapitulos ? '1' : 'auto';
}

// Comparación de candidatos detrás del modo resuelto. La versión aprobada de
// cada tema sigue siendo el fallback, byte a byte, si el candidato no existe.
// - scroll → candidato con avance controlado por scroll.
// - auto   → candidato de entrada que se reproduce una sola vez; al terminar,
//   la pista se resalta para que el invitado continúe bajando.
$heroPlayMode = $planHero;
$heroAutoUrl = '';
if ($heroPlayMode !== '' && preg_match('/^[a-z0-9-]+$/', $themeSlug)) {
    if ($heroPlayMode === 'scroll') {
        $candidateRelative = $heroScrollCandidatesByTheme[$themeSlug] ?? '';
        $candidatePath = $candidateRelative !== '' ? __DIR__ . '/themes/' . $themeSlug . '/' . $candidateRelative : '';
        if ($candidatePath !== '' && is_file($candidatePath)) {
            $candidateUrlPath = implode('/', array_map('rawurlencode', explode('/', $candidateRelative)));
            $heroScrubUrl = 'themes/' . rawurlencode($themeSlug) . '/' . $candidateUrlPath;
            $heroVideoUrl = '';
        }
    } elseif ($heroPlayMode === 'auto') {
        $candidateRelative = $heroAutoCandidatesByTheme[$themeSlug] ?? '';
        $candidatePath = $candidateRelative !== '' ? __DIR__ . '/themes/' . $themeSlug . '/' . $candidateRelative : '';
        if ($candidatePath !== '' && is_file($candidatePath)) {
            $heroAutoUrl = 'themes/' . rawurlencode($themeSlug) . '/' . $candidateRelative;
            $heroScrubUrl = '';
            $heroVideoUrl = '';
        }
    }
}

// Temas sin candidato dedicado (kpop, tropical, familia-canina) resuelven con
// los dos heroes por defecto del tema: invitation-scroll-v1.mp4 avanza con el
// dedo e invitation-motion-v1.mp4 se reproduce solo. Si los dos existen hay que
// elegir según el plan; si no, gana siempre el scroll y el Full se ve idéntico
// al Básico. Carreras e Hielo no pasan por acá: sus candidatos ya decidieron.
if ($heroPlayMode === 'auto' && $heroAutoUrl === '' && $heroVideoUrl !== '') {
    $heroScrubUrl = '';
} elseif ($heroPlayMode === 'scroll' && $heroScrubUrl !== '') {
    $heroVideoUrl = '';
}

// El hero necesita saber esto ANTES de imprimirse: si hay capítulos o lista
// de reproducción después, su botón de abajo no puede ser un salto directo
// a la plantilla (`#inv-detalles`) — eso se saltaría todo el recorrido con
// los personajes. Se calcula temprano, liviano (solo existencia de
// carpeta/archivo), sin repetir toda la carga de capítulos acá.
$chapterQueryModeEarly = $planChapters;
$hasStoryAheadOfPlate = false;
if (preg_match('/^[a-z0-9-]+$/', $themeSlug)) {
    if ($chapterQueryModeEarly === '1') {
        $hasStoryAheadOfPlate = is_dir(__DIR__ . '/themes/' . $themeSlug . '/invitation/chapters');
    } elseif ($chapterQueryModeEarly === 'auto' || $chapterQueryModeEarly === 'candidatos') {
        $playlistEarly = $chapterQueryModeEarly === 'candidatos'
            ? ($playlistCandidateOrdersByTheme[$themeSlug] ?? [])
            : ($playlistOrdersByTheme[$themeSlug] ?? []);
        foreach ($playlistEarly as $fileName => $_caption) {
            if (is_file(__DIR__ . '/themes/' . $themeSlug . '/' . $fileName)) {
                $hasStoryAheadOfPlate = true;
                break;
            }
        }
    }
}

// Compartir por WhatsApp. La URL sale de la configuración, nunca de HTTP_HOST,
// y si falta configuración el enlace simplemente no se ofrece: descargar y
// compartir el archivo siguen funcionando.
$shareUrl = '';
try {
    $shareUrl = cb_invitation_public_url($token);
} catch (Throwable $e) {
    error_log('CumpleClick invitacion URL para compartir: ' . $e->getMessage());
}

// "Según sea el caso": si la invitación tiene video aprobado, esa es la pieza
// que se comparte; si no, la imagen.
$shareKind = $hasVideo ? 'video' : 'image';
$shareKindLabel = $hasVideo ? 'video' : 'imagen';
$shareFileUrl = $hasVideo ? (string) $videoUrl : $imageUrl;

$shareMessageParts = [];
$shareMessageParts[] = '¡Estás invitado ' . $eventoDeQuien . '!';
if ($dateParts['long'] !== '') {
    $shareMessageParts[] = $eventTime !== ''
        ? 'Es el ' . $dateParts['long'] . ' a las ' . $eventTime . '.'
        : 'Es el ' . $dateParts['long'] . '.';
} elseif ($eventTime !== '') {
    $shareMessageParts[] = 'Es a las ' . $eventTime . '.';
}
// La dirección no viaja en el mensaje: se lee dentro de la invitación, que
// exige el enlace con token.
if ($shareUrl !== '') {
    $shareMessageParts[] = 'Mira la invitación acá: ' . $shareUrl;
}
$shareMessage = implode(' ', $shareMessageParts);
$whatsappUrl = $shareUrl !== '' ? 'https://wa.me/?text=' . rawurlencode($shareMessage) : '';

// Plantilla reutilizable: la imagen base NO lleva datos quemados. Trae un panel
// vacío y los datos reales se superponen en HTML sobre esa zona, con
// coordenadas normalizadas del preset (contrato §5.1). Así una misma imagen
// generada sirve para todas las fiestas de la temática, sin volver a generar.
$baseImageUrl = '';
$textArea = [];
if (preg_match('/^[a-z0-9-]+$/', $themeSlug)) {
    // El preset elige qué asset del tema hace de plantilla. Así se reutiliza
    // material ya aprobado del kiosco —que trae los personajes y el estilo
    // correctos— en vez de exigir una imagen nueva por temática.
    $baseCandidates = [];
    if (function_exists('cb_event_profile_preset')) {
        $candidate = cb_event_profile_preset('child_birthday', $themeSlug);
        $configured = trim((string) ($candidate['theme']['base_image'] ?? ''));
        if ($configured !== '' && preg_match('#^[a-z0-9][a-z0-9/_-]*\.(?:png|jpg|jpeg|webp)$#i', $configured)) {
            $baseCandidates[] = $configured;
        }
    }
    // JPG primero: la guía del proyecto pide 1080x1920 JPG ~87 y un PNG de
    // varios MB se paga entero en datos móviles.
    $baseCandidates[] = 'invitation/invitation-base-v1.jpg';
    $baseCandidates[] = 'invitation/invitation-base-v1.png';
    foreach ($baseCandidates as $relative) {
        $basePath = __DIR__ . '/themes/' . $themeSlug . '/' . $relative;
        if (strpos($relative, '..') === false && is_file($basePath)) {
            $baseImageUrl = 'themes/' . rawurlencode($themeSlug) . '/' . $relative;
            break;
        }
    }
}
if ($baseImageUrl !== '') {
    $areaPreset = [];
    if (function_exists('cb_event_profile_preset')) {
        $candidate = cb_event_profile_preset('child_birthday', $themeSlug);
        if (is_array($candidate['theme']['text_area'] ?? null)) {
            $areaPreset = $candidate['theme']['text_area'];
        }
    }
    $areaValue = static function ($value, float $fallback): float {
        $number = is_numeric($value) ? (float) $value : $fallback;
        return max(0.0, min(1.0, $number));
    };
    $areaTone = (string) ($areaPreset['tone'] ?? '');
    $textArea = [
        'x' => $areaValue($areaPreset['x'] ?? null, 0.12),
        'y' => $areaValue($areaPreset['y'] ?? null, 0.60),
        'w' => $areaValue($areaPreset['w'] ?? null, 0.76),
        'h' => $areaValue($areaPreset['h'] ?? null, 0.25),
        'tone' => preg_match('/^#[0-9a-fA-F]{6}$/', $areaTone) === 1 ? $areaTone : '#ffffff',
    ];
}

// Iconos de las secciones del perfil. Los presets traían un carácter suelto
// (◆, ★, ▤...) que en varias fuentes de Android se ve como un cuadrado vacío
// y no dice nada del contenido. Un SVG por sección se ve igual en todos lados
// y sí comunica: regalos parecen regalos y tallas parecen una etiqueta.
$sectionIcon = static function (string $key): string {
    $paths = [
        'introduction' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H10a2 2 0 0 1 2 2v13a2 2 0 0 0-2-2H5.5A1.5 1.5 0 0 1 4 15.5z"/><path d="M20 5.5A1.5 1.5 0 0 0 18.5 4H14a2 2 0 0 0-2 2v13a2 2 0 0 1 2-2h4.5a1.5 1.5 0 0 0 1.5-1.5z"/>',
        'favorites' => '<path d="M12 20.3l-1.5-1.35C6 14.9 3.5 12.6 3.5 9.6 3.5 7.3 5.3 5.5 7.6 5.5c1.3 0 2.5.6 3.3 1.55l1.1 1.3 1.1-1.3A4.3 4.3 0 0 1 16.4 5.5c2.3 0 4.1 1.8 4.1 4.1 0 3-2.5 5.3-7 9.35z"/>',
        'sizes' => '<rect x="2.5" y="7.5" width="19" height="9" rx="1.8"/><path d="M7 7.5v3.4M12 7.5v4.6M17 7.5v3.4"/>',
        'gifts' => '<path d="M3.5 11.5h17V20a1.5 1.5 0 0 1-1.5 1.5H5A1.5 1.5 0 0 1 3.5 20z"/><rect x="2.5" y="7.5" width="19" height="4" rx="1.2"/><path d="M12 7.5v14"/><path d="M12 7.5S10.8 3 8.6 3a2.3 2.3 0 0 0 0 4.5zM12 7.5S13.2 3 15.4 3a2.3 2.3 0 0 1 0 4.5z"/>',
        'custom' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.8v.4"/>',
    ];
    $inner = $paths[$key] ?? '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>';
    return '<svg class="ep-section-glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true" focusable="false">' . $inner . '</svg>';
};

// Cómo llegar. La dirección la escribió el administrador como texto libre, así
// que se consulta por búsqueda: no hay coordenadas ni geocodificación propia.
// Waze y Google entienden ambos ese formato.
$mapsUrl = '';
$wazeUrl = '';
$mapEmbedUrl = '';
if ($address !== '') {
    $mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
    $wazeUrl = 'https://waze.com/ul?q=' . rawurlencode($address) . '&navigate=yes';
    // Vista previa embebida sin API key. Va en un iframe perezoso y sin
    // referrer: la dirección igual llega a Google al cargarlo, así que el
    // mapa solo existe cuando el administrador ya decidió publicar la
    // dirección en la invitación.
    $mapEmbedUrl = 'https://maps.google.com/maps?q=' . rawurlencode($address) . '&z=15&output=embed';
}

// "Agregar a mi calendario". El evento se arma acá y viaja al cliente como
// datos sueltos; invitation.js compone el .ics y lo descarga, que es lo que
// entienden Apple Calendar, Google Calendar y Outlook por igual.
$calendarStart = '';
$calendarEnd = '';
$googleCalendarUrl = '';
if ($eventDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
    $timePart = preg_match('/^(\d{1,2}):(\d{2})/', $eventTime, $timeBits)
        ? sprintf('%02d%02d00', (int) $timeBits[1], (int) $timeBits[2])
        : '120000';
    $dateCompact = str_replace('-', '', $eventDate);
    $calendarStart = $dateCompact . 'T' . $timePart;
    // Tres horas es la duración típica de un cumpleaños infantil y evita
    // pedirle al administrador un dato que hoy no existe en el formulario.
    $startStamp = strtotime($eventDate . ' ' . substr($timePart, 0, 2) . ':' . substr($timePart, 2, 2));
    if ($startStamp !== false) {
        $calendarEnd = date('Ymd\THis', $startStamp + (3 * 3600));
    }
    if ($calendarEnd !== '') {
        $googleCalendarUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            . '&text=' . rawurlencode($eventoTitulo)
            . '&dates=' . $calendarStart . '/' . $calendarEnd
            . ($address !== '' ? '&location=' . rawurlencode($address) : '')
            . '&details=' . rawurlencode($shareUrl !== '' ? 'Invitación: ' . $shareUrl : '');
    }
}

// Nombres reales para el cierre. Salen de los protagonistas publicados; si no
// hay perfil, del nombre de la invitación.
$finaleNames = '';
if ($eventProfile !== null) {
    $names = [];
    foreach ($profilePeople as $person) {
        $candidate = trim((string) ($person['display_name'] ?? $person['name'] ?? ''));
        if ($candidate !== '') {
            $names[] = $candidate;
        }
    }
    if ($names) {
        $last = array_pop($names);
        $finaleNames = $names ? implode(', ', $names) . ' y ' . $last : $last;
    }
}
if ($finaleNames === '') {
    $finaleNames = $birthdayName;
}

// Tokens del hero. Vienen del mismo `surface` del preset que usa la ficha del
// protagonista, y se calculan haya perfil o no: un fondo claro como `hielo`
// necesita mucho más velo que uno nocturno como `kpop` para que el nombre se
// lea. Sin preset se usan valores intermedios seguros.
$invitationThemeStyle = cb_theme_css_vars($themeSlug);
$heroSurface = [];
if (function_exists('cb_event_profile_preset')) {
    $heroPreset = cb_event_profile_preset('child_birthday', $themeSlug);
    if (is_array($heroPreset['theme']['surface'] ?? null)) {
        $heroSurface = $heroPreset['theme']['surface'];
    }
}
$clampHero = static function ($value, float $min, float $max, float $fallback): float {
    $number = is_numeric($value) ? (float) $value : $fallback;
    return max($min, min($max, $number));
};
$heroDim = $clampHero($heroSurface['hero_dim'] ?? null, 0.1, 0.85, 0.5);
$invitationThemeStyle .= '--inv-hero-dim:' . $heroDim . ';';
$invitationThemeStyle .= '--inv-hero-brightness:' . round(1.0 - ($heroDim * 0.22), 3) . ';';
// Mismo token de título legible que usa la ficha: el color de marca de
// `tropical` es coral y sobre su propio fondo coral no alcanza contraste.
$heroTitle = (string) ($heroSurface['title'] ?? '');
if (preg_match('/^#[0-9a-fA-F]{6}$/', $heroTitle)) {
    $invitationThemeStyle .= '--inv-title:' . $heroTitle . ';';
}
?><!doctype html>
<html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>Invitación de <?= $esc($birthdayName !== '' ? $birthdayName : $eventoNombre) ?> · CumpleClick</title>
<?php
// Tarjeta al compartir por WhatsApp y redes. A propósito NO lleva la dirección:
// la vista previa se muestra en cada grupo donde se reenvíe el enlace y Meta la
// cachea en sus servidores, así que la casa y la hora exacta de la fiesta
// quedarían visibles sin que nadie abra la invitación. Dentro de la página sí
// va, que para eso hay que tener el enlace.
$ogNombre = $birthdayName !== ''
    ? $birthdayName
    : ($esBabyShower ? 'Baby shower' : 'Cumpleaños');
// El bebé no invita a su propio baby shower: invitan los papás.
$ogTitulo = $esBabyShower
    ? ($birthdayName !== ''
        ? 'Te invitamos al baby shower de ' . $birthdayName
        : 'Te invitamos a nuestro baby shower')
    : $ogNombre . ' te invita a su cumpleaños';
$ogMeses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
            'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$ogDesc = 'Toca para ver la invitación.';
if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $eventDate, $m)) {
    $ogDesc = (int) $m[3] . ' de ' . ($ogMeses[(int) $m[2]] ?? '');
    if (preg_match('/^(\d{2}):(\d{2})/', $eventTime, $h)) {
        $ogDesc .= ' · ' . $h[1] . ':' . $h[2] . ' hrs';
    }
}
$ogUrl = cb_invitation_pretty_url($token, $birthdayName);
$ogImagen = cb_invitation_download_url($token, 'image') . '&preview=1';
?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="CumpleClick">
<meta property="og:title" content="<?= $esc($ogTitulo) ?>">
<meta property="og:description" content="<?= $esc($ogDesc) ?>">
<meta property="og:image" content="<?= $esc($ogImagen) ?>">
<meta property="og:url" content="<?= $esc($ogUrl) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/svg+xml" href="brand/cumpleclick-mark.svg">
<?php
// El número de versión de estas dos hojas se mantenía a mano, y olvidarlo es
// silencioso: el servidor queda con el CSS nuevo y el invitado que ya abrió la
// invitación sigue viendo el viejo, sin ningún error. Pasó al subir el ajuste
// de los botones a 44 px. Ahora sale de la fecha de modificación del archivo:
// cambia solo cuando el archivo cambia, y nunca hay que acordarse.
$cssVer = static function (string $rel): string {
    $ruta = __DIR__ . '/' . $rel;
    $sello = is_file($ruta) ? (string) filemtime($ruta) : '0';
    return $rel . '?v=' . substr(md5($sello), 0, 8);
};
?>
<link rel="stylesheet" href="<?= $esc($cssVer('assets/invitation.css')) ?>">
<?php if ($eventProfile !== null): ?><link rel="stylesheet" href="<?= $esc($cssVer('assets/event-profile.css')) ?>"><?php endif; ?>
</head>
<body class="inv-body" data-theme="<?= $esc($themeSlug) ?>" style="<?= $esc($invitationThemeStyle) ?>">

  <?php // Música + narración de Alice, igual que en el kiosco: un solo
        // <audio> de fondo que arranca con el primer toque (los navegadores
        // bloquean autoplay con sonido) y baja de volumen mientras Alice
        // habla. Si falta algún MP3, ese audio simplemente no suena — el
        // resto de la invitación funciona exactamente igual (mismo patrón
        // resiliente que la narración de personajes del kiosco). ?>
  <?php if ($musicUrl !== ''): ?>
  <audio data-inv-music src="<?= $esc($musicUrl) ?>" loop preload="none"></audio>
  <button class="mute-btn" type="button" data-inv-mute aria-label="Silenciar música" hidden>🎵</button>
  <?php endif; ?>
  <?php if ($narrationIntroUrl !== ''): ?>
  <audio data-inv-narration-intro src="<?= $esc($narrationIntroUrl) ?>" preload="none"></audio>
  <?php endif; ?>
  <?php if ($narrationOutroUrl !== ''): ?>
  <audio data-inv-narration-outro src="<?= $esc($narrationOutroUrl) ?>" preload="none"></audio>
  <?php endif; ?>
  <?php if ($musicUrl !== '' || $narrationIntroUrl !== ''): ?>
  <button class="inv-audio-activate" type="button" data-inv-audio-activate hidden>Activar música y voz</button>
  <?php endif; ?>

<section class="inv-entry-gate" data-inv-entry-gate aria-label="Abrir invitación">
  <div class="inv-entry-gate-card">
    <?php // El sobre es el gesto: tocarlo dispara la misma apertura que antes
          // (misma lógica en invitation.js), solo que ahora se ve como un
          // sobre que se abre y saca la carta, en vez de un botón plano. ?>
    <button type="button" class="inv-envelope-btn" data-inv-entry-open aria-label="Toca el sobre para abrir la invitación">
      <span class="inv-envelope" aria-hidden="true">
        <span class="inv-envelope-letter">
          <span class="inv-envelope-letter-line"></span>
          <span class="inv-envelope-letter-line"></span>
          <span class="inv-envelope-letter-line inv-envelope-letter-line--short"></span>
        </span>
        <span class="inv-envelope-body"></span>
        <span class="inv-envelope-pocket"></span>
        <span class="inv-envelope-flap"></span>
      </span>
      <span class="inv-entry-gate-title">Tu invitación está lista</span>
      <span class="inv-entry-gate-hint">Toca el sobre para abrirlo</span>
    </button>
  </div>
</section>

<?php if ($themeIntroUrl !== ''): ?>
<section class='inv-theme-intro' data-inv-theme-intro role='dialog' aria-modal='true' aria-label='Intro de la invitación' aria-hidden='true' hidden>
  <video class='inv-theme-intro-media' data-inv-theme-intro-video src='<?= $esc($themeIntroUrl) ?>'<?php if ($themeIntroPosterUrl !== ''): ?> poster='<?= $esc($themeIntroPosterUrl) ?>'<?php endif; ?> playsinline preload='metadata'></video>
  <div class='inv-theme-intro-shade' aria-hidden='true'></div>
  <img class='inv-theme-intro-brand' src='brand/cumpleclick-lockup.svg' alt='CumpleClick' width='150' height='38'>
  <button class='inv-theme-intro-skip' type='button' data-inv-theme-intro-skip>Omitir intro</button>
  <div class='inv-theme-intro-progress' aria-hidden='true'><span data-inv-theme-intro-progress></span></div>
</section>
<?php endif; ?>

<main class="inv">

  <?php // El hero mide más de una pantalla y su escenario queda fijo: al
        // scrollear, el fondo se acerca y el velo se abre, así el invitado
        // siente que entra en la escena en vez de pasar a otra sección. ?>
  <section class="inv-hero<?= $heroScrubUrl !== '' ? ' inv-hero--dive' : '' ?>" id="inv-inicio"<?= $heroScrubUrl !== '' ? ' data-inv-dive' : '' ?>>
    <div class="inv-hero-stage">
      <div class="inv-hero-bg" aria-hidden="true">
        <?php if ($heroScrubUrl !== ''): ?>
        <?php // El scroll controla el fotograma: el invitado avanza dentro de
              // la escena. Sin JS o con movimiento reducido queda el primer
              // cuadro como fondo fijo, que sigue siendo válido. ?>
        <video class="inv-hero-media" data-inv-scrub src="<?= $esc($heroScrubUrl) ?>" muted playsinline preload="auto"<?= $heroImageUrl !== '' ? ' poster="' . $esc($heroImageUrl) . '"' : '' ?>></video>
        <?php elseif ($heroVideoUrl !== ''): ?>
        <video class="inv-hero-media" src="<?= $esc($heroVideoUrl) ?>" autoplay muted loop playsinline preload="metadata"<?= $heroImageUrl !== '' ? ' poster="' . $esc($heroImageUrl) . '"' : '' ?>></video>
        <?php elseif ($heroAutoUrl !== ''): ?>
        <?php // Comparación "auto": se reproduce una sola vez, sin loop y sin
              // scroll-scrub. Al terminar, invitation.js resalta la pista de
              // scroll para que el invitado siga bajando por su cuenta. ?>
        <video class="inv-hero-media" data-inv-autoplay-once src="<?= $esc($heroAutoUrl) ?>" muted playsinline preload="auto"<?= $heroImageUrl !== '' ? ' poster="' . $esc($heroImageUrl) . '"' : '' ?>></video>
        <?php elseif ($heroImageUrl !== ''): ?>
        <img class="inv-hero-media" src="<?= $esc($heroImageUrl) ?>" alt="" decoding="async">
        <?php endif; ?>
      </div>

      <div class="inv-sparks" aria-hidden="true">
        <?php for ($spark = 1; $spark <= 12; $spark++): ?><span class="inv-spark" style="--i:<?= $spark ?>"></span><?php endfor; ?>
      </div>

      <div class="inv-hero-copy">
        <p class="inv-kicker"><?= $esc($heroKicker) ?></p>
        <h1 class="inv-name"><?= $esc($birthdayName !== '' ? $birthdayName : $eventoSinNombre) ?></h1>
        <?php if ($dateParts['long'] !== '' || $eventTime !== ''): ?>
        <p class="inv-hero-when">
          <?= $esc($dateParts['long']) ?><?= $dateParts['long'] !== '' && $eventTime !== '' ? ' · ' : '' ?><?= $esc($eventTime) ?>
        </p>
        <?php endif; ?>
        <?php if ($cuentaTexto !== '' || $incognitaTexto !== ''): ?>
        <div class="inv-hero-countdown">
          <?php if ($cuentaTexto !== ''): ?>
          <p class="inv-hero-count">
            <?php if ($cuentaNumero !== ''): ?>
            <span class="inv-hero-count-num"><?= $esc($cuentaNumero) ?></span>
            <?php endif; ?>
            <span class="inv-hero-count-label"><?= $esc($cuentaTexto) ?></span>
          </p>
          <?php endif; ?>
          <?php if ($incognitaTexto !== ''): ?>
          <?php // El "¿?" ocupa el lugar del número a propósito: esa cifra no
                // existe. aria-label lo dice con palabras, porque un lector de
                // pantalla leyendo dos signos sueltos no comunica nada. ?>
          <p class="inv-hero-count inv-hero-count--incognita">
            <span class="inv-hero-count-num" aria-label="No se sabe cuántos">¿?</span>
            <span class="inv-hero-count-label"><?= $esc($incognitaTexto) ?></span>
          </p>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <?php // Con capítulos o lista de reproducción por delante, esto NO
            // puede ser un salto a la plantilla: se saltaría todo el
            // recorrido con los personajes. Queda solo como invitación a
            // seguir bajando, sin `href`. ?>
      <?php if ($hasStoryAheadOfPlate): ?>
      <span class="inv-scroll-hint<?= $heroAutoUrl !== '' ? ' inv-scroll-hint--waiting' : '' ?>" data-inv-scroll-only<?= $heroAutoUrl !== '' ? ' data-inv-auto-hint aria-hidden="true"' : '' ?>>
        <span>Desliza para seguir</span>
        <span class="inv-scroll-arrow" aria-hidden="true"></span>
      </span>
      <?php else: ?>
      <a class="inv-scroll-hint" href="#inv-detalles">
        <span>Ver invitación</span>
        <span class="inv-scroll-arrow" aria-hidden="true"></span>
      </a>
      <?php endif; ?>
    </div>
  </section>

  <?php
    // Capítulos ilustrados en scroll — versión 2, para comparar contra el
    // hero de video de arriba. Solo aparecen si la temática tiene la
    // carpeta invitation/chapters/ poblada; si no existe, no se imprime
    // nada y la página se comporta exactamente como antes.
    // Detrás de un flag opcional: la versión aprobada (sin capítulos) sigue
    // siendo el comportamiento por defecto, byte a byte. `?capitulos=1` en
    // la URL activa la versión 2 para comparar, sin duplicar la página.
    // `capitulos=1` → el invitado controla el avance con el dedo (scroll),
    // sobre imágenes fijas que se funden entre sí.
    // `capitulos=auto` → nada de imágenes disfrazadas de video: es una
    // lista de reproducción de videos REALES ya existentes del kiosco,
    // uno tras otro, sin pedirle scroll a nadie. Combina con `hero=auto`.
    // Un solo recorrido continuo: capítulos ilustrados + el video de Rayo
    // cruzando la meta + el cierre grupal, todos como parte de la MISMA
    // sección de scroll (Luis pidió explícitamente que no queden separados
    // en secciones distintas). Cada slot tiene un `span`: cuánto recorrido
    // de scroll ocupa. Las imágenes valen 1; el video vale más (3), porque
    // necesita ese ancho para que el scroll lo recorra cuadro a cuadro en
    // vez de solo cruzarlo de golpe.
    $chapterSlots = [];
    $playlistSlots = [];
    $chapterQueryMode = $planChapters;
    if ($chapterQueryMode === '1' && preg_match('/^[a-z0-9-]+$/', $themeSlug)) {
        $chaptersDir = __DIR__ . '/themes/' . $themeSlug . '/invitation/chapters';
        if (is_dir($chaptersDir)) {
            // Orden narrativo explícito: no se puede ordenar por nombre de
            // archivo porque "conn-01" queda antes que "scene-01" en orden
            // alfabético y el recorrido saldría desordenado (los dos
            // conectores primero, las tres escenas después).
            $chapterOrderIntro = [
                'scene-01' => 'El equipo ya está listo',
                'conn-01' => 'Mate llega primero',
                'scene-02' => 'Sally viene en camino',
                'conn-02' => 'Cruz calienta motores',
                'scene-03' => 'Luigi no se queda atrás',
                'conn-03' => 'El Rey no se lo pierde',
                'scene-04' => 'Rayo se prepara para la meta',
            ];
            // `conn-04` (la foto de Rayo cruzando la meta) se sacó de acá:
            // ese mismo instante ahora es un video real que el invitado
            // controla con el dedo, dentro del mismo recorrido — no tenía
            // sentido repetirlo como foto y como video.
            foreach ($chapterOrderIntro as $baseName => $caption) {
                $filePath = $chaptersDir . '/' . $baseName . '.jpg';
                if (!is_file($filePath)) {
                    continue;
                }
                $chapterSlots[] = [
                    'type' => 'image',
                    'span' => 1,
                    'url' => 'themes/' . rawurlencode($themeSlug) . '/invitation/chapters/' . rawurlencode($baseName) . '.jpg',
                    'caption' => $caption,
                    'kind' => strpos($baseName, 'conn') === 0 ? 'connector' : 'scene',
                ];
            }

            // El video va acá adentro, como un slot más del mismo recorrido
            // — no como una sección aparte.
            $momentPath = __DIR__ . '/themes/' . $themeSlug . '/invitation/candidate-wan27-scroll.mp4';
            if (is_file($momentPath)) {
                $momentPosterUrl = '';
                $momentPosterPath = __DIR__ . '/themes/' . $themeSlug . '/invitation/moment-rayo-meta-poster.jpg';
                if (is_file($momentPosterPath)) {
                    $momentPosterUrl = 'themes/' . rawurlencode($themeSlug) . '/invitation/moment-rayo-meta-poster.jpg';
                }
                $chapterSlots[] = [
                    'type' => 'video',
                    // Más ancho que las fotos (que valen 1): con span 3 el video se
                    // sentía apurado y cortado a medio recorrer. Con 6 el invitado
                    // scrollea el doble de lento y el clip se ve completo.
                    'span' => 6,
                    'url' => 'themes/' . rawurlencode($themeSlug) . '/invitation/candidate-wan27-scroll.mp4',
                    'poster' => $momentPosterUrl,
                    'caption' => 'Rayo cruza la meta',
                    'kind' => 'video',
                ];
            }

            $finalePath = $chaptersDir . '/scene-05.jpg';
            if (is_file($finalePath)) {
                $chapterSlots[] = [
                    'type' => 'image',
                    'span' => 1,
                    'url' => 'themes/' . rawurlencode($themeSlug) . '/invitation/chapters/scene-05.jpg',
                    'caption' => '¡Te esperamos!',
                    'kind' => 'scene',
                ];
            }
        }
    } elseif (($chapterQueryMode === 'auto' || $chapterQueryMode === 'candidatos') && preg_match('/^[a-z0-9-]+$/', $themeSlug)) {
        // Videos existentes y aprobados del kiosco. El mapa quedó declarado
        // arriba porque también decide, antes de renderizar el hero, si la
        // historia tiene contenido al cual continuar.
        $playlistOrder = $chapterQueryMode === 'candidatos'
            ? ($playlistCandidateOrdersByTheme[$themeSlug] ?? [])
            : ($playlistOrdersByTheme[$themeSlug] ?? []);
        // Narración de Alice del modo video: texto fijo por tema (no depende
        // de la invitación), UNA vez por capítulo. El último capítulo
        // ("¡Te esperamos!") reutiliza el mismo audio de despedida global en
        // vez de pedir una grabación aparte, porque dice lo mismo.
        $playlistKeys = array_keys($playlistOrder);
        $lastPlaylistKey = end($playlistKeys);
        foreach ($playlistOrder as $fileName => $slot) {
            $filePath = __DIR__ . '/themes/' . $themeSlug . '/' . $fileName;
            if (!is_file($filePath)) {
                continue;
            }
            // Normalmente el valor es el texto del capítulo y la narración se
            // deduce del nombre del clip. Puede venir como array cuando un mismo
            // video necesita más de una narración — el capítulo del nombre usa el
            // mismo gorrito diga lo que diga Alice.
            $caption = is_array($slot) ? (string) ($slot['caption'] ?? '') : (string) $slot;
            $narrationBaseName = is_array($slot) && !empty($slot['narracion'])
                ? (string) $slot['narracion']
                : pathinfo($fileName, PATHINFO_FILENAME);
            $narrationKey = preg_replace('/-v[0-9]+$/', '', $narrationBaseName);
            $narrationPath = __DIR__ . '/themes/' . $themeSlug . '/narracion-video/' . $narrationKey . '.mp3';
            $narrationUrl = '';
            // Regla global: el último capítulo de CUALQUIER temática siempre
            // usa el mismo audio de cierre del recorrido (invita a seguir
            // bajando). NO es el mismo audio que la sección final "Guarda y
            // comparte" — esa suena aparte, cuando el invitado realmente
            // llega ahí con el scroll (ver `$narrationOutroUrl` más arriba).
            if ($fileName === $lastPlaylistKey && $narrationPlaylistEndUrl !== '') {
                $narrationUrl = $narrationPlaylistEndUrl;
            } elseif (is_file($narrationPath)) {
                $narrationUrl = 'themes/' . rawurlencode($themeSlug) . '/narracion-video/' . rawurlencode($narrationKey) . '.mp3';
            }
            $encodedFilePath = implode('/', array_map('rawurlencode', explode('/', $fileName)));
            $playlistSlots[] = [
                'url' => 'themes/' . rawurlencode($themeSlug) . '/' . $encodedFilePath,
                'caption' => $caption,
                'narration' => $narrationUrl,
            ];
        }
    }

    // Ancho total del recorrido: la suma de los `span` de cada slot, no la
    // cantidad de slots — el video vale 3, no 1, así que el CSS necesita
    // este número para darle a la sección el alto de scroll correcto.
    $chapterUnits = 0;
    foreach ($chapterSlots as $slot) {
        $chapterUnits += $slot['span'];
    }
  ?>
  <?php if ($chapterSlots): ?>
  <?php // Recorrido único: capítulos ilustrados + el video de Rayo cruzando
        // la meta + el cierre grupal, todo en la MISMA sección de scroll. El
        // video es un slot más (más ancho que los demás), no una sección
        // aparte. El acceso a la invitación solo aparece en el último slot. ?>
  <section class="inv-chapters" data-chapters style="--inv-chapters-count:<?= count($chapterSlots) ?>;--inv-chapters-units:<?= $chapterUnits ?>" aria-label="<?= $esc('El recorrido hasta ' . ($esBabyShower ? 'el baby shower' : 'la fiesta')) ?>">
    <div class="inv-chapters-stage">
      <div class="inv-chapters-media" aria-hidden="true">
        <?php foreach ($chapterSlots as $i => $chapter): ?>
        <?php if ($chapter['type'] === 'video'): ?>
        <figure class="inv-chapters-layer inv-chapters-layer--video" data-chapter-layer data-chapter-index="<?= $i ?>" data-chapter-span="<?= $chapter['span'] ?>">
          <?php // El scroll también controla el fotograma de este video,
                // igual que el hero: invitation.js lo detecta por
                // `data-chapter-video` y le aplica el mismo truco del blob
                // para poder saltar de cuadro sin depender de Range. ?>
          <video data-chapter-video src="<?= $esc($chapter['url']) ?>" muted playsinline preload="auto"<?= ($chapter['poster'] ?? '') !== '' ? ' poster="' . $esc($chapter['poster']) . '"' : '' ?>></video>
        </figure>
        <?php else: ?>
        <figure class="inv-chapters-layer inv-chapters-layer--<?= $esc($chapter['kind']) ?>" data-chapter-layer data-chapter-index="<?= $i ?>" data-chapter-span="<?= $chapter['span'] ?>">
          <img src="<?= $esc($chapter['url']) ?>" alt="" decoding="async" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
        </figure>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <div class="inv-chapters-progress" aria-hidden="true"><span data-chapters-progress-bar></span></div>
      <nav class="inv-chapters-dots" aria-hidden="true">
        <?php foreach ($chapterSlots as $i => $chapter): ?>
        <span class="inv-chapters-dot" data-chapter-dot data-chapter-index="<?= $i ?>"></span>
        <?php endforeach; ?>
      </nav>
      <div class="inv-chapters-copy">
        <?php foreach ($chapterSlots as $i => $chapter): ?>
        <?php if ($chapter['caption'] !== ''): ?>
        <p class="inv-chapters-caption" data-chapter-caption data-chapter-index="<?= $i ?>"><?= $esc($chapter['caption']) ?></p>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php // Solo aparece en el último slot ("¡Te esperamos!"); invitation.js
            // le agrega `is-visible` según cuál esté activo. ?>
      <a class="inv-scroll-hint" href="#inv-detalles" data-chapters-hint>
        <span>Ver invitación</span>
        <span class="inv-scroll-arrow" aria-hidden="true"></span>
      </a>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($playlistSlots): ?>
  <?php // Lista de reproducción real: un único <video> va cambiando de
        // fuente cuando cada clip termina. invitation.js la arranca cuando
        // el hero "auto" termina (o de inmediato si no hay hero=auto). ?>
  <section class="inv-playlist" data-video-playlist aria-label="Los personajes te esperan">
    <div class="inv-playlist-stage">
      <video class="inv-playlist-media" data-playlist-video muted playsinline preload="auto"></video>
      <?php // Los clips de la lista van SIN audio propio (muted arriba): acá
            // va la voz de Alice narrando cada capítulo, si existe el MP3. ?>
      <audio data-playlist-narration preload="none"></audio>
      <div class="inv-playlist-progress" aria-hidden="true"><span data-playlist-progress-bar></span></div>
      <p class="inv-playlist-caption" data-playlist-caption></p>
      <?php // Aparece al entrar al último clip ("¡Te esperamos!"). ?>
      <a class="inv-scroll-hint" href="#inv-detalles" data-playlist-hint>
        <span>Ver invitación</span>
        <span class="inv-scroll-arrow" aria-hidden="true"></span>
      </a>
    </div>
    <script type="application/json" data-playlist-data><?= json_encode($playlistSlots, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  </section>
  <?php endif; ?>

  <section class="inv-art-section inv-reveal" id="inv-detalles">
    <?php if ($baseImageUrl !== '' && $textArea): ?>
    <?php // Plantilla + datos: la misma imagen base sirve para cualquier fiesta
          // de esta temática y el nombre se compone en HTML sobre el panel. ?>
    <figure class="inv-art-frame inv-art-frame--template">
      <img class="inv-art" src="<?= $esc($baseImageUrl) ?>" alt="" decoding="async">
      <div class="inv-plate" style="--plate-x:<?= $esc((string) ($textArea['x'] * 100)) ?>%;--plate-y:<?= $esc((string) ($textArea['y'] * 100)) ?>%;--plate-w:<?= $esc((string) ($textArea['w'] * 100)) ?>%;--plate-h:<?= $esc((string) ($textArea['h'] * 100)) ?>%;--plate-tone:<?= $esc($textArea['tone']) ?>">
        <?php // Luis pidió el texto largo SOLO para el kicker del hero (el de
              // arriba, "inicio"); este de la placa se queda en la versión
              // corta ya corregida (ver inv-kicker más arriba para el otro). ?>
        <?php if ($themeSlug === 'hielo'): ?>
        <div class="inv-plate-snow" aria-hidden="true"><span>❄</span><span>✦</span><span>❄</span></div>
        <p class="inv-plate-kicker">Una celebración mágica para:</p>
        <p class="inv-plate-name"><?= $esc($birthdayName) ?></p>
        <div class="inv-plate-details">
          <?php if ($dateParts['long'] !== ''): ?>
          <p class="inv-plate-chip inv-plate-chip--date"><span aria-hidden="true">📅</span><span><?= $esc($dateParts['long']) ?></span></p>
          <?php endif; ?>
          <?php if ($eventTime !== ''): ?>
          <p class="inv-plate-chip"><span aria-hidden="true">🕓</span><span><?= $esc($eventTime) ?> horas</span></p>
          <?php endif; ?>
          <?php if ($address !== ''): ?>
          <p class="inv-plate-chip inv-plate-chip--place"><span aria-hidden="true">📍</span><span><?= $esc($address) ?></span></p>
          <?php endif; ?>
        </div>
        <?php elseif ($esBabyShower): ?>
        <?php // Misma riqueza que la lámina de Hielo, con el vocabulario y
              // los adornos de la espera en vez de los del cumpleaños. ?>
        <div class="inv-plate-orn" aria-hidden="true"><span></span><span></span><span></span></div>
        <p class="inv-plate-kicker">Un baby shower para recibir a:</p>
        <p class="inv-plate-name"><?= $esc($bebeTitulo) ?></p>
        <div class="inv-plate-details">
          <?php if ($dateParts['long'] !== ''): ?>
          <p class="inv-plate-chip inv-plate-chip--date"><span aria-hidden="true">📅</span><span><?= $esc($dateParts['long']) ?></span></p>
          <?php endif; ?>
          <?php if ($eventTime !== ''): ?>
          <p class="inv-plate-chip"><span aria-hidden="true">🕓</span><span><?= $esc($eventTime) ?> horas</span></p>
          <?php endif; ?>
          <?php if ($address !== ''): ?>
          <p class="inv-plate-chip inv-plate-chip--place"><span aria-hidden="true">📍</span><span><?= $esc($address) ?></span></p>
          <?php endif; ?>
        </div>
        <?php if ($cuentaRegresiva !== ''): ?>
        <p class="inv-plate-countdown"><?= $esc($cuentaRegresiva) ?></p>
        <?php endif; ?>
        <?php else: ?>
        <p class="inv-plate-kicker"><?= $esc('Te invitamos al ' . $eventoNombre . ' de:') ?></p>
        <p class="inv-plate-name"><?= $esc($birthdayName) ?></p>
        <?php if ($dateParts['long'] !== '' || $eventTime !== ''): ?>
        <p class="inv-plate-when"><?= $esc($dateParts['long']) ?><?= $dateParts['long'] !== '' && $eventTime !== '' ? ' · ' : '' ?><?= $esc($eventTime) ?></p>
        <?php endif; ?>
        <?php if ($address !== ''): ?>
        <p class="inv-plate-where">📍 <?= $esc($address) ?></p>
        <?php endif; ?>
        <?php endif; ?>
      </div>
      <figcaption class="inv-art-caption">Invitación de <?= $esc($birthdayName) ?></figcaption>
    </figure>
    <?php else: ?>
    <figure class="inv-art-frame">
      <img class="inv-art" src="<?= $esc($imageUrl) ?>" alt="Invitación de <?= $esc($birthdayName) ?>" decoding="async">
      <?php if ($hasVideo): ?>
      <video class="inv-art-video" src="<?= $esc((string) $videoUrl) ?>" controls playsinline loop muted preload="none"></video>
      <?php endif; ?>
    </figure>
    <?php endif; ?>
  </section>

  <?php if ($dateParts['long'] !== '' || $eventTime !== '' || $address !== ''): ?>
  <section class="inv-facts inv-reveal">
    <?php if ($dateParts['long'] !== '' || $eventTime !== ''): ?>
    <article class="inv-fact" data-fact="cuando"
      <?php if ($calendarStart !== '' && $calendarEnd !== ''): ?>
      data-inv-calendar
      data-cal-start="<?= $esc($calendarStart) ?>"
      data-cal-end="<?= $esc($calendarEnd) ?>"
      data-cal-title="<?= $esc($eventoTitulo) ?>"
      data-cal-location="<?= $esc($address) ?>"
      data-cal-url="<?= $esc($shareUrl) ?>"
      data-cal-filename="<?= $esc($eventoArchivo) ?>"
      <?php endif; ?>>
      <p class="inv-fact-label"><?= $dateParts['long'] !== '' ? 'Cuándo' : 'Hora' ?></p>
      <p class="inv-fact-value"><?= $esc($dateParts['long'] !== '' ? $dateParts['long'] : $eventTime) ?></p>
      <?php if ($dateParts['long'] !== '' && $eventTime !== ''): ?><p class="inv-fact-extra"><?= $esc($eventTime) ?> horas</p><?php endif; ?>

      <?php if ($googleCalendarUrl !== ''): ?>
      <div class="inv-fact-actions">
        <?php // El .ics lo arma invitation.js y sirve para Apple Calendar,
              // Outlook y Android. Sin JS queda el enlace de Google, que
              // funciona sin descargar nada. ?>
        <button class="inv-chip" type="button" data-cal-add hidden>
          <svg class="inv-glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M12 14v4M10 16h4"/></svg>
          Agregar a mi calendario
        </button>
        <a class="inv-chip" href="<?= $esc($googleCalendarUrl) ?>" target="_blank" rel="noopener" data-cal-fallback>
          <svg class="inv-glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M12 14v4M10 16h4"/></svg>
          Agregar a mi calendario
        </a>
      </div>
      <?php endif; ?>
    </article>
    <?php endif; ?>

    <?php if ($address !== ''): ?>
    <article class="inv-fact" data-fact="donde">
      <p class="inv-fact-label">Dónde</p>
      <p class="inv-fact-value"><?= $esc($address) ?></p>

      <?php if ($mapEmbedUrl !== ''): ?>
      <?php // El mapa se carga en diferido: no cuesta datos a quien solo
            // abre la invitación a mirar, y la dirección ya es pública
            // dentro de este enlace con token. ?>
      <div class="inv-map">
        <iframe src="<?= $esc($mapEmbedUrl) ?>" title="Mapa de <?= $esc($address) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
      </div>
      <?php endif; ?>

      <div class="inv-fact-actions">
        <a class="inv-chip" href="<?= $esc($mapsUrl) ?>" target="_blank" rel="noopener">
          <svg class="inv-glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>
          Google Maps
        </a>
        <a class="inv-chip" href="<?= $esc($wazeUrl) ?>" target="_blank" rel="noopener">
          <svg class="inv-glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 3L3 10.5l7.5 3L14 21z"/></svg>
          Waze
        </a>
      </div>
    </article>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($message !== ''): ?>
  <section class="inv-message inv-reveal">
    <blockquote><?= nl2br($esc($message)) ?></blockquote>
  </section>
  <?php endif; ?>

  <?php if ($esBabyShower && $regalos['total'] > 0): ?>
  <?php // "Para cuando llegue" — el nombre lo decidió Luis el 2026-08-25.
        // Se renderiza entero en el servidor: sin JS se ve igual, solo que
        // no se puede reservar, y eso es mejor que una sección vacía. ?>
  <section class="inv-gifts inv-reveal" data-inv-gifts data-inv-token="<?= $esc($token) ?>">
    <p class="inv-kicker">Para cuando llegue</p>
    <h2 class="inv-gifts-title"><?= $esc($regalosGancho) ?></h2>
    <p class="inv-gifts-lede">Elige uno y márcalo. Así nadie lleva lo mismo.</p>
    <p class="inv-gifts-count" data-gifts-count>
      <?= $esc($regalos['tomados'] . ' de ' . $regalos['total'] . ' ya tienen quien los lleve') ?>
    </p>

    <?php // El nombre va en un campo a la vista y no en un window.prompt: el
          // resto de la invitación está cuidado al píxel y un cuadro del
          // navegador encima rompe el tono justo en el único momento en que
          // el invitado hace algo. Sin JS queda oculto, porque sin JS no hay
          // reserva posible. ?>
    <div class="inv-gifts-yo" data-gifts-yo hidden>
      <label for="inv-gifts-nombre">Te anotamos como</label>
      <input id="inv-gifts-nombre" type="text" maxlength="80" autocomplete="name"
             placeholder="Tu nombre" data-gifts-nombre>
      <span class="inv-gifts-yo-nota">Solo lo ven los papás.</span>
    </div>

    <ul class="inv-gifts-list" data-gifts-list>
      <?php foreach ($regalos['items'] as $regalo): ?>
      <li class="inv-gift<?= $regalo['tomado'] ? ' inv-gift--tomado' : '' ?>" data-gift-id="<?= (int) $regalo['id'] ?>">
        <div class="inv-gift-texto">
          <p class="inv-gift-title"><?= $esc($regalo['title']) ?></p>
          <?php if ($regalo['notes'] !== ''): ?>
          <p class="inv-gift-notes"><?= $esc($regalo['notes']) ?></p>
          <?php endif; ?>
        </div>
        <?php // Nunca sale de acá quién lo tomó: ver lib.gifts.php. ?>
        <?php if ($regalo['tomado']): ?>
        <span class="inv-gift-estado">Ya lo tomaron</span>
        <?php else: ?>
        <button class="inv-gift-btn" type="button" data-gift-claim>Yo lo regalo</button>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>

    <p class="inv-gifts-status" data-gifts-status role="status" aria-live="polite"></p>

    <button class="inv-gifts-otro" type="button" data-gift-add hidden>Voy a regalar otra cosa</button>
    <div class="inv-gifts-nuevo" data-gifts-nuevo hidden>
      <input type="text" maxlength="120" placeholder="¿Qué vas a regalar?" data-gifts-nuevo-titulo>
      <button class="inv-gift-btn" type="button" data-gifts-nuevo-ok>Agregarlo</button>
    </div>
    <?php // El costo de no tener cuentas se dice, no se esconde. ?>
    <p class="inv-gifts-nota" data-gifts-nota hidden>
      Lo que marques queda guardado en este navegador. Si borras sus datos vas a
      poder seguir llevándolo, pero ya no soltarlo por tu cuenta.
    </p>
  </section>
  <?php endif; ?>

  <section class="inv-save inv-reveal"
    data-inv-share
    data-inv-narration-outro-trigger
    data-share-url="<?= $esc($shareFileUrl) ?>"
    data-share-kind="<?= $esc($shareKind) ?>"
    data-share-label="<?= $esc($shareKindLabel) ?>"
    data-share-name="<?= $esc('invitacion-' . ($birthdayName !== '' ? $birthdayName : 'cumpleclick')) ?>"
    data-share-text="<?= $esc($shareMessage) ?>"
    data-share-title="<?= $esc($birthdayName !== '' ? 'Invitación de ' . $birthdayName : 'Invitación') ?>">
    <p class="inv-save-label">Guarda y comparte tu invitación</p>

    <div class="inv-save-actions">
      <?php if ($whatsappUrl !== ''): ?>
      <a class="inv-button inv-button-wa" href="<?= $esc($whatsappUrl) ?>" target="_blank" rel="noopener">
        <?php // Marca de WhatsApp en uso nominativo: identifica a dónde va el
              // mensaje. Va inline para que no dependa de una petición extra
              // y herede el color del botón. ?>
        <svg class="inv-wa-glyph" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.25-.46-2.38-1.47-.88-.78-1.47-1.75-1.65-2.05-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.6-.92-2.2-.24-.58-.48-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.01-1.04 2.47s1.06 2.87 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.75-.72 2-1.41.25-.69.25-1.28.17-1.41-.07-.13-.27-.2-.57-.35z"/><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.32 4.96L2 22l5.25-1.38a9.87 9.87 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm0 18.13h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.37c0-4.54 3.7-8.23 8.25-8.23a8.19 8.19 0 0 1 8.24 8.24c0 4.54-3.7 8.23-8.24 8.23z"/></svg>Enviar por WhatsApp
      </a>
      <?php endif; ?>

      <?php // Solo aparece si el navegador puede compartir el archivo en sí.
            // invitation.js lo revela; sin soporte queda el envío por enlace. ?>
      <button class="inv-button inv-button-ghost" type="button" data-share-file hidden>
        <?php // Compartir: el glifo del sistema (nodos enlazados), no un
              // cuadrado genérico, para que se reconozca de inmediato. ?>
        <svg class="inv-glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
        Compartir <?= $esc($shareKindLabel) ?>
      </button>

      <a class="inv-button inv-button-ghost" href="<?= $esc($imageUrl) ?>" download>
        <?php // Imagen con flecha de descarga: dice qué se baja, no solo que
              // se baja algo. ?>
        <svg class="inv-glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 13V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7"/><circle cx="9" cy="9" r="1.6"/><path d="M4 15.5l4-3.5 3.5 3"/><path d="M18 15v6M15.2 18.4L18 21.2l2.8-2.8"/></svg>
        Descargar imagen
      </a>
      <?php if ($hasVideo): ?>
      <a class="inv-button inv-button-ghost" href="<?= $esc((string) $videoUrl) ?>" download>
        <svg class="inv-glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 12V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h9"/><path d="M15 9.5l6-3.5v12l-6-3.5"/><path d="M18 15v6M15.2 18.4L18 21.2l2.8-2.8"/></svg>
        Descargar video
      </a>
      <?php endif; ?>
    </div>

    <p class="inv-share-status" data-share-status role="status" aria-live="polite"></p>
  </section>

  <?php if ($eventProfile !== null): ?>
  <section class="inv-finale inv-reveal" id="inv-protagonista">
    <p class="inv-kicker"><?= $esc($esBabyShower ? 'Antes del baby shower' : 'Antes de la fiesta') ?></p>
    <h2 class="inv-finale-title">¿Quieres conocer a <?= $esc($finaleNames) ?>?</h2>
    <p class="inv-finale-lede">Gustos, tallas e ideas para regalar, contados por la familia.</p>

    <div class="ep-entry" data-event-profile style="--ep-accent:<?= $esc($accent) ?>;--ep-highlight:<?= $esc($yellow) ?>">
      <?php // La sección ya trae su propio antetítulo: el botón no lo repite. ?>
      <button class="ep-trigger" type="button" data-ep-open aria-haspopup="dialog" aria-controls="event-profile-dialog">
        <?php // El botón principal: una tarjeta de identidad dice "acá se
              // cuenta quién es" mejor que una flecha. ?>
        <svg class="ep-trigger-glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><circle cx="8.5" cy="10.5" r="2.2"/><path d="M5 16.2c.5-1.7 1.9-2.6 3.5-2.6s3 .9 3.5 2.6M15 9.5h4M15 13h3"/></svg>
        <span class="ep-trigger-label"><?= $esc($profileCta) ?></span>
      </button>
      <dialog class="ep-dialog" id="event-profile-dialog" data-ep-dialog aria-label="<?= $esc($profileTitle) ?>">
        <div class="ep-shell" data-ep-theme="<?= $esc($themeSlug) ?>" data-ep-layout="<?= $esc($profileLayout) ?>" data-ep-intro-style="<?= $esc($profileIntroStyle) ?>" style="<?= $esc($profileThemeStyle) ?>">
          <div class="ep-toolbar">
            <button class="ep-skip" type="button" data-ep-skip<?= $profileVideoUrl === '' ? ' hidden' : '' ?>>
              <svg class="ep-btn-glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 5l8 7-8 7zM18 5v14"/></svg>
              Omitir intro
            </button>
            <button class="ep-close" type="button" data-ep-close aria-label="Cerrar perfil">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
          </div>

          <?php if ($profileVideoUrl !== ''): ?>
          <section class="ep-view ep-intro" data-ep-intro aria-label="Introducción de <?= $esc($profileTitle) ?>">
            <video data-ep-video src="<?= $esc($profileVideoUrl) ?>"<?= $profilePosterUrl !== '' ? ' poster="' . $esc($profilePosterUrl) . '"' : '' ?> preload="metadata" playsinline muted aria-label="Video de introducción"></video>
            <div class="ep-intro-copy">
              <p class="ep-kicker">Una historia muy especial</p>
              <h2 class="ep-intro-title"><?= $esc($profileTitle) ?></h2>
              <?php if ($profileIntroPhrase !== ''): ?><p class="ep-intro-phrase"><?= $esc($profileIntroPhrase) ?></p><?php endif; ?>
            </div>
            <?php // El icono lo alterna el CSS según `aria-pressed`, así el JS
                  // sigue tocando solo el texto y no borra el SVG. ?>
            <button class="ep-sound" type="button" data-ep-sound aria-pressed="false">
              <svg class="ep-btn-glyph ep-sound-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M11 5L6.5 9H3v6h3.5L11 19z"/><path d="M16 9.5l4.5 5M20.5 9.5l-4.5 5"/></svg>
              <svg class="ep-btn-glyph ep-sound-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M11 5L6.5 9H3v6h3.5L11 19z"/><path d="M15.5 9.2a4 4 0 0 1 0 5.6M18.2 6.8a7.6 7.6 0 0 1 0 10.4"/></svg>
              <span data-ep-sound-label>Activar sonido</span>
            </button>
          </section>
          <?php endif; ?>

          <section class="ep-view ep-profile" data-ep-profile<?= $profileVideoUrl !== '' ? ' hidden' : '' ?>>
            <div class="ep-profile-inner">
              <header class="ep-profile-header">
                <p class="ep-kicker">Un momento muy especial</p>
                <h2 class="ep-heading" id="event-profile-title"><?= $esc($profileTitle) ?></h2>
                <?php if ($profileIntroPhrase !== ''): ?><p class="ep-profile-lede"><?= $esc($profileIntroPhrase) ?></p><?php endif; ?>
              </header>

              <?php if (count($profilePeople) > 1): ?>
              <div class="ep-tabs" role="tablist" aria-label="Protagonistas">
                <?php foreach ($profilePeople as $personIndex => $person):
                    $personId = 'event-profile-person-' . ($personIndex + 1);
                    $personName = trim((string) ($person['display_name'] ?? $person['name'] ?? ''));
                ?>
                <button class="ep-tab" type="button" role="tab" id="event-profile-tab-<?= $personIndex + 1 ?>" aria-controls="<?= $personId ?>" aria-selected="<?= $personIndex === 0 ? 'true' : 'false' ?>" tabindex="<?= $personIndex === 0 ? '0' : '-1' ?>"><?= $esc($personName) ?></button>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

              <?php foreach ($profilePeople as $personIndex => $person):
                  $personName = trim((string) ($person['display_name'] ?? $person['name'] ?? ''));
                  $nickname = trim((string) ($person['nickname'] ?? ''));
                  $presentation = trim((string) ($person['intro_text'] ?? $person['presentation'] ?? ''));
                  $photo = is_array($person['photo'] ?? null) ? $person['photo'] : null;
                  $photoUrl = $profileMediaUrl($photo);
                  $personFields = is_array($person['fields'] ?? null) ? array_values($person['fields']) : [];
                  $panelId = 'event-profile-person-' . ($personIndex + 1);
                  $initial = function_exists('mb_substr') ? mb_substr($personName, 0, 1, 'UTF-8') : substr($personName, 0, 1);
                  // El acento sale del orden, nunca del nombre. El primero hereda
                  // el color del tema para no romper la coherencia visual con la
                  // invitación; a partir del segundo se usa la paleta de
                  // distinción para que se noten como personas distintas.
                  $personAccent = ($personIndex > 0 && $personAccents)
                      ? $personAccents[($personIndex - 1) % count($personAccents)]
                      : '';
                  $personStyle = $personAccent !== '' ? '--ep-person-accent:' . $personAccent . ';' : '';
              ?>
              <article class="ep-person" id="<?= $panelId ?>"<?= count($profilePeople) > 1 ? ' role="tabpanel" aria-labelledby="event-profile-tab-' . ($personIndex + 1) . '"' : '' ?><?= $personIndex > 0 ? ' hidden' : '' ?><?= $personStyle !== '' ? ' style="' . $esc($personStyle) . '"' : '' ?>>
                <div class="ep-person-hero">
                  <?php if ($photoUrl !== ''): ?>
                  <?php // El texto alternativo que escribió el admin describe mejor la
                        // foto que repetir el nombre, que ya está en el encabezado. ?>
                  <img class="ep-portrait" src="<?= $esc($photoUrl) ?>" alt="<?= $esc(trim((string) ($photo['alt_text'] ?? '')) !== '' ? $photo['alt_text'] : $personName) ?>" loading="lazy" decoding="async">
                  <?php else: ?>
                  <div class="ep-monogram" aria-hidden="true"><?= $esc($initial) ?></div>
                  <?php endif; ?>
                  <div class="ep-person-identity">
                    <h3 class="ep-person-name"><?= $esc($personName) ?></h3>
                    <?php if ($nickname !== ''): ?><p class="ep-person-nickname"><?= $esc($nickname) ?></p><?php endif; ?>
                  </div>
                  <?php if ($presentation !== ''): ?><p class="ep-presentation"><?= nl2br($esc($presentation)) ?></p><?php endif; ?>
                </div>

                <div class="ep-sections">
                  <?php foreach ($profileSections as $section):
                      $sectionKey = (string) ($section['key'] ?? '');
                      $sectionFields = array_values(array_filter($personFields, static function ($field) use ($sectionKey): bool {
                          if (!is_array($field) || (string) ($field['section_key'] ?? '') !== $sectionKey) {
                              return false;
                          }
                          return trim((string) ($field['value'] ?? $field['value_text'] ?? '')) !== '';
                      }));
                      usort($sectionFields, static function (array $a, array $b): int {
                          return ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
                      });
                      if (!$sectionFields) {
                          continue;
                      }
                      $sectionLabel = trim((string) ($section['label'] ?? 'Detalles'));
                      // Gustos, tallas y regalos se distinguen por dato, no por
                      // nombre: una sección nueva cae en el acento neutro.
                      $accentData = is_array($sectionAccents[$sectionKey] ?? null) ? $sectionAccents[$sectionKey] : [];
                      $accentTone = (string) ($accentData['tone'] ?? '');
                      $accentGlyph = trim((string) ($accentData['glyph'] ?? ''));
                      $sectionStyle = preg_match('/^#[0-9a-fA-F]{6}$/', $accentTone) === 1
                          ? '--ep-section-tone:' . $accentTone . ';'
                          : '';
                  ?>
                  <section class="ep-section" data-ep-section="<?= $esc($sectionKey) ?>"<?= $sectionStyle !== '' ? ' style="' . $esc($sectionStyle) . '"' : '' ?>>
                    <h4><?= $sectionIcon($sectionKey) ?><?= $esc($sectionLabel) ?></h4>
                    <dl class="ep-fields">
                      <?php foreach ($sectionFields as $field):
                          $fieldLabel = trim((string) ($field['label'] ?? ''));
                          $fieldValue = trim((string) ($field['value'] ?? $field['value_text'] ?? ''));
                      ?>
                      <div class="ep-field"><dt><?= $esc($fieldLabel) ?></dt><dd><?= $esc($fieldValue) ?></dd></div>
                      <?php endforeach; ?>
                    </dl>
                  </section>
                  <?php endforeach; ?>
                </div>
              </article>
              <?php endforeach; ?>

              <div class="ep-profile-foot">
                <button class="ep-close-bottom" type="button" data-ep-close>
                  <svg class="ep-btn-glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10 5l-7 7 7 7M3 12h18"/></svg>
                  Volver a la invitación
                </button>
              </div>
            </div>
          </section>
        </div>
      </dialog>
    </div>
  </section>
  <?php endif; ?>

  <footer class="inv-footer">
    <img class="inv-footer-logo" src="brand/cumpleclick-lockup.svg" alt="CumpleClick" width="160" height="40" loading="lazy">
    <p class="inv-footer-credit">
      Hecho por <a href="https://automatizatech.cl" target="_blank" rel="noopener">AutomatizaTech</a>
    </p>
  </footer>
</main>

<script src="assets/invitation.js?v=<?= $esc((string) filemtime(__DIR__ . '/assets/invitation.js')) ?>" defer></script>
<?php if ($eventProfile !== null): ?><script src="assets/event-profile.js?v=2" defer></script><?php endif; ?>
</body></html>
