# Manual del Portal OmniCliente — AutomatizaTech

> **Versión:** 1.0  
> **Última actualización:** 2025-07-08  
> **Plataforma:** Portal OmniCliente de AutomatizaTech (automatizatech.cl)

---

## 1. ¿Qué es OmniCliente?

OmniCliente es un portal web de gestión omnicanal desarrollado por AutomatizaTech. Permite a las empresas:

- Centralizar las conversaciones de WhatsApp, Telegram y otros canales en una sola bandeja.
- Gestionar agentes, equipos y supervisores.
- Configurar bots automatizados por canal.
- Monitorear métricas y auditoría de la operación.
- Crear y dar seguimiento a tickets de soporte.
- Consultar información operativa con el Asistente IA (Omni).

---

## 2. Roles y Permisos

### 2.1. Cliente (Company Admin)
El dueño de la empresa que contrata el servicio. Accede al portal con su API Key.

**Vistas disponibles:**
- **Bandeja de Entrada (Inbox)** — Conversaciones de todos los canales
- **Canales** — Configuración de WhatsApp, Telegram, etc.
- **Tipos de Canal** — Gestión de tipos de canal personalizados
- **Configuración de Bots** — Creación y edición de bots por canal (3 pestañas: flujos, respuestas rápidas, configuración general)
- **Agentes** — Gestión del equipo (crear, editar, activar/desactivar agentes)
- **Auditoría** — Registro de eventos y acciones en la plataforma
- **Soporte** — Crear y consultar tickets de soporte técnico

### 2.2. Agente
Miembro del equipo que atiende las conversaciones.

**Vistas disponibles:**
- **Bandeja de Entrada (Inbox)** — Conversaciones asignadas y en espera
- **Agentes** — Ver listado y perfil de compañeros
- **Mi Perfil** — Editar datos personales, foto, contraseñas
- **Soporte** — Crear y consultar tickets de soporte

### 2.3. Supervisor (Agente con permisos de supervisor)
Agente con permisos adicionales de gestión.

**Vistas adicionales (además de las del Agente):**
- **Configuración de Bots** — Puede crear y editar la configuración de bots (flujos, respuestas rápidas, config general)
- **Prompts** — Solo lectura. Puede ver los prompts configurados pero NO puede editarlos (solo el Admin puede editar prompts)
- **Auditoría** — Ver registro de eventos del equipo

### 2.4. Super Admin
Administrador de la plataforma AutomatizaTech. Gestiona todas las empresas.

**Vistas disponibles (todas las del Cliente más):**
- **Clientes** — CRUD de empresas: crear, editar, activar/desactivar, gestionar planes y períodos
- **Dashboard** — Métricas globales de todos los clientes
- **Prompt del Asistente IA** — Editar el prompt/instrucciones del asistente IA
- **Soporte** — Vista master-detail de todos los tickets con gestión de estados

---

## 3. Módulos Detallados

### 3.1. Bandeja de Entrada (Inbox)

**Propósito:** Centralizar todas las conversaciones de múltiples canales en un solo lugar.

**Funcionalidades:**
- Ver lista de conversaciones ordenadas por última actividad
- Filtrar por canal (WhatsApp, Telegram, etc.), estado (abierta, en agente, bot, resuelta, cerrada)
- Buscar contactos por nombre o número de teléfono
- Abrir una conversación para ver el historial de mensajes
- Enviar mensajes de texto e imágenes
- Transferir conversación a otro agente
- Resolver o cerrar una conversación
- Ver información del contacto (nombre, teléfono, canal)
- Indicador de mensajes no leídos

**Cómo usar:**
1. Al entrar al portal, la Bandeja de Entrada se muestra por defecto.
2. En la columna izquierda aparecen las conversaciones. Haz clic en una para abrirla.
3. Escribe tu mensaje en el campo de texto inferior y presiona Enter o el botón de enviar.
4. Para transferir una conversación, usa el menú de opciones en la cabecera de la conversación.
5. Para resolver/cerrar, usa los botones de acción en la conversación.

---

### 3.2. Canales

