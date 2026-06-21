<?php
if (!defined('ABSPATH')) {
    exit;
}

const AT_HOME_PREMIUM_ATTR_VERSION = '2026_06_20_reuse_pipeline_v2';

add_action('init', 'automatiza_tech_home_premium_maybe_install_attribution');
add_action('wp_head', 'automatiza_tech_home_premium_head', 2);
add_filter('robots_txt', 'automatiza_tech_home_premium_robots_txt', 10, 2);
// Atribución por session_id (path Opción A: el lead lo crea n8n, no hay lead_id al enviar).
add_action('wp_ajax_at_home_premium_attr', 'automatiza_tech_home_premium_attr_ajax');
add_action('wp_ajax_nopriv_at_home_premium_attr', 'automatiza_tech_home_premium_attr_ajax');

/**
 * Side-table de atribucion, no CRM paralelo.
 * Fuente unica de verdad comercial/agendamiento: wp_automatiza_leads.
 */
function automatiza_tech_home_premium_maybe_install_attribution() {
    if (get_option('at_home_premium_attr_version') === AT_HOME_PREMIUM_ATTR_VERSION) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'automatiza_lead_attribution';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
        session_id varchar(100) DEFAULT '',
        created_at datetime NOT NULL,
        source varchar(80) DEFAULT '',
        intent varchar(30) DEFAULT '',
        interest varchar(120) DEFAULT '',
        lead_temperature varchar(20) DEFAULT 'tibio',
        page varchar(255) DEFAULT '',
        referrer varchar(255) DEFAULT '',
        utm_source varchar(120) DEFAULT '',
        utm_medium varchar(120) DEFAULT '',
        utm_campaign varchar(160) DEFAULT '',
        utm_content varchar(160) DEFAULT '',
        utm_term varchar(160) DEFAULT '',
        PRIMARY KEY (id),
        KEY lead_id (lead_id),
        KEY session_id (session_id),
        KEY source (source)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Fixes para tablas creadas con el esquema v1 (UNIQUE lead_id, sin session_id).
    if (!$wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'session_id'")) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN session_id varchar(100) DEFAULT '' AFTER lead_id");
        $wpdb->query("ALTER TABLE {$table} ADD INDEX session_id (session_id)");
    }
    $idx = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'lead_id'");
    if (!empty($idx) && (int) $idx[0]->Non_unique === 0) {
        $wpdb->query("ALTER TABLE {$table} DROP INDEX lead_id");
        $wpdb->query("ALTER TABLE {$table} ADD INDEX lead_id (lead_id)");
    }

    update_option('at_home_premium_attr_version', AT_HOME_PREMIUM_ATTR_VERSION, false);
}

function automatiza_tech_home_premium_store_lead_attribution($lead_id, $params) {
    if (!$lead_id || empty($params) || !is_array($params)) {
        return;
    }

    $source = isset($params['source']) ? sanitize_text_field($params['source']) : '';
    if (strpos($source, 'home_premium') !== 0) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'automatiza_lead_attribution';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        automatiza_tech_home_premium_maybe_install_attribution();
    }

    $intent = isset($params['intent']) ? sanitize_text_field($params['intent']) : '';
    $interest = isset($params['interest']) ? sanitize_text_field($params['interest']) : '';
    $temperature = automatiza_tech_home_premium_temperature($intent, $interest, isset($params['scheduled_date']) ? $params['scheduled_date'] : '');

    $row = array(
        'lead_id' => (int) $lead_id,
        'created_at' => current_time('mysql'),
        'source' => $source,
        'intent' => $intent,
        'interest' => $interest,
        'lead_temperature' => $temperature,
        'page' => isset($params['page']) ? sanitize_text_field($params['page']) : '',
        'referrer' => isset($params['referrer']) ? esc_url_raw($params['referrer']) : '',
        'utm_source' => isset($params['utm_source']) ? sanitize_text_field($params['utm_source']) : '',
        'utm_medium' => isset($params['utm_medium']) ? sanitize_text_field($params['utm_medium']) : '',
        'utm_campaign' => isset($params['utm_campaign']) ? sanitize_text_field($params['utm_campaign']) : '',
        'utm_content' => isset($params['utm_content']) ? sanitize_text_field($params['utm_content']) : '',
        'utm_term' => isset($params['utm_term']) ? sanitize_text_field($params['utm_term']) : '',
    );

    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE lead_id = %d", $lead_id));
    if ($exists) {
        $wpdb->update($table, $row, array('lead_id' => (int) $lead_id));
    } else {
        $wpdb->insert($table, $row);
    }
}

/**
 * AJAX público: guarda atribución por session_id (path Opción A, sin lead_id aún).
 * n8n crea el lead con el mismo session_id en wp_automatiza_leads; el cruce
 * attribution.session_id = leads.session_id da el lead. Sin PII.
 */
function automatiza_tech_home_premium_attr_ajax() {
    check_ajax_referer('at_home_premium_attr', 'nonce');
    $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
    $source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '';
    if ($session_id === '' || strpos($source, 'home_premium') !== 0) {
        wp_send_json_error(array('message' => 'invalid'), 400);
    }
    automatiza_tech_home_premium_store_attr_by_session($session_id, wp_unslash($_POST));
    wp_send_json_success(array('ok' => true));
}

