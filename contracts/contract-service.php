<?php
/**
 * ContractService — flujo de DOBLE FIRMA (AT primero, luego cliente).
 *
 * Ciclo de vida de status:
 *   draft → at_pending → at_signed → sent → viewed → signed
 *
 * API principal:
 *   - create_contract($args)                  -> draft + pdf preliminar + token AT
 *   - sign_as_at($id, $data)                  -> firma representante AT (status: at_signed)
 *   - send_for_client_signature($id [,email]) -> envía link al cliente (status: sent)
 *   - register_view($token)                   -> marca viewed_at
 *   - sign_as_client($token, $data)           -> firma cliente, regenera PDF final con AMBAS firmas (status: signed)
 */

if (!defined('ABSPATH')) require_once dirname(__DIR__) . '/wp-load.php';
require_once __DIR__ . '/contract-mailer.php';

class ContractService {

    public static function table() { global $wpdb; return $wpdb->prefix . 'automatiza_contracts'; }

    /* ---------- Storage ---------- */
    public static function storage_dir() {
        $up = wp_upload_dir();
        $dir = trailingslashit($up['basedir']) . 'automatiza-tech-contracts';
        if (!file_exists($dir)) wp_mkdir_p($dir);
        // A2.3: block direct file access (overwrite to ensure updated rule is applied)
        @file_put_contents($dir . '/.htaccess', "Order deny,allow\nDeny from all\nOptions -Indexes\n");
        return $dir;
    }
    public static function storage_url() {
        $up = wp_upload_dir(); return trailingslashit($up['baseurl']) . 'automatiza-tech-contracts';
    }

    /**
     * A2.3: Generate a secure AJAX download URL for a contract PDF.
     * For admin/logged-in users, pass $nonce = wp_create_nonce('at_dl_contract_'.$c->id).
     * For public sign pages, pass $token = $c->sign_token (or $c->at_review_token).
     */
    public static function secure_pdf_url( $c, $signed = false, $token = '', $nonce = '' ) {
        $url = admin_url('admin-ajax.php') . '?action=at_download_contract&contract_id=' . intval($c->id);
        if ($signed) $url .= '&signed=1';
        if ($token)  $url .= '&token='    . urlencode($token);
        if ($nonce)  $url .= '&_wpnonce=' . urlencode($nonce);
        return $url;
    }

    /* ---------- Template ---------- */
    /** Archivo de plantilla según el id; lo desconocido cae en la de soporte, como antes. */
    public static function archivo_plantilla($template_id) {
        $mapa = array('servicios_v1' => 'CONTRATO_SERVICIO_DESARROLLO.md');
        return $mapa[(string) $template_id] ?? 'CONTRATO_SOPORTE_POSTPROYECTO.md';
    }
    public static function load_template($template_id = 'soporte_v2') {
        $archivo = self::archivo_plantilla($template_id);
        $candidates = array(
            ABSPATH . 'Docs/' . $archivo,
            dirname(ABSPATH) . '/Docs/' . $archivo,
            dirname(__DIR__) . '/Docs/' . $archivo,
        );
        foreach ($candidates as $p) if (file_exists($p)) return file_get_contents($p);
        return '';
    }
    public static function titulo_por_tipo($type) {
        if ($type === 'servicios') {
            return 'CONTRATO DE PRESTACIÓN DE SERVICIOS DE DESARROLLO E IMPLEMENTACIÓN';
        }
        return 'CONTRATO DE PRESTACIÓN DE SERVICIOS, CESIÓN DE PROPIEDAD INTELECTUAL Y SOPORTE TÉCNICO POST-PROYECTO';
    }

