# QA-01 — Login y Autenticación

**Proyecto:** OmniCliente — Portal Omnicanal  
**Módulo:** Login, Autenticación y Recuperación de Contraseña  
**Versión:** 1.0  
**Fecha:** 2026-03-29  
**Roles cubiertos:** Agente, Supervisor, Cliente (API Key), Super Admin  

---

## 1. Login de Agente (Email + Contraseña)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| LG-001 | Login exitoso como agente | Agente activo existe en BD | 1. Abrir `/omnicliente/` 2. Seleccionar tab "Agente" 3. Ingresar email y contraseña válidos 4. Clic en "Iniciar Sesión" | Redirige al Inbox con sidebar de agente. Muestra nombre y avatar en sidebar. | Alta |
| LG-002 | Login con contraseña incorrecta | Agente activo existe | 1. Ingresar email correcto 2. Ingresar contraseña incorrecta 3. Clic en "Iniciar Sesión" | Muestra error "Contraseña incorrecta" o similar. No redirige. | Alta |
| LG-003 | Login con email no registrado | N/A | 1. Ingresar email que no existe 2. Ingresar cualquier contraseña 3. Clic en "Iniciar Sesión" | Muestra error genérico. No revela si el email existe. | Alta |
| LG-004 | Login con campos vacíos | N/A | 1. Dejar email y/o contraseña vacíos 2. Clic en "Iniciar Sesión" | Botón deshabilitado o muestra validación. | Media |
| LG-005 | Login con caracteres prohibidos | N/A | 1. Ingresar `<script>` en email o contraseña 2. Intentar login | Muestra error de caracteres no permitidos `< > " ' ; \ { } \|`. No envía request. | Alta |
| LG-006 | Login como agente suspendido | Agente con status "inactive" | 1. Ingresar credenciales de agente inactivo 2. Clic en "Iniciar Sesión" | Muestra error indicando cuenta suspendida. | Alta |
| LG-007 | Login como agente de cliente expirado | Cliente con period_end vencido | 1. Login con agente de ese cliente | Muestra modal de expiración o error indicando servicio expirado. | Alta |

---

## 2. Login de Cliente (API Key)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| LG-020 | Login con API Key válida | Cliente activo con API Key | 1. Seleccionar tab "Cliente" 2. Ingresar API Key válida 3. Clic en "Iniciar Sesión" | Redirige al Inbox con sidebar completa de cliente (Canales, Bot Config, Agentes, etc.). | Alta |
| LG-021 | Login con API Key inválida | N/A | 1. Ingresar API Key incorrecta 2. Clic en "Iniciar Sesión" | Muestra error "API Key inválida". | Alta |
| LG-022 | Login con API Key de cliente suspendido | Cliente status "suspended" | 1. Ingresar API Key de cliente suspendido | Muestra error de acceso denegado. | Alta |

---

## 3. Login de Super Admin (WP User)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| LG-040 | Login como Super Admin | Usuario WP con rol admin | 1. Seleccionar tab "Admin" 2. Ingresar usuario y contraseña WP 3. Clic en "Iniciar Sesión" | Redirige al portal con sidebar admin completa (Clientes, Dashboard, AI Prompt). | Alta |
| LG-041 | Login admin con credenciales WP incorrectas | N/A | 1. Ingresar usuario/contraseña WP incorrectos | Muestra error de autenticación. | Alta |

---

## 4. Recuperación de Contraseña

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| LG-060 | Solicitar reset de contraseña | Agente activo con email | 1. Clic en "¿Olvidaste tu contraseña?" 2. Ingresar email del agente 3. Clic en "Enviar enlace" | Muestra mensaje de éxito. Se recibe email con enlace de reset (válido 1 hora). | Alta |
| LG-061 | Reset con enlace válido | Token de reset generado | 1. Abrir enlace del email 2. Ingresar nueva contraseña (cumplir requisitos) 3. Confirmar contraseña 4. Enviar | Contraseña actualizada. Puede iniciar sesión con nueva contraseña. | Alta |
| LG-062 | Reset con enlace expirado | Token generado hace >1 hora | 1. Abrir enlace del email expirado 2. Intentar cambiar contraseña | Muestra error "Enlace expirado". Sugiere solicitar nuevo enlace. | Media |
| LG-063 | Reset con contraseña débil | Token válido | 1. Ingresar contraseña sin mayúscula, sin número o sin carácter especial | Muestra validación indicando requisitos faltantes (8 chars, mayúscula, número, especial). | Alta |
| LG-064 | Reset con email no registrado | N/A | 1. Solicitar reset con email que no existe | Muestra mensaje genérico (no revelar si email existe). | Media |

---

## 5. Gestión de Sesión

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| LG-080 | Persistencia de sesión al recargar | Sesión activa (agente/admin/cliente) | 1. Iniciar sesión 2. Recargar página (F5) | Sesión se mantiene. No vuelve al login. | Alta |
| LG-081 | Logout correcto | Sesión activa | 1. Clic en "Cerrar Sesión" en sidebar | Redirige al login. localStorage limpio. No puede navegar sin login. | Alta |
| LG-082 | Sesión expirada | Token con fecha expirada | 1. Esperar a que expire el token (o modificar manualmente) 2. Intentar acción en el portal | Redirige al login automáticamente. | Media |
| LG-083 | Cambio de modo oscuro en login | Pantalla de login | 1. Clic en toggle de dark mode 2. Verificar que se guarda al recargar | Tema cambia correctamente. Persiste en localStorage (`omni_theme`). | Baja |

---

## 6. Seguridad

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| LG-100 | Acceso directo a ruta protegida sin sesión | Sin sesión activa | 1. Navegar directamente a `/omnicliente/#/inbox` | Redirige al login. | Alta |
| LG-101 | Inyección SQL en campos de login | N/A | 1. Ingresar `' OR 1=1 --` en email 2. Intentar login | No permite login. Input sanitizado. | Alta |
| LG-102 | XSS en campo de email | N/A | 1. Ingresar `<img src=x onerror=alert(1)>` en email | Caracteres prohibidos detectados. No se ejecuta script. | Alta |
