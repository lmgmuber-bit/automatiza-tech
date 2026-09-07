<?php
/**
 * puntajes.php — donde los juegos anotan su resultado y de donde sale la tabla de posiciones.
 *
 * GET  ?p=<slug>              devuelve las posiciones de esa fiesta
 * POST p, juego, jugador, puntaje   anota un intento
 *
 * **No lleva autenticación, y es a propósito.** Lo llama un juego que corre en la tablet de
 * la fiesta, sin sesión de admin y sin PIN: pedir una credencial obligaría a guardarla en el
 * JavaScript del juego, donde cualquiera la lee, y no protegería nada. Lo que sí se hace es
 * acotar el daño: solo se aceptan fiestas que existen y juegos del catálogo, el nombre se
 * limpia, el puntaje se acota y hay límite de peticiones por dirección.
 *
 * El peor caso real es que alguien con el QR del cartel meta puntajes falsos en la tabla de
 * un cumpleaños. Molesto, sin consecuencias, y se arregla borrando filas. No justifica pedir
 * una clave a un niño de cinco años frente a la tablet.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/lib.puntajes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function puntajes_responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$slug = (string) ($_REQUEST['p'] ?? '');
if (!cb_valid_public_slug($slug)) {
    puntajes_responder(400, ['ok' => false, 'error' => 'Falta la fiesta.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Un turno dura un minuto, así que ni el juego más rápido necesita más de esto. El
    // límite evita que un script llene la tabla de una fiesta en curso.
    $limite = cb_rate_limit('puntaje', cb_request_identity() . '|' . $slug, 120, 3600, 600);
    if (empty($limite['allowed'])) {
        puntajes_responder(429, ['ok' => false, 'error' => 'Demasiados envíos seguidos.']);
    }

    $r = cb_puntaje_anotar(
        $slug,
        (string) ($_POST['juego'] ?? ''),
        (string) ($_POST['jugador'] ?? ''),
        (int) ($_POST['puntaje'] ?? -1)
    );
    puntajes_responder(!empty($r['ok']) ? 200 : 400, $r);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    puntajes_responder(405, ['ok' => false, 'error' => 'Método no permitido.']);
}

if (cb_party_db_id($slug) === null) {
    puntajes_responder(404, ['ok' => false, 'error' => 'La fiesta no existe.']);
}

// El menú pide esto mismo al abrirse, así que viaja junto con las posiciones: una sola
// llamada en vez de dos, que en la tablet de la fiesta se nota. Nada de acá es privado —el
// nombre y la temática ya están impresos en el cartel que cuelga en la mesa.
$fiesta = cb_load_party_raw($slug);
$tema = (string) ($fiesta['tema'] ?? '');
$juegos = [];
foreach (cb_juegos_de_tema($tema) as $id => $j) {
    $juegos[] = ['id' => $id, 'nombre' => $j['nombre']];
}

puntajes_responder(200, [
    'ok' => true,
    'fiesta' => ['nombre' => (string) ($fiesta['nombre'] ?? ''), 'tema' => $tema],
    'juegos_disponibles' => $juegos,
] + cb_posiciones($slug));
