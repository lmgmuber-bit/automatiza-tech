<?php
/**
 * Documentos de varias páginas sobre el motor de PDF propio.
 *
 * `CcPdf` (lib.pdf.php) dibuja donde se le dice: coordenadas absolutas, sin noción de "lo
 * que sigue va debajo" ni de que una página se llena. Para la boleta alcanzaba, porque cabe
 * en una hoja. El manual de la fiesta y el comprobante de Términos no: son textos largos, con
 * secciones, tablas y una imagen, y tienen que cortarse solos al llegar al pie de la hoja.
 *
 * Esta clase pone encima un cursor. Cada método dibuja "lo siguiente" y avanza; si no cabe,
 * cierra la página con su numeración y sigue en la próxima. Quien la usa nunca calcula una
 * coordenada.
 *
 * Los renglones se parten acá y no con `CcPdf::parrafo()`, porque ese método dibuja todas
 * las líneas de una vez y no puede detenerse a mitad de párrafo para cambiar de hoja. Acá
 * cada línea se dibuja por separado, comprobando antes si cabe.
 */

require_once __DIR__ . '/lib.pdf.php';

final class CcDocumento
{
    private CcPdf $pdf;
    private float $y;
    private int $pagina = 1;
    private string $pie = '';

    // Márgenes en mm. El inferior es más generoso porque ahí va la numeración.
    private const IZQ = 18.0;
    private const DER = 18.0;
    private const ARRIBA = 16.0;
    private const ABAJO = 20.0;

    private const TINTA = [34, 20, 54];
    private const HUMO = [110, 103, 134];
    private const ENLACE = [109, 63, 212];
    private const BORDE = [220, 214, 232];
    private const RELLENO = [246, 243, 252];
    // El magenta del degradado de la cabecera del correo: el mismo tono que ya usa la marca
    // y, en la forma del glifo, se lee como Instagram sin copiar su degradado.
    private const INSTAGRAM = [214, 48, 127];

    public function __construct()
    {
        $this->pdf = new CcPdf(210.0, 297.0);
        $this->y = self::ARRIBA;
    }

    /** Texto que va al pie de cada página, junto al número. */
    public function pie(string $texto): void
    {
        $this->pie = $texto;
    }

    public function anchoUtil(): float
    {
        return $this->pdf->anchoMm() - self::IZQ - self::DER;
    }

    private function limiteInferior(): float
    {
        return $this->pdf->altoMm() - self::ABAJO;
    }

    /** Si lo que viene no cabe, cierra la página y abre otra. */
    private function asegurar(float $altoMm): void
    {
        if ($this->y + $altoMm > $this->limiteInferior()) {
            $this->saltarPagina();
        }
    }

    public function saltarPagina(): void
    {
        $this->dibujarPie();
        $this->pdf->nuevaPagina();
        $this->pagina++;
        $this->y = self::ARRIBA;
    }

    private function dibujarPie(): void
    {
        $yPie = $this->pdf->altoMm() - 11.0;
        $this->pdf->linea(self::IZQ, $yPie - 4.0, $this->pdf->anchoMm() - self::DER, $yPie - 4.0, 0.2, self::BORDE);
        if ($this->pie !== '') {
            $this->pdf->texto(self::IZQ, $yPie, $this->pie, 8.0, false, self::HUMO);
        }
        $this->pdf->texto($this->pdf->anchoMm() - self::DER, $yPie, 'Página ' . $this->pagina, 8.0, false, self::HUMO, 'der');
    }

