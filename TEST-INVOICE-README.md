# 🧪 Sistema de Previsualización de Facturas y Correos

## 📋 Descripción

Este sistema te permite **previsualizar** las facturas y correos electrónicos que se generan cuando un cliente pasa a estado "Contratado", **sin enviar correos reales** ni modificar la base de datos.

## 🚀 Cómo Usar

### 1. Acceder al Sistema de Pruebas

```
http://localhost/automatiza-tech/test-invoice-preview.php
```

### 2. Características del Preview

El sistema muestra 5 pestañas diferentes:

#### 📄 **Factura HTML**
- Vista previa completa de la factura que se adjunta al correo
- Botones para descargar e imprimir
- Diseño profesional con colores de AutomatizaTech

#### 📧 **Correo al Cliente**
- Preview del correo que recibe el cliente
- Optimizado con reglas anti-spam
- Incluye mensaje de bienvenida y detalles del plan

#### 📨 **Correo Interno**
- Preview del correo de notificación a automatizatech.bots@gmail.com
- Incluye toda la información del cliente y plan contratado
- Mantiene diseño colorido para uso interno

#### 📝 **Texto Plano**
- Versión alternativa del correo (AltBody)
- Mejora la deliverability del correo
- Se envía automáticamente junto con la versión HTML

#### 🔧 **Headers Anti-Spam**
- Lista de todos los headers configurados
- Mejores prácticas aplicadas
- Checklist de optimización

## 📦 Archivos Incluidos

```
automatiza-tech/
├── test-invoice-preview.php          # Archivo principal de previsualización
├── generate-invoice-html.php         # Generador de factura
├── generate-email-client.php         # Generador de correo al cliente
└── generate-email-internal.php       # Generador de correo interno
```

## ✅ Datos de Prueba

El sistema usa datos ficticios:

**Cliente:**
- Nombre: Juan Pérez González
- Email: test@ejemplo.com
- Empresa: Empresa Demo S.A.
- Teléfono: +56 9 6432 4169

**Plan:**
- Se usa el primer plan activo de la base de datos
- Si no hay planes, mostrará un error

**Factura:**
- Número: AT-YYYYMMDD-TEST
- IVA: 19%
- Fecha: Actual

## 🔒 Seguridad

- ⚠️ **Solo accesible para administradores**
- ❌ **No envía correos reales**
- ❌ **No modifica la base de datos**
- ✅ **Entorno 100% seguro para pruebas**

## 🎯 Flujo de Trabajo Recomendado

1. **Desarrollo Local:**
   ```
   1. Abre test-invoice-preview.php
   2. Revisa diseño de factura y correos
   3. Verifica que todo se vea correcto
   4. Ajusta colores/textos si es necesario
   ```

2. **Antes de Producción:**
   ```
   1. Verifica headers anti-spam
   2. Confirma que el texto plano se lee bien
   3. Revisa la información del plan
   4. Asegúrate de que los totales son correctos
   ```

3. **Prueba Real (Opcional):**
   ```
   1. Ve al panel de contactos
   2. Crea un contacto de prueba con TU email
   3. Cámbialo a "Contratado" y selecciona un plan
   4. Revisa el correo real en tu bandeja
   ```

4. **Producción:**
   ```
   1. Sube contact-form.php a producción
   2. Limpia cache de WordPress
   3. Prueba con un cliente real
   ```

## 🎨 Personalización

Si necesitas modificar el diseño:

1. **Colores:**
   - Edita las variables en cada archivo generador
   - `$primary_color = '#1e3a8a'` (Azul)
   - `$secondary_color = '#06d6a0'` (Verde)
   - `$accent_color = '#f59e0b'` (Naranja)

2. **Textos:**
   - Edita los textos directamente en los archivos
   - `generate-email-client.php` para correo al cliente
   - `generate-email-internal.php` para correo interno
   - `generate-invoice-html.php` para la factura

3. **Layout:**
   - Modifica el CSS dentro de cada `<style>` tag
   - Usa las clases existentes como referencia

## 📊 Checklist de Verificación

Antes de pasar a producción, verifica:

- [ ] La factura muestra correctamente el plan contratado
- [ ] Los totales (subtotal, IVA, total) son correctos
- [ ] El correo al cliente tiene tono profesional
- [ ] El correo interno muestra toda la información
- [ ] Los headers anti-spam están configurados
- [ ] La versión texto plano se lee correctamente
- [ ] Los colores son consistentes con la marca
- [ ] La información de contacto es correcta
- [ ] El número de factura se genera correctamente
- [ ] Los enlaces funcionan (si los hay)

## 🐛 Solución de Problemas

### Error: "No hay planes activos"
```bash
# Ejecuta este comando para activar planes:
php activate-plans.php
```

### Error: "Acceso denegado"
```
Debes estar logueado como administrador en WordPress
```

### Los colores no se ven bien
```
Verifica que las variables de color estén definidas correctamente
en cada archivo generador
```

### La factura no se descarga
```
Verifica que JavaScript esté habilitado en tu navegador
```

## 📞 Soporte

Si tienes problemas:

1. Verifica que WordPress esté funcionando
2. Asegúrate de tener al menos un plan activo
3. Revisa la consola del navegador (F12) para errores JavaScript
4. Verifica los logs de PHP en WAMP

## 🚀 Próximos Pasos

Una vez verificado todo en el preview:

1. **Prueba con Email Real:**
   - Crea un contacto de prueba con tu email
   - Márcalo como contratado
   - Verifica que el correo llega a bandeja de entrada

2. **Configuración DNS (Producción):**
   - Configura SPF record
   - Configura DKIM
   - Configura DMARC
   - Esto mejorará la deliverability

3. **Monitoreo:**
   - Revisa logs de correos enviados
   - Verifica tasas de entrega
   - Pide feedback a los primeros clientes

## ✨ Características Anti-Spam Aplicadas

✅ Asunto personalizado con nombre del cliente
✅ Headers profesionales (X-Priority, X-Mailer, etc.)
✅ Versión texto plano alternativa (multipart/alternative)
✅ Diseño simple tipo transaccional
✅ Sin emojis excesivos en el asunto
✅ Ratio texto/HTML balanceado
✅ From address verificado
✅ List-Unsubscribe header
✅ Precedence: bulk
✅ X-Auto-Response-Suppress

---

**Desarrollado para AutomatizaTech** 🚀
*Sistema de Facturación Automática v1.0*
