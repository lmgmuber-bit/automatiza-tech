<?php
// ver-demo.php
require_once('wp-load.php');

$unique_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';

if (!$unique_id) {
    wp_die('ID de demo no válido.');
}

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_propuestas';
$proposal = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE unique_link_id = %s", $unique_id));

if (!$proposal) {
    wp_die('Demo no encontrada.');
}

$n8n_chat_url = $proposal->n8n_chat_url;
$system_prompt = $proposal->system_prompt_text;

$company_name = 'tu empresa';
if (!empty($proposal->company_name)) {
    $company_name = $proposal->company_name;
} elseif (preg_match('/Asistente Virtual de\s+(.*?)(?:\.|,|$)/iu', $system_prompt, $matches)) {
    $company_name = trim($matches[1]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo - <?php echo esc_html($company_name); ?> | AutomatizaTech</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@n8n/chat/dist/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            width: 100%;
            height: 100%;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #06d6a0 0%, #1e40af 50%, #06d6a0 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 20px;
            min-height: 100vh;
        }
        
        /* Header */
        .demo-header {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        .logo-img {
            height: 90px;
            width: auto;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
        }
        
        .client-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-block;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .client-badge span {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        .client-badge strong {
            color: white;
            font-size: 1rem;
            font-weight: 600;
        }

        /* Chat Widget Container - Estilo como el sitio oficial */
        .chat-container {
            width: 100%;
            max-width: 400px;
            height: 550px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Header del Chat */
        .chat-header {
            background: linear-gradient(135deg, #06d6a0 0%, #1e40af 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-header h3 {
            font-size: 1.1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chat-header .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.8;
        }

        /* Mensajes */
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
        }

        .message {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .message-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #06d6a0, #1e40af);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .message-bubble {
            background: white;
            padding: 12px 16px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            max-width: 280px;
            line-height: 1.5;
            color: #333;
        }

        .message-bubble.user {
            background: linear-gradient(135deg, #06d6a0, #1e40af);
            color: white;
            margin-left: auto;
        }

        /* Input Area */
        .chat-input-area {
            padding: 15px 20px;
            background: white;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .chat-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .chat-input:focus {
            border-color: #1e40af;
        }

        .chat-input::placeholder {
            color: #aaa;
        }

        .send-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #06d6a0, #1e40af);
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .send-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(30, 64, 175, 0.4);
        }

        .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Loading dots */
        .typing-indicator {
            display: none;
            padding: 12px 16px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .typing-indicator.show {
            display: inline-block;
        }

        .typing-indicator span {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: linear-gradient(135deg, #06d6a0, #1e40af);
            border-radius: 50%;
            margin: 0 2px;
            animation: bounce 1.4s infinite ease-in-out both;
        }

        .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
        .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }

        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }

        /* Responsive */
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            .logo-img {
                height: 50px;
            }
            .chat-container {
                height: calc(100vh - 120px);
                max-width: 100%;
                border-radius: 12px;
            }
        }

        /* Placeholder si no hay URL */
        .placeholder {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 400px;
        }
        .placeholder h1 {
            color: #06d6a0;
            margin-bottom: 15px;
        }
        .placeholder p {
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>

    <header class="demo-header">
        <img src="https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech%20+%20slogan.png" alt="Automatiza Tech" class="logo-img">
        <div class="client-badge">
            <span>Demo para</span> <strong><?php echo esc_html($company_name); ?></strong>
        </div>
    </header>

<?php if ($n8n_chat_url): ?>
    <div class="chat-container">
        <div class="chat-header">
            <h3>🤖 Asistente <?php echo esc_html($company_name); ?></h3>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <div class="message">
                <div class="message-avatar">🤖</div>
                <div class="message-bubble">
                    ¡Hola! Soy Tech 🤖 tu asistente virtual de <?php echo esc_html($company_name); ?>. ¿En qué puedo ayudarte hoy?
                </div>
            </div>
            <div class="message">
                <div class="message-avatar">🤖</div>
                <div class="typing-indicator" id="typingIndicator">
                    <span></span><span></span><span></span>
                </div>
            </div>
        </div>
        
        <div class="chat-input-area">
            <input type="text" class="chat-input" id="chatInput" placeholder="Escribe tu mensaje..." autocomplete="off">
            <button class="send-btn" id="sendBtn" title="Enviar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 2L11 13M22 2L15 22L11 13L2 9L22 2Z"/>
                </svg>
            </button>
        </div>
    </div>

    <script>
        const webhookUrl = '<?php echo esc_url($n8n_chat_url); ?>';
        const sessionId = '<?php echo esc_js($unique_id); ?>';
        const systemPrompt = <?php echo json_encode($system_prompt); ?>;
        
        const chatMessages = document.getElementById('chatMessages');
        const chatInput = document.getElementById('chatInput');
        const sendBtn = document.getElementById('sendBtn');
        const typingIndicator = document.getElementById('typingIndicator');

        // Función para agregar mensaje
        function addMessage(text, isUser = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message';
            
            if (isUser) {
                messageDiv.innerHTML = `
                    <div class="message-bubble user">${escapeHtml(text)}</div>
                `;
            } else {
                messageDiv.innerHTML = `
                    <div class="message-avatar">🤖</div>
                    <div class="message-bubble">${escapeHtml(text)}</div>
                `;
            }
            
            // Insertar antes del typing indicator
            const typingMessage = typingIndicator.parentElement;
            chatMessages.insertBefore(messageDiv, typingMessage);
            
            // Scroll al final
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Escapar HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Enviar mensaje
        async function sendMessage() {
            const message = chatInput.value.trim();
            if (!message) return;

            // Mostrar mensaje del usuario
            addMessage(message, true);
            chatInput.value = '';
            
            // Mostrar indicador de typing
            typingIndicator.classList.add('show');
            sendBtn.disabled = true;
            chatMessages.scrollTop = chatMessages.scrollHeight;

            console.log('Enviando a:', webhookUrl);
            console.log('Datos:', { chatInput: message, sessionId: sessionId });

            try {
                const response = await fetch(webhookUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        chatInput: message,
                        sessionId: sessionId,
                        system_prompt: systemPrompt,
                        action: 'sendMessage'
                    })
                });

                console.log('Response status:', response.status);
                
                // Intentar leer como texto primero
                const textResponse = await response.text();
                console.log('Response text:', textResponse);
                
                // Ocultar typing
                typingIndicator.classList.remove('show');
                sendBtn.disabled = false;

                // Intentar parsear JSON
                let data;
                try {
                    data = JSON.parse(textResponse);
                } catch (e) {
                    // Si no es JSON, usar el texto directamente
                    if (textResponse && textResponse.trim()) {
                        addMessage(textResponse);
                        return;
                    }
                    throw new Error('Respuesta vacía del servidor');
                }

                // Mostrar respuesta del bot - manejar diferentes formatos de respuesta de n8n
                let botResponse = null;
                
                // Formato directo
                if (data.output) {
                    botResponse = data.output;
                } else if (data.text) {
                    botResponse = data.text;
                } else if (data.message) {
                    botResponse = data.message;
                } else if (data.response) {
                    botResponse = data.response;
                } 
                // n8n a veces devuelve un array
                else if (Array.isArray(data) && data.length > 0) {
                    const firstItem = data[0];
                    botResponse = firstItem.output || firstItem.text || firstItem.message || firstItem.response;
                    if (!botResponse && typeof firstItem === 'object') {
                        // Buscar cualquier campo que parezca una respuesta
                        for (const key of Object.keys(firstItem)) {
                            if (typeof firstItem[key] === 'string' && firstItem[key].length > 10) {
                                botResponse = firstItem[key];
                                break;
                            }
                        }
                    }
                }
                // Objeto vacío o sin campos conocidos - buscar cualquier string
                else if (typeof data === 'object' && data !== null) {
                    for (const key of Object.keys(data)) {
                        if (typeof data[key] === 'string' && data[key].length > 5) {
                            botResponse = data[key];
                            break;
                        }
                    }
                }
                else if (typeof data === 'string') {
                    botResponse = data;
                }

                if (botResponse) {
                    addMessage(botResponse);
                } else {
                    console.log('Formato de respuesta no reconocido:', data);
                    console.log('Keys disponibles:', Object.keys(data || {}));
                    // No mostrar mensaje de error si el workflow se ejecutó
                    if (Object.keys(data || {}).length === 0) {
                        addMessage('El asistente procesó tu mensaje pero no devolvió respuesta. Intenta de nuevo.');
                    } else {
                        addMessage('Respuesta recibida. (Ver consola F12 para detalles)');
                    }
                }

            } catch (error) {
                console.error('Error completo:', error);
                typingIndicator.classList.remove('show');
                sendBtn.disabled = false;
                addMessage('Error: ' + error.message + '. Revisa la consola del navegador (F12) para más detalles.');
            }
        }

        // Event listeners
        sendBtn.addEventListener('click', sendMessage);
        
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        // Focus en el input al cargar
        chatInput.focus();
    </script>
<?php else: ?>
    <div class="placeholder">
        <h1>Demo en Configuración</h1>
        <p>Estamos terminando de configurar tu asistente virtual personalizado.</p>
        <p>Por favor, vuelve a intentar en unos minutos o contacta a nuestro equipo si el problema persiste.</p>
    </div>
<?php endif; ?>

</body>
</html>
