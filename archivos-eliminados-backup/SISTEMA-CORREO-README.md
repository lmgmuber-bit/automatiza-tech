# 📧 Sistema de Envío de Correos - Automatiza Tech

## 🎯 Resumen Ejecutivo

Sistema completo de envío de correos electrónicos para contactos nuevos en WordPress, diseñado específicamente para **Hostinger** con diseño moderno, bots simpáticos y contenido dinámico desde base de datos.

---

## ✨ Características

- ✅ **Envío masivo** a todos los contactos con estado "Nuevo"
- ✅ **Diseño moderno** con gradientes, bots y emojis
- ✅ **Logo profesional** incluido en el header
- ✅ **Contenido dinámico** cargado desde base de datos (planes de precios)
- ✅ **Responsive** compatible con todos los clientes de correo
- ✅ **Configuración SMTP** optimizada para Hostinger
- ✅ **Logging** de errores y envíos exitosos
- ✅ **Test integrado** para verificar configuración

---

## 📦 Archivos del Sistema

### Archivos Principales
```
wp-content/themes/automatiza-tech/
├── inc/
│   ├── contact-form.php          # Sistema de contactos con envío de correos
│   ├── smtp-config.php            # Configuración SMTP para Hostinger
│   └── contact-shortcode.php     # Shortcodes de formulario
├── assets/
│   └── images/
│       └── logo-automatiza-tech.png  # Logo para emails
└── functions.php                  # Incluye todos los módulos

Raíz del proyecto:
├── verify-email-setup.php         # Script de verificación (temporal)
├── CONFIGURACION-CORREO-HOSTINGER.md   # Guía completa
├── DEPLOYMENT-CHECKLIST.md        # Lista de verificación
└── smtp-config.env.example        # Ejemplo de configuración
```

### Archivos de Documentación
- **CONFIGURACION-CORREO-HOSTINGER.md**: Guía paso a paso completa
- **DEPLOYMENT-CHECKLIST.md**: Lista de verificación para deployment
- **smtp-config.env.example**: Ejemplo de configuración para wp-config.php

---

## 🚀 Instalación Rápida (5 pasos)

### 1. Crear Cuenta de Correo en Hostinger
```
Panel Hostinger → Correos → Crear cuenta
Email: info@automatizatech.cl
Contraseña: [crear contraseña segura]
```

### 2. Configurar wp-config.php en Producción
Agregar antes de `/* That's all, stop editing! */`:

```php
define('SMTP_USER', 'info@automatizatech.cl');
define('SMTP_PASS', 'tu_contraseña_real');
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587);
```

### 3. Subir Archivos
```bash
# Subir vía FTP/SFTP estos archivos actualizados:
- wp-content/themes/automatiza-tech/inc/contact-form.php
- wp-content/themes/automatiza-tech/inc/smtp-config.php
- wp-content/themes/automatiza-tech/functions.php
- wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png
```

### 4. Verificar Configuración
```
Acceder a: https://tudominio.com/verify-email-setup.php
Verificar que todas las comprobaciones pasen (≥80%)
```

### 5. Enviar Correo de Prueba
```
WordPress Admin → Automatiza Tech → Contactos
Click en "📧 Test de Correo"
Verificar recepción en bandeja de entrada
```

---

## 🎨 Diseño del Email

### Estructura Visual

```
┌─────────────────────────────────┐
│   HEADER CON GRADIENTE PÚRPURA  │
│   Logo Automatiza Tech (320px)  │
│   Bot animado 🤖 (60px)         │
│   Tagline con emojis            │
├─────────────────────────────────┤
│   DECORACIÓN DE BOTS            │
│   🤖💬🚀⚡🎯                   │
├─────────────────────────────────┤
│   SALUDO PERSONALIZADO          │
│   ¡Hola [Nombre]! 👋✨          │
│   Mensaje de bienvenida         │
├─────────────────────────────────┤
│   PLANES (Dinámicos desde BD)   │
│   ┌───────────────────────┐    │
│   │ 🌟 Plan Básico        │    │
│   │ $99 USD/mes           │    │
│   │ ✅ Características    │    │
│   └───────────────────────┘    │
│   [Plan Profesional]            │
│   [Plan Enterprise]             │
├─────────────────────────────────┤
│   CALL TO ACTION                │
│   🎯 ¿Listo para comenzar?     │
│   [WhatsApp] [Sitio Web]       │
├─────────────────────────────────┤
│   FOOTER PROFESIONAL            │
│   🤖 Info de contacto           │
│   Enlaces sociales              │
│   Copyright                     │
└─────────────────────────────────┘
```

### Colores del Sistema
- **Primary Gradient**: #667eea → #764ba2 (Púrpura-Violeta)
- **Plan 1**: #a8edea → #fed6e3 (Aqua-Pink)
- **Plan 2**: #d299c2 → #fef9d7 (Lilac-Yellow)
- **Plan 3**: #ffecd2 → #fcb69f (Orange-Peach)
- **WhatsApp**: #25D366 (Verde oficial)

---

## 🔧 Funcionalidades Técnicas

### Sistema de Contactos

**Filtrado Avanzado:**
- Búsqueda por nombre, email, teléfono, mensaje
- Filtro por estado (7 estados disponibles)
- Debounce de 300ms para búsquedas

