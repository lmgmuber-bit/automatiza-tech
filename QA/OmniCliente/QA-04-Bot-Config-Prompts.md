# QA-04 — Configuración de Bots y Prompts

**Proyecto:** OmniCliente — Portal Omnicanal  
**Módulo:** BotConfigUnifiedView (3 tabs: Config, Prompts, Preview)  
**Versión:** 1.0  
**Fecha:** 2026-03-29  
**Roles cubiertos:** Admin AT, Cliente (API Key), Supervisor (solo lectura), Agente (solo lectura)  

---

## 1. Tab "Configuración del Bot" (BotsView)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| BC-001 | Ver configuraciones de bots | Login como Cliente o Admin | 1. Ir a "Canalbot" en sidebar 2. Tab "Configuración del Bot" seleccionada | Lista de bot configs (1 por canal). Muestra modelo AI, tokens máx, temperatura. | Alta |
| BC-002 | Agente ve config en solo lectura | Login como Agente | 1. Ir a "Canalbot" | No aparece botón "Configurar". Datos visibles pero no editables. | Alta |
| BC-003 | Supervisor ve config en solo lectura | Login como Supervisor | 1. Ir a "Canalbot" | No aparece botón "Configurar". Solo lectura. | Alta |
| BC-004 | Abrir formulario de edición | Login como Cliente | 1. Clic en "Configurar" en un bot config | Formulario se expande con campos: nombre bot, modelo AI, tokens máx, temperatura, webhook N8N, auto-respuesta fuera de horario, activo. | Alta |
| BC-005 | Cambiar modelo AI | Editando config | 1. Cambiar modelo a "gpt-4o" 2. Guardar | Se guarda. Quick info muestra "gpt-4o". | Alta |
| BC-006 | Ajustar temperatura con slider | Editando config | 1. Mover slider de temperatura de 0.3 a 0.8 | Slider muestra valor. Etiquetas: "Preciso" (izq) — "Creativo" (der). | Media |
| BC-007 | Validar rango de tokens | Editando config | 1. Ingresar 50 en max_response_tokens | Campo no acepta valores < 100 o > 4000 (min/max validados por input). | Media |
| BC-008 | Activar auto-respuesta fuera de horario | Editando config | 1. Activar toggle "Auto-respuesta fuera de horario" 2. Guardar | Valor guardado como '1'. Bot responde fuera de horario. | Media |
| BC-009 | Configurar webhook N8N | Editando config | 1. Pegar URL de webhook N8N 2. Guardar | URL guardada. Campo tipo URL validado. | Media |
| BC-010 | Cancelar edición | Editando config | 1. Clic en "Cancelar" | Formulario se cierra. No se guardan cambios. | Baja |
| BC-011 | Desactivar bot | Editando config | 1. Desmarcar toggle "Activo" 2. Guardar | Bot desactivado. Config muestra estado inactivo. | Alta |

---

