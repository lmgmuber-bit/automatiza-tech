<?php
/**
 * Generador de COTIZACIONES en PDF usando FPDF
 * Basado en invoice-pdf-fpdf.php pero adaptado para cotizaciones
 * - Muestra "COTIZACION" en lugar de "FACTURA"
 * - Numero formato: C-AT-YYYYMMDD-XXXX
 * - Validez: 3 dias desde emision
 * - Incluye IVA para Chile, sin IVA para otros paises
 * - Moneda dinamica: CLP (Chile) o USD (otros paises)
 */

// Asegurarse de que estamos en contexto WordPress
if (!function_exists('get_template_directory')) {
    die('Este archivo debe ser cargado desde WordPress');
}

require_once(get_template_directory() . '/lib/fpdf.php');
require_once(get_template_directory() . '/lib/qrcode.php');

// Usar la misma función helper de utf8_to_latin1
if (!function_exists('utf8_to_latin1')) {
    function utf8_to_latin1($text) {
        if (empty($text)) {
            return $text;
        }
        
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        }
        
        if (function_exists('utf8_decode')) {
            return @utf8_decode($text);
        }
        
        return $text;
    }
}

class QuotationPDFFPDF extends FPDF {
    
    private $contact_data;
    private $plan_data;
    private $quotation_number;
    private $valid_until;
    private $contact_country;
    private $currency;
    private $currency_symbol;
    
    // Colores corporativos
    private $primary_color = array(0, 150, 199); // #0096C7
    private $secondary_color = array(0, 191, 179); // #00BFB3
    private $accent_color = array(255, 152, 0); // #FF9800 - Naranja para cotización
    private $text_color = array(33, 33, 33);
    private $gray_color = array(117, 117, 117);
    
    public function __construct($contact_data, $plan_data, $quotation_number = '', $valid_until = '') {
        parent::__construct('P', 'mm', 'A4');
        $this->contact_data = $contact_data;
        
        // Configurar zona horaria de Chile al inicio
        date_default_timezone_set('America/Santiago');
        
        // DEBUG: Log de lo que recibe el constructor
        error_log("DEBUG QuotationPDFFPDF Constructor: tipo de plan_data = " . gettype($plan_data));
        if (is_array($plan_data)) {
            error_log("DEBUG QuotationPDFFPDF: Recibiendo array con " . count($plan_data) . " planes");
            $this->plan_data = $plan_data;
        } else {
            error_log("DEBUG QuotationPDFFPDF: Recibiendo objeto único, convirtiéndolo a array");
            $this->plan_data = array($plan_data);
        }
        
        error_log("DEBUG QuotationPDFFPDF: plan_data final contiene " . count($this->plan_data) . " planes");
        
        $this->quotation_number = $quotation_number;
        $this->valid_until = $valid_until;
        
        // Detectar país del contacto y configurar moneda
        $this->contact_country = $this->detect_contact_country($contact_data);
        $this->configure_currency($this->contact_country);
        
        error_log("DEBUG QuotationPDFFPDF: País detectado = {$this->contact_country}, Moneda = {$this->currency}");
        
        // Configurar márgenes del documento: 15mm izquierda, 15mm derecha
        $this->SetMargins(15, 10, 15);
        $this->SetAutoPageBreak(true, 15);
        $this->AddPage();
    }
    
    /**
     * Detectar país del contacto basado en campo country o código telefónico
     */
    private function detect_contact_country($contact_data) {
        // Prioridad 1: Campo country en la base de datos
        if (isset($contact_data->country) && !empty($contact_data->country)) {
            return strtoupper($contact_data->country);
        }
        
        // Prioridad 2: Detectar por código telefónico de WhatsApp
        if (isset($contact_data->phone) && !empty($contact_data->phone)) {
            $phone = $contact_data->phone;
            
            // Códigos telefónicos internacionales
            $country_codes = [
                '+56' => 'CL',  // Chile
                '+1'  => 'US',  // USA/Canadá
                '+54' => 'AR',  // Argentina
                '+57' => 'CO',  // Colombia
                '+52' => 'MX',  // México
                '+51' => 'PE',  // Perú
                '+34' => 'ES',  // España
                '+55' => 'BR',  // Brasil
            ];
            
            foreach ($country_codes as $code => $country) {
                if (strpos($phone, $code) === 0) {
                    return $country;
                }
            }
        }
        
        // Por defecto: Chile
        return 'CL';
    }
    
