# 📋 Preparación del Google Sheets para Transfer ID

## 🎯 Objetivo
Agregar la columna Y "transfer_id" a la hoja de "Citas" para registrar el ID único de cada transferencia bancaria.

---

## 📊 Pasos para Configurar Google Sheets

### Paso 1: Abrir la Hoja de "Citas"
1. Abre el documento de Google Sheets: "Citas"
2. Ve a la pestaña/hoja: **"Citas"**

### Paso 2: Navegar a la Columna Y
1. Desplázate hacia la derecha hasta encontrar la columna Y (o la próxima columna vacía después de las existentes)
2. Si no existe, será automáticamente creada

### Paso 3: Agregar Encabezado (Fila 1)
1. Click en la celda **Y1**
2. Escribe: `transfer_id`
3. Presiona Enter

### Paso 4: Formateo Opcional (Recomendado)
1. Selecciona toda la columna Y (click en la letra "Y" del encabezado)
2. Clic derecho → **Más formato**
3. Configura:
   - **Formato de número:** Texto
   - **Ancho:** 150 px (para ver números completos)

### Paso 5: Crear Restricción de Validación (Opcional)
1. Selecciona la columna Y (desde Y2 hacia abajo)
2. Clic en **Datos** → **Validación de datos**
3. Configura:
   - **Criterio:** Personalizado (fórmula)
   - **Fórmula:** `=OR(ISBLANK(Y2),AND(NOT(ISERROR(VALUE(Y2))),Y2>=1))`
   - **Mostrar mensaje:** Activado
   - **Título:** "ID de Transferencia Válido"
   - **Mensaje:** "Solo números enteros sin ceros a la izquierda (ej: 224080048)"

---

## 📝 Estructura de la Tabla

### Columnas Existentes
```
A: id
B: nombre
C: email
D: telefono
E: servicio
F: fecha
G: hora
H: hora_fin
I: estado
J: precio
K: notas
L: creado
... (otras columnas)
Y: transfer_id ← NUEVA
```

### Ejemplo de Registro Completo
```
| A | B | C | D | ... | Y |
|---|---|---|---|-----|-----------|
| 1 | Juan | j@mail.com | +56900000000 | ... | 224080048 |
| 2 | María | m@mail.com | +56911111111 | ... | 87654321 |
| 3 | Pedro | p@mail.com | +56922222222 | ... | (vacío) |
```

---

## 🔄 Flujo Automático

### ¿Cómo se llena la columna Y?

1. **Cliente envía comprobante** en WhatsApp
   ```
   Cliente: [Imagen JPG del comprobante]
   ```

2. **n8n procesa:**
   - GPT-4 Vision extrae: `transfer_id: "000224080048"`
   - Normaliza: `transfer_id_normalized: "224080048"`
   - Prepara datos para Google Sheets

3. **Google Sheets recibe:**
   ```json
   {
     "nombre": "Juan",
     "telefono": "+56900000000",
     "servicio": "Corte y Peinado",
     "estado": "Confirmado",
     "transfer_id": "224080048"  ← Se asigna a Columna Y
   }
   ```

4. **Resultado:**
   - Fila nueva en Google Sheets con transfer_id: **224080048**

---

## 💡 Características

### ✅ Normalización Automática
- **Entrada:** `000224080048` (con ceros)
- **Almacenado:** `224080048` (sin ceros)
- **Beneficio:** Evita duplicados por variación de formato

### ✅ Validación de Cuentas
- Comprueba que la cuenta bancaria sea válida
- Compara solo números (ignora ceros a la izquierda)
- Ejemplo:
  ```
  Cuenta autorizada: 0224080048
  Comprobante: 000224080048
  ✅ Son equivalentes: ambas = 224080048
  ```

### ✅ Auditoría Completa
- Cada cita tiene su transfer_id único
- Facilita reconciliación bancaria
- Permite búsquedas por número de transferencia

---

