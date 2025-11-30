<?php
/**
 * Chat Widget Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

function automatiza_tech_chat_scripts() {
    wp_enqueue_style(
        'automatiza-ai-chat-style',
        get_template_directory_uri() . '/assets/chat/css/style.css',
        array(),
        '1.0'
    );

    wp_enqueue_script(
        'automatiza-ai-chat-script',
        get_template_directory_uri() . '/assets/chat/js/chat.js',
        array('jquery'),
        '1.0',
        true
    );

    // Pass configuration to JS
    $schedule_settings = get_option('automatiza_chat_schedule', array());
    
    // Default values if not set
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    foreach ($days as $day) {
        if (!isset($schedule_settings[$day])) {
            $schedule_settings[$day] = array(
                'enabled' => true,
                'start' => ($day == 'saturday' || $day == 'sunday') ? '15:00' : '09:00',
                'end' => ($day == 'saturday' || $day == 'sunday') ? '17:00' : '21:00'
            );
        }
    }
    
    // Ensure holidays is an array
    if (!isset($schedule_settings['holidays'])) {
        $schedule_settings['holidays'] = '';
    }

    wp_localize_script('automatiza-ai-chat-script', 'AutomatizaAIChat', array(
        'webhookUrl' => 'https://n8n-n8n.kchiba.easypanel.host/webhook/becd5a16-7b3a-4961-8a2c-e86ca01d069e', // URL Producción
        'greeting' => '¡Hola! Soy Tech 🤖 tu asistente virtual de Automatiza Tech. ¿En qué puedo ayudarte hoy?',
        'logoUrl' => get_template_directory_uri() . '/assets/images/solo-logo.svg',
        'schedule' => $schedule_settings
    ));
}
add_action('wp_enqueue_scripts', 'automatiza_tech_chat_scripts');

/**
 * Admin Settings for Chat Schedule
 */
function automatiza_tech_chat_admin_menu() {
    add_menu_page(
        'Configuración Chat IA',
        'Chat IA',
        'manage_options',
        'automatiza-chat-settings',
        'automatiza_tech_chat_settings_page',
        'dashicons-format-chat',
        100
    );
}
add_action('admin_menu', 'automatiza_tech_chat_admin_menu');

function automatiza_tech_chat_settings_page() {
    ?>
    <div class="wrap">
        <h1>Configuración de Horarios - Chat IA</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('automatiza_chat_options');
            do_settings_sections('automatiza-chat-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function automatiza_tech_chat_register_settings() {
    register_setting('automatiza_chat_options', 'automatiza_chat_schedule');

    add_settings_section(
        'automatiza_chat_schedule_section',
        'Horarios de Atención Semanal',
        null,
        'automatiza-chat-settings'
    );

    $days = [
        'monday' => 'Lunes',
        'tuesday' => 'Martes',
        'wednesday' => 'Miércoles',
        'thursday' => 'Jueves',
        'friday' => 'Viernes',
        'saturday' => 'Sábado',
        'sunday' => 'Domingo'
    ];

    foreach ($days as $key => $label) {
        add_settings_field(
            'schedule_' . $key, 
            $label, 
            'automatiza_chat_day_callback', 
            'automatiza-chat-settings', 
            'automatiza_chat_schedule_section',
            array('day' => $key)
        );
    }

    add_settings_section(
        'automatiza_chat_holidays_section',
        'Días Feriados / Bloqueados',
        null,
        'automatiza-chat-settings'
    );

    add_settings_field(
        'holidays', 
        'Fechas Bloqueadas', 
        'automatiza_chat_holidays_callback', 
        'automatiza-chat-settings', 
        'automatiza_chat_holidays_section'
    );
}
add_action('admin_init', 'automatiza_tech_chat_register_settings');

function automatiza_chat_day_callback($args) {
    $options = get_option('automatiza_chat_schedule');
    $day = $args['day'];
    
    $enabled = isset($options[$day]['enabled']) ? $options[$day]['enabled'] : true;
    $start = isset($options[$day]['start']) ? $options[$day]['start'] : '09:00';
    $end = isset($options[$day]['end']) ? $options[$day]['end'] : '18:00';
    ?>
    <label>
        <input type="checkbox" name="automatiza_chat_schedule[<?php echo $day; ?>][enabled]" value="1" <?php checked($enabled, 1); ?>> 
        Habilitado
    </label>
    &nbsp;&nbsp;
    <input type="time" name="automatiza_chat_schedule[<?php echo $day; ?>][start]" value="<?php echo esc_attr($start); ?>">
    a
    <input type="time" name="automatiza_chat_schedule[<?php echo $day; ?>][end]" value="<?php echo esc_attr($end); ?>">
    <?php
}

function automatiza_chat_holidays_callback() {
    $options = get_option('automatiza_chat_schedule');
    $holidays = isset($options['holidays']) ? $options['holidays'] : '';
    ?>
    <textarea name="automatiza_chat_schedule[holidays]" rows="5" cols="50" placeholder="YYYY-MM-DD (una fecha por línea)"><?php echo esc_textarea($holidays); ?></textarea>
    <p class="description">Ingresa las fechas que no habrá atención, una por línea. Ejemplo: 2025-12-25</p>
    <?php
}

function automatiza_tech_render_chat_widget() {
    ?>
    <div id="automatiza-ai-chat-widget" class="closed">
        <div class="chat-header">
            <div class="chat-title">Asistente Automatiza Tech 🤖</div>
            <button class="chat-close-btn">&times;</button>
        </div>
        <div class="chat-messages" id="chat-messages">
            <!-- Messages will appear here -->
        </div>
        <div class="chat-input-area">
            <input type="text" id="chat-input" placeholder="Escribe tu mensaje..." />
            <button id="chat-send-btn">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            </button>
        </div>
    </div>

    <!-- Chat Toggle Container with Robot Animation -->
    <div id="automatiza-chat-toggle-container">
        <div class="chat-robot-peek">
            <div class="chat-robot-content-wrapper">
                <div class="chat-robot-avatar">🤖</div>
                <div class="chat-robot-bubble">
                    <span class="chat-robot-text" id="chatRobotMessage">¡Hola! ¿Te ayudo?</span>
                    <div class="chat-bubble-tail"></div>
                </div>
            </div>
        </div>
        <button id="automatiza-ai-chat-toggle">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            <span class="toggle-text">Pregúntale a Tech</span>
        </button>
    </div>
    <?php
}
add_action('wp_footer', 'automatiza_tech_render_chat_widget');
