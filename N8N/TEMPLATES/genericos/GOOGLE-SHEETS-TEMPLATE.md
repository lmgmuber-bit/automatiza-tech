# 📊 Plantilla Google Sheets para WhatsApp Bot Demo v4

## Características del Template v4:
- ✅ **Servicios con duración parametrizable** - Calcula slots según duración del servicio
- ✅ **Bloqueos de horarios** - Únicos, diarios o anuales (ej: almuerzo, feriados)
- ✅ **Buffer de concatenación de mensajes** - Agrupa mensajes rápidos (ventana 5 seg)
- ✅ **Notas de voz** - Recibe audio → transcribe con Whisper → responde con voz TTS
- ✅ **Excepción agendamiento** - Respuestas con acciones (agendar, cancelar) van como texto/botones

## Link de plantilla (duplicar):
> Crea tu propio Sheet con esta estructura

---

## 📋 HOJA 1: Citas

Crear hoja con nombre exacto: `Citas`

### Columnas (fila 1 - headers):

```
| A        | B       | C         | D     | E      | F    | G        | H        | I       | J          |
|----------|---------|-----------|-------|--------|------|----------|----------|---------|------------|
| id       | nombre  | telefono  | email | fecha  | hora | hora_fin | servicio  | estado  | created_at |
```

### Ejemplo de datos:

```
| id          | nombre      | telefono     | email           | fecha      | hora  | hora_fin | servicio          | estado     | created_at               |
|-------------|-------------|--------------|-----------------|-----------:|-------|----------|------------------|------------|--------------------------|
| abc123xyz   | Juan Pérez  | 56912345678  | juan@email.com  | 2025-01-20 | 10:00 | 11:00    | Diagnóstico       | confirmado | 2025-01-15T14:30:00.000Z |
| def456uvw   | María López | 56987654321  | maria@email.com | 2025-01-21 | 15:00 | 17:00    | Instalación       | cancelado  | 2025-01-16T09:15:00.000Z |
```

### Estados válidos:
- `confirmado` - Cita activa
- `cancelado` - Cita cancelada por usuario
- `completado` - Cita realizada
- `no_show` - Cliente no asistió

---

## ⚙️ HOJA 2: Configuracion

Crear hoja con nombre exacto: `Configuracion`

### Columnas (fila 1 - headers):

```
| A           | B                |
|-------------|------------------|
| parametro   | valor            |
```

### Datos requeridos:

```
| parametro         | valor                                      |
|-------------------|--------------------------------------------|
| horario_inicio    | 09:00                                      |
| horario_fin       | 18:00                                      |
| dias_habiles      | lunes,martes,miercoles,jueves,viernes      |
| intervalo_slots   | 30                                         |
| buffer_entre_citas| 0                                          |
| negocio_nombre    | Mi Negocio Demo                            |
| negocio_telefono  | +56 9 1234 5678                            |
| negocio_email     | contacto@minegocio.cl                      |
| negocio_web       | www.minegocio.cl                           |
| negocio_direccion | Av. Principal 123, Santiago                |
| negocio_instagram | @minegocio                                 |
| negocio_facebook  | facebook.com/minegocio                     |
| negocio_tiktok    | @minegocio                                 |
| zona_horaria      | America/Santiago                           |
| moneda_codigo     | CLP                                        |
| moneda_simbolo    | $                                          |
| moneda_nombre     | Pesos Chilenos                             |
```

---

## 🛍️ HOJA 3: Servicios

Crear hoja con nombre exacto: `Servicios`

### Columnas (fila 1 - headers):

```
| A  | B      | C           | D      | E           |
|----|--------|-------------|--------|-------------|
| id | nombre | duracion_min| precio | descripcion |
```

### Ejemplo de datos:

```
| id | nombre        | duracion_min | precio  | descripcion                 |
|----|---------------|--------------|---------|-----------------------------|
| 1  | Diagnóstico   | 60           | 19990   | Revisión y diagnóstico      |
| 2  | Instalación   | 120          | 49990   | Instalación completa        |
```

---

## 🚫 HOJA 4: Bloqueos


Crear hoja con nombre exacto: `Bloqueos`

### Columnas (fila 1 - headers):

```
| A      | B          | C        | D       | E          |
|--------|------------|----------|---------|------------|
| fecha  | hora_inicio| hora_fin | motivo  | recurrente |
```

### Valores de `recurrente` soportados por el template:
- `no` (bloqueo puntual por fecha exacta)
- `diario` (si `fecha` es `*` aplica todos los días)
- `anual` (repite cada año en mismo MM-DD)

### Ejemplo de datos:

```
| fecha      | hora_inicio | hora_fin | motivo          | recurrente |
|------------|-------------|----------|-----------------|------------|
| 2025-01-18 | 00:00       | 23:59    | Feriado         | no         |
| *          | 13:00       | 14:00    | Almuerzo        | diario     |
| 2025-12-25 | 00:00       | 23:59    | Navidad         | anual      |
```

---

## 🔗 Cómo obtener el ID del Spreadsheet

1. Abre tu Google Sheet
2. Mira la URL:
   ```
   https://docs.google.com/spreadsheets/d/[AQUI-ESTA-EL-ID]/edit#gid=0
   ```
3. Copia solo la parte entre `/d/` y `/edit`

**Ejemplo:**
- URL: `https://docs.google.com/spreadsheets/d/1aBcD2eFgH3iJkL4mNoP5qRsT6uVwX7yZ/edit#gid=0`
- ID: `1aBcD2eFgH3iJkL4mNoP5qRsT6uVwX7yZ`