    /**
     * Configurar moneda según el país
     */
    private function configure_currency($country) {
        if ($country === 'CL') {
            // Chile: Pesos chilenos con IVA
            $this->currency = 'CLP';
            $this->currency_symbol = '$';
        } else {
            // Otros países: Dólares americanos sin IVA
            $this->currency = 'USD';
            $this->currency_symbol = 'USD $';
        }
    }
    
    // Header
    function Header() {
        // Fondo del header con gradiente visual
        $this->SetFillColor(245, 248, 252);
        $this->Rect(0, 0, 210, 45, 'F');
        
        // Buscar logo
        $logo_paths = array(
            get_template_directory() . '/assets/images/logo-automatiza-tech.png',
            get_template_directory() . '/assets/images/solo-logo.svg',
            get_template_directory() . '/lib/tutorial/logo.png'
        );
        
        $logo_found = false;
        foreach ($logo_paths as $logo_path) {
            if (file_exists($logo_path)) {
                $ext = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));
                if ($ext === 'png') {
                    $this->Image($logo_path, 15, 8, 35);
                    $logo_found = true;
                    break;
                }
            }
        }
        
        if (!$logo_found) {
            $this->SetFont('Arial', 'B', 24);
            $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
            $this->SetXY(18, 14);
            $this->Cell(50, 10, 'AutomatizaTech', 0, 0, 'L');
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
            $this->SetXY(18, 24);
            $this->Cell(50, 4, utf8_to_latin1('Transformación Digital'), 0, 0, 'L');
        }
        
        // Info empresa (derecha)
        $company_name = get_option('company_name', 'AutomatizaTech SpA');
        $company_email = get_option('company_email', 'info@automatizatech.shop');
        $company_phone = get_option('company_phone', '+56 9 4033 1127');
        $company_website = get_option('company_website', 'www.automatizatech.shop');
        
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        $this->SetXY(110, 12);
        $this->Cell(85, 6, utf8_to_latin1($company_name), 0, 1, 'R');
        
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
        $this->SetX(110);
        $this->Cell(85, 4, utf8_to_latin1($company_email), 0, 1, 'R');
        $this->SetX(110);
        $this->Cell(85, 4, utf8_to_latin1($company_phone), 0, 1, 'R');
        $this->SetX(110);
        $this->Cell(85, 4, utf8_to_latin1($company_website), 0, 1, 'R');
        
        // Línea separadora
        $this->SetDrawColor($this->secondary_color[0], $this->secondary_color[1], $this->secondary_color[2]);
        $this->SetLineWidth(0.8);
        $this->Line(15, 42, 195, 42);
        
        $this->Ln(10);
    }
    
    // Footer
    function Footer() {
        $this->SetY(-15);
        
        // Línea separadora
        $this->SetDrawColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
        $this->SetLineWidth(0.3);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        
        $this->Ln(3);
        
        // Texto del footer
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
        
        // Mensaje de validez con tildes
        $this->Cell(0, 4, utf8_to_latin1('Válida únicamente por 3 días desde su emisión.'), 0, 1, 'C');
        
        // Número de página con tilde
        $this->SetY(-10);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 4, utf8_to_latin1('Página ') . $this->PageNo() . ' de {nb}', 0, 0, 'C');
    }
    
    /**
     * Construir el PDF completo
     */
    public function build() {
        $this->AliasNbPages();
        
        // Agregar espacio después del header para evitar colisión
        $this->Ln(10);
        
        // Título principal - COTIZACIÓN
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor($this->accent_color[0], $this->accent_color[1], $this->accent_color[2]);
        $this->Cell(0, 10, utf8_to_latin1('COTIZACIÓN'), 0, 1, 'C');
        
        $this->Ln(3);
        
        // Número de cotización y validez
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        $this->Cell(0, 6, utf8_to_latin1('Nro de Cotización: ') . $this->quotation_number, 0, 1, 'C');
        
        // Fecha de emisión y validez
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor($this->gray_color[0], $this->gray_color[1], $this->gray_color[2]);
        $fecha_emision = date('d-m-Y H:i');
        $this->Cell(0, 5, utf8_to_latin1('Fecha de emisión: ') . $fecha_emision, 0, 1, 'C');
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->accent_color[0], $this->accent_color[1], $this->accent_color[2]);
        $this->Cell(0, 5, utf8_to_latin1('Válida hasta: ') . date('d-m-Y', strtotime($this->valid_until)), 0, 1, 'C');
        
        $this->Ln(8);
        
        // Información del contacto
        $this->draw_contact_info();
        
        $this->Ln(5);
        
        // Tabla de planes/servicios
        $this->draw_services_table();
        
        $this->Ln(8);
        
        // Condiciones y notas
        $this->draw_conditions();
        
        // QR Code (opcional)
        $this->draw_qr_code();
    }
    
    /**
     * Dibujar información del contacto
     */
    private function draw_contact_info() {
        // Rectángulo de fondo
        $this->SetFillColor(248, 249, 250);
        $this->Rect(15, $this->GetY(), 180, 28, 'F');
        
        $start_y = $this->GetY();
        
        // Título sección
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->SetXY(20, $start_y + 4);
        $this->Cell(0, 5, utf8_to_latin1('DATOS DEL CONTACTO'), 0, 1);
        
        // Datos del contacto
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        
        $info_y = $start_y + 11;
        $this->SetXY(20, $info_y);
        
        // Columna izquierda
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(25, 5, 'Nombre:', 0, 0);
        $this->SetFont('Arial', '', 9);
        $this->Cell(60, 5, utf8_to_latin1($this->contact_data->name), 0, 0);
        
        // Columna derecha
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(20, 5, 'Email:', 0, 0);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, utf8_to_latin1($this->contact_data->email), 0, 1);
        
        $this->SetX(20);
        if (!empty($this->contact_data->company)) {
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 5, 'Empresa:', 0, 0);
            $this->SetFont('Arial', '', 9);
            $this->Cell(60, 5, utf8_to_latin1($this->contact_data->company), 0, 0);
        }
        
        if (!empty($this->contact_data->phone)) {
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(20, 5, utf8_to_latin1('Teléfono:'), 0, 0);
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 5, utf8_to_latin1($this->contact_data->phone), 0, 1);
        }
        
        $this->SetY($start_y + 28);
    }
    
    /**
     * Dibujar tabla de servicios cotizados
     */
    private function draw_services_table() {
        // Título
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Cell(0, 6, utf8_to_latin1('SERVICIOS COTIZADOS'), 0, 1);
        
        $this->Ln(2);
        
        // Encabezados de tabla
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor($this->secondary_color[0], $this->secondary_color[1], $this->secondary_color[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor($this->secondary_color[0], $this->secondary_color[1], $this->secondary_color[2]);
        $this->SetLineWidth(0.3);
        
        $w = array(15, 90, 35, 40); // Anchos de columnas
        // Header dinámico según moneda
        $price_header = ($this->currency === 'CLP') ? 'Precio CLP' : 'Precio USD';
        $headers = array('#', utf8_to_latin1('Descripción'), 'Cantidad', $price_header);
        
        for ($i = 0; $i < count($headers); $i++) {
            $this->Cell($w[$i], 8, $headers[$i], 1, 0, 'C', true);
        }
        $this->Ln();
        
        // Filas de la tabla
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        $this->SetFillColor(250, 250, 250);
        
        // Soportar múltiples planes
        $items = is_array($this->plan_data) ? $this->plan_data : array($this->plan_data);
        error_log("DEBUG Quotation PDF Render: Renderizando " . count($items) . " items en la tabla");
        
        $fill = false;
        $total = 0;
        
        foreach ($items as $index => $item) {
            $item_num = $index + 1;
            $item_name = isset($item->name) ? utf8_to_latin1($item->name) : 'Servicio ' . $item_num;
            $item_price = $this->get_item_price($item);
            $total += $item_price;
            
            error_log("DEBUG Quotation PDF Render: Item " . $item_num . " - Nombre: {$item->name}, Precio: {$item_price}");
            
            // Número
            $this->Cell($w[0], 10, $item_num, 'LR', 0, 'C', $fill);
            
            // Descripción (puede ser multilínea)
            $x = $this->GetX();
            $y = $this->GetY();
            $this->MultiCell($w[1], 5, $item_name, 'LR', 'L', $fill);
            $current_y = $this->GetY();
            $height = $current_y - $y;
            
            // Si la descripción ocupó más de una línea, ajustar
            if ($height > 10) {
                $this->SetXY($x + $w[1], $y);
            } else {
                $this->SetXY($x + $w[1], $y);
            }
            
            // Cantidad
            $this->Cell($w[2], 10, '1', 'LR', 0, 'C', $fill);
            
            // Precio
            $this->Cell($w[3], 10, $this->currency_symbol . ' ' . number_format($item_price, 0, ',', '.'), 'LR', 0, 'R', $fill);
            
            $this->Ln();
            $fill = !$fill;
        }
        
        // Línea de cierre de tabla
        $this->Cell(array_sum($w), 0, '', 'T');
        $this->Ln();
        
        // Calcular IVA y totales (solo para Chile)
        $subtotal = $total;
        $iva = 0;
        $total_con_iva = $total;
        
        if ($this->contact_country === 'CL') {
            $iva = $subtotal * 0.19;
            $total_con_iva = $subtotal + $iva;
        }
        
        // SUBTOTAL
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        $this->SetFillColor(245, 245, 245);
        $this->Cell($w[0] + $w[1] + $w[2], 8, 'Subtotal:', 1, 0, 'R', true);
        $this->Cell($w[3], 8, $this->currency_symbol . ' ' . number_format($subtotal, 0, ',', '.'), 1, 1, 'R', true);
        
        // IVA (solo para Chile)
        if ($this->contact_country === 'CL') {
            $this->Cell($w[0] + $w[1] + $w[2], 8, 'IVA (19%):', 1, 0, 'R', true);
            $this->Cell($w[3], 8, $this->currency_symbol . ' ' . number_format($iva, 0, ',', '.'), 1, 1, 'R', true);
        }
        
        // TOTAL FINAL
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Cell($w[0] + $w[1] + $w[2], 10, 'TOTAL COTIZADO:', 1, 0, 'R', true);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell($w[3], 10, $this->currency_symbol . ' ' . number_format($total_con_iva, 0, ',', '.'), 1, 1, 'R', true);
    }
    
    /**
     * Dibujar condiciones y notas
     */
    private function draw_conditions() {
        $start_y = $this->GetY();
        
        // Ancho de página A4: 210mm
        // Márgenes del documento: 15mm izquierda + 15mm derecha = 30mm
        // Ancho disponible: 210 - 30 = 180mm
        $page_width = 210;
        $left_margin = 15;
        $right_margin = 15;
        $content_width = $page_width - $left_margin - $right_margin; // 180mm
        
        // Título
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->accent_color[0], $this->accent_color[1], $this->accent_color[2]);
        $this->SetX($left_margin);
        $this->Cell($content_width, 5, utf8_to_latin1('CONDICIONES DE LA COTIZACIÓN'), 0, 1, 'C');
        
        $this->Ln(2); // Espacio después del título
        
        // Condiciones - texto profesional compacto y justificado
        $this->SetFont('Arial', '', 7); // Fuente reducida para mejor ajuste
        $this->SetTextColor($this->text_color[0], $this->text_color[1], $this->text_color[2]);
        
        // Condiciones profesionales según moneda - versión compacta
        if ($this->currency === 'CLP') {
            $conditions = array(
                utf8_to_latin1('Validez: tres (3) días calendario desde la emisión. Los precios y condiciones quedan sujetos a revisión posterior a este plazo.'),
                utf8_to_latin1('Precios expresados en pesos chilenos (CLP) e incluyen IVA (19%). Este documento es una cotización comercial sin efectos tributarios; la factura tributaria electrónica se emitirá al contratar el servicio.'),
                utf8_to_latin1('Los plazos de implementación, desarrollo y entrega serán acordados mutuamente al confirmar la contratación, considerando disponibilidad y complejidad del proyecto.'),
                utf8_to_latin1('Para proceder con la contratación o solicitar aclaraciones, responda a este correo o contáctenos por nuestros canales oficiales.')
            );
        } else {
            $conditions = array(
                utf8_to_latin1('Validez: tres (3) días calendario desde la emisión. Los precios y condiciones quedan sujetos a revisión posterior a este plazo.'),
                utf8_to_latin1('Precios expresados en dolares americanos (USD) sin incluir impuestos locales. Este documento es una cotización comercial sin efectos tributarios; la factura comercial se emitirá al contratar el servicio.'),
                utf8_to_latin1('Los plazos de implementación, desarrollo y entrega serán acordados mutuamente al confirmar la contratación, considerando disponibilidad y complejidad del proyecto.'),
                utf8_to_latin1('Para proceder con la contratación o solicitar aclaraciones, responda a este correo o contáctenos por nuestros canales oficiales.')
            );
        }
        
        // Calcular altura necesaria para el cuadro (antes de dibujarlo)
        $line_height = 3.2; // Reducido para texto más compacto
        $lines_count = 0;
        foreach ($conditions as $condition) {
            // Estimar cuántas líneas ocupará cada condición
            $lines_count += ceil($this->GetStringWidth($condition) / $content_width);
        }
        
        $title_height = 6; // Título + espacio
        $text_height = count($conditions) * $line_height * 2.0; // Reducido para texto compacto
        $padding_top = 3;
        $padding_bottom = 2;
        $box_height = $title_height + $text_height + $padding_top + $padding_bottom;
        
        // Ahora dibujar el cuadro con la altura correcta (sin espacio extra al final)
        $this->SetFillColor(255, 248, 225); // Fondo amarillo suave
        $this->SetDrawColor($this->accent_color[0], $this->accent_color[1], $this->accent_color[2]);
        $this->SetLineWidth(0.5);
        $this->Rect($left_margin, $start_y, $content_width, $box_height, 'DF');
        
        // Volver a posicionar para escribir el título (ya está posicionado)
        $this->SetXY($left_margin, $start_y + 3);
        $this->Cell($content_width, 5, utf8_to_latin1('CONDICIONES DE LA COTIZACIÓN'), 0, 1, 'C');
        
        // El texto usa el margen izquierdo del documento
        $this->SetXY($left_margin, $start_y + 9);
        foreach ($conditions as $index => $condition) {
            // Usar 'J' para justificado, mismo ancho que el cuadro
            $this->MultiCell($content_width, $line_height, $condition, 0, 'J');
            $this->SetX($left_margin); // Resetear X al margen izquierdo
            // Espacio mínimo entre condiciones
            if ($index < count($conditions) - 1) {
                $this->Ln(0.8);
            }
        }
        
        // Asegurar que el cursor esté después del cuadro con espacio adicional para el QR
        $this->SetY($start_y + $box_height + 3); // 3mm de espacio adicional
    }
    
    /**
     * Dibujar código QR
     */
    private function draw_qr_code() {
        // Datos para el QR
        $plans_names = array();
        foreach ($this->plan_data as $plan) {
            $plans_names[] = $plan->name;
        }
        $plans_text = implode(' + ', $plans_names);
        
        $qr_data = "COTIZACION: {$this->quotation_number}\nContacto: {$this->contact_data->name}\nPlanes: {$plans_text}\nValida hasta: " . date('d-m-Y', strtotime($this->valid_until));
        
        try {
            $qr_temp_path = sys_get_temp_dir() . '/qr_' . md5($qr_data) . '.png';
            QRcode::png($qr_data, $qr_temp_path, 'L', 4);
            
            if (file_exists($qr_temp_path)) {
                $this->Image($qr_temp_path, 170, $this->GetY() + 5, 25, 25);
                @unlink($qr_temp_path);
            }
        } catch (Exception $e) {
            error_log("Error generando QR para cotización: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener precio del item según moneda del contacto
     */
    private function get_item_price($item) {
        if ($this->currency === 'CLP') {
            // Chile: usar precio en pesos chilenos
            if (isset($item->price_clp) && $item->price_clp > 0) {
                return floatval($item->price_clp);
            }
        } else {
            // Otros países: usar precio en dólares
            if (isset($item->price_usd) && $item->price_usd > 0) {
                return floatval($item->price_usd);
            }
        }
        
        // Fallback a price genérico
        if (isset($item->price) && $item->price > 0) {
            return floatval($item->price);
        }
        
        return 0.00;
    }
    
    /**
     * Guardar PDF en archivo
     */
    public function save_to_file($file_path) {
        $dir = dirname($file_path);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        
        $this->Output('F', $file_path);
        return file_exists($file_path);
    }
}