## 2. Tab "Prompts" (PromptsView)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| BC-030 | Ver lista de prompt configs | Login como Cliente | 1. Tab "Prompts" | Lista de prompt configs con nombre, canal asignado, estado activo/inactivo. | Alta |
| BC-031 | Supervisor ve prompts en solo lectura | Login como Supervisor | 1. Tab "Prompts" | Puede ver prompts pero NO editar, crear ni eliminar. Sin botones de acción. | Alta |
| BC-032 | Crear nueva config de prompt | Login como Cliente | 1. Clic en "Nuevo Prompt" 2. Ingresar nombre (requerido) 3. Seleccionar canal (requerido) 4. Completar secciones | Prompt creado. Aparece en la lista. | Alta |
| BC-033 | Validar nombre y canal requeridos | Creando prompt | 1. Intentar guardar sin nombre ni canal | Error de validación: nombre y canal requeridos. | Alta |
| BC-034 | Llenar sección Negocio | Editando prompt | 1. Expandir sección "🏢 Negocio" 2. Completar: nombre, teléfono, dirección, RRSS, horario | Campos guardados. Sección siempre visible (no se puede desactivar). | Alta |
| BC-035 | Llenar sección Asistente | Editando prompt | 1. Expandir "🤖 Asistente" 2. Ingresar nombre, emoji, tono, idioma, máx párrafos | Campos guardados. Sección siempre activa. | Alta |
| BC-036 | Toggle sección Mensajes | Editando prompt | 1. Activar/desactivar sección "💬 Mensajes" | Sección se expande o colapsa. Campos: saludo, respuesta agendar, cancelar, escalación, fuera horario, despedida. | Media |
| BC-037 | Configurar Servicios | Editando prompt | 1. Expandir "📋 Servicios" 2. Ingresar categorías, catálogo JSON, duración, requerimientos | Datos guardados. Soporta JSON para catálogo. | Media |
| BC-038 | Configurar Productos | Editando prompt | 1. Expandir "🛒 Productos" 2. Ingresar catálogo, categorías, despacho, devoluciones | Datos guardados. | Media |
| BC-039 | Configurar Agenda | Editando prompt | 1. Expandir "📅 Agenda" 2. Ingresar horario inicio/fin, días hábiles, intervalo, buffer, moneda, bloqueos | Datos guardados. Bloqueos en formato JSON. | Media |
| BC-040 | Configurar Pagos | Editando prompt | 1. Expandir "💰 Pagos" 2. Ingresar condiciones, métodos, cuentas bancarias | Datos guardados. Dos campos de cuenta bancaria. | Media |
| BC-041 | Configurar FAQ | Editando prompt | 1. Expandir "❓ FAQ" 2. Ingresar contenido FAQ, info adicional, políticas | Datos guardados. | Baja |
| BC-042 | Configurar Escalación con agentes | Editando prompt | 1. Expandir "🔀 Escalación" 2. Agregar agente custom (seleccionar agente, área, checkbox defecto) | Agente de escalación vinculado. CRUD de agentes custom. | Alta |
| BC-043 | Configurar Reglas | Editando prompt | 1. Expandir "⚠️ Reglas" 2. Ingresar restricciones, capacidades, ejemplo, temas prohibidos | Datos guardados. Sección siempre activa (no desactivable). | Alta |
| BC-044 | Importar desde otra config | Prompt existente | 1. Clic en "Importar" 2. Seleccionar otra config como fuente 3. Confirmar | Campos copiados desde la config seleccionada. | Media |
| BC-045 | Importar desde CSV | Prompt existente | 1. Clic en "Importar" 2. Seleccionar CSV (formato parametro;valor) | Campos parseados y cargados desde CSV. | Media |
| BC-046 | Eliminar prompt config | Prompt existente | 1. Clic en "Eliminar" 2. Confirmar en modal | Prompt eliminado de la lista. | Alta |
| BC-047 | Activar/desactivar prompt | Prompt existente | 1. Toggle "Activo" | Estado cambia entre activo e inactivo. Solo 1 config activa por canal recomendado. | Media |

---

## 3. Tab "Vista Previa Prompt" (PromptPreviewPanel)

| ID | Caso de Uso | Precondición | Pasos | Resultado Esperado | Prioridad |
|---|---|---|---|---|---|
| BC-060 | Ver preview de prompt armado | Al menos 1 prompt config | 1. Tab "Vista Previa Prompt" 2. Seleccionar config del dropdown | Texto completo del prompt armado con todos los datos, incluyendo agentes de escalación resueltos con nombres y horarios. | Alta |
| BC-061 | Copiar prompt | Preview visible | 1. Clic en botón "Copiar" | Texto copiado al portapapeles. Feedback visual de copiado. | Media |
| BC-062 | Ver JSON crudo | Preview visible | 1. Activar toggle "Show raw JSON" | Muestra JSON raw de la configuración completa. | Baja |
| BC-063 | Selección automática | Tab preview abierta | 1. Abrir tab sin selección previa | Auto-selecciona la primera config disponible. | Baja |
