# 🚀 Checklist de Deployment - Sistema de Correo Automatiza Tech

## 📦 Antes de Subir a Producción

### ✅ Archivos a Subir
- [ ] `wp-content/themes/automatiza-tech/inc/contact-form.php` (actualizado)
- [ ] `wp-content/themes/automatiza-tech/inc/smtp-config.php` (nuevo)
- [ ] `wp-content/themes/automatiza-tech/functions.php` (actualizado)
- [ ] `wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png` (nuevo)
- [ ] `verify-email-setup.php` (temporal - para verificación)

### ✅ Base de Datos
- [ ] Tabla `wp_automatiza_tech_contacts` existe
- [ ] Tabla `wp_automatiza_services` existe
- [ ] Hay al menos 3 planes activos en `wp_automatiza_services` con `category='pricing'`
- [ ] Los precios de los planes están en USD ($99, $199, $399)

---

## 🔧 Configuración en Hostinger

### 1. Crear Cuenta de Correo
- [ ] Acceder a hPanel de Hostinger
- [ ] Ir a **Correos** → **Cuentas de correo**
- [ ] Crear correo: `contacto@automatizatech.cl`
- [ ] Establecer contraseña segura (guárdala en lugar seguro)
- [ ] Verificar que la cuenta esté activa

### 2. Configurar wp-config.php en Producción
- [ ] Conectar vía FTP/SFTP o File Manager
- [ ] Abrir `wp-config.php` en el servidor
- [ ] Agregar ANTES de `/* That's all, stop editing! */`:

```php
/**
 * Configuración SMTP para envío de correos
 */
define('SMTP_USER', 'contacto@automatizatech.cl');
define('SMTP_PASS', 'TU_CONTRASEÑA_DEL_CORREO');
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587);
```

- [ ] Reemplazar `TU_CONTRASEÑA_DEL_CORREO` con la contraseña real
- [ ] Guardar y cerrar

### 3. Actualizar Ajustes de WordPress
- [ ] Ir a **Ajustes** → **Generales**
- [ ] Cambiar **Dirección de correo electrónico** a: `contacto@automatizatech.cl`
- [ ] Guardar cambios

---

## 🧪 Verificación y Pruebas

### 1. Verificación Automática
- [ ] Acceder a: `https://tudominio.com/verify-email-setup.php`
- [ ] Verificar que el porcentaje sea ≥ 80%
- [ ] Revisar cada punto de verificación
- [ ] Corregir cualquier error mostrado

### 2. Test de Correo
- [ ] En la página de verificación, hacer clic en **"📧 Enviar Test de Correo"**
- [ ] Verificar que muestre mensaje de éxito
- [ ] Revisar bandeja de entrada de `contacto@automatizatech.cl`
- [ ] Verificar que el correo llegó correctamente
- [ ] Revisar que el logo se vea correctamente
- [ ] Verificar links de WhatsApp y sitio web

### 3. Test con Contacto Real
- [ ] Ir a **Automatiza Tech** → **Contactos**
- [ ] Crear un contacto de prueba con estado "Nuevo"
- [ ] Hacer clic en **"📧 Enviar Correo a Nuevos Contactos"**
- [ ] Verificar mensaje de éxito en admin
- [ ] Revisar el correo recibido
- [ ] Verificar diseño completo:
  - [ ] Logo visible
  - [ ] Gradientes correctos
  - [ ] Bots y emojis presentes
  - [ ] 3 planes con precios correctos
  - [ ] Botón WhatsApp funcional
  - [ ] Botón sitio web funcional
  - [ ] Footer con información de contacto

---

## 🔐 Seguridad Post-Deployment

### Archivos a Eliminar
- [ ] **ELIMINAR** `verify-email-setup.php` (después de verificar)
- [ ] **ELIMINAR** `smtp-config.env.example` (si lo subiste)
- [ ] **NO SUBIR** archivos de backup con credenciales