function automatiza_tech_home_premium_store_attr_by_session($session_id, $params) {
    global $wpdb;
    $table = $wpdb->prefix . 'automatiza_lead_attribution';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        automatiza_tech_home_premium_maybe_install_attribution();
    }
    $f = static function ($k) use ($params) { return isset($params[$k]) ? sanitize_text_field($params[$k]) : ''; };
    $intent = $f('intent');
    $interest = $f('interest');
    $row = array(
        'session_id' => $session_id,
        'created_at' => current_time('mysql'),
        'source' => $f('source'),
        'intent' => $intent,
        'interest' => $interest,
        'lead_temperature' => automatiza_tech_home_premium_temperature($intent, $interest, $f('scheduled_date')),
        'page' => $f('page'),
        'referrer' => isset($params['referrer']) ? esc_url_raw($params['referrer']) : '',
        'utm_source' => $f('utm_source'),
        'utm_medium' => $f('utm_medium'),
        'utm_campaign' => $f('utm_campaign'),
        'utm_content' => $f('utm_content'),
        'utm_term' => $f('utm_term'),
    );
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE session_id = %s", $session_id));
    if ($exists) {
        $wpdb->update($table, $row, array('session_id' => $session_id));
    } else {
        $wpdb->insert($table, $row);
    }
}

function automatiza_tech_home_premium_temperature($intent, $interest, $scheduled_date = '') {
    $interest = strtolower(remove_accents((string) $interest));
    if ($intent === 'demo' || $scheduled_date !== '') {
        return 'caliente';
    }
    if (strpos($interest, 'sistema') !== false || strpos($interest, 'app') !== false || strpos($interest, 'sitio web') !== false) {
        return 'caliente';
    }
    if (strpos($interest, 'diagnostico') !== false || strpos($interest, 'asistente') !== false || strpos($interest, 'chatbot') !== false) {
        return 'tibio';
    }
    return 'frio';
}

function automatiza_tech_home_premium_head() {
    if (!is_page_template('template-home-premium.php')) {
        return;
    }

    $url = get_permalink();
    $title = 'AutomatizaTech - Automatizacion y tecnologia digital premium para negocios';
    $desc = 'Asistentes inteligentes, automatizaciones, sitios web premium, aplicaciones web, apps moviles y sistemas a medida. Agenda un diagnostico gratis.';
    $img = get_template_directory_uri() . '/assets/home-premium/img/at-logo.png';
    $schema = array(
        '@context' => 'https://schema.org',
        '@graph' => array(
            array(
                '@type' => 'WebPage',
                '@id' => $url . '#webpage',
                'url' => $url,
                'name' => $title,
                'description' => $desc,
                'inLanguage' => get_bloginfo('language'),
            ),
            array(
                '@type' => 'Organization',
                '@id' => home_url('/#organization'),
                'name' => 'AutomatizaTech',
                'url' => home_url('/'),
                'logo' => $img,
            ),
            array(
                '@type' => 'Service',
                '@id' => $url . '#service',
                'name' => 'Diagnostico gratis de tecnologia para negocios',
                'provider' => array('@id' => home_url('/#organization')),
                'areaServed' => array('@type' => 'Country', 'name' => 'Chile'),
                'serviceType' => array('Asistentes inteligentes', 'Automatizacion de negocios', 'Sitios web premium', 'Aplicaciones web', 'Apps moviles', 'Sistemas a medida'),
            ),
            array(
                '@type' => 'FAQPage',
                '@id' => $url . '#faq',
                'mainEntity' => array(
                    array(
                        '@type' => 'Question',
                        'name' => 'Que tecnologia necesita mi negocio primero?',
                        'acceptedAnswer' => array('@type' => 'Answer', 'text' => 'AutomatizaTech revisa tu operacion y recomienda si conviene partir por asistente inteligente, automatizacion, sitio premium, web app, app movil o sistema a medida.'),
                    ),
                    array(
                        '@type' => 'Question',
                        'name' => 'El diagnostico es gratis?',
                        'acceptedAnswer' => array('@type' => 'Answer', 'text' => 'Si. En 15 a 30 minutos se revisa el negocio y se define una primera ruta tecnologica.'),
                    ),
                ),
            ),
            array(
                '@type' => 'BreadcrumbList',
                '@id' => $url . '#breadcrumb',
                'itemListElement' => array(
                    array('@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => home_url('/')),
                    array('@type' => 'ListItem', 'position' => 2, 'name' => 'Diagnostico tecnologico', 'item' => $url),
                ),
            ),
        ),
    );

    echo "\n<link rel=\"canonical\" href=\"" . esc_url($url) . "\">\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    echo '<meta property="og:image" content="' . esc_url($img) . '">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($desc) . '">' . "\n";
    echo '<meta name="twitter:image" content="' . esc_url($img) . '">' . "\n";
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}

function automatiza_tech_home_premium_robots_txt($output, $public) {
    $extra = "\nSitemap: " . home_url('/wp-sitemap.xml') . "\n";
    $extra .= "LLMS: " . home_url('/llms.txt') . "\n";
    return $output . $extra;
}