    /**
     * Parte un texto en renglones que caben en el ancho. Una "palabra" más larga que el
     * renglón (una URL, una huella SHA-256) se corta por letras: si no, se saldría del
     * margen y en un comprobante la huella tiene que verse entera.
     */
    private function partir(string $texto, float $ancho, float $pt, bool $negrita): array
    {
        // Un ancho no positivo es siempre un error de quien llama, y acá se convertiría en un
        // bucle infinito: ninguna letra "cabe", así que nunca se avanza. Vale más devolver el
        // texto sin partir —feo pero visible— que colgar el servidor sin decir por qué.
        if ($ancho <= 0) {
            return [trim($texto)];
        }
        $lineas = [];
        foreach (preg_split('/\r?\n/', $texto) as $bloque) {
            $linea = '';
            foreach (preg_split('/\s+/u', trim($bloque)) ?: [] as $palabra) {
                if ($palabra === '') { continue; }
                while ($this->pdf->anchoTextoMm($palabra, $pt, $negrita) > $ancho) {
                    // Cuánto de la palabra cabe en lo que queda del renglón.
                    $prefijo = $linea === '' ? '' : $linea . ' ';
                    $cabe = '';
                    foreach (preg_split('//u', $palabra, -1, PREG_SPLIT_NO_EMPTY) as $letra) {
                        if ($this->pdf->anchoTextoMm($prefijo . $cabe . $letra, $pt, $negrita) > $ancho) { break; }
                        $cabe .= $letra;
                    }
                    if ($cabe === '') {
                        // Ni una letra cabe al lado de lo que ya hay: cerrar el renglón y seguir.
                        if ($linea !== '') { $lineas[] = $linea; $linea = ''; continue; }
                        $cabe = mb_substr($palabra, 0, 1);
                    }
                    $lineas[] = $prefijo . $cabe;
                    $linea = '';
                    $palabra = mb_substr($palabra, mb_strlen($cabe));
                }
                if ($palabra === '') { continue; }
                $prueba = $linea === '' ? $palabra : $linea . ' ' . $palabra;
                if ($linea !== '' && $this->pdf->anchoTextoMm($prueba, $pt, $negrita) > $ancho) {
                    $lineas[] = $linea;
                    $linea = $palabra;
                } else {
                    $linea = $prueba;
                }
            }
            $lineas[] = $linea;
        }
        return $lineas;
    }

    /** Dibuja renglones ya partidos desde el cursor, cambiando de hoja cuando haga falta. */
    private function renglones(array $lineas, float $x, float $pt, bool $negrita, array $rgb, float $interlinea): void
    {
        foreach ($lineas as $l) {
            $this->asegurar($interlinea);
            if ($l !== '') { $this->pdf->texto($x, $this->y, $l, $pt, $negrita, $rgb); }
            $this->y += $interlinea;
        }
    }

    // ── Bloques ────────────────────────────────────────────────────────────

    /**
     * Imagen a todo el ancho de la hoja, sin márgenes, para la cabecera de la temática.
     *
     * **Siempre llena el ancho**, y el alto sale de la proporción de la imagen. El motor no
     * sabe recortar —solo escala—, así que si la imagen es más alta de lo que se quiere, hay
     * que recortarla antes con GD: eso lo hace quien compone la cabecera. Un intento anterior
     * de "recortar" achicándola dejaba 45 mm de blanco a cada lado en una hoja A4.
     *
     * @return float El alto que ocupó, para escribir encima con sobreBanner(). Cero si la
     *               imagen no existe o no es JPEG: el documento sigue, solo que sin ella.
     */
    public function banner(string $rutaJpeg): float
    {
        if (!is_file($rutaJpeg)) { return 0.0; }
        $info = @getimagesize($rutaJpeg);
        if (!$info || ($info[2] ?? 0) !== IMAGETYPE_JPEG) { return 0.0; }
        $anchoHoja = $this->pdf->anchoMm();
        $alto = $this->pdf->imagenJpeg($rutaJpeg, 0.0, 0.0, $anchoHoja);
        $this->y = $alto + 8.0;
        return $alto;
    }

