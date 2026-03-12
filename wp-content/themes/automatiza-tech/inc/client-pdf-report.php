<?php
/**
 * Automatiza Tech - Generador de Reporte PDF del Cliente para IA
 */

if (!defined('ABSPATH')) {
    exit;
}

// Incluir FPDF si no está cargado
if (!class_exists('FPDF')) {
    require_once get_template_directory() . '/lib/fpdf.php';
}

class AutomatizaTech_Client_Report_PDF extends FPDF {
    
    // Ancho de página utilizable
    private $contentWidth = 190;
    
    function Header() {
        // Logo
        $logo = get_template_directory() . '/assets/images/logo-automatiza-tech.png';
        if (file_exists($logo)) {
            $this->Image($logo, 10, 10, 30);
        }
        
        // Colores Corporativos (Azul Oscuro: #1E3A8A / RGB: 30, 58, 138)
        $this->SetDrawColor(30, 58, 138); 
        $this->SetLineWidth(0.8);
        $this->Line(10, 42, 200, 42); // Línea gruesa azul
        
        // Título Arial bold 16
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(30, 58, 138); // Azul corporativo
        $this->Cell(80); // Mover a la derecha
        $this->Cell(110, 15, utf8_decode('Informe Completo de Cliente para IA'), 0, 0, 'R');
        $this->Ln(8);
        
        // Subtítulo
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(190, 10, utf8_decode('Generado por Automatiza Tech System'), 0, 0, 'R');
        $this->Ln(25);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        // Pie con Branding
        $this->Cell(0, 10, utf8_decode('Automatiza Tech - Pagina ' . $this->PageNo() . '/{nb} - Generado el ' . date('d/m/Y H:i')), 0, 0, 'C');
    }
    
    function SectionTitle($label) {
        $this->SetFont('Arial', 'B', 12);
        // Fondo Azul Claro (#EEF2FF / RGB: 238, 242, 255) para las secciones
        $this->SetFillColor(238, 242, 255);
        $this->SetTextColor(30, 58, 138); // Azul Oscuro
        $this->Ln(5);
        $this->Cell(0, 10, utf8_decode("  " . strtoupper($label)), 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0); // Reset a negro
        $this->Ln(2);
    }
    
    // Función auxiliar para sanitizar textos problemáticos (bullets y caracteres raros)
    function cleanText($text) {
        // Reemplazar bullets standard por guión para evitar problemas de encoding
        $text = str_replace('•', '-', $text);
        return utf8_decode($text);
    }
    
    function KeyValueRow($key, $value) {
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(50, 6, $this->cleanText($key . ':'), 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->MultiCell(0, 6, $this->cleanText($value));
        $this->Ln(1);
    }
    
    function LongText($text) {
        $this->SetFont('Arial', '', 9);
        $this->MultiCell(0, 5, $this->cleanText($text));
        $this->Ln(2);
    }
}

class AutomatizaTech_Client_Report_Generator {
    
    public function __construct() {
        add_action('wp_ajax_download_client_report_pdf', array($this, 'generate_report'));
    }
    
    /**
     * Helper para obtener ruta de imagen válida para FPDF
     * Intenta local primero, luego descarga temporal
     */
    private function get_printable_image($url) {
        if (empty($url)) return false;
        
        // 1. Intento Mapeo Local (Rápido)
        $rel_path = strstr($url, 'wp-content');
        if ($rel_path) {
            $local_path = ABSPATH . $rel_path;
            $local_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $local_path);
            if (file_exists($local_path)) {
                return $local_path;
            }
        }

        // 2. Descarga a Temporal (Para URLs remotas en entorno local)
        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (!$ext) $ext = 'jpg';
        
        $temp_dir = get_temp_dir(); // WP Temp Dir
        $temp_file = $temp_dir . 'pdf_img_' . md5($url) . '.' . $ext;
        
        // Si ya existe y no es vieja (cache simple de sesión)
        if (file_exists($temp_file)) {
            return $temp_file;
        }

        // Usar WP_Http para saltar problemas de SSL locales
        $response = wp_remote_get($url, array('sslverify' => false, 'timeout' => 10));
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
            $body = wp_remote_retrieve_body($response);
            if ($body) {
                file_put_contents($temp_file, $body);
                return $temp_file;
            }
        }
        