**Estados de Contacto:**
1. 🆕 Nuevo
2. 📞 Contactado
3. 📅 Seguimiento
4. 💜 Interesado
5. 👎 No Interesado
6. ✅ Contratado (mueve a tabla de clientes)
7. 🔒 Cerrado

### Envío de Correos

**Características:**
- Envío masivo con pausa de 0.5s entre correos
- Logging automático de éxitos y errores
- Contador de correos enviados/fallados
- Plantilla HTML responsive
- Contenido dinámico desde BD

**Configuración SMTP:**
- Host: smtp.hostinger.com
- Puerto: 587 (TLS) o 465 (SSL)
- Autenticación requerida
- Charset: UTF-8
- Encoding: base64

---

## 📊 Base de Datos

### Tablas Utilizadas

**wp_automatiza_tech_contacts:**
```sql
- id (int): ID único
- name (varchar): Nombre del contacto
- email (varchar): Correo electrónico
- phone (varchar): Teléfono
- message (text): Mensaje del contacto
- status (varchar): Estado del contacto
- submitted_at (datetime): Fecha de envío
```

**wp_automatiza_services:**
```sql
- id (int): ID único
- name (varchar): Nombre del plan
- price (decimal): Precio del plan
- currency (varchar): Moneda (USD)
- category (varchar): 'pricing' para planes
- features (text): JSON con características
- active (tinyint): 1 = activo
```

---

## 🧪 Testing

### Test Automático
```
URL: https://tudominio.com/verify-email-setup.php

Verifica:
✅ Archivo smtp-config.php existe
✅ Credenciales SMTP configuradas
✅ Correo admin correcto
✅ Logo disponible
✅ Función wp_mail activa
✅ Tabla de contactos existe
✅ Planes activos en BD
```

### Test Manual
```
Admin → Automatiza Tech → Contactos
1. Click "📧 Test de Correo"
2. Revisar mensaje de éxito
3. Verificar email recibido
4. Comprobar diseño completo
```

---

## 🐛 Troubleshooting

### Problema: Correos no llegan

**Solución 1**: Verificar credenciales
```php
// En wp-config.php
define('SMTP_USER', 'info@automatizatech.cl'); // ✅ Correo completo
define('SMTP_PASS', 'contraseña_correcta');    // ✅ Sin espacios
```

**Solución 2**: Cambiar puerto
```php
define('SMTP_PORT', 465);         // En vez de 587
define('SMTP_SECURE', 'ssl');     // En vez de 'tls'
```

**Solución 3**: Activar debug
```php
// En wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
// Revisar: wp-content/debug.log
```

### Problema: Correos en SPAM

**Solución**:
1. Configurar SPF y DKIM en Hostinger
2. Usar remitente con dominio propio
3. Evitar palabras spam
4. Equilibrar texto/imágenes

### Problema: Logo no se ve

**Solución**:
```bash
# Verificar que existe:
/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png

# Verificar permisos:
chmod 644 logo-automatiza-tech.png
```

---

## 📈 Límites y Consideraciones

### Hostinger Limits
- **Correos por hora**: 100-300 (según plan)
- **Tamaño máximo**: 25MB por correo
- **Adjuntos**: Hasta 10MB recomendado

### Recomendaciones
- Para más de 300 correos/día: considerar servicio SMTP externo
- Pausas entre envíos: 0.5s (ya implementado)
- Monitorear tasa de rebote
- Revisar logs regularmente

---

## 🔐 Seguridad

### ✅ Implementado
- Verificación de nonce en todas las acciones AJAX
- Verificación de permisos de administrador
- Escape de datos con esc_html(), esc_url()
- Sanitización de inputs
- Credenciales en wp-config.php (fuera de repositorio)

### ⚠️ Importante
- **NUNCA** subir wp-config.php al repositorio
- **NUNCA** compartir credenciales SMTP
- **ELIMINAR** verify-email-setup.php después de verificar
- Cambiar contraseñas cada 3-6 meses

---

## 📞 Soporte y Recursos

### Documentación
- **Guía completa**: CONFIGURACION-CORREO-HOSTINGER.md
- **Checklist**: DEPLOYMENT-CHECKLIST.md
- **WordPress Codex**: https://codex.wordpress.org/

### Herramientas Útiles
- **Mail Tester**: https://www.mail-tester.com/ (test de spam)
- **MXToolbox**: https://mxtoolbox.com/ (verificar DNS)
- **Hostinger Support**: Chat 24/7 disponible

### Contacto Técnico
- Email: automatizatech.bots@gmail.com
- WhatsApp: +56 9 4033 1127
- Web: https://automatizatech.cl

---

## 📝 Changelog

### Versión 1.0 (Noviembre 2025)
- ✅ Sistema completo de envío de correos
- ✅ Diseño moderno con bots y emojis
- ✅ Integración con Hostinger SMTP
- ✅ Logo profesional incluido
- ✅ Contenido dinámico desde BD
- ✅ Logging y debug
- ✅ Script de verificación
- ✅ Documentación completa

---

## 🎉 Estado del Proyecto

**✅ LISTO PARA PRODUCCIÓN**

Sistema completamente funcional y probado, listo para deployment en Hostinger con:
- Diseño profesional y amigable
- Código optimizado y seguro
- Documentación completa
- Herramientas de verificación incluidas

---

**Última actualización**: 11 de Noviembre 2025  
**Versión**: 1.0  
**Desarrollado por**: Automatiza Tech Development Team  
**Licencia**: Propietario - Automatiza Tech