    /**
     * Escribe encima de lo ya dibujado, en una posición fija, sin mover el cursor. Sirve para
     * poner el título sobre la cabecera de la temática: el texto va vectorial y nítido en vez
     * de quemado en el JPEG.
     *
     * `$desdeAbajoMm` mide desde el borde inferior de la cabecera hacia arriba, que es como
     * se piensa al componer: "el título va a 14 mm del pie de la imagen".
     */
    public function sobreBanner(string $texto, float $altoBannerMm, float $desdeAbajoMm,
                                float $pt, bool $negrita = true, array $rgb = [255, 255, 255]): void
    {
        $y = $altoBannerMm - $desdeAbajoMm;
        foreach ($this->partir($texto, $this->anchoUtil(), $pt, $negrita) as $l) {
            $this->pdf->texto(self::IZQ, $y, $l, $pt, $negrita, $rgb);
            $y += $pt * 0.48;
        }
    }

    public function titulo(string $texto, float $pt = 20.0): void
    {
        $lineas = $this->partir($texto, $this->anchoUtil(), $pt, true);
        $this->asegurar(count($lineas) * $pt * 0.48 + 4.0);
        $this->renglones($lineas, self::IZQ, $pt, true, self::TINTA, $pt * 0.48);
        $this->y += 3.0;
    }

    public function subtitulo(string $texto): void
    {
        $pt = 13.0;
        $lineas = $this->partir($texto, $this->anchoUtil(), $pt, true);
        // Un subtítulo no se queda solo al pie de la hoja: se lleva al menos dos renglones.
        $this->asegurar(count($lineas) * $pt * 0.48 + 12.0);
        $this->y += 4.0;
        $this->renglones($lineas, self::IZQ, $pt, true, self::TINTA, $pt * 0.48);
        $this->pdf->linea(self::IZQ, $this->y - 1.0, self::IZQ + $this->anchoUtil(), $this->y - 1.0, 0.3, self::BORDE);
        $this->y += 3.5;
    }

    public function texto(string $texto, float $pt = 10.0, bool $negrita = false, ?array $rgb = null): void
    {
        $lineas = $this->partir($texto, $this->anchoUtil(), $pt, $negrita);
        $this->renglones($lineas, self::IZQ, $pt, $negrita, $rgb ?? self::TINTA, $pt * 0.46);
        $this->y += 2.5;
    }

    /** Texto chico y gris: notas, huellas, advertencias. */
    public function nota(string $texto): void
    {
        $this->texto($texto, 8.5, false, self::HUMO);
    }

    /** @param string[] $items */
    public function lista(array $items, float $pt = 10.0): void
    {
        $sangria = 6.0;
        foreach ($items as $item) {
            $lineas = $this->partir($item, $this->anchoUtil() - $sangria, $pt, false);
            $this->asegurar($pt * 0.46);
            $this->pdf->texto(self::IZQ + 1.5, $this->y, '•', $pt, false, self::TINTA);
            $this->renglones($lineas, self::IZQ + $sangria, $pt, false, self::TINTA, $pt * 0.46);
            $this->y += 1.2;
        }
        $this->y += 1.5;
    }

    /**
     * Pasos numerados: el número en un círculo y el título en negrita, como en el manual.
     * @param array<int,array{0:string,1:string}> $pasos  [titulo, texto]
     */
    public function pasos(array $pasos): void
    {
        $pt = 10.0;
        $sangria = 11.0;
        foreach ($pasos as $i => [$tituloPaso, $textoPaso]) {
            $lineasT = $this->partir($tituloPaso, $this->anchoUtil() - $sangria, 10.5, true);
            $lineasX = $this->partir($textoPaso, $this->anchoUtil() - $sangria, $pt, false);
            $this->asegurar(10.5 * 0.48 + $pt * 0.46 * 2);
            $this->pdf->rectangulo(self::IZQ, $this->y - 3.6, 7.0, 7.0, [139, 92, 246]);
            // El motor solo alinea a izquierda o derecha: el número se centra a mano en la caja.
            $numero = (string) ($i + 1);
            $xNumero = self::IZQ + (7.0 - $this->pdf->anchoTextoMm($numero, 9.5, true)) / 2;
            $this->pdf->texto($xNumero, $this->y, $numero, 9.5, true, [255, 255, 255]);
            $this->renglones($lineasT, self::IZQ + $sangria, 10.5, true, self::TINTA, 10.5 * 0.48);
            $this->renglones($lineasX, self::IZQ + $sangria, $pt, false, self::TINTA, $pt * 0.46);
            $this->y += 3.0;
        }
        $this->y += 1.0;
    }

