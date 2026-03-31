# QA-09 — Emails y Notificaciones

**Proyecto:** OmniCliente — Portal Omnicanal  
**Módulo:** Emails del Sistema — 7 Templates con Branding AutomatizaTech  
**Versión:** 1.0  
**Fecha:** 2026-03-29  
**Archivos fuente:** api-omnichannel.php, omnichannel-controller.php  

---

## 1. Branding Común a Todos los Emails

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| EM-001 | Logo en header | Cualquier email del sistema | 1. Verificar header del email | Logo AutomatizaTech (60px alto) centrado en header con gradiente #4F46E5→#7C3AED. | Alta |
| EM-002 | Footer profesional | Cualquier email del sistema | 1. Verificar footer del email | Footer con: "AutomatizaTech — Automatización Inteligente", "soporte@automatizatech.cl · automatizatech.cl". | Alta |
| EM-003 | Diseño responsive | Email en móvil | 1. Ver email en dispositivo móvil o ancho estrecho | Layout 100% ancho, contenido centrado, legible en móvil. | Media |

---

## 2. Email: Código de Verificación (Cambio de Contraseña)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| EM-020 | Envío de código de verificación | Agente en paso 2 de cambio de contraseña | 1. Ingresar nueva contraseña válida 2. Confirmar 3. Clic "Continuar" | Email recibido con código de 6 dígitos, nombre del agente, nota de expiración 5 min. | Alta |
| EM-021 | Código de 6 dígitos | Email recibido | 1. Verificar código en email | Código numérico de 6 dígitos mostrado en fuente grande monoespaciada. | Alta |
| EM-022 | Expiración indicada | Email recibido | 1. Verificar texto | Indica que el código expira en 5 minutos. | Media |
| EM-023 | Asunto del email | Email recibido | 1. Ver asunto en bandeja | Asunto: "Código de verificación — OmniCliente Portal". | Media |

---

## 3. Email: Bienvenida (Nuevo Agente)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| EM-040 | Email de bienvenida al crear agente | Admin o Cliente crea un agente | 1. Crear nuevo agente con email válido | Email de bienvenida enviado con: nombre, email, credenciales temporales, link al portal. | Alta |
| EM-041 | Credenciales incluidas | Email recibido | 1. Verificar contenido | Incluye email de login y contraseña temporal. Instrucción de cambiar contraseña. | Alta |
| EM-042 | Link al portal funcional | Email recibido | 1. Clic en link del portal | Abre página de login del portal OmniCliente. | Alta |

---

## 4. Email: Recuperación de Contraseña

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| EM-060 | Envío de email de recuperación | Clic en "Olvidé mi contraseña" con email válido | 1. Ingresar email 2. Clic "Enviar" | Email enviado con link o código de recuperación. Logo y footer presentes. | Alta |
| EM-061 | Email no registrado | Email inexistente | 1. Ingresar email que no existe 2. Solicitar recuperación | No se envía email. Mensaje genérico (no revela si email existe). | Alta |

---

## 5. Email: Expiración de Cliente

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| EM-080 | Notificación de expiración a cliente | period_end próximo o vencido, cron ejecutado | 1. Cron de expiración se ejecuta | Email al cliente notificando expiración del período, con datos de contacto para renovar. | Alta |
| EM-081 | Contenido de expiración | Email recibido | 1. Verificar contenido | Muestra nombre de empresa, fecha de expiración, plan actual, instrucciones para renovar. | Media |

---

## 6. Email: Expiración de Agente

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| EM-100 | Notificación de expiración a agente | Cliente expirado, cron ejecutado | 1. Cron de expiración se ejecuta | Email al agente notificando que su acceso será suspendido por expiración del cliente. | Media |
| EM-101 | Contenido de expiración agente | Email recibido | 1. Verificar contenido | Muestra nombre del agente, empresa, instrucciones de contactar al administrador. | Media |

---

## 7. Notificaciones del Navegador (Browser Push)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| EM-120 | Solicitar permiso de notificaciones | Primera vez en el portal | 1. Login como agente | Browser solicita permiso para notificaciones push. | Alta |
| EM-121 | Notificación de nuevo mensaje | Agente en inbox, nueva conversación asignada | 1. Recibir nuevo mensaje en una conversación | Notificación del navegador con preview del mensaje y nombre del contacto. | Alta |
| EM-122 | Notificación con portal en segundo plano | Agente con tab del portal en background | 1. Nuevo mensaje llega | Notificación push aparece incluso con la pestaña en segundo plano. | Alta |
| EM-123 | Clic en notificación abre chat | Notificación recibida | 1. Clic en la notificación del navegador | Portal se enfoca y abre la conversación correspondiente. | Media |
| EM-124 | Sin notificación si permiso denegado | Permiso denegado | 1. Verificar comportamiento con permiso denegado | No se muestran notificaciones push. Portal funciona normalmente sin ellas. | Baja |