    /** Campos que AT ajusta antes de firmar un contrato de servicios: clave => [etiqueta, 'tipo'|'documento'|'linea'|'texto']. */
    public static function campos_revision() {
        return array(
            'tipo_cliente'                 => array('Tipo de cliente', 'tipo'),
            'razon_social_cliente'         => array('Cliente (razón social o nombre)', 'linea'),
            'tipo_documento_cliente'       => array('Tipo de documento del cliente', 'documento'),
            'rut_cliente'                  => array('Documento del cliente (RUT si es empresa)', 'linea'),
            'domicilio_cliente'            => array('Domicilio del cliente', 'linea'),
            'representante_cliente_nombre' => array('Representante (solo si es empresa)', 'linea'),
            'tipo_documento_representante' => array('Tipo de documento del representante', 'documento'),
            'representante_cliente_rut'    => array('Documento del representante (solo si es empresa)', 'linea'),
            'servicios_contratados'        => array('Servicios contratados (uno por línea, empezando con «- »)', 'texto'),
            'alcance'                      => array('Alcance', 'texto'),
            'entregables'                  => array('Entregables (uno por línea, empezando con «- »)', 'texto'),
            'plazo'                        => array('Plazo', 'texto'),
            'monto_total'                  => array('Precio total, IVA incluido', 'linea'),
            'forma_pago'                   => array('Forma de pago', 'texto'),
            'fases_siguientes'             => array('Fases siguientes (una por línea, empezando con «- »)', 'texto'),
        );
    }
    /** Tipos de cliente de un contrato de servicios: valor => texto del selector. */
    public static function tipos_cliente() {
        return array(
            'persona' => 'Persona natural (a su nombre)',
            'empresa' => 'Empresa o persona jurídica',
        );
    }
    /**
     * Task 15: tipos de documento del cliente y del representante: valor => texto del selector. Sin
     * tipo guardado (contratos anteriores) cuenta como RUT. La lista vive en at_cc_tipos_documento()
     * (cierre de cliente); esta copia es para cuando el módulo no está cargado.
     */
    public static function tipos_documento() {
        if (function_exists('at_cc_tipos_documento')) return at_cc_tipos_documento();
        return array('rut' => 'RUT', 'dni' => 'DNI', 'pasaporte' => 'Pasaporte');
    }
    /** El número vale para su tipo (vacío = RUT). Sin el módulo de cierre, solo se valida el RUT si se puede. */
    private static function documento_valido($tipo, $numero) {
        $tipo = (string) $tipo !== '' ? (string) $tipo : 'rut';
        if (function_exists('at_cc_documento_valido')) return at_cc_documento_valido($tipo, (string) $numero);
        if ($tipo === 'rut' && function_exists('at_cc_rut_valido')) return at_cc_rut_valido((string) $numero);
        return array_key_exists($tipo, self::tipos_documento());
    }
    /** Un contrato de servicios no se firma hasta que AT guarda su revisión. */
    public static function necesita_revision($c) {
        if (!$c || $c->type !== 'servicios') return false;
        $ph = json_decode($c->placeholders, true) ?: array();
        return empty($ph['revision_at']);
    }
    /**
     * Datos esenciales que faltan (o son inválidos) para firmar un contrato de servicios: sus
     * etiquetas, en orden. [] si está completo o si el contrato no es de servicios.
     */
    public static function faltantes($c) {
        if (!$c || ($c->type ?? '') !== 'servicios') return array();
        $ph = json_decode((string) $c->placeholders, true) ?: array();
        $campos = self::campos_revision();
        $tipo = (string) ($ph['tipo_cliente'] ?? '');
        $exigidos = array();
        // Task 15: el número de la persona y el del representante pueden ser RUT, DNI o pasaporte.
        if ($tipo === 'persona') {
            $exigidos = array('razon_social_cliente' => 'Nombre completo del cliente', 'rut_cliente' => 'Documento del cliente');
        } elseif ($tipo === 'empresa') {
            $exigidos = array(
                'razon_social_cliente'         => 'Razón social',
                'rut_cliente'                  => 'RUT de la empresa',
                'representante_cliente_nombre' => $campos['representante_cliente_nombre'][0],
                'representante_cliente_rut'    => 'Documento del representante',
            );
        } else {
            // Sin tipo: se exige elegirlo y, mientras, se listan también los datos comunes.
            $exigidos = array('razon_social_cliente' => $campos['razon_social_cliente'][0], 'rut_cliente' => $campos['rut_cliente'][0]);
        }
        foreach (array('domicilio_cliente', 'monto_total', 'forma_pago') as $k) {
            $exigidos[$k] = $campos[$k][0];
        }
        // Tipo con que se valida cada número (vacío = RUT, contratos anteriores a la Task 15). El RUT de
        // una empresa siempre se valida como RUT, diga lo que diga tipo_documento_cliente.
        $tipo_de = array(
            'rut_cliente'               => $tipo === 'empresa' ? 'rut' : (string) ($ph['tipo_documento_cliente'] ?? ''),
            'representante_cliente_rut' => (string) ($ph['tipo_documento_representante'] ?? ''),
        );
        $faltan = array_key_exists($tipo, self::tipos_cliente()) ? array() : array($campos['tipo_cliente'][0]);
        foreach ($exigidos as $k => $etiqueta) {
            $v = trim((string) ($ph[$k] ?? ''));
            if ($v === '') {
                $faltan[] = $etiqueta;
            } elseif (array_key_exists($k, $tipo_de) && !self::documento_valido($tipo_de[$k], $v)) {
                $faltan[] = $etiqueta . ' (no es válido)';
            }
        }
        // Red de seguridad: persona natural cuyo nombre sigue siendo el de la marca de la
        // propuesta (razon_social_cliente == nombre_proyecto) y distinto del nombre de quien
        // aceptó. No debería firmarse así aunque el campo no esté vacío. Se compara contra
        // nombre_de_quien_acepto() (el marcador inmutable 'aceptante_nombre' si existe) y no
        // solo contra representante_cliente_nombre, que puede haberse borrado en una revisión
        // anterior (ese campo es «solo si es empresa» y se borra al elegir persona natural).
        if ($tipo === 'persona') {
            $razon     = trim((string) ($ph['razon_social_cliente'] ?? ''));
            $proyecto  = trim((string) ($ph['nombre_proyecto'] ?? ''));
            $aceptante = self::nombre_de_quien_acepto($ph);
            if ($razon !== '' && $proyecto !== '' && $aceptante !== ''
                && self::normalizar_nombre($razon) === self::normalizar_nombre($proyecto)
                && self::normalizar_nombre($razon) !== self::normalizar_nombre($aceptante)) {
                $faltan[] = 'Nombre completo del cliente (hoy dice el nombre de la marca)';
            }
        }
        return $faltan;
    }
    /** El aviso de lo que falta para firmar (el mismo en la firma y en la página de revisión). */
    public static function mensaje_faltantes(array $faltantes) {
        return 'Antes de firmar completa: ' . implode(', ', $faltantes) . '.';
    }
    /**
     * Párrafo de EL CLIENTE en «Comparecientes», según sea persona natural o empresa. Se calcula
     * al generar el PDF desde los marcadores; nunca se guarda. Vacíos = '_______', como el resto del PDF.
     */
    public static function comparecencia_cliente(array $ph) {
        $limpio = function ($k) use ($ph) {
            // Una sola línea de Markdown: sin saltos ni asteriscos que rompan las negritas.
            return trim((string) preg_replace('/\s+/u', ' ', str_replace('*', '', (string) ($ph[$k] ?? ''))));
        };
        $val = function ($k) use ($limpio) {
            $s = $limpio($k);
            return $s !== '' ? $s : '_______';
        };
        // Task 15: 'RUT **n**', 'DNI N° **n**' o 'pasaporte N° **n**' según el tipo guardado (vacío = RUT).
        $documento = function ($k_tipo, $k_numero) use ($ph, $limpio, $val) {
            if (!function_exists('at_cc_documento_texto')) {
                return 'RUT **' . $val($k_numero) . '**';
            }
            return at_cc_documento_texto((string) ($ph[$k_tipo] ?? ''), $limpio($k_numero));
        };
        $tipo = (string) ($ph['tipo_cliente'] ?? '');
        // La empresa siempre muestra RUT para su propio número; la persona (o sin tipo), su documento.
        $doc_cliente = $tipo === 'empresa' ? 'RUT **' . $val('rut_cliente') . '**' : $documento('tipo_documento_cliente', 'rut_cliente');
        $t = '**' . $val('razon_social_cliente') . '** (en adelante "**EL CLIENTE**"), ' . $doc_cliente;
        if ($tipo === 'empresa') {
            $t .= ', representada por **' . $val('representante_cliente_nombre') . '**, ' . $documento('tipo_documento_representante', 'representante_cliente_rut');
        }
        $t .= ', correo ' . $val('email_cliente') . ', teléfono ' . $val('telefono_cliente') . ', con domicilio en ' . $val('domicilio_cliente');
        if ($tipo === 'persona') {
            $normal = function ($s) {
                $s = trim((string) preg_replace('/\s+/u', ' ', (string) $s));
                return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
            };
            $proyecto = $normal($ph['nombre_proyecto'] ?? '');
            if ($proyecto !== '' && $proyecto !== $normal($ph['razon_social_cliente'] ?? '')) {
                $t .= ', para su proyecto «' . $val('nombre_proyecto') . '»';
            }
        }
        return $t . '.';
    }
    /**
     * Limpia un texto que va al PDF: sin bytes nulos, UTF-8 válido, fines de línea \n y recortado.
     * No quita etiquetas, entidades ni '%': donde se muestra en HTML se escapa al imprimir.
     */
    private static function limpiar_texto($v) {
        $v = str_replace("\0", '', (string) $v);
        $v = wp_check_invalid_utf8($v, true);
        $v = str_replace(array("\r\n", "\r"), "\n", $v);
        return trim($v);
    }
    /**
     * Persona natural = sus datos de la aceptación: nombre y documento vacíos se toman de quien aceptó.
     * Task 15: el tipo de documento viaja con el número (un DNI copiado no queda rotulado como RUT).
     */
    private static function persona_con_datos_de_aceptacion(array $ph) {
        if (($ph['tipo_cliente'] ?? '') !== 'persona') return $ph;
        $origen = array('razon_social_cliente' => 'representante_cliente_nombre', 'rut_cliente' => 'representante_cliente_rut');
        foreach ($origen as $k => $de) {
            if (trim((string) ($ph[$k] ?? '')) === '' && trim((string) ($ph[$de] ?? '')) !== '') {
                $ph[$k] = $ph[$de];
                if ($k === 'rut_cliente') {
                    $tipo_repr = trim((string) ($ph['tipo_documento_representante'] ?? ''));
                    if ($tipo_repr !== '') $ph['tipo_documento_cliente'] = $tipo_repr; else unset($ph['tipo_documento_cliente']);
                }
            }
        }
        return $ph;
    }
    /** Task 15: '' si el tipo de documento es válido (rut, dni, pasaporte o vacío); si no, el error. */
    private static function error_tipo_documento(array $cambios) {
        foreach (array('tipo_documento_cliente', 'tipo_documento_representante') as $k) {
            if (!array_key_exists($k, $cambios)) continue;
            $v = self::limpiar_texto($cambios[$k]);
            if ($v !== '' && !array_key_exists($v, self::tipos_documento())) {
                return new WP_Error('tipo_documento_invalido', 'El tipo de documento debe ser RUT, DNI o pasaporte.');
            }
        }
        return '';
    }
    /** Normaliza un nombre para comparar: sin mayúsculas ni espacios de más. */
    private static function normalizar_nombre($s) {
        $s = trim((string) preg_replace('/\s+/u', ' ', (string) $s));
        return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    }
    /**
     * Nombre de quien aceptó la propuesta, para saber si «persona natural» sigue a nombre de la
     * marca. Se prefiere el marcador inmutable 'aceptante_nombre' (lo escribe una sola vez
     * at_cc_marcadores_servicios() al crear el contrato y no está en campos_revision(): nunca se
     * edita ni se borra desde la revisión). Si el contrato es anterior a ese marcador (o se creó
     * sin pasar por at_cc_marcadores_servicios(), como en las pruebas), se cae a
     * representante_cliente_nombre tal como venga en $ph.
     */
    private static function nombre_de_quien_acepto(array $ph) {
        $v = trim((string) ($ph['aceptante_nombre'] ?? ''));
        return $v !== '' ? $v : trim((string) ($ph['representante_cliente_nombre'] ?? ''));
    }
    /**
     * Persona natural cuyo razon_social_cliente sigue siendo el de la marca de la propuesta (el
     * formulario precarga razon_social_cliente = nombre_proyecto = empresa): se reemplaza por el
     * nombre de quien aceptó, salvo que Luis ya haya escrito un nombre distinto del de la marca.
     * $ph_original son los marcadores tal como estaban ANTES de aplicar los cambios de esta misma
     * llamada: representante_cliente_nombre puede llegar recién vaciado en $ph (Luis lo borra al
     * elegir persona natural, siguiendo la etiqueta «solo si es empresa»), así que
     * nombre_de_quien_acepto() se calcula sobre el estado previo, no sobre el ya mutado.
     */
    private static function persona_marca_igual_al_proyecto(array $ph, array $ph_original) {
        if (($ph['tipo_cliente'] ?? '') !== 'persona') return $ph;
        $razon     = trim((string) ($ph['razon_social_cliente'] ?? ''));
        $proyecto  = trim((string) ($ph['nombre_proyecto'] ?? ''));
        $aceptante = self::nombre_de_quien_acepto($ph_original);
        if ($razon !== '' && $proyecto !== '' && $aceptante !== ''
            && self::normalizar_nombre($razon) === self::normalizar_nombre($proyecto)
            && self::normalizar_nombre($razon) !== self::normalizar_nombre($aceptante)) {
            $ph['razon_social_cliente'] = $aceptante;
        }
        return $ph;
    }
    /** Guarda los marcadores y regenera el PDF preliminar. */
    private static function guardar_marcadores($c, array $ph) {
        global $wpdb;
        $wpdb->update(self::table(), array('placeholders' => wp_json_encode($ph, JSON_UNESCAPED_UNICODE)), array('id' => $c->id));
        $pdf = self::render_pdf($c->id);
        if ($pdf) {
            $wpdb->update(self::table(), array('pdf_url' => self::path_to_url($pdf), 'document_hash' => hash_file('sha256', $pdf)), array('id' => $c->id));
        }
        return self::get_by_id($c->id);
    }
    /** Guarda la revisión de AT (solo antes de firmar) y regenera el PDF. */
    public static function guardar_revision($contract_id, array $cambios) {
        $c = self::get_by_id($contract_id);
        if (!$c) return new WP_Error('not_found', 'Contrato no encontrado');
        if (!in_array($c->status, array('draft', 'at_pending'), true)) {
            return new WP_Error('bad_status', 'El contrato ya fue firmado: no se puede editar.');
        }
        if (array_key_exists('tipo_cliente', $cambios)) {
            $tipo = self::limpiar_texto($cambios['tipo_cliente']);
            if ($tipo !== '' && !array_key_exists($tipo, self::tipos_cliente())) {
                return new WP_Error('tipo_cliente_invalido', 'El tipo de cliente debe ser persona natural o empresa.');
            }
        }
        $error_doc = self::error_tipo_documento($cambios);
        if (is_wp_error($error_doc)) return $error_doc;
        $ph_original = json_decode($c->placeholders, true) ?: array();
        $ph = $ph_original;
        foreach (self::campos_revision() as $k => $_) {
            if (array_key_exists($k, $cambios)) {
                $v = self::limpiar_texto($cambios[$k]);
                if ($v === '') unset($ph[$k]); else $ph[$k] = $v;
            }
        }
        $ph = self::persona_marca_igual_al_proyecto($ph, $ph_original);
        $ph = self::persona_con_datos_de_aceptacion($ph);
        $ph['revision_at'] = current_time('mysql');
        return self::guardar_marcadores($c, $ph);
    }
    /**
     * Datos que da el cliente después de aceptar (tipo, razón social, RUT y domicilio). Solo en un
     * contrato de servicios sin firmar y sin la revisión de AT; no marca la revisión.
     */
    public static function actualizar_datos_cliente($contract_id, array $datos) {
        $c = self::get_by_id($contract_id);
        if (!$c) return new WP_Error('not_found', 'Contrato no encontrado');
        if ($c->type !== 'servicios') {
            return new WP_Error('bad_type', 'Este contrato no recibe datos del cliente.');
        }
        if (!in_array($c->status, array('draft', 'at_pending'), true)) {
            return new WP_Error('bad_status', 'El contrato ya fue firmado: no se puede editar.');
        }
        $ph = json_decode($c->placeholders, true) ?: array();
        if (!empty($ph['revision_at'])) {
            return new WP_Error('ya_revisado', 'AutomatizaTech ya revisó el contrato: los cambios de datos se ven directamente con nosotros.');
        }
        $nuevos = array();
        // Task 15: tipo_documento_cliente acompaña a rut_cliente (una empresa lo manda como 'rut').
        foreach (array('tipo_cliente', 'razon_social_cliente', 'tipo_documento_cliente', 'rut_cliente', 'domicilio_cliente') as $k) {
            if (!array_key_exists($k, $datos)) continue;
            $v = self::limpiar_texto($datos[$k]);
            if ($v === '') continue; // Lo que el cliente deja en blanco no borra lo que ya había.
            if ($k === 'tipo_cliente' && !array_key_exists($v, self::tipos_cliente())) {
                return new WP_Error('tipo_cliente_invalido', 'El tipo de cliente debe ser persona natural o empresa.');
            }
            if ($k === 'tipo_documento_cliente' && !array_key_exists($v, self::tipos_documento())) {
                return new WP_Error('tipo_documento_invalido', 'El tipo de documento debe ser RUT, DNI o pasaporte.');
            }
            $nuevos[$k] = $v;
        }
        $tipo_previo = (string) ($ph['tipo_cliente'] ?? '');
        $ph = array_merge($ph, $nuevos);
        if (($ph['tipo_cliente'] ?? '') === 'persona' && $tipo_previo !== 'persona') {
            // A su nombre, la primera vez que pasa a persona en esta llamada: el contrato va a
            // nombre y RUT de quien aceptó, no de la marca de la propuesta (o de la empresa
            // anterior), salvo que el cliente haya escrito otros. Si ya era 'persona' antes de
            // esta llamada, no se toca: no hay que borrar un nombre que el cliente ya personalizó
            // en una llamada previa solo porque esta llamada no lo reenvía.
            foreach (array('razon_social_cliente' => 'representante_cliente_nombre', 'rut_cliente' => 'representante_cliente_rut') as $k => $de) {
                if (!isset($nuevos[$k]) && trim((string) ($ph[$de] ?? '')) !== '') unset($ph[$k]);
            }
        }
        $ph = self::persona_con_datos_de_aceptacion($ph);
        return self::guardar_marcadores($c, $ph);
    }