---

## 🔐 Permisos necesarios

Para que n8n pueda acceder al Sheet:

### Opción A: OAuth2 (recomendado para demos)
1. Usar credencial Google Sheets OAuth2 en n8n
2. Autorizar la cuenta de Google

### Opción B: Service Account (para producción)
1. Crear Service Account en Google Cloud
2. Descargar JSON de credenciales
3. Compartir el Sheet con el email del Service Account
4. Usar credencial Service Account en n8n

---

## 📊 Fórmulas útiles para el Sheet

### Contar citas del día:
```
=COUNTIFS(Citas!E:E, TODAY(), Citas!G:G, "confirmado")
```

### Listar citas pendientes de hoy:
```
=FILTER(Citas!A:H, Citas!E:E=TODAY(), Citas!G:G="confirmado")
```

### Calcular tasa de cancelación:
```
=COUNTIF(Citas!G:G, "cancelado") / COUNTA(Citas!G:G) * 100 & "%"
```

---

## 🎨 Template Visual (crear manualmente)

Para una mejor experiencia del cliente, puedes:

1. **Agregar colores a los headers** (fila 1)
   - Fondo azul (#4285F4)
   - Texto blanco y bold

2. **Condicional formatting en Estado**
   - Verde para "confirmado"
   - Rojo para "cancelado"
   - Gris para "completado"
   - Naranja para "no_show"

3. **Proteger la hoja Configuracion**
   - Datos → Proteger hojas y rangos
   - Solo propietario puede editar

4. **Crear Dashboard** (hoja adicional)
   - Resumen de citas del mes
   - Gráfico de citas por día
   - Métricas de cancelación

---

##  Notas de Voz - Configuración

El template v4 soporta notas de voz bidireccionales:

### Flujo de recepción de audio:
```
Usuario envía audio  Get Audio URL  Download Audio  Whisper (transcribe)  texto normal
```

### Flujo de respuesta con audio:
```
IA genera texto  TTS (OpenAI)  Fix MimeType  Upload a WhatsApp  Send Audio
```

### Excepción importante:
Si el usuario envía audio pero la IA decide mostrar servicios, calendario o botones, 
la respuesta va como **texto/botones** en lugar de audio (WhatsApp no permite botones en audio).

### Credenciales necesarias:

| Credencial | Uso |
|------------|-----|
| WhatsApp API (HTTP Header Auth) | Enviar mensajes, subir media |
| OpenAI | GPT-4o-mini (chat), Whisper (transcripción), TTS (voz) |
| Google Sheets OAuth2 | Leer/escribir citas y configuración |

### Configurar Token WhatsApp:
1. En n8n, crea credencial "HTTP Header Auth"
2. Name: `Authorization`
3. Value: `Bearer TU_TOKEN_DE_WHATSAPP_BUSINESS`

---

##  Buffer de Mensajes

El template agrupa mensajes rápidos del mismo usuario en una ventana de 5 segundos:

```
Usuario: "Hola"
Usuario: "quiero"  
Usuario: "agendar una cita"
 Bot recibe: "Hola quiero agendar una cita" (como un solo mensaje)
```

### Comportamiento:
- Primer mensaje inicia el buffer
- Mensajes dentro de 5 seg se acumulan
- Después de 5 seg se procesa todo junto
- Si incluye audio, se mantiene el flag `isFromAudio`

### Ajustar el tiempo:
Edita el nodo "Wait 5 Seconds" y "Buffer Manager" si necesitas otra ventana.

---

##  Notas de Voz - Configuración

El template v4 soporta notas de voz bidireccionales:

### Flujo de recepción de audio:
```
Usuario envía audio  Get Audio URL  Download Audio  Whisper (transcribe)  texto normal
```

### Flujo de respuesta con audio:
```
IA genera texto  TTS (OpenAI)  Fix MimeType  Upload a WhatsApp  Send Audio
```

### Excepción importante:
Si el usuario envía audio pero la IA decide mostrar servicios, calendario o botones, 
la respuesta va como **texto/botones** en lugar de audio (WhatsApp no permite botones en audio).

### Credenciales necesarias:

| Credencial | Uso |
|------------|-----|
| WhatsApp API (HTTP Header Auth) | Enviar mensajes, subir media |
| OpenAI | GPT-4o-mini (chat), Whisper (transcripción), TTS (voz) |
| Google Sheets OAuth2 | Leer/escribir citas y configuración |

### Configurar Token WhatsApp:
1. En n8n, crea credencial "HTTP Header Auth"
2. Name: `Authorization`
3. Value: `Bearer TU_TOKEN_DE_WHATSAPP_BUSINESS`

---

##  Buffer de Mensajes

El template agrupa mensajes rápidos del mismo usuario en una ventana de 5 segundos:

```
Usuario: "Hola"
Usuario: "quiero"  
Usuario: "agendar una cita"
 Bot recibe: "Hola quiero agendar una cita" (como un solo mensaje)
```

### Comportamiento:
- Primer mensaje inicia el buffer
- Mensajes dentro de 5 seg se acumulan
- Después de 5 seg se procesa todo junto
- Si incluye audio, se mantiene el flag `isFromAudio`

### Ajustar el tiempo:
Edita el nodo "Wait 5 Seconds" y "Buffer Manager" si necesitas otra ventana.
