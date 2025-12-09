<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tech 2.0: Asistente Multimodal + N8N</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { 
            margin: 0; 
            overflow: hidden; 
            background: radial-gradient(circle at center, #0b1a33 0%, #000000 120%);
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }
        canvas { display: block; position: absolute; top: 0; left: 0; z-index: 0; }
        
        .glass-panel {
            background: rgba(11, 26, 51, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(0, 210, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }

        .typing-dot { animation: typing 1.4s infinite ease-in-out both; }
        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }
        @keyframes typing {
            0%, 80%, 100% { transform: scale(0); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }

        /* Animación para imagen adjunta */
        .image-preview-enter { animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        /* Scrollbar oculta */
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
    <!-- Three.js -->
    <script src="https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js"></script>
</head>
<body class="text-cyan-400 font-sans selection:bg-cyan-900 selection:text-white">

    <!-- Botón de Salida -->
    <a href="/" class="absolute top-6 left-6 z-20 flex items-center gap-2 text-cyan-500 hover:text-cyan-300 transition-colors opacity-70 hover:opacity-100">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        <span class="text-sm font-bold tracking-widest uppercase">Volver</span>
    </a>

    <!-- UI Layer -->
    <div class="relative z-10 h-screen flex flex-col justify-between pointer-events-none p-4 md:p-6">
        
        <!-- Header -->
        <div class="text-center mt-8 opacity-90 transition-opacity duration-500" id="header-ui">
            <h1 class="text-xs md:text-sm tracking-[0.3em] uppercase font-bold text-cyan-300 drop-shadow-[0_0_10px_rgba(0,210,255,0.5)] flex items-center justify-center gap-2">
                <span>✦</span> Tech 2.0 <span>✦</span>
            </h1>
            <p class="text-[10px] text-cyan-600 tracking-widest mt-1 uppercase">Automatiza Tech &bull; N8N Integrated</p>
        </div>

        <!-- Chat Area -->
        <div class="w-full max-w-lg mx-auto mb-4 pointer-events-auto flex flex-col gap-3">
            
            <!-- Chat History -->
            <div id="chat-history" class="space-y-3 max-h-[30vh] overflow-y-auto p-4 rounded-2xl hidden scrollbar-hide mask-image-gradient">
                <!-- Messages injected here -->
            </div>

            <!-- Image Preview Area -->
            <div id="image-preview-container" class="hidden px-4">
                <div class="relative inline-block group image-preview-enter">
                    <img id="image-preview" class="h-16 w-16 object-cover rounded-lg border border-cyan-500/50 shadow-[0_0_15px_rgba(0,210,255,0.2)]" />
                    <button id="remove-image-btn" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-0.5 w-5 h-5 flex items-center justify-center text-xs hover:bg-red-600 transition shadow-md">×</button>
                </div>
            </div>

            <!-- Input Box -->
            <div class="glass-panel rounded-2xl p-2 flex items-center gap-2 transition-all duration-300 focus-within:border-cyan-400 focus-within:shadow-[0_0_25px_rgba(0,210,255,0.2)]">
                
                <!-- Upload Button -->
                <button id="upload-btn" class="p-2.5 rounded-xl text-cyan-400/70 hover:text-cyan-200 hover:bg-cyan-500/20 transition-all" title="Adjuntar imagen para análisis visual">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                </button>
                <input type="file" id="file-input" accept="image/*" class="hidden">

                <input type="text" id="user-input" 
                    class="bg-transparent border-none text-white placeholder-cyan-600/50 w-full px-2 py-2 focus:outline-none text-sm md:text-base"
                    placeholder="Escribe o muestra algo a Tech 2.0..." autocomplete="off">
                
                <!-- Send Button -->
                <button id="send-btn" class="p-2.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-400/20 text-cyan-300 transition-all hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
            </div>

            <!-- Status -->
            <div id="status-text" class="text-center text-[10px] text-cyan-600/80 tracking-[0.2em] uppercase h-4 font-semibold">
                Esperando Input
            </div>
        </div>
    </div>

    <!-- Main Logic -->
    <script type="module">
        // --- Config ---
        // ⚠️ REEMPLAZA ESTO CON TU API KEY REAL DE GEMINI ⚠️
        const apiKey = "TU_API_KEY_AQUI"; 
        
        // ⚠️ CONFIGURACIÓN N8N (Opcional) ⚠️
        // Si tienes un webhook de n8n para procesar mensajes, colócalo aquí.
        const n8nWebhookUrl = ""; // Ej: "https://tu-n8n.com/webhook/..."

        let isThinking = false;
        let isSpeaking = false;
        let audioContext = null;
        let currentImageBase64 = null;

        // Target Colors for Emotion (Interpolation)
        const colors = {
            neutral: new THREE.Color(0x00d2ff),   // Cian (Automatiza Tech)
            happy: new THREE.Color(0x00ff88),     // Verde Tech
            alert: new THREE.Color(0xff3300),     // Rojo Alerta
            mysterious: new THREE.Color(0xaa00ff) // Violeta IA
        };
        let targetColor = colors.neutral;
        let currentColor = colors.neutral.clone();

        // --- Three.js Setup ---
        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 100);
        
        // Ajuste de cámara para mejor encuadre del nuevo cuerpo
        camera.position.set(0, 1.0, 10);
        camera.lookAt(0, 0.5, 0);

        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.4;
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.SoftShadowMap;
        document.body.appendChild(renderer.domElement);

        // --- Materials ---
        const glassMaterial = new THREE.MeshPhysicalMaterial({
            color: 0x001a33, metalness: 0.3, roughness: 0.1,
            transmission: 0.9, thickness: 0.8, ior: 1.5,
            clearcoat: 1.0, side: THREE.DoubleSide
        });

        // Dynamic Light Material (Eyes/Core)
        const lightMaterial = new THREE.MeshStandardMaterial({
            color: 0x00d2ff, emissive: 0x00d2ff, emissiveIntensity: 1.0, roughness: 0.2
        });

        // Darker joints for contrast
        const jointMaterial = new THREE.MeshStandardMaterial({
            color: 0x051020, metalness: 0.8, roughness: 0.4
        });

        // Chat Icon Material
        const iconMaterial = new THREE.MeshStandardMaterial({
            color: 0x00ffff, emissive: 0x00ffff, emissiveIntensity: 1.5, roughness: 0.1
        });

        // --- Robot Geometry (Tech 2.0 Design) ---
        const robotGroup = new THREE.Group();
        scene.add(robotGroup);

        // 1. Torso
        const torsoGroup = new THREE.Group();
        robotGroup.add(torsoGroup);

        const chestGeo = new THREE.CylinderGeometry(0.9, 0.7, 1.2, 32);
        const chest = new THREE.Mesh(chestGeo, glassMaterial);
        chest.position.y = 0.6; chest.castShadow = true;
        torsoGroup.add(chest);

        const abdomenGeo = new THREE.SphereGeometry(0.7, 32, 32);
        abdomenGeo.scale(1, 0.8, 1);
        const abdomen = new THREE.Mesh(abdomenGeo, glassMaterial);
        abdomen.position.y = -0.1;
        torsoGroup.add(abdomen);

        const core = new THREE.Mesh(new THREE.SphereGeometry(0.35, 32, 32), lightMaterial);
        core.position.set(0, 0.6, 0.5);
        torsoGroup.add(core);

        // 2. Cabeza
        const headGroup = new THREE.Group();
        headGroup.position.set(0, 1.4, 0);
        robotGroup.add(headGroup);

        const headGeo = new THREE.SphereGeometry(1.1, 64, 64);
        headGeo.scale(1.1, 1, 1.1); 
        const head = new THREE.Mesh(headGeo, glassMaterial);
        head.castShadow = true;
        headGroup.add(head);

        // Auriculares Tech
        const earGeo = new THREE.CylinderGeometry(0.5, 0.5, 0.2, 32);
        earGeo.rotateZ(Math.PI / 2);
        
        const leftEar = new THREE.Mesh(earGeo, jointMaterial);
        leftEar.position.set(-1.1, 0, 0); headGroup.add(leftEar);
        const earLight = new THREE.Mesh(new THREE.TorusGeometry(0.3, 0.05, 16, 32), lightMaterial);
        earLight.rotation.y = Math.PI / 2; earLight.position.set(-1.21, 0, 0); headGroup.add(earLight);

        const rightEar = new THREE.Mesh(earGeo, jointMaterial);
        rightEar.position.set(1.1, 0, 0); headGroup.add(rightEar);
        const rightEarLight = earLight.clone();
        rightEarLight.position.set(1.21, 0, 0); headGroup.add(rightEarLight);

        // Ojos
        const eyeGeo = new THREE.CapsuleGeometry(0.25, 0.3, 4, 16);
        const leftEye = new THREE.Mesh(eyeGeo, lightMaterial);
        leftEye.position.set(-0.45, 0.1, 0.98); leftEye.rotation.x = -0.1; leftEye.rotation.z = 0.1; leftEye.scale.set(1, 1, 0.2);
        headGroup.add(leftEye);

        const rightEye = new THREE.Mesh(eyeGeo, lightMaterial);
        rightEye.position.set(0.45, 0.1, 0.98); rightEye.rotation.x = -0.1; rightEye.rotation.z = -0.1; rightEye.scale.set(1, 1, 0.2);
        headGroup.add(rightEye);

        // 3. Brazos
        function createRobotArm(isLeft) {
            const armGroup = new THREE.Group();
            const shoulder = new THREE.Mesh(new THREE.SphereGeometry(0.45, 32, 32), jointMaterial);
            armGroup.add(shoulder);
            const upperArm = new THREE.Mesh(new THREE.CylinderGeometry(0.35, 0.3, 1.0, 32), glassMaterial);
            upperArm.position.set(isLeft ? -0.2 : 0.2, -0.7, 0); upperArm.rotation.z = isLeft ? 0.2 : -0.2;
            shoulder.add(upperArm);
            const elbow = new THREE.Mesh(new THREE.SphereGeometry(0.3, 32, 32), jointMaterial);
            elbow.position.set(0, -0.6, 0); upperArm.add(elbow);
            const forearm = new THREE.Mesh(new THREE.CylinderGeometry(0.3, 0.25, 0.9, 32), glassMaterial);
            forearm.position.set(0, -0.6, 0.2); forearm.rotation.x = -0.5;
            elbow.add(forearm);
            const hand = new THREE.Mesh(new THREE.SphereGeometry(0.3, 32, 32), jointMaterial);
            hand.position.set(0, -0.5, 0); forearm.add(hand);
            armGroup.position.set(isLeft ? -1.1 : 1.1, 0.8, 0);
            return { group: armGroup, hand: hand };
        }

        const lArm = createRobotArm(true); torsoGroup.add(lArm.group);
        const rArm = createRobotArm(false); torsoGroup.add(rArm.group);

        // 4. Base
        const thrusterGeo = new THREE.CylinderGeometry(0.4, 0.1, 0.5, 32);
        const thruster = new THREE.Mesh(thrusterGeo, jointMaterial);
        thruster.position.y = -0.6; torsoGroup.add(thruster);
        const glowRing = new THREE.Mesh(new THREE.TorusGeometry(0.5, 0.05, 16, 32), lightMaterial);
        glowRing.rotation.x = Math.PI / 2; glowRing.position.y = -0.7; torsoGroup.add(glowRing);

        // Chat Icon
        const chatGroup = new THREE.Group();
        const bub = new THREE.Mesh(new THREE.BoxGeometry(0.7, 0.5, 0.1), iconMaterial);
        const tri = new THREE.Mesh(new THREE.ConeGeometry(0.1, 0.2, 3), iconMaterial);
        tri.rotation.z = -0.8; tri.position.set(0.2, -0.35, 0); chatGroup.add(bub, tri);
        const dGeo = new THREE.CircleGeometry(0.05, 8);
        const dMat = new THREE.MeshBasicMaterial({ color: 0xffffff });
        [-0.15, 0, 0.15].forEach(x => { const d = new THREE.Mesh(dGeo, dMat); d.position.set(x, 0, 0.06); chatGroup.add(d); });
        chatGroup.position.set(0, 0.8, 0); rArm.hand.add(chatGroup);

        // Lighting
        const ambient = new THREE.AmbientLight(0x000000, 1.0); scene.add(ambient); 
        const spot = new THREE.SpotLight(0xffffff, 20); spot.position.set(-5, 10, 8); spot.castShadow = true; scene.add(spot);
        const rim1 = new THREE.SpotLight(0x00d2ff, 50); rim1.position.set(5, 5, -5); scene.add(rim1);
        const rim2 = new THREE.PointLight(0xff00aa, 8); rim2.position.set(-5, -5, -5); scene.add(rim2);

        // --- Interaction Logic ---
        let mouse = { x: 0, y: 0 };
        document.addEventListener('mousemove', e => {
            mouse.x = (e.clientX - window.innerWidth/2) / 100;
            mouse.y = (e.clientY - window.innerHeight/2) / 100;
        });

        // --- Animation Loop ---
        const clock = new THREE.Clock();
        function animate() {
            requestAnimationFrame(animate);
            const t = clock.getElapsedTime();

            currentColor.lerp(targetColor, 0.05);
            lightMaterial.color.copy(currentColor);
            lightMaterial.emissive.copy(currentColor);
            rim1.color.lerp(targetColor, 0.02);

            robotGroup.position.y = Math.sin(t * 1.5) * 0.15;
            torsoGroup.scale.y = 1 + Math.sin(t * 2) * 0.01;

            headGroup.rotation.y = THREE.MathUtils.lerp(headGroup.rotation.y, mouse.x * 0.5, 0.1);
            headGroup.rotation.x = THREE.MathUtils.lerp(headGroup.rotation.x, mouse.y * 0.3, 0.1);
            torsoGroup.rotation.y = THREE.MathUtils.lerp(torsoGroup.rotation.y, mouse.x * 0.2, 0.05);

            if (isThinking) {
                chatGroup.rotation.y += 0.25; chatGroup.scale.setScalar(1 + Math.sin(t*10)*0.1);
                iconMaterial.emissive.setHex(0xff00ff);
            } else {
                chatGroup.rotation.y = Math.sin(t) * 0.3; chatGroup.scale.setScalar(1);
                iconMaterial.emissive.copy(currentColor); iconMaterial.color.copy(currentColor);
            }

            if (isSpeaking) {
                const wave = (Math.sin(t * 25) + Math.sin(t * 50) + Math.cos(t * 15)) * 0.3 + 0.5;
                const intensity = 0.5 + (wave * 4); 
                lightMaterial.emissiveIntensity = intensity;
                core.scale.setScalar(1 + wave * 0.3);
                leftEye.scale.y = 1 + wave * 0.2; rightEye.scale.y = 1 + wave * 0.2;
            } else {
                lightMaterial.emissiveIntensity = THREE.MathUtils.lerp(lightMaterial.emissiveIntensity, 0.8, 0.1);
                core.scale.setScalar(THREE.MathUtils.lerp(core.scale.x, 1, 0.1));
                leftEye.scale.y = THREE.MathUtils.lerp(leftEye.scale.y, 1, 0.1);
                rightEye.scale.y = THREE.MathUtils.lerp(rightEye.scale.y, 1, 0.1);
            }
            renderer.render(scene, camera);
        }
        animate();

        // --- UI & Gemini Logic ---
        const ui = {
            input: document.getElementById('user-input'),
            btn: document.getElementById('send-btn'),
            uploadBtn: document.getElementById('upload-btn'),
            fileInput: document.getElementById('file-input'),
            imgPreview: document.getElementById('image-preview'),
            imgContainer: document.getElementById('image-preview-container'),
            removeImg: document.getElementById('remove-image-btn'),
            history: document.getElementById('chat-history'),
            status: document.getElementById('status-text')
        };

        ui.uploadBtn.addEventListener('click', () => ui.fileInput.click());
        ui.fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                currentImageBase64 = e.target.result.split(',')[1];
                ui.imgPreview.src = e.target.result;
                ui.imgContainer.classList.remove('hidden');
                ui.status.innerText = "Imagen cargada en memoria visual";
            };
            reader.readAsDataURL(file);
        });
        ui.removeImg.addEventListener('click', () => {
            currentImageBase64 = null; ui.fileInput.value = ''; ui.imgContainer.classList.add('hidden'); ui.status.innerText = "Imagen descartada";
        });

        function addMessage(text, isUser, isImage = false) {
            ui.history.classList.remove('hidden');
            const div = document.createElement('div');
            let contentHtml = text;
            if (isImage) {
                contentHtml = `<div class="flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg> <i>[Imagen Analizada]</i></div>` + text;
            }
            div.className = isUser 
                ? 'ml-auto max-w-[85%] bg-cyan-500/20 text-white p-3 rounded-l-2xl rounded-tr-2xl text-xs md:text-sm border border-cyan-500/30 backdrop-blur-sm shadow-[0_4px_10px_rgba(0,0,0,0.2)]'
                : 'mr-auto max-w-[85%] bg-black/60 text-cyan-50 p-3 rounded-r-2xl rounded-tl-2xl text-xs md:text-sm border border-white/10 backdrop-blur-sm shadow-[0_4px_10px_rgba(0,0,0,0.3)]';
            div.innerHTML = contentHtml;
            ui.history.appendChild(div);
            ui.history.scrollTop = ui.history.scrollHeight;
        }

        // --- N8N INTEGRATION ---
        async function sendToN8N(text, imageBase64) {
            if (!n8nWebhookUrl) return; // Si no hay webhook, no hacer nada
            
            try {
                console.log("Enviando datos a N8N...");
                // Enviar en segundo plano sin bloquear la UI
                fetch(n8nWebhookUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        message: text,
                        image: imageBase64 || null,
                        timestamp: new Date().toISOString(),
                        source: "Tech 2.0 Web Assistant"
                    })
                }).catch(err => console.error("Error enviando a N8N:", err));
            } catch (e) {
                console.error("Error N8N:", e);
            }
        }

        // --- GEMINI CORE ---
        async function askGemini(prompt) {
            if (isThinking) return;
            if (!apiKey || apiKey === "TU_API_KEY_AQUI") {
                addMessage("⚠️ Error: Falta la API Key de Gemini.", false);
                return;
            }

            isThinking = true;
            ui.status.innerHTML = "Tech 2.0 procesando <span class='typing-dot'>.</span><span class='typing-dot'>.</span><span class='typing-dot'>.</span>";
            ui.btn.disabled = true;

            // Trigger N8N in background
            sendToN8N(prompt, currentImageBase64);

            try {
                const parts = [];
                if (prompt) parts.push({ text: prompt });
                if (currentImageBase64) {
                    parts.push({
                        inlineData: { mimeType: "image/jpeg", data: currentImageBase64 }
                    });
                }

                const systemPrompt = `
                Eres Tech 2.0, el asistente virtual avanzado de 'Automatiza Tech'.
                Tu misión es ayudar a los usuarios a entender cómo la automatización, los bots y el CRM pueden escalar sus negocios.
                
                IMPORTANTE: Al inicio de tu respuesta, DEBES incluir una etiqueta de emoción entre corchetes para controlar tus luces:
                [HAPPY] - Si la respuesta es positiva, alegre o celebratoria.
                [ALERT] - Si hay un error, peligro, o algo negativo.
                [MYSTERIOUS] - Si estás reflexionando, contando un secreto o siendo profundo.
                [NEUTRAL] - Para todo lo demás.

                Responde siempre en español conciso. Si ves una imagen, descríbela brevemente relacionándola con tecnología o automatización si es posible.
                `;

                const response = await fetch(
                    `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=${apiKey}`,
                    {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            contents: [{ parts: parts }],
                            systemInstruction: { parts: [{ text: systemPrompt }] }
                        })
                    }
                );

                const data = await response.json();
                
                if (data.candidates && data.candidates[0].content) {
                    let rawText = data.candidates[0].content.parts[0].text;
                    let cleanText = rawText;
                    
                    if (rawText.includes('[HAPPY]')) { targetColor = colors.happy; cleanText = rawText.replace('[HAPPY]', ''); }
                    else if (rawText.includes('[ALERT]')) { targetColor = colors.alert; cleanText = rawText.replace('[ALERT]', ''); }
                    else if (rawText.includes('[MYSTERIOUS]')) { targetColor = colors.mysterious; cleanText = rawText.replace('[MYSTERIOUS]', ''); }
                    else { targetColor = colors.neutral; cleanText = rawText.replace('[NEUTRAL]', ''); }

                    cleanText = cleanText.trim();
                    addMessage(cleanText, false);
                    ui.status.innerText = "Respuesta vocalizada";
                    
                    if (currentImageBase64) {
                        currentImageBase64 = null; ui.imgContainer.classList.add('hidden'); ui.fileInput.value = '';
                    }
                    isThinking = false; ui.btn.disabled = false;
                    await speakWithGemini(cleanText);

                } else {
                    throw new Error("No content");
                }

            } catch (error) {
                console.error(error);
                isThinking = false; ui.btn.disabled = false;
                ui.status.innerText = "Error de sistema";
                addMessage("Mis sensores no pudieron procesar esa solicitud.", false);
                targetColor = colors.alert;
            }
        }

        async function speakWithGemini(text) {
            try {
                isSpeaking = true;
                const response = await fetch(
                    `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-tts:generateContent?key=${apiKey}`,
                    {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            contents: [{ parts: [{ text: text }] }],
                            generationConfig: {
                                responseModalities: ["AUDIO"],
                                speechConfig: { voiceConfig: { prebuiltVoiceConfig: { voiceName: "Kore" } } }
                            }
                        })
                    }
                );
                const data = await response.json();
                if (data.candidates?.[0]?.content?.parts?.[0]?.inlineData) {
                    const audioBase64 = data.candidates[0].content.parts[0].inlineData.data;
                    const audioBytes = Uint8Array.from(atob(audioBase64), c => c.charCodeAt(0));
                    if (!audioContext) audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    playPCM(audioBytes.buffer);
                } else {
                    isSpeaking = false;
                }
            } catch (e) {
                console.error("TTS Fail", e);
                isSpeaking = false;
            }
        }

        function playPCM(arrayBuffer) {
            const sampleRate = 24000;
            const float32Data = new Float32Array(arrayBuffer.byteLength / 2);
            const dataView = new DataView(arrayBuffer);
            for (let i = 0; i < float32Data.length; i++) {
                const int16 = dataView.getInt16(i * 2, true);
                float32Data[i] = int16 < 0 ? int16 / 0x8000 : int16 / 0x7FFF;
            }
            const buffer = audioContext.createBuffer(1, float32Data.length, sampleRate);
            buffer.getChannelData(0).set(float32Data);
            const source = audioContext.createBufferSource();
            source.buffer = buffer;
            source.connect(audioContext.destination);
            source.onended = () => { isSpeaking = false; ui.status.innerText = "En línea"; };
            source.start();
        }

        function handleSend() {
            const text = ui.input.value.trim();
            if ((!text && !currentImageBase64) || isThinking) return;
            addMessage(text || "Analizando imagen...", true, !!currentImageBase64);
            ui.input.value = '';
            askGemini(text || "Describe qué ves en esta imagen y qué te parece.");
        }

        ui.btn.addEventListener('click', handleSend);
        ui.input.addEventListener('keypress', (e) => { if (e.key === 'Enter') handleSend(); });
        window.addEventListener('resize', () => {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        });

        // Bienvenida
        setTimeout(() => {
            addMessage("¡Hola! Soy Tech 2.0 🤖. Ahora puedo ver y escuchar. Muéstrame algo o pregúntame sobre automatización.", false);
        }, 1500);

    </script>
</body>
</html>