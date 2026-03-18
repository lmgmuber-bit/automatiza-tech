# Manual de Usuario — Portal OmniCliente AutomatizaTech

> **Versión:** 1.0  
> **Última actualización:** 17 de Marzo de 2026  
> **Dirigido a:** Agentes, Supervisores y Administradores del Portal OmniCliente

---

## 1. Introducción

El **Portal OmniCliente** es la plataforma centralizada de AutomatizaTech para gestionar todas las conversaciones con clientes a través de múltiples canales (WhatsApp, Email, Webchat y más) desde un único lugar.

---

## 2. Acceso al Portal

### 2.1 Tipos de Usuario

| Tipo | Descripción | Cómo accede |
|------|-------------|-------------|
| **Agente** | Atiende conversaciones de clientes | Tab "Agente" con email + contraseña |
| **Administrador WP** | Gestiona todo el sistema | Tab "Admin" con credenciales WordPress |
| **Cliente** | Accede a recursos de su empresa | Tab "Cliente" con API Key |

### 2.2 Iniciar Sesión

1. Abre el portal en tu navegador
2. Selecciona la pestaña correspondiente a tu tipo de usuario (**Agente**, **Admin** o **Cliente**)
3. Ingresa tus credenciales
4. Haz clic en **Iniciar Sesión**

### 2.3 Olvidé mi Contraseña (Agentes)

1. En la pestaña **Agente**, haz clic en **"¿Olvidaste tu contraseña?"**
2. Ingresa tu email registrado
3. Recibirás un enlace de recuperación por correo

### 2.4 Problemas para Iniciar Sesión

Si no puedes acceder al portal:
1. Haz clic en **"¿Problemas para iniciar sesión?"** (disponible en todas las pestañas)
2. Completa el formulario con tu nombre, email y descripción del problema
3. Puedes adjuntar capturas de pantalla (hasta 5 imágenes)
4. El equipo de soporte recibirá tu solicitud y te contactará

---

## 3. Interfaz General

### 3.1 Barra Lateral (Sidebar)

La barra lateral izquierda contiene la navegación principal. Las opciones visibles dependen de tu rol:

**Agente:**
- 💬 Mis Conversaciones
- 👥 Equipo
- 🛟 Soporte

**Supervisor:**
- 💬 Bandeja Unificada
- 🤖 Configurar Bots
- 👥 Agentes
- 📋 Auditoría
- 🛟 Soporte

**Administrador WP:**
- Todo lo anterior +
- 🏢 Clientes
- 📊 Dashboard
- 🛟 Soporte (con badge de tickets pendientes)

### 3.2 Modo Oscuro/Claro

En la parte inferior de la barra lateral encontrarás el botón para alternar entre **Modo Oscuro** y **Modo Claro**.

### 3.3 Uso en Dispositivo Móvil

El portal se adapta automáticamente a dispositivos móviles:
- La barra lateral se oculta y aparece un botón de hamburguesa (☰) en la esquina superior izquierda
- Las vistas con paneles laterales se convierten en navegación de pantalla completa
- Toca el botón ☰ para abrir el menú lateral

---

## 4. Bandeja de Conversaciones

### 4.1 Vista General

La bandeja muestra todas las conversaciones activas organizadas por canal. En **escritorio** se divide en:
- **Panel izquierdo**: Lista de conversaciones con filtros
- **Panel derecho**: Chat activo con mensajes

En **móvil**, se muestra una pantalla a la vez (lista o chat).

### 4.2 Filtros

- **Buscador**: Busca por nombre de contacto o contenido
- **Estado**: Filtrar por activas, pendientes, cerradas
- **Canal**: Filtrar por tipo de canal (WhatsApp, Email, etc.)

### 4.3 Gestión de Conversaciones

- **Tomar control**: Haz clic en "Tomar" para asignarte una conversación
- **Liberar**: Devuelve la conversación al pool general
- **Transferir**: Envía la conversación a otro agente
- **Enviar mensaje**: Escribe en el campo inferior y presiona Enter o el botón enviar