**Propósito:** Conectar y gestionar canales de comunicación (WhatsApp, Telegram, etc.)

**Funcionalidades:**
- Ver lista de canales configurados (nombre, tipo, estado activo/inactivo)
- Agregar nuevo canal con configuración de webhook, token y credenciales
- Editar configuración de un canal existente
- Activar o desactivar canales
- Copiar webhook URL para configurar en el proveedor del canal

**Cómo usar:**
1. Ve a la sección "Canales" desde el menú lateral.
2. Haz clic en "Nuevo Canal" para agregar uno.
3. Selecciona el tipo de canal, completa nombre y credenciales.
4. Copia la URL del webhook y configúrala en tu proveedor (ej. WhatsApp Business API, Telegram BotFather).
5. Activa el canal para empezar a recibir mensajes.

---

### 3.3. Tipos de Canal

**Propósito:** Definir y personalizar los tipos de canal disponibles en la plataforma.

**Funcionalidades:**
- Ver lista de tipos de canal existentes
- Crear nuevos tipos de canal personalizados
- Editar nombre, ícono y configuración de un tipo de canal
- Eliminar tipos de canal no utilizados

---

### 3.4. Configuración de Bots

**Propósito:** Crear y gestionar bots automatizados que responden en cada canal.

**Pestañas:**
1. **Flujos del Bot** — Configurar el árbol de decisiones y respuestas automáticas
2. **Respuestas Rápidas** — Definir atajos de respuestas predefinidas para los agentes
3. **Configuración General** — Parámetros del bot (nombre, tiempo de espera, mensajes de bienvenida, horarios)

**Permisos por rol:**
- **Cliente / Admin:** Puede editar toda la configuración de bots y prompts.
- **Supervisor:** Puede editar la configuración de bots, pero los Prompts son de **solo lectura** (puede verlos pero no modificarlos).
- **Agente:** No tiene acceso a esta sección.

**Cómo usar:**
1. Ve a "Config. Bots" en el menú lateral.
2. Selecciona el canal para el cual quieres configurar el bot.
3. En la pestaña "Flujos", diseña el árbol de respuestas automáticas.
4. En "Respuestas Rápidas", crea atajos que los agentes puedan usar durante la conversación.
5. En "Config. General", ajusta el comportamiento: nombre del bot, mensaje de bienvenida, horarios de atención.

---

### 3.5. Agentes

**Propósito:** Gestionar el equipo de atención al cliente.

**Funcionalidades:**
- Ver listado de agentes con su estado (activo/inactivo), rol y asignaciones
- Crear nuevo agente (nombre, email, contraseña, rol: agente o supervisor)
- Editar datos de un agente existente
- Activar o desactivar agentes
- Asignar agentes a canales específicos

**Cómo usar:**
1. Ve a "Agentes" en el menú lateral.
2. Haz clic en "Nuevo Agente" para crear uno.
3. Completa los datos: nombre, email, contraseña y rol.
4. El agente recibirá sus credenciales y podrá iniciar sesión.
5. Para desactivar un agente, usa el toggle de estado en su perfil.

---

### 3.6. Auditoría

**Propósito:** Registro histórico de todas las acciones realizadas en la plataforma.

**Funcionalidades:**
- Ver lista de eventos con fecha, hora, usuario y acción
- Filtrar por tipo de evento, fecha o usuario
- Exportar registros

**Eventos registrados:**
- Inicio/cierre de sesión de agentes
- Transferencias de conversaciones
- Cambios de estado de conversaciones
- Creación/edición de canales
- Creación/edición de agentes
- Cambios de configuración de bots

---

### 3.7. Mi Perfil (Agentes)

**Propósito:** Gestionar datos personales del agente.

**Funcionalidades:**
- Ver y editar nombre, email, foto de perfil
- Cambiar contraseña
- Ver estadísticas personales (conversaciones atendidas, mensajes enviados)
- Configurar preferencias de notificación

---

### 3.8. Soporte (Tickets)

**Propósito:** Sistema de tickets para reportar problemas o solicitar ayuda técnica.