        // 3. Retornar URL original (FPDF intentará cargarla si allow_url_fopen está on)
        return $url;
    }
    
    public function generate_report() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos.');
        }
        
        $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
        if (!$client_id) {
            wp_die('ID de cliente faltante.');
        }
        
        global $wpdb;
        
        // 1. Obtener Datos del Cliente
        $table_clientes = $wpdb->prefix . 'crm_clientes';
        $cliente = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_clientes WHERE id = %d", $client_id));
        
        if (!$cliente) {
            wp_die('Cliente no encontrado.');
        }
        
        // 2. Obtener Proyectos
        $table_proyectos = $wpdb->prefix . 'crm_proyectos';
        $proyectos = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_proyectos WHERE cliente_id = %d ORDER BY created_at DESC", $client_id));
        
        // 3. Obtener Historial Detallado (Timeline)
        $table_details = $wpdb->prefix . 'automatiza_clients_details';
        $timeline = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_details WHERE client_id = %d ORDER BY created_at DESC", $client_id));
        
        // 4. Buscar Datos de Propuesta Original (si existe email coincidente)
        $table_propuestas = $wpdb->prefix . 'automatiza_propuestas';
        $propuesta = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_propuestas WHERE client_email = %s ORDER BY id DESC LIMIT 1", $cliente->email));

        // INICIAR PDF
        $pdf = new AutomatizaTech_Client_Report_PDF();
        $pdf->AliasNbPages();
        $pdf->AddPage();
        
        // SECCIÓN 1: DATOS GENERALES
        $pdf->SectionTitle('1. FICHA DEL CLIENTE');
        $pdf->KeyValueRow('Nombre', $cliente->nombre);
        $pdf->KeyValueRow('Empresa', $cliente->empresa);
        $pdf->KeyValueRow('Email', $cliente->email);
        $pdf->KeyValueRow('Teléfono', $cliente->telefono);
        $pdf->KeyValueRow('Estado', ucfirst($cliente->estado));
        $pdf->KeyValueRow('Tipo', ucfirst($cliente->tipo));
        $pdf->KeyValueRow('AI Identifier', $cliente->ai_identifier);
        $pdf->KeyValueRow('Rubro', $cliente->rubro);
        $pdf->KeyValueRow('Fecha Contacto', $cliente->fecha_contacto);
        
        if (!empty($cliente->notas)) {
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'Notas Internas:', 0, 1);
            $pdf->LongText($cliente->notas);
        }

        // SECCIÓN 2: IDENTIDAD CORPORATIVA
        $pdf->SectionTitle('2. IDENTIDAD CORPORATIVA');
        
        $has_branding = false;

        // Logos e Identidad Visual
        $logos_viz = [
            'Logo Principal' => $cliente->logo_url ?? '',
            'Variante: Nombre' => $cliente->logo_nombre ?? '',
            'Variante: Isotipo' => $cliente->logo_isotipo ?? '',
            'Variante: Tagline' => $cliente->logo_tagline ?? ''
        ];

        $logos_found = array_filter($logos_viz);
        
        if (!empty($logos_found)) {
             $has_branding = true;
             $pdf->SetFont('Arial', 'B', 10);
             $pdf->Cell(0, 6, 'Logos y Variaciones:', 0, 1);
             $pdf->Ln(2);

             // Grid 2x2 Logic
             $y_base = $pdf->GetY();
             $x_base = $pdf->GetX();
             
             $count = 0;
             foreach ($logos_viz as $label => $url) {
                 if (empty($url)) continue;
                 
                 $col = $count % 2;
                 $row = floor($count / 2);
                 
                 // Coordenadas para cada celda (ancho ~95, alto ~40)
                 $x_curr = $x_base + ($col * 95);
                 $y_curr = $y_base + ($row * 40);
                 
                 // Verificar salto de página si es una fila nueva y estamos muy abajo
                 if ($y_curr > 250 && $row > 0 && $col == 0) {
                     $pdf->AddPage();
                     $y_base = $pdf->GetY();
                     $y_curr = $y_base; // Reset Y
                 }

                 $pdf->SetXY($x_curr, $y_curr);
                 $pdf->SetFont('Arial', 'B', 8);
                 $pdf->Cell(90, 5, utf8_decode($label), 0, 1);
                 
                 $logo_printable = $this->get_printable_image($url);
                 try {
                     // Get current clean Y after label
                     $pdf->SetXY($x_curr, $y_curr + 6);
                     // Image max height 30, max width 80
                     $pdf->Image($logo_printable, $x_curr, $pdf->GetY(), 0, 25); 
                 } catch (Exception $e) {
                    $pdf->SetXY($x_curr, $y_curr + 6);
                    $pdf->SetFont('Arial', 'I', 8);
                    $pdf->Cell(90, 5, '[Error Imagen]', 0, 1);
                 }
                 
                 $count++;
             }
             // Mover cursor al final del bloque de logos
             $rows_total = ceil($count/2);
             $pdf->SetY($y_base + ($rows_total * 40) + 5);
        }

        // Colores
        if (!empty($cliente->color_principal) || !empty($cliente->color_secundario_1)) {
            $has_branding = true;
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'Paleta de Colores:', 0, 1);
            
            // Función auxiliar hex to rgb
            $hex2rgb = function($hex) {
                $hex = str_replace("#", "", $hex);
                if(strlen($hex) == 3) {
                    $r = hexdec(substr($hex,0,1).substr($hex,0,1));
                    $g = hexdec(substr($hex,1,1).substr($hex,1,1));
                    $b = hexdec(substr($hex,2,1).substr($hex,2,1));
                } else {
                    $r = hexdec(substr($hex,0,2));
                    $g = hexdec(substr($hex,2,2));
                    $b = hexdec(substr($hex,4,2));
                }
                return array($r, $g, $b);
            };

            $printColor = function($label, $hex, $pdf) use ($hex2rgb) {
                if (empty($hex)) return;
                list($r, $g, $b) = $hex2rgb($hex);
                $pdf->SetFillColor($r, $g, $b);
                $pdf->Rect($pdf->GetX(), $pdf->GetY() + 1, 15, 6, 'F');
                $pdf->SetXY($pdf->GetX() + 17, $pdf->GetY());
                $pdf->Cell(40, 8, utf8_decode("$label ($hex)"));
            };

            $printColor('Principal', $cliente->color_principal, $pdf);
            $printColor('Secundario 1', $cliente->color_secundario_1, $pdf);
            $printColor('Secundario 2', $cliente->color_secundario_2, $pdf);
            $pdf->Ln(10);
        }

        if (!empty($cliente->tipografia)) {
            $has_branding = true;
            $pdf->KeyValueRow('Tipografia', $cliente->tipografia);
            $pdf->Ln(2);
        }

        if (!$has_branding) {
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->Cell(0, 8, utf8_decode('No se ha registrado información de marca.'), 0, 1);
        }


        // SECCIÓN 3: PROYECTOS
        if ($proyectos) {
            $pdf->SectionTitle('3. PROYECTOS ACTIVOS E HISTÓRICOS');
            foreach ($proyectos as $p) {
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(5, 6, '-', 0, 0); // Guion como bullet seguro
                $pdf->Cell(0, 6, $pdf->cleanText($p->nombre . " (" . ucfirst($p->estado) . ")"), 0, 1);
                
                $pdf->SetFont('Arial', '', 9);
                if($p->descripcion) $pdf->MultiCell(0, 5, $pdf->cleanText("Desc: " . $p->descripcion));
                if($p->precio_acordado) $pdf->Cell(0, 5, $pdf->cleanText("Presupuesto: " . $p->precio_acordado . " " . $p->moneda), 0, 1);
                $pdf->Ln(2);
            }
        }

        // SECCIÓN 4: CONTEXTO IA (PROMPTS Y TRANS)
        if ($propuesta) {
            $pdf->AddPage();
            $pdf->SectionTitle('4. CONTEXTO INICIAL (IA & DEMO)');
            
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 8, $pdf->cleanText('Transcripción Original (Input Usuario):'), 0, 1);
            $pdf->LongText(strip_tags($propuesta->transcript_text));
            
            $pdf->Ln(4);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 8, $pdf->cleanText('System Prompt Generado (Personalidad Bot):'), 0, 1);
            $pdf->LongText(strip_tags($propuesta->system_prompt_text));

            $pdf->Ln(4);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 8, $pdf->cleanText('Gamma Prompt (Presentación):'), 0, 1);
            $pdf->LongText(strip_tags($propuesta->gamma_prompt_text));
            
            $pdf->Ln(4);
            $pdf->KeyValueRow('Link Demo Generado', get_site_url() . '/ver-demo.php?id=' . $propuesta->unique_link_id);
        }

        // SECCIÓN 5: TIMELINE COMPLETO
        if ($timeline) {
            $pdf->AddPage();
            $pdf->SectionTitle('5. HISTORIAL DE INTERACCIONES (TIMELINE)');
            
            // Verificación Previa: ¿Hay Prototipos/Imágenes en el historial?
            foreach ($timeline as $item) {
                if (!empty($item->attachment_url) && (strpos($item->attachment_url, '.jpg') !== false || strpos($item->attachment_url, '.png') !== false || strpos($item->attachment_url, '.jpeg') !== false)) {
                     // Solo imágenes visuales
                     $pdf->SetFont('Arial', 'B', 11);
                     $pdf->SetTextColor(220, 38, 38); // Rojo notificación
                     $pdf->Cell(0, 10, utf8_decode('⚠️ PROTOTIPO / REFERENCIA VISUAL ENCONTRADA:'), 0, 1);
                     $pdf->SetTextColor(0);
                     
                     // Usar Helper
                     $img_path = $this->get_printable_image($item->attachment_url);

                     try {
                        $pdf->Image($img_path, $pdf->GetX(), $pdf->GetY(), 80); // Max width 80
                        $pdf->Ln(60); // Espacio para la imagen (aprox)
                        $pdf->SetFont('Arial', 'I', 8);
                        $pdf->Cell(0, 5, utf8_decode('Ref: ' . $item->title), 0, 1);
                     } catch (Exception $e) {
                        $pdf->SetTextColor(0, 0, 255);
                        $pdf->SetFont('Arial', 'U', 9);
                        $pdf->Cell(0, 8, utf8_decode('>>> VER IMAGEN ORIGINAL (' . basename($item->attachment_url) . ')'), 0, 1, 'L', false, $item->attachment_url);
                        $pdf->SetTextColor(0);
                     }
                     $pdf->Ln(5);
                }
            }

            // Cabecera tabla
            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(25, 8, 'Fecha', 1, 0, 'C', true);
            $pdf->Cell(25, 8, 'Tipo', 1, 0, 'C', true);
            $pdf->Cell(110, 8, utf8_decode('Título / Descripción'), 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Estado', 1, 1, 'C', true);
            
            $pdf->SetFont('Arial', '', 8);
            
            foreach ($timeline as $item) {
                $date = date('d/m/Y', strtotime($item->created_at));
                
                // Calcular altura de celda basada en el texto más largo
                $text = $pdf->cleanText($item->title);
                $lines = ceil($pdf->GetStringWidth($text) / 110);
                if ($lines < 1) $lines = 1;
                $height = 6 * $lines;
                
                // Verificar salto de página
                if ($pdf->GetY() + $height > 270) {
                    $pdf->AddPage();
                    // Reimprimir cabecera
                    $pdf->SetFont('Arial', 'B', 9);
                    $pdf->Cell(25, 8, 'Fecha', 1, 0, 'C', true);
                    $pdf->Cell(25, 8, 'Tipo', 1, 0, 'C', true);
                    $pdf->Cell(110, 8, utf8_decode('Título / Descripción'), 1, 0, 'C', true);
                    $pdf->Cell(30, 8, 'Estado', 1, 1, 'C', true);
                    $pdf->SetFont('Arial', '', 8);
                }

                $pdf->Cell(25, $height, $date, 1, 0, 'C');
                $pdf->Cell(25, $height, $pdf->cleanText(substr($item->detail_type, 0, 15)), 1, 0, 'C');
                
                // MutiCell manual simulator
                $x = $pdf->GetX();
                $y = $pdf->GetY();
                $pdf->MultiCell(110, 6, $text, 1, 'L');
                $pdf->SetXY($x + 110, $y); 
                
                $pdf->Cell(30, $height, $pdf->cleanText($item->status), 1, 1, 'C');
                
                 // Detalles extra (descripción o adjuntos)
                if ((strlen($item->description) > 3) || !empty($item->attachment_url)) {
                     $pdf->SetFont('Arial', 'I', 7);
                     $pdf->SetTextColor(100);
                     
                     $desc = "";
                     if (!empty($item->description)) $desc .= "Nota: " . substr(strip_tags($item->description), 0, 200) . "... ";
                     if (!empty($item->attachment_url)) $desc .= " [Adjunto: " . basename($item->attachment_url) . "]";
                     
                     // Pequeña fila extra abajo
                     if(!empty($desc)){
                         $pdf->MultiCell(190, 5, $pdf->cleanText($desc), 'LRB', 'L');
                     }
                     
                     $pdf->SetTextColor(0);
                     $pdf->SetFont('Arial', '', 8);
                }
            }
        }

        // SALIDA
        $filename = 'Informe_Cliente_' . sanitize_title($cliente->empresa) . '_' . date('Ymd') . '.pdf';
        $pdf->Output('D', $filename);
        exit;
    }
}

new AutomatizaTech_Client_Report_Generator();