---

## 5. Canales

### 5.1 Ver Canales

Muestra todos los canales de comunicación configurados con su estado y tipo.

### 5.2 Crear Canal (Admin/Supervisor)

1. Haz clic en **"Nuevo Canal"**
2. Selecciona el tipo de canal
3. Completa los campos requeridos (nombre, credenciales del canal)
4. Haz clic en **Crear**

### 5.3 Editar/Eliminar

- Haz clic en el ícono de edición (lápiz) para modificar
- Haz clic en el ícono de papelera para eliminar (requiere confirmación)

---

## 6. Tipos de Canal

Administra las plantillas de canales disponibles. Cada tipo define qué campos de configuración requiere (API keys, webhooks, tokens, etc.).

### 6.1 Crear Tipo de Canal

1. Haz clic en **"Nuevo Tipo"**
2. Define nombre, ícono y descripción
3. Agrega **campos dinámicos** (key, label, placeholder) que se pedirán al crear un canal de este tipo
4. Guarda

---

## 7. Configuración de Bots

### 7.1 Ver Bots

Muestra las configuraciones de bot por canal con sus parámetros actuales.

### 7.2 Editar Configuración

1. Haz clic en el bot que deseas configurar
2. Modifica los parámetros (prompt del sistema, modelo, temperatura, etc.)
3. Haz clic en **Guardar**

---

## 8. Gestión de Agentes

### 8.1 Lista de Agentes

Muestra todos los agentes registrados con su nombre, email, rol, departamento y estado.

### 8.2 Crear Agente (Admin/Supervisor)

1. Haz clic en **"Nuevo Agente"**
2. Completa: nombre, email, contraseña, rol, departamento, habilidades
3. Haz clic en **Crear**
4. El agente recibirá un email de bienvenida con sus credenciales

### 8.3 Roles de Agente

| Rol | Permisos |
|-----|----------|
| **Agente** | Conversaciones propias, equipo (solo lectura), soporte |
| **Supervisor** | Todo de agente + bandeja completa, bots, auditoría, gestión de agentes |
| **Admin** | Todo de supervisor + acceso total |

---

## 9. Auditoría

### 9.1 Registro de Actividad

Muestra un timeline cronológico de todas las acciones del sistema:
- Creación/edición/eliminación de recursos
- Cambios de estado
- Accesos al sistema
- Acciones de soporte

### 9.2 Filtros y Ordenamiento

- Ordena por fecha (más reciente/más antiguo)
- Filtra por tipo de acción
- Expande cada entrada para ver detalles completos (JSON)

---

## 10. Perfil del Agente

### 10.1 Acceder al Perfil

Haz clic en **"Mi Perfil"** en la tarjeta de agente dentro de la barra lateral.

### 10.2 Editar Información

- Nombre, departamento, habilidades
- **Avatar**: Haz clic en la imagen de perfil para cambiarla (máx. 2MB, formatos: JPG, PNG, WebP, GIF)

### 10.3 Cambiar Contraseña

1. Haz clic en **"Cambiar Contraseña"**
2. Se enviará un código de verificación de 6 dígitos a tu email
3. Ingresa el código
4. Define tu nueva contraseña (mínimo 8 caracteres, debe incluir mayúscula, minúscula, número y símbolo)

---

## 11. Sistema de Soporte

### 11.1 Para Agentes

#### Crear un Ticket

1. Ve a la sección **Soporte**
2. Haz clic en **"Nuevo Ticket"**
3. Completa:
   - **Asunto**: Resumen breve del problema
   - **Descripción**: Detalle completo
   - **Categoría**: General, Técnico, Facturación, Solicitud de Función, Error/Bug
   - **Prioridad**: Baja, Media, Alta, Urgente
   - **Imágenes** (opcional): Hasta 5 capturas de pantalla
