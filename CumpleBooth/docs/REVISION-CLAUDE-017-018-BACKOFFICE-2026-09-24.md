# Entrega a Claude — CumpleClick 018, 017 y correcciones
Fecha: 2026-09-24. Luis aprueba; Claude orquesta/revisa; Codex ejecutó.

## Tres ramas independientes desde main (21c0c1a)
- `codex/agenda-eventos`: commit `95e714f`, PR borrador [#40](https://github.com/lmgmuber-bit/automatiza-tech/pull/40).
  Agenda, logística, checklist, ICS privado y avisos. Diario 09:00 Chile; semanal lunes 08:00.
  Admin global, operadores solo sus eventos asignados; aviso también sin eventos. No activado.
- `codex/marketing-contenido`: commits `ae89cf1` y `34c949a`. Backend Contenido implementado:
  calendario/semana/ficha, estados, texto para copiar, assets privados, métricas e importador.
  La estrategia de 30 días y los assets finales siguen pendientes en esta misma rama.
  No hay PR creado: la revisión automática anterior rechazó publicar ese payload en un
  repositorio público sin autorización específica. El código permanece local.
- `codex/correcciones-backoffice`: ocho hallazgos de la auditoría corregidos y probados.
  Archivado sin pérdida de datos; fechas/horas válidas; responsive; etiquetas accesibles;
  migración 022 repetible; suites actualizadas y aisladas. Ver su HEAD con
  `git rev-parse codex/correcciones-backoffice`. No se intentó publicar esta rama.

## Evidencia nueva
- Correcciones: 29 suites backend, 1313 comprobaciones; frontend 214. Cero fallos.
- Regresión HTTP nueva 35; validadores de fechas 25; migración 022 SQLite 3 y MySQL 3.
- UI focalizada 36 vistas con formularios abiertos y protagonista clonado; árbol accesible
  y etiquetas explícitas sin vacíos, ids sin duplicar.
- Backoffice completo: 29 estados × 6 anchos = 174 vistas; sin desbordes ni errores JS.
  60 comprobaciones de permisos pasan. Contacto/protagonista clonados y 12 pasos Tab pasan.
- Lint: 19 PHP cambiados/nuevos y 21 archivos del admin (hay solapamiento), cero errores.
- 018 revalidado: SQLite 154, MySQL 154, HTTP 30.
- 017 revalidado: SQLite 60, MySQL 60, HTTP 36.
- MySQL 8.4.7 de QA en loopback:33387, bases desechables; instancia detenida al terminar.
- Las 36 vistas responsive previas de Agenda/Contenido siguen como evidencia de esos commits,
  cuyas fuentes no se modificaron aquí. No se confunden con las 174 vistas del backoffice base.

Informe HTML, Markdown, salidas completas y capturas:
`C:/Users/luis_/.codex/visualizations/2026/09/12/01a093c7-6b37-7821-b563-efba7da3fe00/correcciones-backoffice/index.html`.
El informe externo incluye los hashes finales y el estado Git capturado después del commit.
Cada rama contiene su sección final en `CumpleBooth/docs/FTP-MANIFEST.md`, con rutas exactas,
orden y **No probado**. Los manifiestos no son autorización de despliegue.

## Integración que debe revisar Claude
1. Revisar 018, luego el backend 017 y la rama de correcciones. Las tres se basan en main;
   ninguna incluye automáticamente a las otras. No se hizo merge ni se probó una rama combinada.
2. En `public/admin/_acceso.php`, conservar Agenda después de Fiestas y Contenido después de
   Finanzas. En `public/lib.admin-usuarios.php`, conservar agenda después de invitados y
   marketing al final después de perfil. Las adiciones están en lugares diferentes.
3. Las tres agregan secciones al final de `docs/FTP-MANIFEST.md`: conservar las tres.
   Es el único archivo desplegable/documental compartido con la rama de correcciones;
   no reemplazar archivos enteros desde un worktree al integrar.
4. Repetir las pruebas de las tres ramas juntas y las vistas de Agenda/Contenido con el CSS
   final integrado. Revisar el plan de subida consolidado antes de solicitar deploy a Luis.
5. Graphify se actualizó con AST local; sus generados quedan locales sin commit y no forman
   parte del cambio de producto. Se preservan para no dejar el grafo local obsoleto.
6. No se tocaron la copia principal con cambios ajenos, las fuentes 017/018, CLAUDE.md,
   public/lib.php, public/data/marca.json, producción ni cuentas reales.

## Pendientes y límites
El brief `CODEX-HANDOFF-MARKETING-Y-AGENDA-2026-09-24.md` y los YAML 017/018 nombrados
por Luis no se localizaron en los worktrees actuales. Contrastar la entrega con las copias
canónicas que conserva Claude; no se inventaron sus apéndices. La implementación se
contrastó con los requisitos escritos por Luis, los commits y sus manifiestos.

No probado: hosting, SMTP/cron real, recepción en buzones, Teomar real, calendarios externos,
Safari/Firefox y dispositivos físicos, accesibilidad con lector real, integración conjunta,
CI remota ni recuperación de datos dañados por el archivado previo.
El detalle particular de cada rama está en su manifiesto.

Preguntas abiertas: fecha de inicio del plan de 30 días; autorización de publicación remota
de las ramas locales; autorización posterior de merge/deploy; ubicación de los briefs canónicos.
No bloquean la revisión local de los tres entregables.
Costos de generación: 0 créditos Higgsfield y ElevenLabs; request_id no aplica.
