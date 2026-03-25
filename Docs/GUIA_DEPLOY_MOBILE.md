# Guía de Despliegue Mobile — OmniCliente como App Android e iOS

> **Versión:** 1.0  
> **Fecha:** 24 de marzo de 2026  
> **Aplica a:** Portal OmniCliente (React 19 + Vite + Tailwind)  
> **URL actual:** https://automatizatech.cl/omnicliente/

---

## Índice

1. [Opciones Disponibles](#1-opciones-disponibles)
2. [Opción A: PWA (Progressive Web App)](#2-opción-a-pwa--recomendada-para-empezar)
3. [Opción B: Capacitor (App Nativa Híbrida)](#3-opción-b-capacitor--recomendada-para-stores)
4. [Opción C: TWA (Trusted Web Activity — Solo Android)](#4-opción-c-twa--solo-android)
5. [Comparativa de Opciones](#5-comparativa-de-opciones)
6. [Guía Paso a Paso — Capacitor](#6-guía-paso-a-paso--capacitor)
7. [Publicación en Google Play Store](#7-publicación-en-google-play-store)
8. [Publicación en Apple App Store](#8-publicación-en-apple-app-store)
9. [Push Notifications](#9-push-notifications)
10. [Consideraciones Especiales](#10-consideraciones-especiales)

---

## 1. Opciones Disponibles

| Opción | Complejidad | Costo | Tiempo | Resultado |
|---|---|---|---|---|
| **A. PWA** | ⭐ Baja | Gratis | 1-2 horas | Instalable desde navegador |
| **B. Capacitor** | ⭐⭐⭐ Media | ~$125/año (Apple) + $25 (Google) | 2-5 días | App en Play Store + App Store |
| **C. TWA** | ⭐⭐ Media-Baja | $25 (Google) | 1 día | Solo Android, Play Store |

**Recomendación:** Empezar con **Opción A (PWA)** para validar, luego migrar a **Opción B (Capacitor)** para publicar en stores.

---

## 2. Opción A: PWA — Recomendada para Empezar

### ¿Qué es?
Una PWA permite que la web se instale como app en el dispositivo del usuario, sin necesidad de tiendas de aplicaciones.

### Estado Actual del Proyecto

El portal OmniCliente **ya tiene configuración PWA parcial**:

✅ `manifest.json` con nombre, colores, icono  
✅ Meta tags de apple-mobile-web-app en index.html  
✅ `display: standalone` (se ve como app nativa)  
✅ Viewport configurado con `viewport-fit=cover`  
⚠️ Falta ícono de 512x512  
⚠️ Falta Service Worker para offline/caching  
⚠️ Falta ícono de Apple Touch

### 2.1 Completar PWA — Requisitos Mínimos

#### A. Crear íconos en múltiples tamaños

Necesitas el logo en estos tamaños (PNG):
- 72x72, 96x96, 128x128, 144x144, 152x152, 192x192, 384x384, 512x512

**Herramientas:**
- https://realfavicongenerator.net/
- https://maskable.app/editor (para íconos maskable)

Guardar en `omnicliente/icons/` (crear el directorio).

#### B. Actualizar manifest.json

```json
{
  "name": "OmniCliente - AutomatizaTech",
  "short_name": "OmniCliente",
  "description": "Portal omnicanal - WhatsApp, Instagram, Telegram, Messenger",
  "start_url": "./",
  "scope": "./",
  "display": "standalone",
  "background_color": "#0f172a",
  "theme_color": "#1e40af",
  "orientation": "any",
  "categories": ["business", "productivity"],
  "icons": [
    { "src": "./icons/icon-72x72.png",   "sizes": "72x72",   "type": "image/png" },
    { "src": "./icons/icon-96x96.png",   "sizes": "96x96",   "type": "image/png" },
    { "src": "./icons/icon-128x128.png", "sizes": "128x128", "type": "image/png" },
    { "src": "./icons/icon-144x144.png", "sizes": "144x144", "type": "image/png" },
    { "src": "./icons/icon-152x152.png", "sizes": "152x152", "type": "image/png" },
    { "src": "./icons/icon-192x192.png", "sizes": "192x192", "type": "image/png", "purpose": "any maskable" },
    { "src": "./icons/icon-384x384.png", "sizes": "384x384", "type": "image/png" },
    { "src": "./icons/icon-512x512.png", "sizes": "512x512", "type": "image/png", "purpose": "any maskable" }
  ],
  "screenshots": [
    {
      "src": "./screenshots/inbox.png",
      "sizes": "1080x1920",
      "type": "image/png",
      "form_factor": "narrow",
      "label": "Bandeja de entrada unificada"
    }
  ]
}
```

#### C. Crear Service Worker

Crear archivo `omnicliente/sw.js`:

```javascript
const CACHE_NAME = 'omnicliente-v1';
const STATIC_ASSETS = [
  './',
  './index.html',
  './manifest.json',
  './logo-automatiza-tech.png'
];

// Install: cache static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Fetch: network-first with cache fallback
self.addEventListener('fetch', (event) => {
  // Skip API calls and non-GET requests
  if (event.request.method !== 'GET' || event.request.url.includes('api-omnichannel')) {
    return;
  }
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        const clone = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
        return response;
      })
      .catch(() => caches.match(event.request))
  );
});
```

#### D. Registrar Service Worker en index.html

Agregar antes del cierre de `</body>`:

```html
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('./sw.js');
    });
  }
</script>
```

#### E. Agregar Apple Touch Icon en `<head>`

```html
<link rel="apple-touch-icon" href="./icons/icon-192x192.png" />
```

### 2.2 Probar PWA

1. Abrir Chrome en Android → visitar https://automatizatech.cl/omnicliente/
2. Menú ⋮ → "Instalar app" o "Añadir a pantalla de inicio"
3. La app se abre en modo standalone (sin barra de navegador)

En iPhone/Safari:
1. Visitar la URL → Compartir → "Añadir a pantalla de inicio"

### 2.3 Verificar con Lighthouse

```
Chrome DevTools → Lighthouse → Progressive Web App
```

Debe pasar todos los checks:
- ✅ HTTPS
- ✅ Service Worker registrado
- ✅ Manifest válido con íconos
- ✅ start_url funcional
- ✅ Respuesta 200 offline

---

## 3. Opción B: Capacitor — Recomendada para Stores

### ¿Qué es?
[Capacitor](https://capacitorjs.com/) (de Ionic) envuelve tu app web en un contenedor nativo, dando acceso a APIs nativas (push notifications, cámara, archivos) y permitiendo publicar en Play Store y App Store.

### 3.1 Ventajas

- **Reutiliza el 100% del código React existente**
- Acceso a APIs nativas (push notifications, biometrics, camera)
- Publicación en Play Store y App Store
- Actualizaciones instantáneas (el contenido web se carga remoto o local)
- Sin necesidad de aprender Swift/Kotlin

### 3.2 Requisitos Previos

#### Para Android:
- Node.js 18+
- Android Studio (latest)
- Android SDK 33+ (API Level 33)
- JDK 17+

#### Para iOS:
- Mac con macOS 13+ (Ventura o superior)
- Xcode 15+
- CocoaPods
- Cuenta Apple Developer ($99/año)

---

## 4. Opción C: TWA — Solo Android

### ¿Qué es?
Trusted Web Activity envuelve tu PWA en una app Android nativa usando Chrome Custom Tabs. No necesita Capacitor.

### Ventajas
- Muy simple de implementar
- El contenido siempre está actualizado (carga desde la web)
- Tamaño de APK mínimo (~2MB)

### Desventajas
- Solo Android
- Sin acceso a APIs nativas
- Requiere Digital Asset Links (verificación de dominio)
- No disponible para iOS

### Herramienta
- [Bubblewrap](https://github.com/nicolueg/nicolueg-nicolueg.github.io): Genera el proyecto Android automáticamente desde tu manifest.json

```bash
npx @nicolueg/nicolueg-nicolueg.github.io init --manifest=https://automatizatech.cl/omnicliente/manifest.json
```

---

## 5. Comparativa de Opciones

| Feature | PWA | Capacitor | TWA |
|---|---|---|---|
| Play Store | ❌(*)  | ✅ | ✅ |
| App Store | ❌ | ✅ | ❌ |
| Instalable sin Store | ✅ | ❌ | ❌ |
| Push Notifications | ✅(web) | ✅(nativo) | ✅(web) |
| Offline | ✅(SW) | ✅ | ❌ |
| Acceso Cámara | ✅(web) | ✅(nativo) | ✅(web) |
| Biometrics | ❌ | ✅ | ❌ |
| Deep Links | ✅ | ✅ | ✅ |
| Tamaño APK | N/A | ~5-15MB | ~2MB |
| Actualización | Instantánea | Instantánea(web) | Instantánea |
| Código nativo | No | Opcional | No |
| Costo publicación | $0 | $125+$25 | $25 |

(*) Google Play permite listar PWAs via TWA.

---

## 6. Guía Paso a Paso — Capacitor

### 6.1 Instalar Capacitor

```bash
cd c:\wamp64\www\automatiza-tech\client-portal-omnichannel

# Instalar Capacitor core y CLI
npm install @capacitor/core @capacitor/cli

# Inicializar Capacitor
npx cap init "OmniCliente" "cl.automatizatech.omnicliente" --web-dir dist
```

Esto crea `capacitor.config.ts`:

```typescript
import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'cl.automatizatech.omnicliente',
  appName: 'OmniCliente',
  webDir: 'dist',
  server: {
    // Para dev: cargar desde servidor remoto
    // url: 'https://automatizatech.cl/omnicliente/',
    // cleartext: true,
    
    // Para producción: usar archivos locales (del build)
    androidScheme: 'https'
  },
  plugins: {
    SplashScreen: {
      launchShowDuration: 2000,
      backgroundColor: '#0f172a',
      showSpinner: false
    },
    StatusBar: {
      style: 'DARK',
      backgroundColor: '#1e40af'
    }
  }
};

export default config;
```

### 6.2 Agregar Plataformas

```bash
# Build React primero
npm run build

# Android
npm install @capacitor/android
npx cap add android

# iOS (solo desde Mac)
npm install @capacitor/ios
npx cap add ios
```

Esto crea las carpetas `android/` y `ios/` con los proyectos nativos.

### 6.3 Plugins Útiles (Opcionales)

```bash
# Push Notifications
npm install @capacitor/push-notifications

# Status Bar control
npm install @capacitor/status-bar

# Splash Screen
npm install @capacitor/splash-screen

# Haptics (vibración)
npm install @capacitor/haptics

# Teclado (gestión de teclado virtual)
npm install @capacitor/keyboard

# Browser (abrir links externos)
npm install @capacitor/browser

# App (deep links, estado de la app)
npm install @capacitor/app

# Sincronizar plugins con proyectos nativos
npx cap sync
```

### 6.4 Configurar Iconos y Splash Screen

```bash
# Instalar herramienta de assets
npm install @capacitor/assets --save-dev

# Preparar archivos fuente:
# resources/icon.png (1024x1024, PNG)
# resources/splash.png (2732x2732, PNG)
# resources/icon-foreground.png (1024x1024, para Android adaptive icons)

# Generar todos los assets
npx capacitor-assets generate
```

### 6.5 Workflow de Desarrollo

```bash
# 1. Hacer cambios en React
# 2. Build
npm run build

# 3. Sincronizar con proyectos nativos
npx cap sync

# 4. Abrir en IDE nativo
npx cap open android    # Abre Android Studio
npx cap open ios        # Abre Xcode (solo Mac)

# 5. Ejecutar en emulador o dispositivo desde el IDE

# --- O para desarrollo rápido ---
# Live reload (el dispositivo/emulador carga desde tu PC)
npx cap run android --livereload --external
npx cap run ios --livereload --external
```

### 6.6 Ajustes Específicos para OmniCliente

#### A. Manejo de URLs (api.js)

El cliente API debe detectar si está corriendo en Capacitor:

```javascript
// En api.js
const isCapacitor = window.Capacitor !== undefined;

const API_BASE = isCapacitor
  ? 'https://automatizatech.cl/api-omnichannel.php'  // Siempre apunta al servidor
  : (window.location.hostname === 'localhost'
      ? '/api-omnichannel.php'
      : `${window.location.origin}/api-omnichannel.php`);
```

#### B. StatusBar y SafeArea (para notch/island)

En `App.jsx` o `main.jsx`:

```javascript
import { StatusBar, Style } from '@capacitor/status-bar';
import { Capacitor } from '@capacitor/core';

if (Capacitor.isNativePlatform()) {
  StatusBar.setBackgroundColor({ color: '#1e40af' });
  StatusBar.setStyle({ style: Style.Dark });
}
```

En CSS (ya tienes `viewport-fit: cover`):
```css
body {
  padding-top: env(safe-area-inset-top);
  padding-bottom: env(safe-area-inset-bottom);
}
```

#### C. Deep Links

Configurar en `capacitor.config.ts`:
```typescript
plugins: {
  App: {
    url: 'https://automatizatech.cl/omnicliente'
  }
}
```

#### D. Navegación (back button Android)

```javascript
import { App } from '@capacitor/app';

App.addListener('backButton', ({ canGoBack }) => {
  if (canGoBack) {
    window.history.back();
  } else {
    App.exitApp();
  }
});
```

### 6.7 Build de Producción

#### Android (APK / AAB)

```bash
# 1. Build React
npm run build

# 2. Sync con Android
npx cap sync android

# 3. Abrir Android Studio
npx cap open android

# 4. En Android Studio:
#    Build → Generate Signed Bundle/APK
#    a) Crear keystore (primera vez):
#       - Key store path: omnicliente.keystore
#       - Password: (guardar seguro)
#       - Key alias: omnicliente
#       - Validity: 25 years
#    b) Seleccionar "Android App Bundle" (para Play Store)
#       o "APK" (para distribución directa)
#    c) Seleccionar "release"
#    d) Build

# Output:
# android/app/build/outputs/bundle/release/app-release.aab (Play Store)
# android/app/build/outputs/apk/release/app-release.apk (directo)
```

#### iOS (IPA)

```bash
# 1. Build React
npm run build

# 2. Sync con iOS
npx cap sync ios

# 3. Abrir Xcode
npx cap open ios

# 4. En Xcode:
#    a) Seleccionar team de desarrollo (Apple Developer)
#    b) Ajustar Bundle Identifier: cl.automatizatech.omnicliente
#    c) Product → Archive
#    d) Distribute App → App Store Connect
```

---

## 7. Publicación en Google Play Store

### 7.1 Requisitos

- Cuenta Google Play Developer ($25 único)
- App firmada con keystore
- Íconos y screenshots
- Política de privacidad (URL pública)
- Clasificación de contenido

### 7.2 Pasos

1. **Crear cuenta:** https://play.google.com/console/signup
2. **Crear aplicación:**
   - Nombre: "OmniCliente - AutomatizaTech"
   - Idioma: Español (Chile)
   - Categoría: Negocios → Comunicación
3. **Ficha de Play Store:**
   - Título: OmniCliente - Portal Omnicanal
   - Descripción corta (80 chars): "Gestiona WhatsApp, Instagram, Telegram y más desde un solo lugar"
   - Descripción larga: Detallar funcionalidades
   - Capturas de pantalla: Mínimo 2 (teléfono), ideal 4-8
   - Ícono: 512x512 PNG
   - Feature graphic: 1024x500 PNG
4. **Subir AAB:** Producción → Crear nueva versión → Subir app-release.aab
5. **Clasificación de contenido:** Completar cuestionario IARC
6. **Política de privacidad:** URL pública (crear en automatizatech.cl/privacidad)
7. **Revisar y publicar**

### 7.3 Tiempos de Revisión
- Primera app: 1-7 días
- Actualizaciones: 1-3 días
- Rechazo más común: Falta de política de privacidad

---

## 8. Publicación en Apple App Store

### 8.1 Requisitos

- Mac con Xcode 15+
- Cuenta Apple Developer ($99/año): https://developer.apple.com/programs/enroll/
- Certificados de distribución
- App compilada (.ipa)
- Política de privacidad + detalle de datos recopilados

### 8.2 Pasos

1. **Certificados:**
   - Xcode → Preferences → Accounts → Manage Certificates
   - Crear "Apple Distribution" certificate
2. **App Store Connect:** https://appstoreconnect.apple.com
   - New App → iOS
   - Bundle ID: cl.automatizatech.omnicliente
   - SKU: omnicliente-001
   - Name: OmniCliente
3. **Información de la app:**
   - Categoría: Business
   - Subcategoría: Productivity
   - Clasificación de edad: 4+
   - Precio: Gratis (o lo que corresponda)
   - Política de privacidad: URL
   - Privacy Nutrition Labels (qué datos recopila la app)
4. **Screenshots:**
   - iPhone 6.7" (1290×2796) — obligatorio
   - iPhone 6.5" (1284×2778) — recomendado
   - iPad 12.9" — si aplica
5. **Subir build:**
   - Xcode → Product → Archive
   - Distribute → App Store Connect
   - Esperar que aparezca en App Store Connect (5-30 min)
6. **Seleccionar build y enviar a revisión**

### 8.3 Tiempos de Revisión
- Primera app: 1-7 días
- Actualizaciones: 24-48 horas
- Rechazos comunes:
  - La app es un "wrapper" de web sin funcionalidad nativa → agregar push notifications
  - Falta detalle de datos recopilados
  - Links rotos o funcionalidad offline insuficiente

### 8.4 Tips para Aprobación de Apple

Apple es más estricto que Google. Para evitar rechazos:

1. **Agregar al menos un plugin nativo:** Push notifications es el más fácil y útil
2. **Splash screen personalizado:** No usar el default de Capacitor
3. **Manejo de estado offline:** Mostrar mensaje amigable si no hay conexión
4. **No mostrar banners web:** Eliminar cualquier "Instalar como PWA" cuando corre en Capacitor
5. **Test Thoroughly:** El review de Apple prueba toda la funcionalidad. Las credenciales de prueba deben funcionar.

---

## 9. Push Notifications

### 9.1 Setup con Capacitor

```bash
npm install @capacitor/push-notifications
npx cap sync
```

### 9.2 Código en React

```javascript
import { PushNotifications } from '@capacitor/push-notifications';
import { Capacitor } from '@capacitor/core';

async function initPushNotifications() {
  if (!Capacitor.isNativePlatform()) return;

  // Pedir permiso
  const permission = await PushNotifications.requestPermissions();
  if (permission.receive !== 'granted') return;

  // Registrar
  await PushNotifications.register();

  // Obtener token
  PushNotifications.addListener('registration', (token) => {
    console.log('Push token:', token.value);
    // Enviar token al backend para asociarlo al agente/usuario
    // POST /api-omnichannel.php?route=agent/push-token
    //   { token: token.value, platform: Capacitor.getPlatform() }
  });

  // Recibir notificación en foreground
  PushNotifications.addListener('pushNotificationReceived', (notification) => {
    console.log('Push received:', notification);
    // Mostrar toast o badge
  });

  // Usuario toca la notificación
  PushNotifications.addListener('pushNotificationActionPerformed', (action) => {
    console.log('Push action:', action);
    // Navegar a la conversación correspondiente
  });
}
```

### 9.3 Backend — Enviar Push

Para enviar pushes desde PHP cuando llega un mensaje nuevo:

**Servicio:** Firebase Cloud Messaging (FCM) para Android e iOS.

```php
// En omnichannel-controller.php, dentro de receive_message():
// Después de guardar el mensaje, si hay agente asignado:
$this->send_push_notification($agent_push_token, [
    'title' => "Nuevo mensaje de $contact_name",
    'body'  => substr($content, 0, 100),
    'data'  => ['conversation_id' => $conversation_id]
]);
```

---

## 10. Consideraciones Especiales

### 10.1 Modo "Servidor Remoto" vs "Archivos Locales"

**Servidor Remoto** (recomendado para empezar):

En `capacitor.config.ts`:
```typescript
server: {
  url: 'https://automatizatech.cl/omnicliente/',
  cleartext: false
}
```

Ventaja: Actualizaciones instantáneas (solo cambias el servidor web).  
Desventaja: Requiere conexión a internet siempre.

**Archivos Locales** (recomendado para producción):

En `capacitor.config.ts`:
```typescript
server: {
  androidScheme: 'https'
  // No url → usa webDir: 'dist'
}
```

Ventaja: Funciona offline, más rápido.  
Desventaja: Requiere nueva versión en store para actualizar UI.

**Híbrido** (mejor de ambos):

Usar archivos locales + Capacitor Live Update (plugin):
```bash
npm install @capawesome/capacitor-live-update
```
Permite actualizar la web sin pasar por la store.

### 10.2 Seguridad en Mobile

- **Certificate Pinning:** Considerar para evitar MITM attacks
- **Biometric Auth:** Usar `@capacitor/biometric-auth` para login rápido
- **Secure Storage:** `@capacitor/preferences` encripta en keychain (iOS) / keystore (Android)
- **No almacenar tokens en localStorage en producción:** Migrar a Secure Storage

### 10.3 Ajustes CSS para Mobile

Ya tienes `viewport-fit: cover` y `user-scalable=no`. Adicionalmente:

```css
/* Evitar selección de texto accidental */
* {
  -webkit-user-select: none;
  user-select: none;
}
input, textarea {
  -webkit-user-select: text;
  user-select: text;
}

/* Safe areas para iPhone con notch/island */
.sidebar {
  padding-top: max(1rem, env(safe-area-inset-top));
}
.bottom-bar {
  padding-bottom: max(0.5rem, env(safe-area-inset-bottom));
}

/* Evitar rubber-banding bounce en iOS */
html {
  overscroll-behavior: none;
}
```

### 10.4 Testing en Dispositivo Físico

**Android:**
```bash
# Habilitar USB debugging en el dispositivo
# Conectar por USB
npx cap run android --target=<device-id>

# O usar WiFi debugging (Android 11+)
adb pair <ip>:<port>
```

**iOS:**
```bash
# Conectar iPhone por USB
# Xcode: seleccionar dispositivo → Run
npx cap run ios --target=<device-id>
```

### 10.5 Estimación de Costos

| Concepto | Costo | Frecuencia |
|---|---|---|
| Google Play Developer | $25 | Única vez |
| Apple Developer Program | $99 | Anual |
| Firebase (push notifications) | $0 | Gratis hasta 500K/mes |
| Hosting (ya lo tienes) | $0 | Incluido en Hostinger |
| **Total primer año** | **~$124** | |
| **Total años siguientes** | **~$99** | (solo Apple) |

### 10.6 Checklist Pre-Publicación

#### Android
- [ ] Ícono 512x512 en alta resolución
- [ ] Feature graphic 1024x500
- [ ] Mínimo 2 screenshots (teléfono)
- [ ] Política de privacidad publicada
- [ ] App firmada con keystore (guardar backup seguro)
- [ ] ProGuard habilitado (ofuscación)
- [ ] AAB generado (no APK)

#### iOS
- [ ] Ícono 1024x1024
- [ ] Screenshots para iPhone 6.7" y 6.5"
- [ ] Política de privacidad publicada
- [ ] Privacy Nutrition Labels completados
- [ ] Certificados de distribución configurados
- [ ] Info.plist actualizado (permisos explicados)
- [ ] NSAppTransportSecurity configurado
- [ ] Al menos un feature nativo (push notifications)

---

## Resumen: Ruta Recomendada

```
FASE 1 (Inmediato — 1-2 horas)
├── Completar PWA (iconos + service worker)
├── Probar instalación desde Chrome/Safari
└── Validar con Lighthouse

FASE 2 (Corto plazo — 2-3 días)
├── Instalar Capacitor
├── Configurar Android
├── Generar APK de prueba
├── Probar en dispositivo físico
└── Agregar Push Notifications

FASE 3 (Publicación — 3-5 días)
├── Crear cuentas de developer (Google + Apple)
├── Preparar assets (iconos, screenshots, descriptions)
├── Crear política de privacidad
├── Generar AAB (Android) + IPA (iOS)
├── Subir a stores
└── Esperar revisión

FASE 4 (Post-publicación)
├── Monitorear reviews
├── Configurar CI/CD para builds automáticos
├── Implementar Live Update para actualizaciones sin store
└── Analytics con Firebase
```

---

*Para detalles técnicos del portal, consultar AUTOMATIZATECH_TECNICO.md*