**Funcionalidades:**
- Ver lista de tickets propios (agente) o todos (admin) con filtros y búsqueda
- Crear nuevo ticket con: asunto, descripción, categoría (General, Técnico, Facturación, Solicitud de Función, Error/Bug), prioridad (Baja, Media, Alta, Urgente)
- Adjuntar imágenes al ticket (hasta 5 por ticket)
- Ver detalle del ticket con historial de mensajes
- Responder/agregar mensajes a un ticket abierto con imágenes opcionales
- **Admin:** Cambiar estado del ticket (Abierto → En Progreso → Resuelto → Cerrado)

**Estados de un ticket:**
- **Abierto** — Recién creado, pendiente de revisión
- **En Progreso** — El equipo técnico está trabajando en él
- **Resuelto** — Se encontró y aplicó una solución
- **Cerrado** — Caso finalizado

**Cómo usar:**
1. Ve a "Soporte" en el menú lateral.
2. Haz clic en "Nuevo Ticket" para crear uno.
3. **Importante:** Antes de crear un ticket, pregúntale al Asistente Omni. Omni puede resolver la mayoría de dudas sobre el portal.
4. Si Omni no puede resolver tu caso (es un error real del portal), entonces crea el ticket.
5. Completa asunto, descripción detallada, categoría y prioridad.
6. Opcionalmente adjunta capturas de pantalla del problema.
7. Envía y espera la respuesta del equipo técnico.

---

### 3.9. Clientes (Solo Admin)

**Propósito:** Gestionar las empresas/clientes de la plataforma.

**Funcionalidades:**
- Ver listado de clientes con su plan, estado y período
- Crear nuevo cliente (empresa, contacto, plan, período, límites de canales y agentes)
- Editar datos de un cliente existente
- Activar/desactivar clientes
- Ver estadísticas por cliente (conversaciones, agentes, canales)

---

### 3.10. Dashboard (Solo Admin)

**Propósito:** Vista de métricas globales de toda la plataforma.

**Funcionalidades:**
- Total de clientes activos/inactivos
- Total de conversaciones, canales y agentes
- Métricas de mensajes por período
- Gráficos de tendencias
- Top clientes por actividad

---

### 3.11. Prompt del Asistente IA (Solo Admin)

**Propósito:** Editar las instrucciones (prompt) del Asistente IA Omni.

**Funcionalidades:**
- Ver y editar el prompt del sistema que usa el asistente IA
- Variables disponibles: `{user_name}`, `{user_role}`, `{company_name}`, `{client_id}`, `{plan_type}`
- Restaurar al prompt por defecto
- Vista previa del prompt con variables reemplazadas

**Cómo usar:**
1. Ve a "Asistente IA" en el menú lateral (solo visible para admins).
2. Edita las instrucciones en el editor de texto.
3. Usa las variables entre llaves para personalizar las respuestas por usuario.
4. Guarda los cambios. Se aplicarán inmediatamente a todas las conversaciones nuevas.

---

## 4. Omni Asistente (IA)

**Propósito:** Asistente inteligente integrado en el portal que puede responder preguntas sobre la operación y el propio portal.

**Cómo acceder:**
- Haz clic en el botón flotante redondo morado/rosa en la esquina inferior derecha de cualquier vista del portal.

**¿Qué puede hacer Omni?**
- Responder preguntas sobre la empresa (conversaciones, agentes, canales, tickets)
- Mostrar métricas y estadísticas en tiempo real
- Explicar cómo usar cada módulo del portal
- Resolver dudas sobre funcionalidades
- Identificar patrones y sugerir mejoras
- Si Omni detecta que tu problema es un error real del portal, te recomendará crear un ticket de soporte

**Funcionalidades del chat:**
- Historial de conversaciones guardadas (hasta 30)
- Buscar en el historial por contenido
- Crear nueva conversación
- Eliminar conversaciones anteriores
- Sugerencias rápidas al iniciar un nuevo chat