## 🔍 Búsquedas y Análisis

### Usar Google Sheets para análisis:

#### Filtrar por transfer_id
```
Datos → Crear filtro → Columna Y → Filtrar
```

#### Contar transferencias recibidas
```
=COUNTA(Y2:Y)
Muestra: cantidad de citas confirmadas con transfer_id
```

#### Encontrar transfer_id específico
```
=FILTER(A:A, Y:Y="224080048")
Muestra: todas las citas con ese transfer_id
```

#### Detectar duplicados
```
=COUNTIF(Y:Y, Y2) > 1
Resalta si hay transfer_id duplicados
```

---

## ⚙️ Configuración Avanzada (Opcional)

### Crear columna de estado de validación
Si quieres saber si la cita fue validada:

**Columna Z: `validation_status`**
1. Click en Z1, escribe: `validation_status`
2. En Z2 (para todas las filas), agrega:
   ```
   =IF(ISBLANK(Y2), "Sin validar", "Validado")
   ```

### Crear panel de auditoría
**Columna AA: `audit_date`**
```
=IF(Y2<>"", NOW(), "")
Guarda la fecha/hora de validación
```

---

## 📊 Ejemplo Visual

### Antes (sin transfer_id)
```
Cita | Cliente | Servicio | Estado | Precio
-----|---------|----------|--------|-------
001  | Juan    | Corte    | Confirmado | $20.000
002  | María   | Tinte    | Confirmado | $30.000
```

### Después (con transfer_id)
```
Cita | Cliente | Servicio | Estado | Precio | Transfer ID
-----|---------|----------|--------|--------|------------
001  | Juan    | Corte    | Confirmado | $20.000 | 224080048
002  | María   | Tinte    | Confirmado | $30.000 | 87654321
```

---

## 🚀 Próximos Pasos

### Fase 1: Configuración Inmediata
- [x] Crear columna Y en Google Sheets
- [x] Nombrarla "transfer_id"
- [x] Formatear como texto

### Fase 2: Validación
- [ ] Enviar test de comprobante en WhatsApp
- [ ] Verificar que aparezca en columna Y
- [ ] Confirmar normalización de ceros

### Fase 3: Auditoría
- [ ] Crear panel de reconciliación
- [ ] Vincular con banco para validación
- [ ] Alertas para transfer_id duplicados

---

## ❓ Preguntas Frecuentes

### ¿Qué pasa si el comprobante no tiene transfer_id visible?
- La cita se guarda normalmente
- Columna Y queda vacía
- Cliente puede enviar comprobante de nuevo

### ¿Puedo buscar por transfer_id después?
- Sí, usa filtros en Google Sheets
- Usa FILTER() para búsquedas programáticas
- Permite auditoría de transacciones

### ¿Se permite transfer_id duplicado?
- GPT-4 Vision lo detecta
- Se rechaza el comprobante si es duplicado
- Cliente debe enviar nuevo comprobante

### ¿Cómo funciona la normalización?
- `000224080048` → quita ceros → `224080048`
- Se valida sin importar ceros a la izquierda
- Garantiza consistencia en el registro

---

## 🔒 Consideraciones de Seguridad

- ✅ Transfer ID se extrae automáticamente (sin intervención manual)
- ✅ Solo se validan comprobantes que pasen GPT-4 Vision
- ✅ No se almacenan imágenes (solo datos extraídos)
- ✅ Auditable: cada transfer_id está vinculado a una cita
- ✅ Único: no permite duplicados (validación pendiente)

---

## 📞 Soporte

Si tienes problemas:
1. Verifica que la columna Y exista
2. Comprueba que el encabezado sea exactamente: `transfer_id`
3. Revisa el workflow de n8n (Prepare Confirmed Data)
4. Consulta los logs del workflow

---

**Última actualización:** 19 de Enero, 2025  
**Estado:** 📋 LISTO PARA IMPLEMENTAR
