# Archivos a subir a Producción - Campo RUT/DNI/Pasaporte con Validación

## Fecha: 2025-01-15 (Actualizado)

## 📝 Resumen de Cambios

Se agregó el campo obligatorio **RUT/DNI/Pasaporte** al formulario de contacto y sistema de facturación con **validación automática de RUT chileno**.

### Cambios Implementados:

1. **Formulario de Contacto**
   - Campo obligatorio "RUT/DNI/Pasaporte"
   - Label dinámico según país seleccionado:
     - Chile: "RUT"
     - Otros países: "DNI/Cédula/Pasaporte"
   - **VALIDACIÓN AUTOMÁTICA DE RUT CHILENO:**
     - Cálculo automático del dígito verificador
     - Formateo automático con puntos y guión (ej: 12.345.678-9)
     - Validación en tiempo real
     - Mensaje de validación visual (verde=válido, rojo=inválido)
   - Validación de formato para otros países (5-50 caracteres, alfanumérico con puntos y guiones)

2. **Validación Backend (PHP)**
   - Validación de RUT chileno en el servidor
   - Sanitización de entrada
   - Protección contra inyección SQL/XSS

3. **Base de Datos**
   - Nueva columna `tax_id` en tabla `automatiza_tech_contacts`
   - Nueva columna `tax_id` en tabla `automatiza_tech_clients`

4. **Sistema de Facturas**
   - Campo RUT/DNI ahora aparece en la factura PDF
   - Se muestra como primera línea en "DATOS DEL CLIENTE"
   - Label dinámico: "RUT:" para Chile, "RUT/DNI/Pasaporte:" para otros países

5. **URL del QR**
   - ✅ URL ya está correcta: `https://automatizatech.shop/validar-factura.php?id=AT-XXXXX`
   - No requiere cambios

---

## 🎯 Funcionalidades del Validador de RUT

### Para usuarios chilenos (+56):
1. **Ingreso sin formato:** Usuario escribe `12345678` (solo números)
2. **Cálculo automático:** Sistema calcula el dígito verificador (ej: `5`)
3. **Formato automático:** Sistema formatea a `12.345.678-5`
4. **Validación visual:** Muestra ✓ o ❌ según sea válido o no

### Ejemplos de uso:
- Usuario escribe: `17615128` → Sistema muestra: `17.615.128-6` ✓
- Usuario escribe: `11111111` → Sistema muestra: `11.111.111-1` ✓
- Usuario escribe: `99999999` → Sistema muestra: `9.999.999-9` ✓
- Usuario escribe: `12345678-5` → Sistema valida y mantiene formato ✓
- Usuario escribe: `12345678-9` → Sistema marca como inválido ❌

---

## 📁 Archivos que DEBEN subirse a Producción

### 1. Script de actualización de base de datos (ejecutar primero):
```
add-tax-id-field.php
```

**IMPORTANTE:** Ejecutar en producción accediendo a:
`https://automatizatech.shop/add-tax-id-field.php`

Este script:
- Agrega columna `tax_id` a tabla de contactos
- Agrega columna `tax_id` a tabla de clientes
- Verifica si ya existen para evitar errores

### 2. Archivos del tema a subir:
```
wp-content/themes/automatiza-tech/inc/contact-form.php
wp-content/themes/automatiza-tech/inc/contact-shortcode.php
wp-content/themes/automatiza-tech/lib/invoice-pdf-fpdf.php
```

---

## 🔧 Instrucciones de Despliegue

### Paso 1: Subir archivos via FTP/SFTP
```
1. Conectar al servidor de Hostinger
2. Subir add-tax-id-field.php a la raíz del sitio
3. Subir los archivos del tema a sus respectivas ubicaciones
```

### Paso 2: Ejecutar actualización de base de datos
```
1. Navegar a: https://automatizatech.shop/add-tax-id-field.php
2. Verificar que aparezcan los mensajes de éxito:
   ✓ Campo tax_id agregado a tabla de contactos
   ✓ Campo tax_id agregado a tabla de clientes
3. Revisar la estructura de tablas mostrada
4. ELIMINAR el archivo add-tax-id-field.php del servidor por seguridad
```