    /**
     * Tabla de dos columnas, etiqueta y valor. Cada fila mide lo que necesite la columna
     * más larga, y una fila no se parte entre dos hojas.
     *
     * Si el valor empieza con una dirección web, esa parte se pinta en color de enlace y
     * queda **clicable**: un manual en PDF con los enlaces muertos obliga a copiarlos a mano
     * desde el teléfono, que es justo lo que nadie hace.
     *
     * @param array<int,array{0:string,1:string}> $filas
     */
    public function tabla(array $filas, float $anchoEtiqueta = 52.0): void
    {
        $pt = 9.5;
        $inter = $pt * 0.46;
        $ancho = $this->anchoUtil();
        $relleno = 2.2;
        foreach ($filas as $k => [$etiqueta, $valor]) {
            $lE = $this->partir($etiqueta, $anchoEtiqueta - 2 * $relleno, $pt, true);

            // Un valor de una sola palabra que no cabe —una huella SHA-256, un token— se
            // achica hasta entrar en vez de partirse. Cortar una huella por la mitad la deja
            // inservible para verificarla, y si cabe o no depende de qué dígitos le tocaron:
            // en Helvetica una `f` mide la mitad que un `0`, así que dos huellas del mismo
            // largo se comportan distinto. Achicar es la única salida estable.
            $ptValor = $pt;
            $anchoValor = $ancho - $anchoEtiqueta - 2 * $relleno;
            if (strpos(trim($valor), ' ') === false) {
                while ($ptValor > 7.0 && $this->pdf->anchoTextoMm(trim($valor), $ptValor, false) > $anchoValor) {
                    $ptValor -= 0.25;
                }
            }
            $lV = $this->partir($valor, $anchoValor, $ptValor, false);
            $altoFila = max(count($lE), count($lV)) * $inter + 2 * $relleno;
            $this->asegurar($altoFila);
            if ($k % 2 === 0) {
                $this->pdf->rectangulo(self::IZQ, $this->y - $relleno - $pt * 0.3, $ancho, $altoFila, self::RELLENO);
            }
            $yInicio = $this->y;
            $yy = $yInicio;
            foreach ($lE as $l) { $this->pdf->texto(self::IZQ + $relleno, $yy, $l, $pt, true, self::TINTA); $yy += $inter; }

            // ¿El valor empieza con una URL? Entonces ese tramo se pinta como enlace y se
            // anota para que se pueda tocar. La URL puede ocupar varios renglones y el
            // renglón donde termina puede seguir con texto normal —«…?p=samantha (PIN 1234)»—,
            // así que se va consumiendo carácter a carácter en vez de comparar el renglón
            // entero: comparándolo entero, ese último renglón no se reconoce y el enlace
            // desaparece justo en las filas que más se tocan.
            $url = preg_match('#^(https?://\S+)#', $valor, $m) === 1 ? $m[1] : '';
            $restanteUrl = $url;
            $xValor = self::IZQ + $anchoEtiqueta + $relleno;
            $yy = $yInicio;
            foreach ($lV as $l) {
                $tramo = '';
                if ($restanteUrl !== '') {
                    $limpio = ltrim($l);
                    $n = 0;
                    $tope = min(strlen($limpio), strlen($restanteUrl));
                    while ($n < $tope && $limpio[$n] === $restanteUrl[$n]) { $n++; }
                    $tramo = substr($limpio, 0, $n);
                    $restanteUrl = ltrim(substr($restanteUrl, $n));
                }
                if ($tramo === '') {
                    $this->pdf->texto($xValor, $yy, $l, $ptValor, false, self::TINTA);
                } else {
                    // Nombre propio: `$ancho` es el de la tabla; reutilizarlo acá lo pisaba y
                    // dejaba la fila siguiente con un ancho negativo.
                    $anchoTramo = $this->pdf->anchoTextoMm($tramo, $ptValor, false);
                    $this->pdf->texto($xValor, $yy, $tramo, $ptValor, false, self::ENLACE);
                    $this->pdf->linea($xValor, $yy + 0.7, $xValor + $anchoTramo, $yy + 0.7, 0.15, self::ENLACE);
                    $this->pdf->enlace($xValor, $yy, $anchoTramo, $inter, $url);
                    $resto = substr(ltrim($l), strlen($tramo));
                    if ($resto !== '') {
                        $this->pdf->texto($xValor + $anchoTramo, $yy, $resto, $ptValor, false, self::TINTA);
                    }
                }
                $yy += $inter;
            }
            $this->y = $yInicio + $altoFila;
        }
        $this->y += 2.5;
    }

