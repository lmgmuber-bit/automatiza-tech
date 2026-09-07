<?php
/**
 * Escritor de PDF mínimo, sin dependencias.
 *
 * El proyecto no tiene librería de PDF y el hosting no permite instalar uno; los otros PDF
 * (manual, términos) se arman con un navegador en el computador de Luis, pero el comprobante
 * tiene que generarse EN EL SERVIDOR, en el momento en que se manda el correo. De ahí este
 * archivo: cubre exactamente lo que ese documento necesita y nada más.
 *
 * Alcance a propósito acotado:
 *  - Fuentes Helvetica y Helvetica-Bold. Son de las 14 estándar, así que no hay que incrustar
 *    el archivo de fuente: todo lector de PDF las tiene.
 *  - Texto en WinAnsi (CP1252). El acento y la eñe salen bien; lo que no exista en esa tabla
 *    lo translitera iconv en vez de romper el documento.
 *  - Imágenes JPEG embebidas tal cual (filtro DCTDecode). Un JPEG ya viene comprimido, así
 *    que el PDF lo guarda sin tocar; con PNG habría que descomprimir y recomprimir a mano.
 *  - Líneas y rectángulos, que es todo lo que pide la maqueta del comprobante.
 *
 * Las medidas van en milímetros y con el origen ARRIBA a la izquierda, que es como se piensa
 * una hoja; la conversión a puntos y al eje invertido del PDF queda acá adentro.
 */

final class CcPdf
{
    /** Ancho de cada carácter en Helvetica, en milésimas de em (tabla AFM estándar). */
    private const ANCHOS_NORMAL = [
        32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
        56 => 556, 57 => 556, 58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556,
        64 => 1015, 65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
        72 => 722, 73 => 278, 74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778,
        80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556,
        96 => 333, 97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556,
        104 => 556, 105 => 222, 106 => 222, 107 => 500, 108 => 222, 109 => 833, 110 => 556,
        111 => 556, 112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278, 117 => 556,
        118 => 500, 119 => 722, 120 => 500, 121 => 500, 122 => 500, 123 => 334, 124 => 260,
        125 => 334, 126 => 584,
    ];
    /** Lo mismo para Helvetica-Bold. */
    private const ANCHOS_NEGRITA = [
        32 => 278, 33 => 333, 34 => 474, 35 => 556, 36 => 556, 37 => 889, 38 => 722, 39 => 238,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
        56 => 556, 57 => 556, 58 => 333, 59 => 333, 60 => 584, 61 => 584, 62 => 584, 63 => 611,
        64 => 975, 65 => 722, 66 => 722, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
        72 => 722, 73 => 278, 74 => 556, 75 => 722, 76 => 611, 77 => 833, 78 => 722, 79 => 778,
        80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 333, 92 => 278, 93 => 333, 94 => 584, 95 => 556,
        96 => 333, 97 => 556, 98 => 611, 99 => 556, 100 => 611, 101 => 556, 102 => 333, 103 => 611,
        104 => 611, 105 => 278, 106 => 278, 107 => 556, 108 => 278, 109 => 889, 110 => 611,
        111 => 611, 112 => 611, 113 => 611, 114 => 389, 115 => 556, 116 => 333, 117 => 611,
        118 => 556, 119 => 778, 120 => 556, 121 => 556, 122 => 500, 123 => 389, 124 => 280,
        125 => 389, 126 => 584,
    ];
    /** Ancho de referencia para los acentuados: en Helvetica miden como su letra base. */
    private const ANCHO_POR_DEFECTO = 556;

    private float $ancho;   // en puntos
    private float $alto;    // en puntos
    /** @var string[] flujo de dibujo de cada página */
    private array $paginas = [];
    private string $actual = '';
    /** @var array<string,array{id:int,ancho:int,alto:int,datos:string}> */
    private array $imagenes = [];

    public function __construct(float $anchoMm = 210.0, float $altoMm = 297.0)
    {
        $this->ancho = $this->pt($anchoMm);
        $this->alto = $this->pt($altoMm);
    }

    private function pt(float $mm): float
    {
        return $mm * 72.0 / 25.4;
    }

    /** El PDF cuenta la altura desde abajo; acá se piensa desde arriba, como una hoja. */
    private function y(float $mm): float
    {
        return $this->alto - $this->pt($mm);
    }