    /* ---------- Defaults compañía ---------- */
    public static function default_company_placeholders() {
        return array(
            'razon_social_at'         => 'AUTOMATIZATECH SpA',
            'rut_at'                  => '78.363.717-0',
            'fecha_constitucion_at'   => '24 de febrero de 2026',
            'representante_at_nombre' => 'Luis Miguel González Morales',
            'representante_at_rut'    => '26.191.807-2',
            'representante_at_cargo'  => 'Administrador y Representante Legal',
            'domicilio_at'            => 'Santa Beatriz 170, Of. 903 (9P), Providencia, Región Metropolitana, Chile',
            'email_at'                => 'contacto@automatizatech.cl',
            'whatsapp_soporte'        => '',
            'email_soporte'           => 'contacto@automatizatech.cl',
            'url_portal'              => home_url('/portal-omnichannel/'),
            'ciudad_firma'            => 'Santiago',
            'ciudad_jurisdiccion'     => 'Santiago',
            'sla_s1_respuesta'=>'1 hora','sla_s1_resolucion'=>'8 horas hábiles',
            'sla_s2_respuesta'=>'4 horas','sla_s2_resolucion'=>'24 horas hábiles',
            'sla_s3_respuesta'=>'1 día hábil','sla_s3_resolucion'=>'5 días hábiles',
            'sla_s4_respuesta'=>'2 días hábiles','sla_s4_resolucion'=>'mejor esfuerzo',
            'credito_sla'=>'5','dias_pago'=>'15','meses_topes'=>'3',
            'dias_handover'=>'10','horas_handover'=>'4','garantia_meses'=>'12',
            'hora_inicio_soporte'=>'09:00','hora_fin_soporte'=>'18:00',
            'canal_24x7'=>'WhatsApp soporte','frecuencia_reuniones'=>'mensual',
            'backups_incluidos'=>'Diarios, retención 7 días',
            'vigencia_meses'=>'12','horas_evolutivas_mes'=>'4','valor_hora'=>'45.000',
        );
    }