4. Haz clic en **Crear Ticket**

#### Ver Tickets

La lista muestra tus tickets con:
- Número de ticket (formato: `TK-YYYYMMDD-XXXX`)
- Estado actual (Abierto, En Progreso, Resuelto, Cerrado)
- Prioridad
- Fecha de creación

#### Responder a un Ticket

1. Haz clic en un ticket para ver el detalle
2. Los mensajes se muestran como chat (tus mensajes a la derecha, respuestas del admin a la izquierda)
3. Puedes adjuntar imágenes en las respuestas (hasta 5)
4. Escribe tu mensaje y envía

### 11.2 Para Administradores

Los administradores ven **todos** los tickets del sistema con un layout de dos paneles:

- **Panel izquierdo**: Lista de tickets con buscador y filtros
- **Panel derecho**: Detalle del ticket seleccionado

#### Cambiar Estado de un Ticket

En el detalle del ticket, usa los botones de estado:
- **Abierto** → **En Progreso** → **Resuelto** → **Cerrado**
- Puedes cambiar a cualquier estado en cualquier momento
- El agente recibirá un email de notificación

#### Buscar y Filtrar

- **Buscador**: Por número de ticket, asunto o contenido
- **Filtro de estado**: Todos, Abierto, En Progreso, Resuelto, Cerrado
- **Registros por página**: 10, 15, 25 o 50

#### Notificaciones

- Badge rojo en la barra lateral con el conteo de tickets abiertos
- Modal de notificación cuando llegan nuevos tickets

---

## 12. Gestión de Clientes (Solo Admin WP)

### 12.1 Lista de Clientes

Muestra todos los clientes registrados con su empresa, plan, estado y período.

### 12.2 Crear Cliente

1. Haz clic en **"Nuevo Cliente"**
2. Completa los datos de la empresa y contacto
3. Se generará una API Key automáticamente

### 12.3 Importar Clientes

Puedes importar desde:
- **Usuarios WordPress**: Usuarios ya registrados en el sitio
- **Prospectos CRM**: Leads del sistema AutomatizaTech

### 12.4 Detalle del Cliente

Haz clic en un cliente para ver:
- Datos de contacto y empresa
- Plan y período de suscripción
- API Key (con botón de copiar)
- Estado de actividad

---

## 13. Dashboard (Solo Admin WP)

### 13.1 Estadísticas

Tarjetas resumen con:
- Total de clientes
- Clientes activos
- Total de canales
- Total de agentes
- Tickets abiertos

### 13.2 Auditoría de Actividad

Tabla con las acciones más recientes del sistema, con búsqueda y paginación.

### 13.3 Desglose

- Distribución por tipo de canal
- Distribución por plan

---

## 14. Notificaciones por Email

El sistema envía emails automáticos en los siguientes eventos:

| Evento | Destinatario | Contenido |
|--------|-------------|-----------|
| Agente creado | Agente | Credenciales de acceso |
| Recuperación de contraseña | Agente | Enlace de reset |
| Ticket creado | Agente/Creador | Confirmación con datos |
| Estado de ticket cambiado | Agente/Creador | Nuevo estado |
| Ticket cerrado | Agente/Creador | Notificación de cierre |
| Respuesta del admin | Agente/Creador | Contenido de la respuesta |
| Vencimiento de período | Cliente | Aviso de expiración |

Todos los emails incluyen el **logo de AutomatizaTech** en la cabecera y los datos de contacto en el pie.

---

## 15. Atajos y Tips

- **Enter** en el campo de mensaje envía el mensaje
- **Doble clic** en una imagen adjunta la abre en vista ampliada (lightbox)
- Los campos de búsqueda en todas las secciones filtran al presionar **Enter**
- El portal recuerda tu sesión — no necesitas iniciar sesión cada vez
- El badge de soporte en la barra lateral se actualiza automáticamente cada minuto
