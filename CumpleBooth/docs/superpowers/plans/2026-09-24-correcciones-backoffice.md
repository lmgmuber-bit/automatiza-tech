# Correcciones del backoffice — plan de ejecución

> Para revisión de Claude. Se ejecuta con executing-plans, TDD y verification-before-completion. Luis autorizó implementar las correcciones del reporte el 24/09/2026.

**Objetivo:** cerrar F01–F08 sin cambiar permisos, marca, datos reales ni las ramas 017/018.
**Arquitectura:** conservar PHP server-rendered, helpers existentes y tokens visuales. Acciones de estado separadas de edición; validación de fecha/hora centralizada en una librería pequeña; CSS y etiquetas acotados a los formularios.
**Stack:** PHP 8+, PDO SQLite/MySQL, CSS admin, pruebas PHP HTTP y Chromium.
**Clasificación:** C2; riesgo medio; incertidumbre baja tras auditoría reproducible; reversibilidad fácil; perfil strong_reasoner con el modelo configurado. Codex ejecuta; Claude revisa/orquesta; Luis aprueba. Sin gasto, publicación remota ni despliegue.

## Decisiones y límites
- Se conserva la arquitectura y el diseño aprobados; se descartan rediseño del admin y refactor amplio.
- El reporte de auditoría y la autorización actual constituyen el alcance aprobado. No se repite una aprobación de los mismos ocho hallazgos.
- Mismos volúmenes esperados que el admin actual; ningún servicio o dependencia nueva.
- La comprobación ocurre en bases temporales; imágenes sintéticas; correo apagado.
- CLAUDE.md, lib.php, marca.json y ramas de tickets previos no se modifican.
- El brief y YAML 017/018 citados no están en los checkouts actuales; el informe final señalará esa diferencia, contrastando los requisitos escritos por Luis con los commits y entregas existentes.

## 1. Archivado y validación
- [x] Crear tests/backend/backoffice-http.php con servidor local y SQLite propios: datos completos → archivar → datos iguales; ID ajeno y CSRF inválido rechazados; editar explícitamente sigue funcionando.
- [x] Crear tests/backend/fechas.php: 2026-02-30, 2025-02-29, 99:99, 24:00 rechazados; 2028-02-29 y 23:59 aceptados.
- [x] Ejecutar con PHP de WAMP y registrar rojo antes de implementar.
- [x] public/admin/invitations.php: acción archivar_invitacion valida ownership y solo llama cb_update_invitation_status(id, 'archived', actor). El botón envía esa acción. Edición conserva su transacción y las restricciones de publicación.
- [x] public/lib.fechas.php: validadores puros cb_fecha_valida y cb_hora_valida con formato estricto y checkdate/rangos. Requerir desde lib.invitations.php, lib.finanzas.php y formularios afectados; no editar lib.php.
- [x] Rechazar valores inválidos antes de guardar en alta/edición de invitación y Finanzas; revisar Fiestas y recepción del Álbum por la misma validación identificada.
- [x] Ejecutar los tests hasta verde y las regresiones de invitaciones, finanzas, álbum y formularios.

## 2. Responsive y accesibilidad
- [x] Incorporar prueba Chromium reproducible con fixture local para 320/360/390/768/1024/1440; comprobar documento y controles en las pantallas afectadas y nombres accesibles.
- [x] Registrar los desbordes y etiquetas sin nombre antes de cambiar CSS/HTML.
- [x] _style.css.php: permitir reducción de fieldset/cobro y controles, sin ocultar contenido. invitations.php: minmax(0,1fr), min-width:0 y controles adaptables. comprobante.php: selector limitado al contenedor.
- [x] index.php, invitations.php, comprobante.php, event-profile.php: asociar etiquetas a campos y controles de lectura; cubrir formularios dinámicos sin IDs duplicados.
- [x] Comprobar adición de contactos/protagonistas, tamaños, teclado y árbol de accesibilidad, guardar capturas.

## 3. Migración y suites
- [x] Prueba de doble aplicación de 022 en SQLite temporal, conserva games3d_enabled y datos.
- [x] 022_juegos3d.php: comprobar columna por motor antes de ALTER TABLE.
- [x] salas.php usa _migraciones.php; puntajes.php declara el catálogo vigente conservando pruebas de aislamiento entre temas.
- [x] envios.php/manual.php: asegurar precondiciones aisladas sin depender de configuración real; verificar la rotación de tokens con fixture de fiesta.
- [x] Ejecutar suites originales completas, php -l, frontend, build y paridad si corresponden; escaneo de secretos del diff.

## 4. Entrega
- [x] Ejecutar graphify update . y registrar su resultado.
- [x] Revisar diff, lista FTP exacta, rollback y sección No probado; commit local de esta rama con archivos explícitos.
- [x] Revalidar tests de Agenda 018 y Contenido 017 sin modificar sus fuentes; registrar commits, git status, estado PR y pendientes reales.
- [x] Entregar informe por las tres ramas para Claude, con matriz de solapamientos y orden de integración recomendado. No merge, push, PROD ni gastos.

## Verificación final
29 suites backend (1313 comprobaciones), 214 frontend, 174 vistas amplias, 36 vistas focalizadas y 60 permisos: cero fallos. Dos formularios clonados y 12 pasos Tab pasan. Migración 022 también verificada en MySQL. Build/paridad de Vite no aplican: no se cambiaron fuentes ni bundles de frontend. Lint, diff y escaneo de secretos acompañan la entrega. Graphify actualizado sin LLM; generados locales fuera del commit. Informe de tres ramas en docs/REVISION-CLAUDE-017-018-BACKOFFICE-2026-09-24.md y carpeta de evidencia de la tarea. No se integraron las tres ramas ni se publicó o desplegó código.
