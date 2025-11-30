jQuery(document).ready(function($) {
    const $widget = $('#automatiza-ai-chat-widget');
    const $toggleBtn = $('#automatiza-ai-chat-toggle');
    const $closeBtn = $('.chat-close-btn');
    const $messages = $('#chat-messages');
    const $input = $('#chat-input');
    const $sendBtn = $('#chat-send-btn');
    
    // Robot Animation Logic
    const robotMessages = [
        "¡Hola! ¿Te ayudo? 👋",
        "¿Tienes dudas? 🤔",
        "¡Agenda una llamada! 📅",
        "¡Automatiza tu negocio! 🚀"
    ];
    let msgIndex = 0;
    const $robotText = $('#chatRobotMessage');
    const $robotBubble = $('.chat-robot-bubble');

    function rotateRobotMessage() {
        if ($widget.hasClass('closed')) { // Only animate if chat is closed
            $robotBubble.css('opacity', '0');
            setTimeout(function() {
                msgIndex = (msgIndex + 1) % robotMessages.length;
                $robotText.text(robotMessages[msgIndex]);
                $robotBubble.css('opacity', '1');
            }, 500);
        }
    }

    // Rotate message every 5 seconds
    setInterval(rotateRobotMessage, 5000);

    // Toggle Chat
    $toggleBtn.on('click', function() {
        $widget.toggleClass('closed');
        if (!$widget.hasClass('closed')) {
            $input.focus();
            // Hide robot bubble when chat is open
            $('.chat-robot-peek').fadeOut();
            
            if ($messages.children().length === 0) {
                addMessage(AutomatizaAIChat.greeting, 'bot');
            }
        } else {
            // Show robot bubble when chat is closed
            $('.chat-robot-peek').fadeIn();
        }
    });

    $closeBtn.on('click', function() {
        $widget.addClass('closed');
        $('.chat-robot-peek').fadeIn();
    });

    // Send Message
    function sendMessage() {
        const message = $input.val().trim();
        if (message) {
            addMessage(message, 'user');
            $input.val('');
            
            // Show loading
            const $loadingContainer = $('<div class="message-row bot loading-row"></div>');
            const $avatar = $('<img class="chat-message-avatar" src="' + AutomatizaAIChat.logoUrl + '" alt="Bot">');
            const $loadingBubble = $('<div class="message bot loading">Escribiendo...</div>');
            $loadingContainer.append($avatar).append($loadingBubble);
            
            $messages.append($loadingContainer);
            scrollToBottom();

            // Call AI Backend
            sendToBackend(message, $loadingContainer);
        }
    }

    $sendBtn.on('click', sendMessage);

    $input.on('keypress', function(e) {
        if (e.which === 13) {
            sendMessage();
        }
    });

    function formatMessage(text) {
        // 1. Escape HTML (security)
        let safeText = text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");

        // 2. Bold: **text** -> <strong>text</strong>
        safeText = safeText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // 3. Italic: *text* -> <em>text</em>
        safeText = safeText.replace(/\*(.*?)\*/g, '<em>$1</em>');

        // 4. Lists: - text -> • text
        safeText = safeText.replace(/^- /gm, '• ');

        return safeText;
    }

    function addMessage(text, sender) {
        if (sender === 'bot') {
            const $container = $('<div class="message-row bot"></div>');
            const $avatar = $('<img class="chat-message-avatar" src="' + AutomatizaAIChat.logoUrl + '" alt="Bot">');
            // Use .html() with formatted text
            const $bubble = $('<div class="message bot"></div>').html(formatMessage(text));
            $container.append($avatar).append($bubble);
            $messages.append($container);
        } else {
            const $msg = $('<div class="message user"></div>').text(text);
            $messages.append($msg);
        }
        scrollToBottom();
    }

    function scrollToBottom() {
        $messages.scrollTop($messages[0].scrollHeight);
    }

    // Session Management
    const SESSION_TIMEOUT = 60 * 60 * 1000; // 1 hour
    const STORAGE_KEY_SESSION = 'automatiza_chat_session_id';
    const STORAGE_KEY_TIMESTAMP = 'automatiza_chat_last_active';

    function getSessionId() {
        let sid = localStorage.getItem(STORAGE_KEY_SESSION);
        let lastActive = localStorage.getItem(STORAGE_KEY_TIMESTAMP);
        const now = new Date().getTime();

        if (!sid || !lastActive || (now - parseInt(lastActive)) > SESSION_TIMEOUT) {
            // Expired or new session
            sid = 'session-' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem(STORAGE_KEY_SESSION, sid);
            
            // If expired (we had a previous session), clear UI to start fresh
            if (lastActive && $messages.children().length > 0) {
                $messages.empty();
                addMessage(AutomatizaAIChat.greeting, 'bot');
            }
        }
        
        localStorage.setItem(STORAGE_KEY_TIMESTAMP, now);
        return sid;
    }

    let sessionId = getSessionId();

    function sendToBackend(userMessage, $loadingElement) {
        // Check for timeout before sending (in case tab was open for > 1 hour)
        let lastActive = localStorage.getItem(STORAGE_KEY_TIMESTAMP);
        const now = new Date().getTime();

        if (lastActive && (now - parseInt(lastActive)) > SESSION_TIMEOUT) {
            // Session expired
            sessionId = 'session-' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem(STORAGE_KEY_SESSION, sessionId);
            
            // Clear old history from UI, keeping only the new message and loading indicator
            const $userMsgRow = $loadingElement.prev();
            $messages.children().not($userMsgRow).not($loadingElement).remove();
        }

        // Update session timestamp on activity
        localStorage.setItem(STORAGE_KEY_TIMESTAMP, now);

        // If a webhook URL is configured, use it. Otherwise, simulate response.
        if (AutomatizaAIChat.webhookUrl) {
            // Prepare payload for n8n Chat Trigger
            const payload = {
                action: 'sendMessage',
                chatInput: userMessage,
                sessionId: sessionId
            };

            $.ajax({
                url: AutomatizaAIChat.webhookUrl,
                method: 'POST',
                contentType: 'application/json',
                dataType: 'text', // Force text to avoid parsererror
                data: JSON.stringify(payload),
                success: function(responseRaw) {
                    $loadingElement.remove();
                    
                    let response = responseRaw;
                    // Try to parse JSON if possible
                    try {
                        response = JSON.parse(responseRaw);
                    } catch (e) {
                        // It's just text, keep it as is
                    }

                    // n8n Chat Trigger usually returns { output: "text" } or similar
                    // If it returns an array of messages, handle that too
                    let botResponse = "No pude entender la respuesta.";
                    
                    if (typeof response === 'string') {
                        botResponse = response;
                    } else if (response.output) {
                        botResponse = response.output;
                    } else if (response.text) {
                        botResponse = response.text;
                    } else if (response.message) {
                        botResponse = response.message;
                    } else if (Array.isArray(response)) {
                        // Sometimes it returns an array of messages
                        botResponse = response.map(m => m.text || m.output).join('\n');
                    }

                    // Check for form trigger
                    if (botResponse.includes('<<SHOW_DEMO_FORM>>')) {
                        const parts = botResponse.split('<<SHOW_DEMO_FORM>>');
                        const textPart = parts[0].trim();
                        if (textPart) addMessage(textPart, 'bot');
                        
                        // Add delay before showing form
                        setTimeout(function() {
                            showDemoForm();
                        }, 3000);
                    } else {
                        addMessage(botResponse, 'bot');
                    }
                },
                error: function(xhr, status, error) {
                    $loadingElement.remove();
                    console.error("Chat Error:", error, xhr.responseText);
                    addMessage("Lo siento, hubo un error al conectar con el servidor. (" + status + ")", 'bot');
                }
            });
        } else {
            // Simulation for demo purposes
            setTimeout(function() {
                $loadingElement.remove();
                let response = "Gracias por tu mensaje. Actualmente estoy en modo demostración. Para conectarme a la IA, configura la URL del Webhook en el plugin.";
                
                if (userMessage.toLowerCase().includes('agendar') || userMessage.toLowerCase().includes('cita')) {
                    response = "Claro, puedo ayudarte a agendar una llamada. Por favor, indícame tu disponibilidad o visita nuestra página de contacto.";
                }
                
                addMessage(response, 'bot');
            }, 1000);
        }
    }

    function showDemoForm() {
        const $formContainer = $('<div class="message-row bot"></div>');
        const $avatar = $('<img class="chat-message-avatar" src="' + AutomatizaAIChat.logoUrl + '" alt="Bot">');
        
        const $form = $(`
            <div class="chat-form">
                <h4>Agendar Demo</h4>
                <div class="error-msg"></div>
                <input type="text" name="name" placeholder="Tu Nombre" required>
                <input type="email" name="email" placeholder="Tu Correo" required>
                <input type="tel" name="phone" placeholder="Tu Teléfono (+56...)" required>
                
                <label style="font-size: 12px; margin-top: 10px; display: block;">Fecha deseada:</label>
                <input type="date" name="date" required min="${new Date().toISOString().split('T')[0]}">
                
                <label style="font-size: 12px; margin-top: 5px; display: block;">Hora:</label>
                <select name="time" required disabled>
                    <option value="">Selecciona una fecha primero</option>
                </select>

                <button type="button" class="submit-demo-btn">Agendar Reunión</button>
            </div>
        `);

        // Logic for Date/Time
        const $dateInput = $form.find('input[name="date"]');
        const $timeSelect = $form.find('select[name="time"]');

        $dateInput.on('change', function() {
            const dateVal = $(this).val();
            if (!dateVal) return;

            const dateObj = new Date(dateVal + 'T00:00:00'); // Force local time
            const dayIndex = dateObj.getDay(); // 0 = Sunday, 1 = Monday...
            const daysMap = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            const dayName = daysMap[dayIndex];
            
            // Check Holidays
            const holidays = AutomatizaAIChat.schedule.holidays ? AutomatizaAIChat.schedule.holidays.split('\n').map(d => d.trim()) : [];
            if (holidays.includes(dateVal)) {
                $timeSelect.empty().prop('disabled', true);
                $timeSelect.append('<option value="">Día feriado / No disponible</option>');
                return;
            }

            // Get Schedule for specific day
            const daySchedule = AutomatizaAIChat.schedule[dayName];

            if (!daySchedule || !daySchedule.enabled) {
                $timeSelect.empty().prop('disabled', true);
                $timeSelect.append('<option value="">No hay atención este día</option>');
                return;
            }

            const startStr = daySchedule.start;
            const endStr = daySchedule.end;

            if (!startStr || !endStr) {
                $timeSelect.empty().prop('disabled', true).append('<option value="">No hay horarios disponibles</option>');
                return;
            }

            // Show loading state
            $timeSelect.empty().prop('disabled', true).append('<option value="">Verificando disponibilidad...</option>');

            // Async Check with WordPress API
            const payload = {
                date: dateVal
            };

            $.ajax({
                url: '/wp-json/automatiza-tech/v1/check-availability',
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify(payload),
                success: function(response) {
                    // Expected response: { isFullDay: boolean, busySlots: ["10:00", "14:00"] }
                    
                    if (response.isFullDay) {
                         $timeSelect.empty().prop('disabled', true).append('<option value="">Día completo / Sin cupos</option>');
                         return;
                    }

                    const busySlots = response.busySlots || [];
                    
                    $timeSelect.empty().prop('disabled', false);
                    $timeSelect.append('<option value="">Selecciona una hora</option>');

                    const startHour = parseInt(startStr.split(':')[0]);
                    const endHour = parseInt(endStr.split(':')[0]);
                    
                    let availableCount = 0;

                    for (let h = startHour; h < endHour; h++) {
                        const timeStr = h.toString().padStart(2, '0') + ':00';
                        
                        // Check if this slot is in busySlots
                        if (!busySlots.includes(timeStr)) {
                            $timeSelect.append(`<option value="${timeStr}">${timeStr}</option>`);
                            availableCount++;
                        }
                    }

                    if (availableCount === 0) {
                        $timeSelect.empty().prop('disabled', true).append('<option value="">Sin horarios disponibles hoy</option>');
                    }
                },
                error: function() {
                    console.error("Error checking availability");
                    $timeSelect.empty().prop('disabled', true).append('<option value="">Error al verificar disponibilidad</option>');
                }
            });
        });

        $formContainer.append($avatar).append($form);
        $messages.append($formContainer);
        scrollToBottom();

        // Handle Submit
        $form.find('.submit-demo-btn').on('click', function() {
            const $btn = $(this);
            const name = $form.find('input[name="name"]').val().trim();
            const email = $form.find('input[name="email"]').val().trim();
            const phone = $form.find('input[name="phone"]').val().trim();
            const date = $form.find('input[name="date"]').val();
            const time = $form.find('select[name="time"]').val();
            const $error = $form.find('.error-msg');

            // Basic Validation
            if (!name || !email || !phone || !date || !time) {
                $error.text('Por favor completa todos los campos.').slideDown();
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                $error.text('Por favor ingresa un correo válido.').slideDown();
                return;
            }

            // Disable button
            $btn.prop('disabled', true).text('Verificando disponibilidad...');
            $error.slideUp();

            // Send to n8n
            const payload = {
                action: 'saveLead', // Different action for n8n to handle
                sessionId: sessionId,
                leadData: {
                    name: name,
                    email: email,
                    phone: phone,
                    scheduled_date: date,
                    scheduled_time: time
                }
            };

            $.ajax({
                url: AutomatizaAIChat.webhookUrl,
                method: 'POST',
                contentType: 'application/json',
                dataType: 'text', // Force text to avoid parsererror
                data: JSON.stringify(payload),
                success: function(responseRaw) {
                    $form.remove(); // Remove form
                    
                    // Check if response contains "Ocupado" or similar error from n8n
                    let responseText = responseRaw;
                    try {
                        const json = JSON.parse(responseRaw);
                        responseText = json.text || json.output || json.message || JSON.stringify(json);
                    } catch(e) {}

                    addMessage(responseText, 'bot');
                },
                error: function() {
                    $btn.prop('disabled', false).text('Agendar Reunión');
                    $error.text('Error al conectar. Intenta nuevamente.').slideDown();
                }
            });
        });
    }
});