    /* ============================================================
     *  STEP 1 — Crear contrato (draft)
     * ============================================================ */
    public static function create_contract($args) {
        global $wpdb;
        $a = wp_parse_args($args, array(
            'client_id'=>null,'project_id'=>null,'proposal_id'=>null,
            'template_id'=>'soporte_v2','type'=>'soporte',
            'placeholders'=>array(),'starts_at'=>date('Y-m-d'),'ends_at'=>null,
            'monthly_amount'=>null,'currency'=>'CLP','expires_in_days'=>14,
            'created_by'=>get_current_user_id() ?: null,
        ));

        $ph = array_merge(self::default_company_placeholders(), (array) $a['placeholders']);
        $contract_number = self::generate_contract_number();
        $sign_token      = bin2hex(random_bytes(32));
        $at_review_token = bin2hex(random_bytes(32));
        $expires_at      = date('Y-m-d H:i:s', strtotime('+' . intval($a['expires_in_days']) . ' days'));

        $ph['contract_number']  = $contract_number;
        $ph['contract_title']   = self::titulo_por_tipo($a['type']);
        $ph['fecha_firma_larga']= self::fecha_larga(date('Y-m-d'));

        $wpdb->insert(self::table(), array(
            'client_id'=>$a['client_id'],'project_id'=>$a['project_id'],'proposal_id'=>$a['proposal_id'],
            'contract_number'=>$contract_number,'type'=>$a['type'],'template_id'=>$a['template_id'],
            'placeholders'=>wp_json_encode($ph, JSON_UNESCAPED_UNICODE),
            'status'=>'at_pending','sign_token'=>$sign_token,'at_review_token'=>$at_review_token,
            'expires_at'=>$expires_at,'starts_at'=>$a['starts_at'],'ends_at'=>$a['ends_at'],
            'monthly_amount'=>$a['monthly_amount'],'currency'=>$a['currency'],'created_by'=>$a['created_by'],
        ));
        $id = (int) $wpdb->insert_id;
        if (!$id) return new WP_Error('insert_failed', $wpdb->last_error);

        // Render PDF preliminar (sin firmas)
        $pdf_path = self::render_pdf($id);
        $hash     = hash_file('sha256', $pdf_path);
        $wpdb->update(self::table(), array(
            'pdf_url'=>self::path_to_url($pdf_path),'document_hash'=>$hash,
        ), array('id'=>$id));

        // Notifica al rep AT para revisar/firmar (correo a quien lo creó o admin)
        $c = self::get_by_id($id);
        $review_url = home_url('/contracts/at-sign-contract.php?token=' . $c->at_review_token);
        $reviewer_email = '';
        $reviewer_name  = '';
        if ($a['created_by']) {
            $u = get_userdata($a['created_by']);
            if ($u) { $reviewer_email = $u->user_email; $reviewer_name = $u->display_name; }
        }
        if (!$reviewer_email) {
            $reviewer_email = defined('OMNI_MASTER_EMAIL') ? OMNI_MASTER_EMAIL : 'contacto@automatizatech.cl';
            $reviewer_name  = $ph['representante_at_nombre'] ?? 'Representante AT';
        }
        ContractMailer::send_internal_review($reviewer_email, $reviewer_name, $c, $review_url, $pdf_path);

        return $c;
    }