### Permisos y Acceso
- [ ] Verificar que `wp-config.php` tenga permisos 644 o 600
- [ ] Verificar que no se pueda acceder directamente a `/inc/*.php`
- [ ] Confirmar que solo administradores pueden enviar correos masivos

---

## 📊 Monitoreo Post-Deployment

### Primera Semana
- [ ] Revisar logs diarios en `wp-content/debug.log`
- [ ] Monitorear bandeja de entrada de `contacto@automatizatech.cl`
- [ ] Verificar que no haya correos en SPAM
- [ ] Confirmar tasa de entrega exitosa
- [ ] Revisar reportes de apertura (si tienes analytics)

### Configuración SPF/DKIM (Opcional pero Recomendado)
- [ ] En Hostinger, ir a **Correos** → **Autenticación**
- [ ] Copiar registros SPF y DKIM
- [ ] Agregar registros DNS en configuración de dominio
- [ ] Esperar propagación DNS (24-48 horas)
- [ ] Verificar con herramientas online (MXToolbox, etc.)

---

## 🐛 Troubleshooting

### Si los correos no llegan:

1. **Verificar credenciales**
   - [ ] Usuario correcto en SMTP_USER (con @dominio.com)
   - [ ] Contraseña correcta en SMTP_PASS
   - [ ] Host correcto: smtp.hostinger.com

2. **Probar puerto alternativo**
   - [ ] Cambiar SMTP_PORT de 587 a 465
   - [ ] Agregar: `define('SMTP_SECURE', 'ssl');`

3. **Revisar logs**
   - [ ] Activar debug en wp-config.php
   - [ ] Revisar `wp-content/debug.log`
   - [ ] Buscar mensajes de error SMTP

4. **Contactar Hostinger**
   - [ ] Verificar que el puerto 587/465 esté abierto
   - [ ] Confirmar que la cuenta de correo esté activa
   - [ ] Revisar límites de envío

### Si los correos llegan a SPAM:

1. **Configurar autenticación**
   - [ ] Activar SPF en Hostinger
   - [ ] Activar DKIM en Hostinger
   - [ ] Verificar registros DNS

2. **Revisar contenido**
   - [ ] Evitar palabras spam
   - [ ] Balance texto/imágenes correcto
   - [ ] Links válidos y seguros

3. **Remitente correcto**
   - [ ] Usar dominio propio (no gmail, yahoo, etc.)
   - [ ] Remitente coincide con dominio del servidor

---

## ✅ Checklist Final

### Todo OK cuando:
- [x] Verificación automática ≥ 80%
- [x] Correo de prueba recibido exitosamente
- [x] Logo visible en el correo
- [x] Diseño moderno con gradientes
- [x] 3 planes con precios correctos
- [x] Botones funcionales (WhatsApp, Web)
- [x] Correos llegan a bandeja principal (no SPAM)
- [x] No hay errores en logs
- [x] `verify-email-setup.php` eliminado

---

## 📞 Contactos de Soporte

- **Hostinger Support**: https://www.hostinger.com/contact (Chat 24/7)
- **WordPress Forums**: https://wordpress.org/support/
- **Email Deliverability**: https://www.mail-tester.com/ (test de spam)
- **DNS Tools**: https://mxtoolbox.com/ (verificar SPF/DKIM)

---

## 📝 Notas Adicionales

**Límites de Hostinger (verificar tu plan):**
- Correos por hora: ~100-300 (según plan)
- Si necesitas enviar más, considera servicios SMTP externos (SendGrid, Mailgun, etc.)

**Backup:**
- Siempre mantén un backup del `wp-config.php` original
- Guarda las credenciales SMTP en un gestor de contraseñas

**Mantenimiento:**
- Cambiar contraseña de correo cada 3-6 meses
- Revisar logs mensualmente
- Actualizar precios de planes según necesidad

---

**Última actualización**: Noviembre 2025  
**Versión**: 1.0  
**Estado**: ✅ Listo para producción