**Cómo usar:**
1. Haz clic en el botón flotante (robot animado) en la esquina inferior derecha.
2. Escribe tu pregunta en el campo de texto.
3. Omni responderá con información relevante de tu empresa.
4. Si necesitas ayuda sobre el portal, pregunta directamente. Ejemplo: "¿Cómo creo un nuevo agente?" o "¿Cómo configuro un bot?"
5. Si Omni no puede resolver tu problema, te sugerirá crear un ticket de soporte.

---

## 5. Preguntas Frecuentes (FAQ)

### ¿Cómo inicio sesión?
- **Clientes:** Introduce tu API Key proporcionada al contratar el servicio.
- **Agentes/Supervisores:** Usa tu email y contraseña asignados por tu administrador.
- **Admin:** Usa tus credenciales de administrador.

### ¿Cómo conecto WhatsApp?
1. Ve a "Canales" → "Nuevo Canal".
2. Selecciona tipo "WhatsApp".
3. Ingresa las credenciales de tu WhatsApp Business API.
4. Copia el webhook URL y configúralo en tu proveedor.
5. Activa el canal.

### ¿Cómo transfiero una conversación a otro agente?
1. Abre la conversación en la Bandeja de Entrada.
2. Haz clic en el menú de opciones (tres puntos).
3. Selecciona "Transferir" y elige el agente destino.
4. Opcionalmente añade una nota para el agente receptor.

### ¿Cómo creo un bot?
1. Ve a "Config. Bots".
2. Selecciona el canal.
3. Diseña el flujo de respuestas automáticas.
4. Configura mensajes de bienvenida y horarios.
5. Activa el bot.

### ¿Qué hago si el portal no funciona correctamente?
1. **Primero pregunta a Omni** — Haz clic en el asistente IA y describe tu problema.
2. Si Omni confirma que es un error del portal o no puede ayudarte, crea un ticket de soporte.
3. Incluye capturas de pantalla y una descripción detallada del problema.

### ¿Qué planes están disponibles?
- **Starter** — Funcionalidades básicas (sin Asistente IA)
- **Professional** — Incluye Asistente IA y funcionalidades avanzadas
- **Enterprise** — Todo incluido, con soporte prioritario

---

## 6. Navegación del Portal

### Menú Lateral (Sidebar)
El menú lateral izquierdo muestra las secciones disponibles según tu rol:
- Cada sección tiene un ícono y nombre descriptivo.
- El número en rojo indica las notificaciones pendientes (ej. tickets abiertos, mensajes no leídos).
- En pantallas pequeñas (móvil), el menú se oculta y se abre con el botón de hamburguesa.

### Modo Oscuro
- Haz clic en el ícono de sol/luna en la parte inferior del menú lateral para alternar entre modo claro y oscuro.
- La preferencia se guarda automáticamente.

### Notificaciones
- **Tickets abiertos:** El ícono de soporte muestra el número de tickets pendientes.
- **Mensajes no leídos:** El título de la página muestra el conteo entre paréntesis.
- **Conversaciones asignadas:** Los agentes reciben notificaciones cuando se les asigna una nueva conversación.

---

## 7. Solución de Problemas Comunes

| Problema | Solución |
|---|---|
| No puedo iniciar sesión | Verifica tu API Key o credenciales. Si olvidaste tu contraseña, contacta a tu administrador. |
| No veo mensajes nuevos | Actualiza la página (F5). Verifica que el canal esté activo. |
| El bot no responde | Revisa la configuración del bot en "Config. Bots". Verifica que esté activo y el flujo esté configurado. |
| No puedo crear un canal | Verifica que no hayas alcanzado el límite de canales de tu plan. |
| No puedo agregar más agentes | Verifica el límite de agentes de tu plan actual. |
| El Asistente IA no está disponible | Omni está disponible solo para planes Professional y Enterprise. |
| No puedo adjuntar imágenes | Las imágenes deben ser JPEG, PNG, WebP o GIF. Máximo 5 por ticket/mensaje. |

---

> **Nota:** Este manual se actualiza cada vez que se realizan cambios en el portal. Si algo no coincide con lo que ves, pregunta a Omni para obtener la información más reciente.
