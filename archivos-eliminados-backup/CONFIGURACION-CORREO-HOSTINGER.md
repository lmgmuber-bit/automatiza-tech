# 📧 Guía de Configuración de Correo en Hostinger - Automatiza Tech

## 🎯 Objetivo
Configurar el envío de correos electrónicos desde WordPress en el servidor de producción (Hostinger) para el sistema de contactos de Automatiza Tech.

---

## 📋 Pasos de Configuración en Hostinger

### 1️⃣ Crear Cuenta de Correo en Hostinger

1. Accede al **Panel de Hostinger** (hPanel)
2. Ve a **Correos** → **Cuentas de correo**
3. Crea una cuenta de correo:
   - **Email**: `info@automatizatech.cl` (o el dominio que uses)
   - **Contraseña**: Crea una contraseña segura y guárdala
   - **Espacio**: 1GB es suficiente

### 2️⃣ Configurar wp-config.php en Producción

Agrega estas líneas al archivo `wp-config.php` en el servidor de producción (ANTES de la línea `/* That's all, stop editing! */`):

```php
/**
 * Configuración SMTP para envío de correos
 * Automatiza Tech - Hostinger
 */
define('SMTP_USER', 'info@automatizatech.cl'); // Tu correo de Hostinger
define('SMTP_PASS', 'TU_CONTRASEÑA_AQUI');     // La contraseña del correo
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587);
```

**⚠️ IMPORTANTE**: Reemplaza `TU_CONTRASEÑA_AQUI` con la contraseña real del correo.

### 3️⃣ Verificar Configuración del Correo Admin

1. En el panel de WordPress, ve a **Ajustes** → **Generales**
2. Cambia **Dirección de correo electrónico** a: `info@automatizatech.cl`
3. Guarda los cambios

---

## 🧪 Probar el Sistema de Correo

### Método 1: Botón de Test en Admin

1. Ve a **Automatiza Tech** → **Contactos**
2. Haz clic en el botón **"📧 Test de Correo"** (junto al botón de enviar correos)
3. Deberías ver un mensaje de éxito
4. Revisa tu bandeja de entrada en `info@automatizatech.cl`

### Método 2: Test Manual con Plugin (Opcional)

Si quieres hacer más pruebas, puedes instalar temporalmente:
- **WP Mail SMTP** o **Easy WP SMTP** desde el repositorio de plugins
- Configurar con los mismos datos SMTP
- Hacer pruebas de envío

---

## 🚀 Envío de Correos a Contactos Nuevos

Una vez configurado, ya puedes usar el sistema:

1. Ve a **Automatiza Tech** → **Contactos**
2. Verás la lista de contactos con estado "Nuevo"
3. Haz clic en **"📧 Enviar Correo a Nuevos Contactos"**
4. El sistema enviará automáticamente el correo con:
   - ✅ Logo de Automatiza Tech
   - ✅ Diseño moderno con gradientes
   - ✅ Bots y emojis simpáticos
   - ✅ Planes dinámicos desde la base de datos
   - ✅ Botones de WhatsApp y Web
   - ✅ Información de contacto

---

## 🔧 Configuración SMTP de Hostinger

### Datos de Conexión SMTP

```
Servidor SMTP: smtp.hostinger.com
Puerto: 587 (TLS) o 465 (SSL)
Seguridad: TLS/STARTTLS
Usuario: info@automatizatech.cl (tu correo completo)
Contraseña: La contraseña del correo
```

### Alternativa con SSL (Puerto 465)

Si el puerto 587 no funciona, puedes usar:
```php
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl'); // En vez de 'tls'
```

---

## 🐛 Resolución de Problemas

### ❌ "Error al enviar correo"

**Posibles causas:**

1. **Credenciales incorrectas**
   - Verifica usuario y contraseña en `wp-config.php`
   - Asegúrate de usar el correo completo (`info@automatizatech.cl`)

2. **Puerto bloqueado**
   - Contacta a Hostinger para verificar que el puerto 587 esté abierto
   - Prueba con puerto 465 (SSL)

3. **Firewall del servidor**
   - Verifica con Hostinger que no haya restricciones
   - Puede ser necesario añadir IP a whitelist

### 📧 Los correos llegan a SPAM

**Soluciones:**

1. **Configurar SPF y DKIM**
   - Ve a Hostinger → Correos → Configuración
   - Activa autenticación SPF y DKIM
   - Copia los registros DNS y agrégalos a tu dominio

2. **Verificar remitente**
   - Usa siempre `info@automatizatech.cl` como remitente
   - No uses correos genéricos como `wordpress@` o `noreply@`

3. **Contenido del correo**
   - Evita palabras spam: "gratis", "oferta", "urgente"
   - Mantén un balance texto/imágenes
   - Incluye opción de desuscribirse (ya incluida en footer)

### 🔍 Activar Debug (Solo para pruebas)

Edita `inc/smtp-config.php` y descomenta estas líneas:

```php
$phpmailer->SMTPDebug = 2;
$phpmailer->Debugoutput = 'html';
```

**⚠️ IMPORTANTE**: Comenta nuevamente después de hacer debug (no dejar en producción)

---

## 📊 Monitoreo de Correos

### Logs de WordPress

Los errores de correo se guardan en el log de WordPress si tienes activado:

```php
// En wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Los logs estarán en: `wp-content/debug.log`

### Ver Estadísticas

Puedes ver en el panel de WordPress:
- Correos enviados exitosamente
- Correos fallidos
- Detalles de errores

---

## ✅ Checklist de Producción

Antes de activar en producción, verifica:

- [ ] Cuenta de correo creada en Hostinger (`info@automatizatech.cl`)
- [ ] Credenciales SMTP agregadas a `wp-config.php`
- [ ] Correo admin cambiado en WordPress
- [ ] Test de correo realizado exitosamente
- [ ] SPF y DKIM configurados (opcional pero recomendado)
- [ ] Logo PNG subido a assets (`logo-automatiza-tech.png`)
- [ ] Planes activos en la base de datos
- [ ] WhatsApp configurado en el tema
- [ ] Debug desactivado en producción

---

## 🎨 Personalización del Email

### Cambiar Logo

El logo se carga desde:
```
/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png
```

Para cambiarlo, sube un nuevo PNG con el mismo nombre.

### Modificar Diseño

Edita el archivo:
```
/wp-content/themes/automatiza-tech/inc/contact-form.php
```

Busca la función `get_email_template()` (línea ~1240)

### Cambiar Colores

Los gradientes actuales:
- Header: `#667eea` → `#764ba2` (Púrpura-Violeta)
- Planes: Aqua-Pink, Lilac-Yellow, Orange-Peach
- WhatsApp: `#25D366` (Verde WhatsApp oficial)

---

## 📞 Soporte

Si tienes problemas con la configuración:

1. **Hostinger Support**: Chat en vivo disponible 24/7
2. **Documentación SMTP**: https://support.hostinger.com/es/articles/1583229
3. **WordPress Debug**: Revisa `wp-content/debug.log`

---

## 🚀 ¡Todo Listo!

Una vez configurado correctamente:

1. Los correos se enviarán desde `info@automatizatech.cl`
2. Los contactos nuevos recibirán el email profesional
3. Se verá el logo de Automatiza Tech
4. Diseño moderno con bots y emojis
5. Links funcionales a WhatsApp y sitio web

**¡El sistema está listo para producción!** 🎉

---

**Última actualización**: Noviembre 2025  
**Versión**: 1.0  
**Autor**: Automatiza Tech Development Team