    /* ============================================================
     *  STEP 2 — AT firma (representante AutomatizaTech)
     * ============================================================ */
    public static function sign_as_at($contract_id, $data) {
        global $wpdb;
        $c = self::get_by_id($contract_id);
        if (!$c) return new WP_Error('not_found','Contrato no encontrado');
        if (!in_array($c->status, array('draft','at_pending'))) {
            return new WP_Error('bad_status','El contrato ya fue firmado por AT o por el cliente');
        }
        if (self::necesita_revision($c)) {
            return new WP_Error('sin_revision', 'Guarda la revisión del contrato antes de firmarlo.');
        }
        $faltantes = self::faltantes($c);
        if ($faltantes) {
            return new WP_Error('faltan_datos', self::mensaje_faltantes($faltantes));
        }
        foreach (array('signer_name','signer_rut','signer_email','method') as $r) {
            if (empty($data[$r])) return new WP_Error('missing_'.$r, "Falta: $r");
        }
        if (!is_email($data['signer_email'])) return new WP_Error('bad_email','Email inválido');

        $img = self::save_signature_image($c, $data, 'at');
        if (is_wp_error($img)) return $img;

        $now = current_time('mysql');
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 400);

        // Update placeholders con firma AT
        $ph = json_decode($c->placeholders, true) ?: array();
        $ph['representante_at_nombre'] = $data['signer_name'];
        $ph['representante_at_rut']    = $data['signer_rut'];

