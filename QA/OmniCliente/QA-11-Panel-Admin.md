# QA-11 — Panel de Administración (Admin AT)

**Proyecto:** OmniCliente — Portal Omnicanal  
**Módulo:** DashboardView, ClientsView, AiPromptView (Super Admin)  
**Versión:** 1.0  
**Fecha:** 2026-03-29  
**Roles cubiertos:** Admin AT (Super Admin exclusivo)  

---

## 1. Dashboard Administrativo

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-001 | Ver dashboard | Login como Admin AT | 1. Ir a "Dashboard" (vista por defecto de admin) | 5 tarjetas de métricas: Clientes (total + activos), Conversaciones (total + activas), Mensajes hoy, Canales activos, Takeovers activos. | Alta |
| AD-002 | Métricas en tiempo real | Dashboard visible | 1. Verificar datos | Los datos corresponden a los valores reales de la base de datos. | Alta |
| AD-003 | Tabla de auditoría embebida | Dashboard visible | 1. Scroll abajo del dashboard | Tabla de auditoría con columnas: Acción, Entidad, Usuario, Fecha. Ordenable, buscable, 15 por página. | Media |
| AD-004 | Buscar en auditoría del dashboard | Tabla visible | 1. Usar búsqueda en la tabla de auditoría | Filtra registros por texto. | Baja |

---

## 2. Gestión de Clientes

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-020 | Ver lista de clientes | Login como Admin AT | 1. Ir a "Clientes" en sidebar | Lista con: empresa, contacto, email, plan, estado, periodo, acciones. | Alta |
| AD-021 | Buscar cliente por texto | Lista visible | 1. Escribir en campo búsqueda | Filtra por nombre de empresa, contacto o email. | Media |
| AD-022 | Filtrar por estado | Lista visible | 1. Seleccionar status del dropdown (trial, active, suspended, cancelled) | Lista filtrada por estado. Colores: trial=amarillo, active=verde, suspended=rojo, cancelled=gris. | Media |
| AD-023 | Plan badges con colores | Lista visible | 1. Verificar badges de plan | basic=gris, professional=azul, enterprise=púrpura. | Baja |

---

## 3. Crear Cliente

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-040 | Crear cliente completo | Formulario abierto | 1. Llenar: company_name, contact_name, email, phone, plan_type, max_channels, max_agents, business_type, website, timezone, period_start, period_end 2. Clic "Crear" | Cliente creado. Modal muestra API Key (se ve solo una vez). | Alta |
| AD-041 | API Key mostrada solo una vez | Cliente recién creado | 1. Verificar modal de resultado | API Key visible con advertencia "solo se muestra una vez". Botón para copiar. | Alta |
| AD-042 | Validar period_end >= period_start | Formulario abierto | 1. Ingresar period_end anterior a period_start 2. Intentar crear | Validación impide crear: fecha fin debe ser >= fecha inicio. | Alta |
| AD-043 | Valores por defecto | Abrir formulario | 1. Verificar valores iniciales | plan_type="basic", max_channels=2, max_agents=3, timezone="America/Santiago", is_free=false. | Media |
| AD-044 | Toggle is_free | Formulario abierto | 1. Activar toggle "Es gratuito" | Cliente marcado como free. Puede afectar facturación. | Baja |

---

## 4. Importar Clientes

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-060 | Importar desde WordPress | Admin AT | 1. Clic "Importar desde WP" 2. Buscar usuario WP 3. Seleccionar 4. Confirmar | Usuario WP importado como cliente plan "basic". Datos prellenados. | Alta |
| AD-061 | Importar desde CRM prospects | Admin AT | 1. Clic "Importar desde CRM" 2. Buscar prospecto 3. Seleccionar 4. Confirmar | Prospecto CRM importado como cliente. | Media |
| AD-062 | Buscar usuarios WP | Modal de importación WP | 1. Escribir nombre o email de usuario WP | Lista filtrada de usuarios WP disponibles para importar. | Media |

---

## 5. Editar y Eliminar Cliente

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-080 | Ver detalle de cliente | Cliente existente | 1. Clic en cliente de la lista | Panel de detalle con toda la información del cliente. | Alta |
| AD-081 | Editar cliente | Detalle abierto | 1. Clic "Editar" 2. Modificar campos 3. Guardar | Datos actualizados. | Alta |
| AD-082 | Eliminar cliente | Cliente existente | 1. Clic "Eliminar" 2. Confirmar en modal | Modal de advertencia: "TODOS sus datos / no se puede deshacer". Al confirmar, cliente eliminado. | Alta |
| AD-083 | Advertencia de eliminación destructiva | Modal de confirmación | 1. Verificar texto de advertencia | Mensaje claro sobre pérdida irreversible de todos los datos asociados. | Alta |

---

## 6. AI Prompt Template (Super Admin)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| AD-100 | Ver prompt template | Login como Super Admin | 1. Ir a "AI Prompt" en sidebar | Editor de texto con prompt del sistema completo. Panel lateral con placeholders disponibles. | Alta |
| AD-101 | Editar prompt template | Editor visible | 1. Modificar texto del prompt 2. Clic "Guardar Prompt" | Prompt guardado. Banner: "Los cambios aplican a todas las conversaciones nuevas". | Alta |
| AD-102 | Ver placeholders disponibles | Editor visible | 1. Ver panel lateral de info | Lista de todos los `{placeholder}` tokens disponibles con descripción. | Media |
| AD-103 | Restaurar prompt por defecto | Prompt modificado | 1. Clic "Restaurar default" | Prompt vuelve al template original. | Media |
| AD-104 | Solo visible para Super Admin | Login como Admin AT normal / otro rol | 1. Verificar sidebar | Opción "AI Prompt" solo visible para Super Admin. Nota en vista: "Solo visible para Super Admin". | Alta |
| AD-105 | Datos de contexto auto-inyectados | Prompt guardado | 1. Verificar funcionamiento del prompt | Datos contextuales (canales, agentes, conversaciones, tickets) se inyectan automáticamente debajo del template. | Media |