    /** Imagen JPEG centrada, al ancho pedido. */
    public function imagen(string $rutaJpeg, float $anchoMm): void
    {
        $info = @getimagesize($rutaJpeg);
        if (!$info || ($info[2] ?? 0) !== IMAGETYPE_JPEG) { return; }
        $alto = $anchoMm * $info[1] / $info[0];
        $this->asegurar($alto + 4.0);
        $x = self::IZQ + ($this->anchoUtil() - $anchoMm) / 2;
        $this->pdf->rectangulo($x - 1.0, $this->y - 1.0, $anchoMm + 2.0, $alto + 2.0, [255, 255, 255]);
        $this->pdf->linea($x - 1.0, $this->y - 1.0, $x + $anchoMm + 1.0, $this->y - 1.0, 0.2, self::BORDE);
        $this->pdf->linea($x - 1.0, $this->y + $alto + 1.0, $x + $anchoMm + 1.0, $this->y + $alto + 1.0, 0.2, self::BORDE);
        $this->pdf->imagenJpeg($rutaJpeg, $x, $this->y, $anchoMm);
        $this->y += $alto + 5.0;
    }

    /**
     * Una firma dibujada en pantalla llega como PNG con fondo transparente, y el motor solo
     * sabe de JPEG. Se aplana sobre blanco con GD y se dibuja. Devuelve false si no se pudo,
     * en cuyo caso el documento sigue sin la imagen y lo dice.
     */
    public function firmaPng(string $rutaPng, float $anchoMm = 70.0): bool
    {
        if (!is_file($rutaPng) || !function_exists('imagecreatefrompng')) { return false; }
        $png = @imagecreatefrompng($rutaPng);
        if (!$png) { return false; }
        $w = imagesx($png); $h = imagesy($png);
        $plano = imagecreatetruecolor($w, $h);
        imagefill($plano, 0, 0, imagecolorallocate($plano, 255, 255, 255));
        imagecopy($plano, $png, 0, 0, 0, 0, $w, $h);
        $tmp = tempnam(sys_get_temp_dir(), 'cc-firma-');
        $ok = imagejpeg($plano, $tmp, 92);
        // Sin imagedestroy(): desde PHP 8 los recursos GD se liberan solos y en 8.5 avisa.
        if (!$ok) { @unlink($tmp); return false; }
        $this->imagen($tmp, $anchoMm);
        // El motor ya copió los bytes; el archivo temporal no hace falta más.
        @unlink($tmp);
        return true;
    }

    public function espacio(float $mm = 4.0): void
    {
        $this->y += $mm;
    }

