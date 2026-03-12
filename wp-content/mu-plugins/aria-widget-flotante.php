<?php
/**
 * MAXTECH - Widget Flotante con Audio, Archivos e Historial
 */

if (!defined('ABSPATH')) exit;

class ARIA_Widget_Flotante {
    
    public function __construct() {
        add_action('admin_footer', array($this, 'render_widget'));
    }
    
    public function render_widget() {
        $user_id = get_current_user_id();
        $session_id = 'aria_' . $user_id . '_' . time();
        $nonce = wp_create_nonce('aria_nonce');
        $user = wp_get_current_user();
        $first_name = !empty($user->first_name) ? $user->first_name : $user->display_name;
        $hora = (int) current_time('G');
        $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
        ?>
        
        <style>
        /* ===== ARIA WIDGET STYLES ===== */
        :root {
            --aria-primary: #6366f1;
            --aria-primary-dark: #4f46e5;
            --aria-success: #10b981;
            --aria-bg: #f8fafc;
        }
        
        /* Botón flotante */
        #aria-toggle {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 65px;
            height: 65px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--aria-primary) 0%, var(--aria-primary-dark) 100%);
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
            z-index: 999999;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        #aria-toggle:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 6px 25px rgba(99, 102, 241, 0.5);
        }
        #aria-toggle .aria-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        #aria-toggle .pulse {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: var(--aria-primary);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.5; }
            100% { transform: scale(1.5); opacity: 0; }
        }

        /* Burbuja de saludo */
        #aria-greeting-bubble {
            position: fixed;
            bottom: 100px;
            right: 22px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px 16px 4px 16px;
            padding: 12px 16px;
            max-width: 240px;
            font-size: 13px;
            line-height: 1.5;
            color: #1e293b;
            box-shadow: 0 8px 30px rgba(99,102,241,0.2);
            z-index: 999998;
            display: none;
            animation: bubbleIn .4s cubic-bezier(.34,1.56,.64,1) forwards;
        }
        #aria-greeting-bubble.show { display: block; }
        #aria-greeting-bubble::after {
            content: '';
            position: absolute;
            bottom: -8px;
            right: 22px;
            width: 0;
            height: 0;
            border-left: 8px solid transparent;
            border-right: 0;
            border-top: 8px solid #fff;
            filter: drop-shadow(0 2px 2px rgba(0,0,0,.08));
        }
        #aria-greeting-bubble .bubble-close {
            position: absolute;
            top: 5px;
            right: 8px;
            background: none;
            border: none;
            cursor: pointer;
            color: #94a3b8;
            font-size: 14px;
            line-height: 1;
            padding: 0;
        }
        #aria-greeting-bubble .bubble-close:hover { color: #475569; }
        @keyframes bubbleIn {
            from { opacity: 0; transform: scale(.7) translateY(10px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        @keyframes bubbleOut {
            from { opacity: 1; transform: scale(1); }
            to   { opacity: 0; transform: scale(.8) translateY(5px); }
        }
        #aria-greeting-bubble.hiding { animation: bubbleOut .3s ease forwards; }
        
        /* Panel principal */
        #aria-panel {
            position: fixed;
            bottom: 100px;
            right: 25px;
            width: 420px;
            max-height: 600px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.15);
            z-index: 999998;
            display: none;
            flex-direction: column;
            overflow: hidden;
        }
        #aria-panel.active { display: flex; animation: slideUp 0.3s ease; }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Header */
        .aria-header {
            background: linear-gradient(135deg, var(--aria-primary) 0%, var(--aria-primary-dark) 100%);
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .aria-header-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .aria-header-info h4 { margin: 0; font-size: 16px; font-weight: 600; }
        .aria-header-info span { font-size: 12px; opacity: 0.9; }
        .aria-header-actions { margin-left: auto; display: flex; gap: 8px; }
        .aria-header-actions button {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }
        .aria-header-actions button:hover { background: rgba(255,255,255,0.3); }
        
        /* Área de mensajes */
        .aria-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            background: var(--aria-bg);
            max-height: 350px;
        }
        .aria-msg {
            margin-bottom: 12px;
            display: flex;
            gap: 10px;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .aria-msg.user { flex-direction: row-reverse; }
        .aria-msg-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--aria-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        .aria-msg.user .aria-msg-avatar { background: #64748b; }
        .aria-msg-content {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.5;
        }
        .aria-msg.bot .aria-msg-content {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px 16px 16px 4px;
        }
        .aria-msg.user .aria-msg-content {
            background: var(--aria-primary);
            color: white;
            border-radius: 16px 16px 4px 16px;
        }
        .aria-msg-content.loading { color: #94a3b8; font-style: italic; }
        
        /* Archivos adjuntos preview */
        .aria-attachments {
            display: flex;
            gap: 8px;
            padding: 8px 16px;
            background: #f1f5f9;
            flex-wrap: wrap;
        }
        .aria-attachment {
            display: flex;
            align-items: center;
            gap: 6px;
            background: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
        }
        .aria-attachment .remove {
            cursor: pointer;
            color: #ef4444;
            font-weight: bold;
        }
        
        /* Controles de audio */
        .aria-audio-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #fef3c7;
            border-top: 1px solid #fcd34d;
        }
        .aria-audio-controls.recording { background: #fee2e2; border-color: #fca5a5; }
        .aria-audio-controls span { font-size: 12px; flex: 1; }
        
        /* Input área */
        .aria-input-area {
            padding: 12px 16px;
            border-top: 1px solid #e2e8f0;
            background: white;
        }
        .aria-input-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .aria-input-row input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            font-size: 14px;
            outline: none;
            transition: border 0.2s;
        }
        .aria-input-row input:focus { border-color: var(--aria-primary); }
        .aria-input-row button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.2s;
        }
        .aria-btn-attach { background: #f1f5f9; color: #64748b; }
        .aria-btn-attach:hover { background: #e2e8f0; }
        .aria-btn-mic { background: #f1f5f9; color: #64748b; }
        .aria-btn-mic:hover { background: #fef3c7; color: #f59e0b; }
        .aria-btn-mic.recording { background: #fee2e2; color: #ef4444; animation: pulse-mic 1s infinite; }
        @keyframes pulse-mic { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }
        .aria-btn-send { background: var(--aria-primary); color: white; }
        .aria-btn-send:hover { background: var(--aria-primary-dark); }
        
        /* Toggle audio response */
        .aria-options {
            display: flex;
            gap: 12px;
            margin-top: 8px;
            font-size: 12px;
            color: #64748b;
        }
        .aria-options label {
            display: flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
        }
        
        /* Historial panel */
        #aria-historial {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: white;
            z-index: 10;
            display: none;
            flex-direction: column;
        }
        #aria-historial.active { display: flex; }
        .historial-header {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .historial-list {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }
        .historial-item {
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            margin-bottom: 8px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        .historial-item:hover { background: #f8fafc; border-color: var(--aria-primary); }
        .historial-item .fecha { font-size: 11px; color: #94a3b8; }
        .historial-item .preview { font-size: 13px; color: #475569; margin-top: 4px; }
        
        /* Audio player inline */
        .aria-audio-player {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            padding: 8px;
            background: #f8fafc;
            border-radius: 8px;
        }
        .aria-audio-player button {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            background: var(--aria-primary);
            color: white;
            cursor: pointer;
        }
        
        /* ========== RESPONSIVE STYLES ========== */
        
        /* Tablet (768px - 1024px) */
        @media screen and (max-width: 1024px) {
            #aria-panel {
                width: 380px;
                max-height: 550px;
            }
            .aria-messages { max-height: 300px; }
        }
        
        /* Mobile Large (481px - 767px) - FULLSCREEN */
        @media screen and (max-width: 767px) {
            #aria-toggle {
                bottom: 20px;
                right: 20px;
                width: 60px;
                height: 60px;
                z-index: 999998;
            }
            #aria-toggle .aria-avatar {
                width: 45px;
                height: 45px;
                font-size: 22px;
            }
            
            /* Panel FULLSCREEN en móvil */
            #aria-panel {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                width: 100%;
                height: 100%;
                max-height: 100%;
                border-radius: 0;
                z-index: 9999999;
            }
            
            .aria-header {
                padding: 16px 20px;
                padding-top: calc(16px + env(safe-area-inset-top, 0px));
            }
            .aria-header-avatar {
                width: 42px;
                height: 42px;
                font-size: 20px;
            }
            .aria-header-info h4 { font-size: 16px; }
            .aria-header-info span { font-size: 12px; }
            .aria-header-actions button {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .aria-messages {
                flex: 1;
                max-height: none;
                height: auto;
                padding: 16px;
                padding-bottom: 8px;
            }
            .aria-msg-content {
                max-width: 85%;
                padding: 12px 16px;
                font-size: 15px;
            }
            
            .aria-input-area { 
                padding: 12px 16px;
                padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px));
                background: white;
                border-top: 1px solid #e2e8f0;
            }
            .aria-input-row input {
                padding: 14px 18px;
                font-size: 16px;
                border-radius: 24px;
            }
            .aria-input-row button {
                width: 48px;
                height: 48px;
                min-width: 48px;
                font-size: 18px;
            }
            
            .aria-options {
                justify-content: center;
                gap: 20px;
                margin-top: 10px;
                font-size: 13px;
            }
            
            /* Historial también fullscreen */
            #aria-historial {
                padding-top: env(safe-area-inset-top, 0px);
            }
            .historial-header {
                padding: 16px 20px;
            }
            .historial-item {
                padding: 16px;
                margin-bottom: 10px;
            }
            .historial-item .fecha { font-size: 11px; }
            .historial-item .preview { font-size: 14px; }
            
            /* Ocultar botón toggle cuando panel está abierto */
            #aria-panel.active ~ #aria-toggle,
            body.aria-open #aria-toggle {
                display: none;
            }
        }
        
        /* Mobile Small (hasta 480px) - FULLSCREEN */
        @media screen and (max-width: 480px) {
            #aria-toggle {
                bottom: 15px;
                right: 15px;
                width: 55px;
                height: 55px;
            }
            #aria-toggle .aria-avatar {
                width: 42px;
                height: 42px;
                font-size: 20px;
            }
            
            /* Panel sigue siendo FULLSCREEN */
            #aria-panel {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                width: 100%;
                height: 100%;
                max-height: 100%;
                border-radius: 0;
            }
            
            .aria-header {
                padding: 14px 16px;
                padding-top: calc(14px + env(safe-area-inset-top, 0px));
                gap: 10px;
            }
            .aria-header-avatar {
                width: 38px;
                height: 38px;
                font-size: 18px;
            }
            .aria-header-info h4 { font-size: 15px; }
            .aria-header-info span { font-size: 11px; }
            .aria-header-actions {
                gap: 8px;
            }
            .aria-header-actions button {
                width: 36px;
                height: 36px;
                font-size: 14px;
            }
            
            .aria-messages {
                flex: 1;
                max-height: none;
                padding: 12px;
            }
            .aria-msg {
                gap: 8px;
                margin-bottom: 10px;
            }
            .aria-msg-avatar {
                width: 30px;
                height: 30px;
                font-size: 13px;
            }
            .aria-msg-content {
                max-width: 88%;
                padding: 10px 14px;
                font-size: 14px;
                line-height: 1.5;
                border-radius: 14px;
            }
            .aria-msg.bot .aria-msg-content { border-radius: 14px 14px 14px 4px; }
            .aria-msg.user .aria-msg-content { border-radius: 14px 14px 4px 14px; }
            
            .aria-attachments {
                padding: 8px 14px;
                gap: 6px;
            }
            .aria-attachment {
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .aria-audio-controls {
                padding: 8px 14px;
            }
            .aria-audio-controls span { font-size: 12px; }
            
            .aria-input-area {
                padding: 12px 14px;
                padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px));
            }
            .aria-input-row {
                gap: 8px;
            }
            .aria-input-row input {
                padding: 12px 16px;
                font-size: 16px;
                border-radius: 22px;
            }
            .aria-input-row button {
                width: 44px;
                height: 44px;
                min-width: 44px;
                font-size: 16px;
            }
            
            .aria-options {
                margin-top: 8px;
                font-size: 12px;
                gap: 16px;
            }
            
            .historial-header {
                padding: 14px 16px;
            }
            .historial-header h4 { font-size: 15px; }
            .historial-list { padding: 12px; }
            .historial-item {
                padding: 14px;
                margin-bottom: 8px;
            }
            .historial-item .fecha { font-size: 10px; }
            .historial-item .preview { font-size: 13px; }
            
            .aria-audio-player {
                padding: 8px;
                gap: 8px;
            }
            .aria-audio-player button {
                width: 32px;
                height: 32px;
            }
        }
        
        /* Landscape mode on mobile - también fullscreen */
        @media screen and (max-width: 767px) and (orientation: landscape) {
            #aria-panel {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                width: 100%;
                height: 100%;
            }
            .aria-header {
                padding: 10px 16px;
            }
            .aria-messages {
                max-height: none;
                flex: 1;
            }
            .aria-input-area {
                padding: 8px 16px;
            }
            #aria-toggle {
                bottom: 10px;
                right: 10px;
                width: 50px;
                height: 50px;
            }
        }
        
        /* Touch improvements */
        @media (hover: none) and (pointer: coarse) {
            .aria-input-row button,
            .aria-header-actions button,
            .historial-item {
                min-height: 44px;
            }
            .aria-input-row input {
                min-height: 48px;
            }
            #aria-toggle {
                min-width: 55px;
                min-height: 55px;
            }
        }
        
        /* Safe area for notched phones (iPhone X+) */
        @supports (padding: env(safe-area-inset-top)) {
            @media screen and (max-width: 767px) {
                .aria-header {
                    padding-top: calc(16px + env(safe-area-inset-top));
                }
                .aria-input-area {
                    padding-bottom: calc(12px + env(safe-area-inset-bottom));
                }
            }
        }
        
        /* Dark mode support (optional - follows system preference) */
        @media (prefers-color-scheme: dark) {
            /* Uncomment to enable dark mode
            #aria-panel {
                background: #1e293b;
            }
            .aria-messages {
                background: #0f172a;
            }
            .aria-msg.bot .aria-msg-content {
                background: #334155;
                border-color: #475569;
                color: #f1f5f9;
            }
            .aria-input-area {
                background: #1e293b;
                border-color: #334155;
            }
            .aria-input-row input {
                background: #0f172a;
                border-color: #334155;
                color: #f1f5f9;
            }
            */
        }
        </style>
        
        <!-- Burbuja de saludo -->
        <div id="aria-greeting-bubble">
            <button class="bubble-close" id="ariaGreetingClose" title="Cerrar">&times;</button>
            <strong style="color:#6366f1;">🤖 MAXTECH</strong><br>
            <?php echo esc_html($saludo); ?>, <strong><?php echo esc_html($first_name); ?></strong>! 👋<br>
            Estoy aquí para ayudarte.
        </div>

        <!-- Botón flotante -->
        <button id="aria-toggle" title="MAXTECH - Asistente IA">
            <div class="pulse"></div>
            <div class="aria-avatar">🤖</div>
        </button>
        
        <!-- Panel principal -->
        <div id="aria-panel">
            <!-- Header -->
            <div class="aria-header">
                <div class="aria-header-avatar">🤖</div>
                <div class="aria-header-info">
                    <h4>MAXTECH</h4>
                    <span>Tu Experto en CRM</span>
                </div>
                <div class="aria-header-actions">
                    <button onclick="ariaHistorial()" title="Historial">📜</button>
                    <button onclick="ariaNuevaSesion()" title="Nueva conversación">➕</button>
                    <button onclick="toggleAriaPanel()" title="Cerrar">✕</button>
                </div>
            </div>
            
            <!-- Historial panel (oculto por defecto) -->
            <div id="aria-historial">
                <div class="historial-header">
                    <strong>📜 Historial de conversaciones</strong>
                    <button onclick="cerrarHistorial()" class="button button-small">Volver</button>
                </div>
                <div class="historial-list" id="historialList"></div>
            </div>
            
            <!-- Mensajes -->
            <div class="aria-messages" id="ariaMessages">
                <div class="aria-msg bot">
                    <div class="aria-msg-avatar">🤖</div>
                    <div class="aria-msg-content">
                        👋 <?php echo esc_html($saludo); ?>, <strong><?php echo esc_html($first_name); ?></strong>! Soy <strong>MAXTECH</strong>. 🚀<br><br>
                        Puedo ayudarte con el <strong>CRM</strong>, módulo <strong>QA</strong>, automatizaciones, analizar documentos o responder por voz. ¿En qué te ayudo?
                    </div>
                </div>
            </div>
            
            <!-- Archivos adjuntos (si hay) -->
            <div class="aria-attachments" id="ariaAttachments" style="display:none;"></div>
            
            <!-- Controles de grabación (si está grabando) -->
            <div class="aria-audio-controls" id="ariaRecording" style="display:none;">
                <span>🎙️ Grabando... <span id="recordTime">0:00</span></span>
                <button onclick="stopRecording()" class="button button-small" style="background:#ef4444;color:white;border:none;">Detener</button>
            </div>
            
            <!-- Input -->
            <div class="aria-input-area">
                <div class="aria-input-row">
                    <button class="aria-btn-attach" onclick="document.getElementById('ariaFileInput').click()" title="Adjuntar archivo">📎</button>
                    <input type="text" id="ariaInput" placeholder="Escribe tu mensaje...">
                    <button class="aria-btn-mic" id="ariaMicBtn" onclick="toggleRecording()" title="Grabar audio">🎤</button>
                    <button class="aria-btn-send" onclick="ariaEnviar()" title="Enviar">➤</button>
                </div>
                <div class="aria-options">
                    <label><input type="checkbox" id="ariaVoiceResponse"> 🔊 Responder con voz</label>
                </div>
            </div>
            
            <input type="file" id="ariaFileInput" style="display:none;" accept="image/*,.pdf,.txt,.csv" onchange="ariaAdjuntar(this)">
        </div>
        
        <script>
        // Variables globales
        var ariaSessionId = '<?php echo $session_id; ?>';
        var ariaNonce = '<?php echo $nonce; ?>';
        var ariaArchivos = [];
        var mediaRecorder = null;
        var audioChunks = [];
        var recordInterval = null;
        var recognition = null;
        
        // Extrae JSON de WordPress aunque haya warnings/Xdebug antes
        function safeJson(text) {
            if (typeof text === 'object') return text;
            // Buscar patrón WordPress específico primero
            var idx = text.indexOf('{"success":');
            if (idx === -1) idx = text.indexOf('{"data":');
            if (idx === -1) idx = text.indexOf('{');
            if (idx === -1) return null;
            var end = text.lastIndexOf('}');
            if (end === -1 || end <= idx) return null;
            try { return JSON.parse(text.substring(idx, end + 1)); } catch(e) {
                // Si falla, intentar encontrar el JSON más corto válido desde idx
                for (var i = end; i > idx; i--) {
                    try { return JSON.parse(text.substring(idx, i + 1)); } catch(e2) {}
                }
                return null;
            }
        }

        // Toggle panel
        function toggleAriaPanel() {
            document.getElementById('aria-panel').classList.toggle('active');
        }
        document.getElementById('aria-toggle').addEventListener('click', function() {
            // Ocultar burbuja al abrir el panel
            hideGreetingBubble();
            toggleAriaPanel();
        });

        // === Burbuja de saludo ===
        const BUBBLE_KEY = 'aria_greeted_<?php echo get_current_user_id(); ?>_' + new Date().toDateString();
        function showGreetingBubble(){
            const bubble = document.getElementById('aria-greeting-bubble');
            if(!bubble) return;
            bubble.classList.add('show');
            // Auto-dismiss en 6 segundos
            window._ariaBubbleTimer = setTimeout(hideGreetingBubble, 6000);
        }
        function hideGreetingBubble(){
            const bubble = document.getElementById('aria-greeting-bubble');
            if(!bubble || !bubble.classList.contains('show')) return;
            clearTimeout(window._ariaBubbleTimer);
            bubble.classList.add('hiding');
            setTimeout(function(){ bubble.classList.remove('show','hiding'); }, 320);
        }
        document.getElementById('ariaGreetingClose').addEventListener('click', function(e){
            e.stopPropagation();
            hideGreetingBubble();
            localStorage.setItem(BUBBLE_KEY, '1');
        });
        // Mostrar solo una vez por día
        if(!localStorage.getItem(BUBBLE_KEY)){
            setTimeout(showGreetingBubble, 1200);
            localStorage.setItem(BUBBLE_KEY, '1');
        }
        
        // Enviar mensaje
        function ariaEnviar() {
            var input = document.getElementById('ariaInput');
            var mensaje = input.value.trim();
            if (!mensaje && ariaArchivos.length === 0) return;
            
            var messages = document.getElementById('ariaMessages');
            
            // Mostrar mensaje del usuario
            var attachStr = '';
            if (ariaArchivos.length > 0) {
                attachStr = '<br><small>📎 ' + ariaArchivos.length + ' archivo(s)</small>';
            }
            messages.innerHTML += '<div class="aria-msg user"><div class="aria-msg-avatar">👤</div><div class="aria-msg-content">' + (mensaje || 'Archivo adjunto') + attachStr + '</div></div>';
            
            // Loading
            var loadId = 'load-' + Date.now();
            messages.innerHTML += '<div class="aria-msg bot" id="' + loadId + '"><div class="aria-msg-avatar">✨</div><div class="aria-msg-content loading">Pensando...</div></div>';
            messages.scrollTop = messages.scrollHeight;
            
            input.value = '';
            
            // Enviar al servidor
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'text',  // recibir texto crudo para tolerar WP_DEBUG
                timeout: 90000,
                data: {
                    action: 'aria_chat',
                    nonce: ariaNonce,
                    mensaje: mensaje,
                    session_id: ariaSessionId,
                    archivos: JSON.stringify(ariaArchivos)
                },
                success: function(raw) {
                    var el = document.getElementById(loadId);
                    if (!el) return;
                    var response = safeJson(raw);
                    if (response && response.success) {
                        var texto = response.data.respuesta;
                        texto = texto.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                        texto = texto.replace(/\n/g, '<br>');
                        
                        var audioHtml = '';
                        if (document.getElementById('ariaVoiceResponse') && document.getElementById('ariaVoiceResponse').checked) {
                            audioHtml = '<div class="aria-audio-player" id="audioPlayer-' + loadId + '"><button onclick="playAriaAudio(\'' + loadId + '\', \'' + encodeURIComponent(response.data.respuesta.substring(0, 500)) + '\')">▶️</button><span>Reproducir respuesta</span></div>';
                        }
                        
                        el.querySelector('.aria-msg-content').innerHTML = texto + audioHtml;
                    } else if (response && !response.success) {
                        el.querySelector('.aria-msg-content').innerHTML = '❌ ' + (response.data || 'Error al procesar');
                    } else {
                        el.querySelector('.aria-msg-content').innerHTML = '❌ Respuesta inesperada del servidor. Intenta de nuevo.';
                    }
                    messages.scrollTop = messages.scrollHeight;
                },
                error: function(xhr, status) {
                    var el = document.getElementById(loadId);
                    if (!el) return;
                    var msg = status === 'timeout'
                        ? '⏱️ La respuesta tardó demasiado. Intenta de nuevo.'
                        : '❌ Error de conexión. Intenta de nuevo.';
                    el.querySelector('.aria-msg-content').innerHTML = msg;
                    messages.scrollTop = messages.scrollHeight;
                }
            });
            
            // Limpiar archivos
            ariaArchivos = [];
            document.getElementById('ariaAttachments').style.display = 'none';
            document.getElementById('ariaAttachments').innerHTML = '';
        }
        
        // Adjuntar archivo
        function ariaAdjuntar(input) {
            if (!input.files.length) return;
            
            var file = input.files[0];
            var formData = new FormData();
            formData.append('archivo', file);
            formData.append('action', 'aria_upload');
            formData.append('nonce', ariaNonce);
            
            // Mostrar preview
            var attachDiv = document.getElementById('ariaAttachments');
            attachDiv.style.display = 'flex';
            var tempId = 'att-' + Date.now();
            attachDiv.innerHTML += '<div class="aria-attachment" id="' + tempId + '">📄 ' + file.name + ' <span class="remove" onclick="removeAttachment(\'' + tempId + '\')">×</span></div>';
            
            // Subir
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'text',
                success: function(raw) {
                    var response = safeJson(raw);
                    if (response && response.success) {
                        ariaArchivos.push(response.data);
                    } else {
                        document.getElementById(tempId).innerHTML = '❌ Error: ' + (response ? response.data : 'respuesta inválida');
                    }
                }
            });
            
            input.value = '';
        }
        
        function removeAttachment(id) {
            document.getElementById(id).remove();
            ariaArchivos = ariaArchivos.filter(a => a.name !== id);
            if (ariaArchivos.length === 0) {
                document.getElementById('ariaAttachments').style.display = 'none';
            }
        }
        
        // Grabación de voz
        function toggleRecording() {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                stopRecording();
            } else {
                startRecording();
            }
        }
        
        function startRecording() {
            // Usar Web Speech API para transcripción directa
            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                recognition = new SpeechRecognition();
                recognition.lang = 'es-ES';
                recognition.continuous = true;
                recognition.interimResults = true;
                
                document.getElementById('ariaMicBtn').classList.add('recording');
                document.getElementById('ariaRecording').style.display = 'flex';
                
                var seconds = 0;
                recordInterval = setInterval(function() {
                    seconds++;
                    document.getElementById('recordTime').textContent = Math.floor(seconds/60) + ':' + String(seconds%60).padStart(2, '0');
                }, 1000);
                
                recognition.onresult = function(event) {
                    var transcript = '';
                    for (var i = event.resultIndex; i < event.results.length; i++) {
                        transcript += event.results[i][0].transcript;
                    }
                    document.getElementById('ariaInput').value = transcript;
                };
                
                recognition.start();
            } else {
                alert('Tu navegador no soporta reconocimiento de voz');
            }
        }
        
        function stopRecording() {
            if (recognition) {
                recognition.stop();
            }
            clearInterval(recordInterval);
            document.getElementById('ariaMicBtn').classList.remove('recording');
            document.getElementById('ariaRecording').style.display = 'none';
        }
        
        // Reproducir audio de respuesta
        function playAriaAudio(id, texto) {
            var btn = document.querySelector('#audioPlayer-' + id + ' button');
            btn.textContent = '⏳';
            
            jQuery.ajax({
                url: ajaxurl, type: 'POST', dataType: 'text',
                data: { action: 'aria_tts', nonce: ariaNonce, texto: decodeURIComponent(texto) },
                success: function(raw) {
                    var response = safeJson(raw);
                    if (response && response.success) {
                        var audio = new Audio(response.data.audio_url);
                        audio.play();
                        btn.textContent = '🔊';
                        audio.onended = function() { btn.textContent = '▶️'; };
                    } else {
                        btn.textContent = '❌';
                    }
                }
            });
        }
        
        // Historial
        function ariaHistorial() {
            var panel = document.getElementById('aria-historial');
            panel.classList.add('active');
            
            jQuery.ajax({
                url: ajaxurl, type: 'POST', dataType: 'text',
                data: { action: 'aria_historial', nonce: ariaNonce },
                success: function(raw) {
                    var response = safeJson(raw);
                    if (response && response.success) {
                        var html = '';
                        response.data.forEach(function(s) {
                            html += '<div class="historial-item" onclick="cargarSesion(\'' + s.session_id + '\')">';
                            html += '<div class="fecha">' + s.inicio + ' (' + s.mensajes + ' mensajes)</div>';
                            html += '<div class="preview">' + (s.primer_mensaje || '').substring(0, 50) + '...</div>';
                            html += '</div>';
                        });
                        document.getElementById('historialList').innerHTML = html || '<p style="padding:20px;color:#94a3b8;">No hay conversaciones anteriores</p>';
                    } else {
                        document.getElementById('historialList').innerHTML = '<p style="padding:20px;color:#94a3b8;">Error al cargar historial</p>';
                    }
                }
            });
        }
        
        function cerrarHistorial() {
            document.getElementById('aria-historial').classList.remove('active');
        }
        
        function cargarSesion(sessionId) {
            ariaSessionId = sessionId;
            cerrarHistorial();
            
            var messages = document.getElementById('ariaMessages');
            messages.innerHTML = '<div class="aria-msg bot"><div class="aria-msg-avatar">⏳</div><div class="aria-msg-content">Cargando conversación...</div></div>';
            
            jQuery.ajax({
                url: ajaxurl, type: 'POST', dataType: 'text',
                data: { action: 'aria_cargar_sesion', nonce: ariaNonce, session_id: sessionId },
                success: function(raw) {
                    var response = safeJson(raw);
                    if (response && response.success && response.data.length > 0) {
                        var html = '';
                        response.data.forEach(function(msg) {
                            var isBot = msg.role === 'assistant';
                            var avatar = isBot ? '🤖' : '👤';
                            var clase = isBot ? 'bot' : 'user';
                            var contenido = msg.content.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
                            html += '<div class="aria-msg ' + clase + '"><div class="aria-msg-avatar">' + avatar + '</div><div class="aria-msg-content">' + contenido + '</div></div>';
                        });
                        messages.innerHTML = html;
                        messages.scrollTop = messages.scrollHeight;
                    } else {
                        messages.innerHTML = '<div class="aria-msg bot"><div class="aria-msg-avatar">🤖</div><div class="aria-msg-content">No se encontraron mensajes en esta sesión.</div></div>';
                    }
                }
            });
        }
        
        // Nueva sesión
        function ariaNuevaSesion() {
            jQuery.ajax({
                url: ajaxurl, type: 'POST', dataType: 'text',
                data: { action: 'aria_nueva_sesion', nonce: ariaNonce },
                success: function(raw) {
                    var response = safeJson(raw);
                    if (response && response.success) {
                        ariaSessionId = response.data.session_id;
                        document.getElementById('ariaMessages').innerHTML = '<div class="aria-msg bot"><div class="aria-msg-avatar">🤖</div><div class="aria-msg-content">¡Nueva conversación iniciada! ¿En qué te ayudo? 🚀</div></div>';
                    }
                }
            });
        }
        
        // Enter para enviar
        document.getElementById('ariaInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') ariaEnviar();
        });
        
        // Escape para cerrar
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') document.getElementById('aria-panel').classList.remove('active');
        });
        </script>
        <?php
    }
}

new ARIA_Widget_Flotante();