        $wpdb->update(self::table(), array(
            'placeholders'           => wp_json_encode($ph, JSON_UNESCAPED_UNICODE),
            'status'                 => 'at_signed',
            'at_signer_user_id'      => get_current_user_id() ?: null,
            'at_signer_name'         => $data['signer_name'],
            'at_signer_rut'          => $data['signer_rut'],
            'at_signer_email'        => $data['signer_email'],
            'at_signer_ip'           => $ip,
            'at_signed_at'           => $now,
            'at_signature_method'    => $data['method'],
            'at_signature_image_url' => self::path_to_url($img),
            // E4: rotate at_review_token so the old review URL can't be reused
            'at_review_token'        => bin2hex( random_bytes(32) ),
        ), array('id' => $c->id));

        // Re-render PDF con firma AT (cliente pendiente)
        self::render_pdf($c->id);
        return self::get_by_id($c->id);
    }

    /* ============================================================
     *  STEP 3 — Enviar al cliente para que firme
     * ============================================================ */
    public static function send_for_client_signature($id, $to_email = null, $to_name = null) {
        global $wpdb;
        $c = self::get_by_id($id);
        if (!$c) return new WP_Error('not_found','Contrato no encontrado');
        if ($c->status !== 'at_signed') {
            return new WP_Error('bad_status','El contrato debe estar firmado por AT antes de enviarlo al cliente');
        }
        $ph = json_decode($c->placeholders, true) ?: array();
        $email = $to_email ?: ($ph['email_cliente'] ?? '');
        $name  = $to_name  ?: ($ph['representante_cliente_nombre'] ?? ($ph['razon_social_cliente'] ?? 'Cliente'));
        if (!$email || !is_email($email)) return new WP_Error('bad_email','Email destinatario inválido');

        // Contrato de servicios (cierre de cliente): entre la aceptación y este envío hay pasos
        // manuales (datos del cliente, revisión, reunión de inicio), así que el plazo de firma corre
        // desde que se le envía al cliente: 30 días desde hoy, nunca menos de lo que ya tenía.
        if (($c->type ?? '') === 'servicios') {
            $nuevo_vencimiento = date('Y-m-d H:i:s', strtotime('+30 days'));
            if (empty($c->expires_at) || strtotime($c->expires_at) < strtotime($nuevo_vencimiento)) {
                $wpdb->update(self::table(), array('expires_at' => $nuevo_vencimiento), array('id' => $c->id));
                $c->expires_at = $nuevo_vencimiento;
            }
        }

        $sign_url = home_url('/contracts/sign-contract.php?token=' . $c->sign_token);
        $pdf_path = self::contract_pdf_path($c, false);
        ContractMailer::send_signature_request($email, $name, $c, $sign_url, $pdf_path);

        $wpdb->update(self::table(), array(
            'status'=>'sent','sent_at'=>current_time('mysql')
        ), array('id'=>$id));
        return true;
    }

    /* ---------- VIEW ---------- */
    public static function register_view($token) {
        global $wpdb;
        $c = self::get_by_token($token);
        if (!$c) return false;
        if (!$c->viewed_at) {
            $wpdb->update(self::table(), array(
                'viewed_at'=>current_time('mysql'),
                'status'=> $c->status === 'sent' ? 'viewed' : $c->status,
            ), array('id'=>$c->id));
        }
        return true;
    }

    /* ============================================================
     *  STEP 4 — Cliente firma → genera PDF FINAL con AMBAS firmas
     * ============================================================ */
    public static function sign_as_client($token, $data) {
        global $wpdb;
        $c = self::get_by_token($token);
        if (!$c) {
            // C4: log invalid/unknown contract token attempts
            if ( function_exists( 'at_secmon_log_event' ) ) {
                at_secmon_log_event( 'contract_token_invalid', [ 'token_prefix' => substr( $token, 0, 8 ) ] );
            }
            return new WP_Error('not_found','Contrato no encontrado');
        }
        if ($c->status === 'signed') return new WP_Error('already_signed','Ya firmado');
        if (!in_array($c->status, array('sent','viewed','at_signed'))) {
            return new WP_Error('bad_status','El contrato no está disponible para firma del cliente');
        }
        if ($c->expires_at && strtotime($c->expires_at) < time()) {
            // C4: log expired token attempts
            if ( function_exists( 'at_secmon_log_event' ) ) {
                at_secmon_log_event( 'contract_token_invalid', [ 'reason' => 'expired', 'contract' => $c->contract_number ?? $c->id ] );
            }
            return new WP_Error('expired','Link expirado');
        }

        foreach (array('signer_name','signer_rut','signer_email','method') as $r) {
            if (empty($data[$r])) return new WP_Error('missing_'.$r, "Falta: $r");
        }
        if (!is_email($data['signer_email'])) return new WP_Error('bad_email','Email inválido');

        $img = self::save_signature_image($c, $data, 'client');
        if (is_wp_error($img)) return $img;

        $now = current_time('mysql');
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 400);

        $ph = json_decode($c->placeholders, true) ?: array();
        $ph['representante_cliente_nombre'] = $data['signer_name'];
        $ph['representante_cliente_rut']    = $data['signer_rut'];
        $ph['email_cliente']                = $data['signer_email'];
        $ph['fecha_firma_larga']            = self::fecha_larga(date('Y-m-d'));
        $ph['fecha_firma_cliente']          = self::fecha_larga(date('Y-m-d')); // Vigencia inicia desde esta fecha

        // Guardar TODOS los datos del cliente + signed_at ANTES de render_pdf.
        // render_pdf lee la BD para saber si incluir firma del cliente (if $c->signed_at).
        // Si signed_at no está grabado al momento de rendir, el bloque de firma queda vacío.
        $wpdb->update(self::table(), array(
            'placeholders'        => wp_json_encode($ph, JSON_UNESCAPED_UNICODE),
            'status'              => 'signed',
            'signed_at'           => $now,
            'signer_name'         => $data['signer_name'],
            'signer_rut'          => $data['signer_rut'],
            'signer_email'        => $data['signer_email'],
            'signer_ip'           => $ip,
            'signer_user_agent'   => $ua,
            'signature_method'    => $data['method'],
            'signature_image_url' => self::path_to_url($img),
            // E4: rotate sign_token so the old sign URL can't be reused after signing
            'sign_token'          => bin2hex( random_bytes(32) ),
        ), array('id'=>$c->id));

        // Re-render PDF FINAL con ambas firmas (ahora signed_at ya está en BD)
        $signed_path = self::render_pdf($c->id, true);
        $signed_hash = hash_file('sha256', $signed_path);

        $wpdb->update(self::table(), array(
            'signed_pdf_url'      => self::path_to_url($signed_path),
            'signed_document_hash'=> $signed_hash,
        ), array('id'=>$c->id));

        $fresh = self::get_by_id($c->id);
        ContractMailer::send_signed_copy($fresh, $signed_path);
        ContractMailer::send_signed_copy_internal($fresh, $signed_path);
        return $fresh;
    }

    /* ============================================================
     *  Helpers
     * ============================================================ */
    public static function get_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table() . " WHERE id=%d", $id));
    }
    public static function get_by_token($token) {
        global $wpdb;
        if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table() . " WHERE sign_token=%s", $token));
    }
    public static function get_by_at_token($token) {
        global $wpdb;
        if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table() . " WHERE at_review_token=%s", $token));
    }
    public static function list_by_client($client_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_number, type, status, signed_at, sent_at, monthly_amount, currency, signed_pdf_url, pdf_url, created_at
             FROM " . self::table() . " WHERE client_id=%d ORDER BY id DESC", $client_id));
    }

    private static function generate_contract_number() {
        return 'AT-CTR-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    }
    private static function fecha_larga($ymd) {
        $m = array('','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre');
        $t = strtotime($ymd);
        return date('j', $t) . ' de ' . $m[(int)date('n', $t)] . ' de ' . date('Y', $t);
    }
    private static function path_to_url($path) {
        $up = wp_upload_dir();
        return str_replace($up['basedir'], $up['baseurl'], $path);
    }
    private static function contract_pdf_path($c, $signed) {
        $dir = self::storage_dir();
        return $dir . '/' . $c->contract_number . ($signed ? '-FIRMADO' : '') . '.pdf';
    }

    private static function save_signature_image($c, $data, $who = 'client') {
        $dir = self::storage_dir() . '/signatures';
        if (!file_exists($dir)) wp_mkdir_p($dir);
        $base = $dir . '/sig-' . $who . '-' . bin2hex(random_bytes(12));

        if (!empty($data['signature_dataurl'])) {
            if (!preg_match('#^data:image/(png|jpeg|jpg);base64,(.+)$#', $data['signature_dataurl'], $m)) {
                return new WP_Error('bad_dataurl','Imagen de firma inválida');
            }
            $ext = $m[1] === 'jpg' ? 'jpeg' : $m[1];
            $bin = base64_decode($m[2]);
            if ($bin === false || strlen($bin) > 2 * 1024 * 1024) return new WP_Error('bad_size','Firma > 2MB');
            $path = $base . '.' . $ext;
            file_put_contents($path, $bin);
            return $path;
        }
        if (!empty($data['signature_file']) && is_array($data['signature_file']) && !empty($data['signature_file']['tmp_name'])) {
            $f = $data['signature_file'];
            if (!empty($f['error'])) return new WP_Error('upload_err','Error subiendo archivo');
            if ($f['size'] > 2 * 1024 * 1024) return new WP_Error('too_big','Imagen > 2MB');
            $info = @getimagesize($f['tmp_name']);
            if (!$info || !in_array($info['mime'], array('image/png','image/jpeg'))) {
                return new WP_Error('bad_mime','Solo PNG o JPG');
            }
            $ext = $info['mime'] === 'image/png' ? 'png' : 'jpg';
            $path = $base . '.' . $ext;
            move_uploaded_file($f['tmp_name'], $path);
            return $path;
        }
        return new WP_Error('no_signature','Debe firmar o subir una imagen');
    }

    /**
     * Renderiza el PDF (incluye firmas que ya estén guardadas en BD).
     * Si $final=true, genera el archivo "-FIRMADO".
     */
    private static function render_pdf($id, $final = false) {
        $c = self::get_by_id($id);
        if (!$c) return null;
        $ph = json_decode($c->placeholders, true) ?: array();
        $body = self::load_template($c->template_id);

        $signatures = array();
        if ($c->at_signed_at) {
            $img_path = self::url_to_path($c->at_signature_image_url);
            $signatures['at'] = array(
                'image_path' => $img_path,
                'signer_name'=> $c->at_signer_name,
                'signer_rut' => $c->at_signer_rut,
                'signer_email'=> $c->at_signer_email,
                'signed_at'  => $c->at_signed_at,
                'ip'         => $c->at_signer_ip,
                'method'     => $c->at_signature_method,
            );
        }
        if ($c->signed_at) {
            $img_path = self::url_to_path($c->signature_image_url);
            $signatures['client'] = array(
                'image_path' => $img_path,
                'signer_name'=> $c->signer_name,
                'signer_rut' => $c->signer_rut,
                'signer_email'=> $c->signer_email,
                'signed_at'  => $c->signed_at,
                'ip'         => $c->signer_ip,
                'user_agent' => $c->signer_user_agent,
                'method'     => $c->signature_method,
                'token'      => $c->sign_token,
                'hash'       => $c->document_hash,
            );
        }

        require_once get_template_directory() . '/lib/contract-pdf-fpdf.php';
        // Derivado, no se guarda: el párrafo de EL CLIENTE según su tipo (plantilla de servicios).
        $ph['comparecencia_cliente'] = self::comparecencia_cliente($ph);
        $pdf = new ContractPDFFPDF($ph, $body, $signatures);
        $pdf->build();
        $file = self::contract_pdf_path($c, $final);
        $pdf->Output('F', $file);
        return $file;
    }

    private static function url_to_path($url) {
        if (!$url) return null;
        $up = wp_upload_dir();
        return str_replace($up['baseurl'], $up['basedir'], $url);
    }
}