    /**
     * Cierre del documento: una línea y los dos logos lado a lado, con el contacto debajo.
     *
     * Se dibuja una sola vez, al final, y no en el pie de cada hoja: repetido en todas las
     * páginas deja de leerse y se vuelve ruido. Si no cabe en lo que queda de hoja, se pasa a
     * la siguiente entero, porque un cierre partido en dos se ve como un error.
     */
    /**
     * El glifo de Instagram, dibujado: cuadrado redondeado, lente y punto.
     *
     * Va vectorial y no como imagen por dos razones. El motor solo incrusta JPEG, y un JPEG
     * de 4 mm con el borde fino de este ícono sale sucio; y así el ícono no depende de que
     * exista un archivo en el servidor, que es justo lo que rompe un PDF en silencio.
     *
     * `$yMm` es el borde superior del cuadrado, no una línea base: acá se dibuja una figura.
     */
    private function glifoInstagram(float $xMm, float $yMm, float $ladoMm): void
    {
        $grosor = $ladoMm * 0.095;
        $medio = $ladoMm / 2;
        $this->pdf->rectanguloRedondeado($xMm, $yMm, $ladoMm, $ladoMm, $ladoMm * 0.30, $grosor, self::INSTAGRAM);
        $this->pdf->circulo($xMm + $medio, $yMm + $medio, $ladoMm * 0.245, $grosor, self::INSTAGRAM);
        $this->pdf->circulo($xMm + $ladoMm * 0.755, $yMm + $ladoMm * 0.245, $ladoMm * 0.062, 0.0, self::INSTAGRAM, true);
    }

    /**
     * @param array{texto?:string,url?:string} $instagram cuenta de Instagram al pie, con su
     *        ícono y como enlace tocable. Vacío = no se dibuja la línea.
     */
    public function cierreConLogos(string $logoIzq, string $logoDer, string $contacto,
                                   string $nota = '', array $instagram = []): void
    {
        $anchoIzq = 42.0;
        $anchoDer = 32.0;
        $this->asegurar(30.0);
        $this->y += 6.0;
        $this->pdf->linea(self::IZQ, $this->y, self::IZQ + $this->anchoUtil(), $this->y, 0.3, self::BORDE);
        $this->y += 7.0;

        $altoIzq = is_file($logoIzq) ? $this->pdf->imagenJpeg($logoIzq, self::IZQ, $this->y, $anchoIzq) : 0.0;
        $altoDer = 0.0;
        if (is_file($logoDer)) {
            // A la derecha, alineado por su borde: los dos logos comparten la línea base.
            $x = self::IZQ + $this->anchoUtil() - $anchoDer;
            $altoDer = $this->pdf->imagenJpeg($logoDer, $x, $this->y, $anchoDer);
        }
        $this->y += max($altoIzq, $altoDer, 8.0) + 5.0;

        if ($contacto !== '') { $this->texto($contacto, 9.5, false, self::HUMO); }

        $cuenta = trim((string) ($instagram['texto'] ?? ''));
        if ($cuenta !== '') {
            $pt = 9.5;
            $alto = $pt * 0.46;
            $lado = 3.6;
            $hueco = 2.0;
            $this->asegurar($alto);
            // El glifo se apoya en la línea base y sube: así queda a la altura de las
            // minúsculas en vez de flotar por encima del renglón.
            $this->glifoInstagram(self::IZQ, $this->y - $lado + 0.4, $lado);
            $x = self::IZQ + $lado + $hueco;
            $url = trim((string) ($instagram['url'] ?? ''));
            $this->pdf->texto($x, $this->y, $cuenta, $pt, false, $url !== '' ? self::ENLACE : self::HUMO);
            if ($url !== '') {
                $anchoCuenta = $this->pdf->anchoTextoMm($cuenta, $pt, false);
                $this->pdf->linea($x, $this->y + 0.7, $x + $anchoCuenta, $this->y + 0.7, 0.15, self::ENLACE);
                // La zona tocable arranca en el ícono: en el teléfono el dedo apunta ahí.
                $this->pdf->enlace(self::IZQ, $this->y, $lado + $hueco + $anchoCuenta, $alto, $url);
            }
            $this->y += $alto + 1.5;
        }

        if ($nota !== '') { $this->nota($nota); }
    }

    public function salida(): string
    {
        $this->dibujarPie();
        return $this->pdf->salida();
    }
}
