# 🚀 GUÍA RÁPIDA DE DEPLOYMENT - 10 MINUTOS

## 📋 Pre-requisitos
- Acceso FTP/SFTP o File Manager de Hostinger
- Acceso al panel de administración de WordPress
- Acceso al hPanel de Hostinger

---

## ⚡ Paso 1: Crear Correo en Hostinger (2 min)

1. Entra a **hPanel de Hostinger**
2. Click en **Correos**
3. Click en **Cuentas de correo**
4. Click en **Crear**
5. Configurar:
   - Email: `contacto@automatizatech.cl`
   - Contraseña: (crea una segura y **guárdala**)
   - Espacio: 1GB
6. Click en **Crear**

✅ **Anota la contraseña**, la necesitarás en el siguiente paso

---

## ⚡ Paso 2: Subir Archivos (3 min)

### Conecta vía FTP/SFTP o usa File Manager

Sube estos archivos **REEMPLAZANDO** los existentes:

```
/wp-content/themes/automatiza-tech/inc/contact-form.php
/wp-content/themes/automatiza-tech/inc/smtp-config.php (NUEVO)
/wp-content/themes/automatiza-tech/functions.php
/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png (NUEVO)
/verify-email-setup.php (TEMPORAL - a la raíz)
```

### Desde tu computadora local:
```
C:\wamp64\www\automatiza-tech\wp-content\themes\automatiza-tech\
```

---

## ⚡ Paso 3: Configurar wp-config.php (2 min)

1. Abre el archivo `wp-config.php` en tu servidor (en la raíz)
2. Busca la línea: `/* That's all, stop editing! Happy publishing. */`
3. **ANTES** de esa línea, agrega:

```php
/**
 * Configuración SMTP para envío de correos
 */
define('SMTP_USER', 'contacto@automatizatech.cl');
define('SMTP_PASS', 'AQUI_TU_CONTRASEÑA');  // La del paso 1
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587);
```

4. Reemplaza `AQUI_TU_CONTRASEÑA` con la contraseña real del correo
5. **GUARDA** el archivo

⚠️ **IMPORTANTE**: Usa la contraseña del CORREO, NO la de hPanel

---

## ⚡ Paso 4: Actualizar Email Admin (1 min)

1. Ve a tu WordPress Admin
2. Click en **Ajustes** → **Generales**
3. Cambia **Dirección de correo electrónico** a: `contacto@automatizatech.cl`
4. Click en **Guardar cambios**

---

## ⚡ Paso 5: Verificar Todo (2 min)

1. Accede a: `https://tudominio.com/verify-email-setup.php`
2. Deberías ver un **porcentaje ≥ 80%** ✅
3. Si hay errores en rojo ❌, corrígelos
4. Una vez todo verde, click en **"📧 Enviar Test de Correo"**
5. Revisa tu bandeja de entrada de `contacto@automatizatech.cl`

### ¿Todo OK?
- ✅ Correo de prueba recibido
- ✅ Logo visible
- ✅ Diseño correcto

---

## 🎉 ¡LISTO! Ya puedes usar el sistema

### Para enviar correos:

1. Ve a **Automatiza Tech** → **Contactos**
2. Verás todos tus contactos con estado "Nuevo"
3. Click en **"📧 Enviar Correo a Nuevos Contactos"**
4. ¡Los correos se enviarán automáticamente!

---

## 🔒 IMPORTANTE: Limpieza de Seguridad

Una vez que todo funcione correctamente:

1. **ELIMINA** el archivo `verify-email-setup.php` del servidor
   ```
   rm verify-email-setup.php
   ```

2. Nunca compartas tu archivo `wp-config.php`

3. No subas archivos con contraseñas al repositorio

---

## ❓ ¿Problemas?

### Correos no llegan
```php
// En wp-config.php, prueba cambiar el puerto:
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
```

### Ver errores
```php
// En wp-config.php, activa debug:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
// Revisa: wp-content/debug.log
```

### Contactar Soporte Hostinger
- Chat en vivo 24/7 disponible en hPanel
- Verifica que el puerto 587 esté abierto
- Confirma que la cuenta de correo esté activa

---

## 📚 Más Información

- **Guía Completa**: CONFIGURACION-CORREO-HOSTINGER.md
- **Checklist Detallado**: DEPLOYMENT-CHECKLIST.md
- **README del Sistema**: SISTEMA-CORREO-README.md

---

## ✅ Checklist Final

Marca cuando completes cada paso:

- [ ] Cuenta de correo creada en Hostinger
- [ ] Archivos subidos al servidor
- [ ] wp-config.php configurado con credenciales
- [ ] Email admin actualizado en WordPress
- [ ] Verificación automática ≥ 80%
- [ ] Correo de prueba enviado y recibido
- [ ] verify-email-setup.php eliminado
- [ ] Sistema funcionando correctamente

---

**Tiempo total estimado: 10 minutos**

🎉 **¡Sistema listo para producción!**

---

**Última actualización**: 11 de Noviembre 2025  
**Versión**: 1.0