/* ==========================================================
 * A2.3: AJAX handler — serve contract PDFs through WordPress
 * auth layer instead of direct uploads URL
 * ========================================================== */
function at_download_contract_ajax_handler() {
    if (!defined('ABSPATH')) exit;
    require_once ABSPATH . 'at-path-safe.php';

    global $wpdb;
    $contract_id = intval( $_GET['contract_id'] ?? 0 );
    $signed      = ! empty( $_GET['signed'] );
    $token       = sanitize_text_field( $_GET['token']     ?? '' );
    $nonce       = sanitize_text_field( $_GET['_wpnonce']  ?? '' );

    if ( ! $contract_id ) {
        wp_die( 'Contrato no especificado.', 'Error', ['response' => 400] );
    }

    $authorized = false;

    // 1. Logged-in user with valid nonce
    if ( is_user_logged_in() && $nonce && wp_verify_nonce( $nonce, 'at_dl_contract_' . $contract_id ) ) {
        if ( current_user_can( 'manage_options' ) ) {
            $authorized = true; // Admin sees all contracts
        } else {
            // Regular WP client: must own the contract
            $clients_table = $wpdb->prefix . 'automatiza_tech_clients';
            $client = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$clients_table} WHERE wp_user_id = %d",
                get_current_user_id()
            ) );
            if ( $client ) {
                $c_check = ContractService::get_by_id( $contract_id );
                if ( $c_check && (int) $c_check->client_id === (int) $client->id ) {
                    $authorized = true;
                }
            }
        }
    }

    // 2. Token-based access (public sign page / AT review page — no WP login required)
    if ( ! $authorized && $token ) {
        $c_tok = ContractService::get_by_token( $token );
        if ( $c_tok && (int) $c_tok->id === $contract_id ) {
            $authorized = true;
        }
        if ( ! $authorized ) {
            $c_tok = ContractService::get_by_at_token( $token );
            if ( $c_tok && (int) $c_tok->id === $contract_id ) {
                $authorized = true;
            }
        }
    }

    if ( ! $authorized ) {
        wp_die( '❌ Acceso denegado.', 'Error', ['response' => 403] );
    }

    $c = ContractService::get_by_id( $contract_id );
    if ( ! $c ) {
        wp_die( 'Contrato no encontrado.', 'Error', ['response' => 404] );
    }

    $up   = wp_upload_dir();
    $dir  = rtrim( $up['basedir'], '/' ) . '/automatiza-tech-contracts';
    $file = $c->contract_number . ( $signed ? '-FIRMADO' : '' ) . '.pdf';
    $path = at_path_inside( $dir . '/' . $file, $dir );

    if ( ! $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
        wp_die( 'PDF no encontrado.', 'Error', ['response' => 404] );
    }

    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: inline; filename="' . basename( $path ) . '"' );
    header( 'Content-Length: ' . filesize( $path ) );
    header( 'Cache-Control: private, no-cache' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    if ( ob_get_level() ) ob_end_clean();
    readfile( $path );
    exit;
}
add_action( 'wp_ajax_at_download_contract',        'at_download_contract_ajax_handler' );
add_action( 'wp_ajax_nopriv_at_download_contract', 'at_download_contract_ajax_handler' );