    private static function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.') ?: '0';
    }

    public function nuevaPagina(): void
    {
        $this->paginas[] = $this->actual;
        $this->actual = '';
    }

    public function altoMm(): float
    {
        return $this->alto * 25.4 / 72.0;
    }

    public function anchoMm(): float
    {
        return $this->ancho * 25.4 / 72.0;
    }

    /** Pasa el texto a la tabla que entiende el PDF y escapa lo que rompería el flujo. */
    private static function codificar(string $texto): string
    {
        $win = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $texto);
        if ($win === false) {
            $win = preg_replace('/[^\x20-\x7E]/', '', $texto) ?? '';
        }
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $win);
    }

    /** Ancho del texto en milímetros, para poder alinearlo a la derecha o centrarlo. */
    public function anchoTextoMm(string $texto, float $pt, bool $negrita = false): float
    {
        $tabla = $negrita ? self::ANCHOS_NEGRITA : self::ANCHOS_NORMAL;
        $win = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $texto);
        $win = $win === false ? $texto : $win;
        $total = 0;
        for ($i = 0, $n = strlen($win); $i < $n; $i++) {
            $total += $tabla[ord($win[$i])] ?? self::ANCHO_POR_DEFECTO;
        }
        return $total / 1000.0 * $pt * 25.4 / 72.0;
    }

    /**
     * @param array{0:int,1:int,2:int} $rgb
     * @param string $alineacion  'izq' | 'der' | 'centro' (respecto de $xMm)
     */
    public function texto(float $xMm, float $yMm, string $texto, float $pt = 10.0,
                          bool $negrita = false, array $rgb = [0, 0, 0], string $alineacion = 'izq'): void
    {
        if ($texto === '') {
            return;
        }
        if ($alineacion !== 'izq') {
            $w = $this->anchoTextoMm($texto, $pt, $negrita);
            $xMm -= $alineacion === 'der' ? $w : $w / 2;
        }
        [$r, $g, $b] = $rgb;
        $this->actual .= sprintf(
            "BT /%s %s Tf %s %s %s rg %s %s Td (%s) Tj ET\n",
            $negrita ? 'F2' : 'F1',
            self::num($pt),
            self::num($r / 255), self::num($g / 255), self::num($b / 255),
            self::num($this->pt($xMm)), self::num($this->y($yMm)),
            self::codificar($texto)
        );
    }

    /**
     * Texto que se corta solo al llegar al ancho dado. Devuelve la Y donde quedó, para que
     * quien llama siga dibujando debajo sin tener que adivinar cuántas líneas salieron.
     */
    public function parrafo(float $xMm, float $yMm, float $anchoMm, string $texto, float $pt = 10.0,
                            bool $negrita = false, array $rgb = [0, 0, 0], float $interlineaMm = 0): float
    {
        $interlinea = $interlineaMm > 0 ? $interlineaMm : $pt * 0.42;
        $linea = '';
        foreach (preg_split('/\s+/u', trim($texto)) ?: [] as $palabra) {
            $prueba = $linea === '' ? $palabra : $linea . ' ' . $palabra;
            if ($linea !== '' && $this->anchoTextoMm($prueba, $pt, $negrita) > $anchoMm) {
                $this->texto($xMm, $yMm, $linea, $pt, $negrita, $rgb);
                $yMm += $interlinea;
                $linea = $palabra;
            } else {
                $linea = $prueba;
            }
        }
        if ($linea !== '') {
            $this->texto($xMm, $yMm, $linea, $pt, $negrita, $rgb);
            $yMm += $interlinea;
        }
        return $yMm;
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    public function linea(float $x1Mm, float $y1Mm, float $x2Mm, float $y2Mm,
                          float $grosorMm = 0.2, array $rgb = [0, 0, 0]): void
    {
        [$r, $g, $b] = $rgb;
        $this->actual .= sprintf(
            "%s %s %s RG %s w %s %s m %s %s l S\n",
            self::num($r / 255), self::num($g / 255), self::num($b / 255),
            self::num($this->pt($grosorMm)),
            self::num($this->pt($x1Mm)), self::num($this->y($y1Mm)),
            self::num($this->pt($x2Mm)), self::num($this->y($y2Mm))
        );
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    public function rectangulo(float $xMm, float $yMm, float $anchoMm, float $altoMm,
                               array $rgb = [240, 240, 240]): void
    {
        [$r, $g, $b] = $rgb;
        $this->actual .= sprintf(
            "%s %s %s rg %s %s %s %s re f\n",
            self::num($r / 255), self::num($g / 255), self::num($b / 255),
            self::num($this->pt($xMm)), self::num($this->y($yMm + $altoMm)),
            self::num($this->pt($anchoMm)), self::num($this->pt($altoMm))
        );
    }

    /**
     * Coloca un JPEG. La altura sale de la proporción real del archivo: pedir las dos medidas
     * invita a deformar el logo, que es lo único que se dibuja acá.
     * Devuelve la altura usada en milímetros.
     */
    public function imagenJpeg(string $ruta, float $xMm, float $yMm, float $anchoMm): float
    {
        $info = @getimagesize($ruta);
        $datos = @file_get_contents($ruta);
        if (!$info || $datos === false || ($info[2] ?? 0) !== IMAGETYPE_JPEG) {
            return 0.0;   // sin logo el comprobante sigue siendo válido; no se rompe por esto
        }
        $clave = 'Im' . (count($this->imagenes) + 1);
        if (!isset($this->imagenes[$ruta])) {
            $this->imagenes[$ruta] = [
                'nombre' => $clave,
                'ancho' => (int) $info[0],
                'alto' => (int) $info[1],
                'datos' => $datos,
            ];
        }
        $img = $this->imagenes[$ruta];
        $altoMm = $anchoMm * $img['alto'] / $img['ancho'];
        $this->actual .= sprintf(
            "q %s 0 0 %s %s %s cm /%s Do Q\n",
            self::num($this->pt($anchoMm)), self::num($this->pt($altoMm)),
            self::num($this->pt($xMm)), self::num($this->y($yMm + $altoMm)),
            $img['nombre']
        );
        return $altoMm;
    }

    /** Arma el archivo completo. Después de esto el documento no se sigue dibujando. */
    public function salida(): string
    {
        $paginas = $this->paginas;
        if ($this->actual !== '') {
            $paginas[] = $this->actual;
        }
        if (!$paginas) {
            $paginas[] = '';
        }

        $objetos = [];                       // 1-indexado: $objetos[1] es el objeto 1
        $idCatalogo = 1;
        $idPaginas = 2;
        $idFuente1 = 3;
        $idFuente2 = 4;

        $siguiente = 5;
        $recursos = [];
        foreach ($this->imagenes as $ruta => $img) {
            $this->imagenes[$ruta]['id'] = $siguiente;
            $recursos[] = '/' . $img['nombre'] . ' ' . $siguiente . ' 0 R';
            $siguiente++;
        }

        $idsPagina = [];
        $cuerposPagina = [];
        foreach ($paginas as $flujo) {
            $idPagina = $siguiente++;
            $idFlujo = $siguiente++;
            $idsPagina[] = $idPagina;
            $cuerposPagina[$idPagina] = [$idFlujo, $flujo];
        }

        $objetos[$idCatalogo] = "<< /Type /Catalog /Pages $idPaginas 0 R >>";
        $objetos[$idPaginas] = '<< /Type /Pages /Count ' . count($idsPagina) . ' /Kids ['
            . implode(' ', array_map(static fn($i) => "$i 0 R", $idsPagina)) . '] >>';
        $objetos[$idFuente1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[$idFuente2] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($this->imagenes as $img) {
            $objetos[$img['id']] = "<< /Type /XObject /Subtype /Image /Width {$img['ancho']}"
                . " /Height {$img['alto']} /ColorSpace /DeviceRGB /BitsPerComponent 8"
                . ' /Filter /DCTDecode /Length ' . strlen($img['datos']) . " >>\nstream\n"
                . $img['datos'] . "\nendstream";
        }

        $recursosTxt = '<< /Font << /F1 ' . $idFuente1 . ' 0 R /F2 ' . $idFuente2 . ' 0 R >>'
            . ($recursos ? ' /XObject << ' . implode(' ', $recursos) . ' >>' : '') . ' >>';

        foreach ($cuerposPagina as $idPagina => [$idFlujo, $flujo]) {
            $objetos[$idPagina] = '<< /Type /Page /Parent ' . $idPaginas . ' 0 R /MediaBox [0 0 '
                . self::num($this->ancho) . ' ' . self::num($this->alto) . '] /Resources '
                . $recursosTxt . ' /Contents ' . $idFlujo . " 0 R >>";
            // Comprimido si el servidor tiene zlib; si no, en claro. El PDF es válido igual.
            $comprimido = function_exists('gzcompress') ? gzcompress($flujo, 6) : false;
            $datos = $comprimido !== false ? $comprimido : $flujo;
            $filtro = $comprimido !== false ? ' /Filter /FlateDecode' : '';
            $objetos[$idFlujo] = '<< /Length ' . strlen($datos) . $filtro . " >>\nstream\n"
                . $datos . "\nendstream";
        }

        ksort($objetos);
        $salida = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $posiciones = [];
        foreach ($objetos as $id => $cuerpo) {
            $posiciones[$id] = strlen($salida);
            $salida .= "$id 0 obj\n$cuerpo\nendobj\n";
        }
        $inicioTabla = strlen($salida);
        $total = count($objetos) + 1;
        $salida .= "xref\n0 $total\n0000000000 65535 f \n";
        for ($i = 1; $i < $total; $i++) {
            $salida .= sprintf("%010d 00000 n \n", $posiciones[$i] ?? 0);
        }
        $salida .= "trailer\n<< /Size $total /Root $idCatalogo 0 R >>\nstartxref\n$inicioTabla\n%%EOF\n";
        return $salida;
    }
}