### Paso 3: Probar el formulario
```
1. Ir a la página de contacto
2. Seleccionar Chile (+56) en el código de país
3. En el campo RUT, ingresar solo números: 17615128
4. Ver cómo se formatea automáticamente: 17.615.128-6
5. Intentar con RUT inválido: 12345678-9 (debe marcar error)
6. Cambiar a otro país y verificar que funcione DNI/Pasaporte
7. Enviar formulario y verificar que se guarde correctamente
```

### Paso 4: Verificar facturas
```
1. Convertir un contacto a cliente
2. Generar factura
3. Verificar que aparezca el campo RUT/DNI en la sección "DATOS DEL CLIENTE"
4. Verificar que el QR siga funcionando correctamente
```

---

## ✅ Validaciones Agregadas

### Cliente (JavaScript):
- Campo obligatorio
- **Para Chile (+56):**
  - Algoritmo de validación de RUT chileno (Módulo 11)
  - Cálculo automático del dígito verificador
  - Formateo automático con puntos y guión
  - Validación en tiempo real (visual con colores)
  - Solo acepta RUT válidos de 7-8 dígitos + DV
- **Para otros países:**
  - Longitud: 5-50 caracteres
  - Solo letras, números, puntos y guiones
  - Validación en tiempo real

### Servidor (PHP):
- Sanitización de entrada
- Validación de longitud
- **Validación de RUT chileno en servidor** (doble validación)
- Filtrado de caracteres especiales
- Protección contra inyección SQL/XSS

---

## 🧪 Pruebas

Se incluye archivo de prueba: **test-rut-validation.html**

Para probar localmente:
```
http://localhost/automatiza-tech/test-rut-validation.html
```

Casos de prueba incluidos:
- ✓ 12345678 (autocompletar DV)
- ✓ 17615128 (RUT válido)
- ✓ 11111111-1 (RUT válido)
- ✓ 12.345.678-5 (con formato)
- ❌ 12345678-9 (DV incorrecto)
- ❌ invalid (formato inválido)

---

## 📋 Estructura de Base de Datos Actualizada

### Tabla: `automatiza_tech_contacts`
```sql
tax_id varchar(50) DEFAULT NULL
```

### Tabla: `automatiza_tech_clients`
```sql
tax_id varchar(50) DEFAULT NULL
```

---

## 🎨 Cambios Visuales en Factura

### Antes:
```
DATOS DEL CLIENTE
Nombre: Juan Pérez
Teléfono: +56 964324169
Email: juan@example.com
```

### Después:
```
DATOS DEL CLIENTE
RUT: 12.345.678-9                    (Chile)
RUT/DNI/Pasaporte: 12345678         (Otros países)
Nombre: Juan Pérez
Teléfono: +56 964324169
Email: juan@example.com
```

---

## ⚠️ Notas Importantes

1. **Contactos existentes:** Los contactos creados antes de este cambio tendrán `tax_id = NULL`. Esto es normal y no afecta el funcionamiento.

2. **Factura existentes:** Las facturas generadas previamente seguirán mostrando solo los datos originales. Solo las nuevas facturas mostrarán el campo RUT/DNI.

3. **Compatibilidad:** El sistema es 100% compatible con datos anteriores. No se perderá información.

4. **Seguridad:** Después de ejecutar add-tax-id-field.php, ELIMINAR el archivo del servidor.

---

## 🔍 Verificación Post-Despliegue

- [ ] Campo RUT/DNI visible en formulario de contacto
- [ ] Label cambia según país seleccionado
- [ ] **Autocompletado de DV funciona para Chile**
- [ ] **Formateo automático funciona (puntos y guión)**
- [ ] **Validación visual funciona (verde/rojo)**
- [ ] **RUT inválidos son rechazados**
- [ ] Validación funciona para otros países
- [ ] Datos se guardan en base de datos
- [ ] Campo aparece en factura PDF
- [ ] QR code sigue funcionando
- [ ] No hay errores en consola del navegador
- [ ] No hay errores en logs de PHP
- [ ] Validación backend rechaza RUT inválidos

---

## 📞 Soporte

Si hay problemas durante el despliegue:
1. Revisar logs de error de PHP
2. Verificar permisos de archivos (644 para PHP)
3. Verificar que las tablas se actualizaron correctamente
4. Contactar soporte técnico si persisten errores

---

**Fecha de generación:** 15 de Enero 2025
**Versión:** 1.0
**Estado:** Listo para producción ✅
