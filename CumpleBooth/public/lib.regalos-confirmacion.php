<?php
/**
 * Reglas de regalos del baby shower con confirmación (gift_mode = 'rsvp', migración 028).
 *
 * Quien confirma dice qué regalo lleva. Los regalos grandes (coche, cuna...) son de una sola
 * persona; los pañales admiten hasta CB_REGALO_TOPE_PANALES; la ropa, el aseo o el dinero son
 * libres. La categoría la decide la palabra que ENCABEZA el texto, no cualquier palabra:
 * «mantita para la cuna» no choca con una cuna.
 */

const CB_REGALO_TOPE_PANALES = 3;

const CB_REGALO_RELLENO = ['de', 'del', 'para', 'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'mi', 'al', 'y', 'con', 'bebe', 'baby'];

/** Sinónimos de regalos de una sola persona: clave => frases (se normalizan al usarlas). */
const CB_REGALO_GRUPOS_UNICOS = [
    'coche' => ['coche', 'cochecito', 'carrito', 'carriola', 'coche paseo', 'carro paseo', 'travel system'],
    'cuna' => ['cuna', 'cunita'],
    'corral' => ['corral', 'cuna corral', 'practicuna', 'cuna viaje'],
    'moises' => ['moises', 'cuna moises'],
    'silla-auto' => ['silla auto', 'silla carro', 'silla coche', 'butaca'],
    'banera' => ['banera', 'tina', 'tinita'],
    'mochila-portabebe' => ['mochila portabebe', 'portabebe', 'canguro', 'mochila ergonomica'],
    'bolso-maternal' => ['bolso maternal', 'mochila maternal', 'panalera'],
    'monitor' => ['monitor', 'intercomunicador', 'babycall'],
    'cambiador' => ['cambiador', 'mudador'],
    'silla-comer' => ['silla comer', 'silla alta', 'trona'],
    'extractor' => ['extractor leche', 'sacaleche'],
    'esterilizador' => ['esterilizador'],
    'silla-nido' => ['silla nido', 'nido', 'mecedora', 'bouncer'],
];

const CB_REGALO_LIBRES = ['ropa', 'ropita', 'body', 'pilucho', 'osito', 'pijama', 'calcetin', 'gorro', 'mantita', 'manta',
    'frazada', 'enterito', 'conjunto', 'polera', 'pantalon', 'chaleco', 'zapatito', 'babero', 'toallita', 'toallita humeda',
    'shampoo', 'jabon', 'crema', 'colonia', 'aceite', 'algodon', 'aseo', 'kit aseo', 'dinero', 'plata', 'efectivo',
    'gift card', 'giftcard', 'tarjeta regalo', 'transferencia', 'aporte'];

const CB_REGALO_GENERICOS = ['sorpresa', 'sorpresita', 'regalo', 'regalito', 'juguete', 'algo', 'cosa', 'detalle', 'nose',
    'sabe', 'lindo', 'bonito', 'pequeno', 'grande', 'especial', 'otro'];

const CB_REGALO_MENSAJES = [
    'regalo_requerido' => 'Cuéntanos qué regalo llevarás.',
    'regalo_generico' => 'Cuéntanos qué regalo llevarás, por ejemplo: un body, una mantita o un coche.',
    'regalo_tomado' => 'Ese regalo ya lo trae alguien, elige otro.',
    'panales_completos' => 'Ya hay 3 personas que llevan pañales. Elige otro regalo, por favor.',
];

/** Minúsculas, sin tildes, sin puntuación, sin relleno y en singular. Devuelve los tokens unidos por espacio. */
function cb_regalo_normalizar(string $texto): string
{
    return implode(' ', cb_regalo_tokens($texto));
}

/** @return string[] */
function cb_regalo_tokens(string $texto): array
{
    $t = mb_strtolower($texto, 'UTF-8');
    $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    $t = preg_replace('/[^a-z0-9]+/', ' ', $t) ?? '';
    $salida = [];
    foreach (preg_split('/\s+/', trim($t), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $palabra) {
        if (in_array($palabra, CB_REGALO_RELLENO, true)) {
            continue;
        }
        $original = $palabra;
        if (strlen($palabra) > 3) {
            if (preg_match('/[lrndzj]es$/', $palabra)) {
                $palabra = substr($palabra, 0, -2);
            } elseif (substr($palabra, -1) === 's') {
                $palabra = substr($palabra, 0, -1);
            }
        }
        if ($palabra !== $original && in_array($palabra, CB_REGALO_RELLENO, true)) {
            continue; // «bebés» -> «bebe»: también es relleno
        }
        $salida[] = $palabra;
    }
    return $salida;
}

/** @return array{categoria:string, clave:string} */
function cb_regalo_clasificar(string $texto): array
{
    $tokens = cb_regalo_tokens($texto);
    if ($tokens === []) {
        return ['categoria' => 'generico', 'clave' => ''];
    }
    $normal = implode(' ', $tokens);

    // 1. Grupo único: el sinónimo más largo que sea prefijo de los tokens.
    $mejor = null;
    $mejorLargo = 0;
    foreach (CB_REGALO_GRUPOS_UNICOS as $clave => $frases) {
        foreach ($frases as $frase) {
            $ft = cb_regalo_tokens($frase);
            $n = count($ft);
            if ($n > $mejorLargo && array_slice($tokens, 0, $n) === $ft) {
                $mejor = $clave;
                $mejorLargo = $n;
            }
        }
    }
    if ($mejor !== null) {
        return ['categoria' => 'unico', 'clave' => $mejor];
    }

    // 2. Pañales.
    if (in_array('panal', $tokens, true)) {
        return ['categoria' => 'panales', 'clave' => 'panales'];
    }

    // 3. Libres: algún token o bigrama de la lista libre (el primero manda, pero «set ropa» también cuenta).
    $libres = array_map('cb_regalo_normalizar', CB_REGALO_LIBRES);
    $piezas = $tokens;
    for ($i = 0; $i + 1 < count($tokens); $i++) {
        $piezas[] = $tokens[$i] . ' ' . $tokens[$i + 1];
    }
    foreach ($piezas as $pieza) {
        if (in_array($pieza, $libres, true)) {
            return ['categoria' => 'libre', 'clave' => $normal];
        }
    }

    // 4. Genérico: todo son palabras vagas.
    if (array_diff($tokens, array_map('cb_regalo_normalizar', CB_REGALO_GENERICOS)) === []) {
        return ['categoria' => 'generico', 'clave' => $normal];
    }

    // 5. Cualquier otra cosa se trata como regalo único.
    return ['categoria' => 'unico', 'clave' => $normal];
}

/**
 * ¿Puede esta invitación recibir este regalo? Compara contra los regalos aprobados (uno oculto queda libre).
 *
 * @return array{ok:true,categoria:string,clave:string}|array{ok:false,error:string,mensaje:string}
 */
function cb_regalo_validar(int $invitationId, string $texto): array
{
    $falla = static fn(string $error): array => ['ok' => false, 'error' => $error, 'mensaje' => CB_REGALO_MENSAJES[$error]];
    if (trim($texto) === '') {
        return $falla('regalo_requerido');
    }
    $c = cb_regalo_clasificar($texto);
    if ($c['categoria'] === 'generico') {
        return $falla('regalo_generico');
    }
    if ($c['categoria'] !== 'libre') {
        $stmt = cb_pdo()->prepare('SELECT title FROM cc_gift_items WHERE invitation_id = ? AND moderation_status = ?');
        $stmt->execute([$invitationId, 'approved']);
        $mismos = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $titulo) {
            $o = cb_regalo_clasificar((string) $titulo);
            if ($o['categoria'] === $c['categoria'] && $o['clave'] === $c['clave']) {
                $mismos++;
            }
        }
        if ($c['categoria'] === 'unico' && $mismos > 0) {
            return $falla('regalo_tomado');
        }
        if ($c['categoria'] === 'panales' && $mismos >= CB_REGALO_TOPE_PANALES) {
            return $falla('panales_completos');
        }
    }
    return ['ok' => true, 'categoria' => $c['categoria'], 'clave' => $c['clave']];
}
