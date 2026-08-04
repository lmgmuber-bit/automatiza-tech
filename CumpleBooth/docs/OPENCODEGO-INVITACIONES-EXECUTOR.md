# OpenCode Go — paquete de ejecución AT-CUMPLECLICK-006

> **Orden actualizado por Luis:** este documento es el plan padre. La ejecución
> ya no se hace por lotes de 15 imágenes. Cada tema se cierra completamente en
> dos fases: primero invitación descargable mediante enlace opaco y después experiencia
> fotográfica. El ticket activo es `AT-CUMPLECLICK-007` y su contrato específico
> es `docs/OPENCODEGO-TEMA-02-FAMILIA-CANINA.md`.

> **Aclaración canónica de Luis (2026-07-15):** Invitaciones es un módulo
> independiente del Photo Booth. Cada tema tiene una imagen genérica sin datos
> y cada invitación genera una segunda imagen personalizada mediante un prompt
> con placeholders compilados. El admin comparte solo el enlace de descarga;
> no comparte ni adjunta el archivo desde CumpleClick.
> La imagen genérica aprobada es, además, el máster visual obligatorio desde el
> cual se deriva todo el Photo Booth; no crear su paleta ni assets en paralelo.

Este archivo es el contrato operativo. OpenCode Go es el **ejecutor**; Codex
es el planner/analista y revisará el resultado. No cambies alcance ni orden sin
registrarlo y pedir confirmación a Luis.

## Lectura obligatoria, en este orden

1. `docs/CUMPLECLICK-HANDOFF-CODEX.md` completo.
2. `docs/FASE-INVITACIONES-DINAMICAS.md` completo.
3. `docs/ARQUITECTURA.md` y `docs/FASE1.md`.
4. `AGENTS.md`, `OPENCODE.md` y `Docs/ORCHESTRATION/AT-CUMPLECLICK-006.yaml`.
5. `C:\Users\luis_\Documents\Codex\AI-Memory-Vault\20-Shared-Memory\Invitaciones-AI-Prompts.md`.

## Reglas no negociables

- Trabaja solo en la rama y archivos permitidos por el ticket.
- Preserva el worktree sucio; no uses `git add .`, reset, checkout destructivo
  ni incluyas cambios ajenos.
- PHP >=8.0, baseline 8.2, pruebas 8.3/8.4.
- No despliegues, no hagas merge/push y no uses secretos reales.
- No llames Gemini/Higgsfield ni gastes créditos hasta que Luis apruebe por
  escrito el lote, costo cotizado y saldo.
- En **todo prompt generativo**, describe personajes únicamente por rasgos
  físicos. Nunca escribas el nombre de una franquicia, estudio o personaje,
  aunque aparezca en `themes.json`, una imagen de referencia o el admin.
- Ejecuta un chequeo de términos reservados antes de cada llamada. Si falla,
  bloquea la generación y reporta el término; no lo camufles con una falta.
- Las diez imágenes de `C:\Users\luis_\Downloads\Gemini_Generated_Image_*.png`
  son referencias visuales locales: no copiar, publicar ni desplegar.
- La imagen genérica se genera sin texto. La segunda imagen personalizada usa
  el prompt aprobado con datos reales y requiere QA carácter por carácter.
- Si un job externo ya fue cobrado, recupera su resultado antes de reintentar.

## Alcance exacto activo

El catálogo de 15 temáticas permanece como roadmap del ticket padre, pero la
ejecución inmediata se limita a la infraestructura reutilizable y al Tema 02
`familia-canina`, siguiendo literalmente
`OPENCODEGO-TEMA-02-FAMILIA-CANINA.md`. No generar `dinos`, `hielo`, `cachorros`,
`arcade`, `fashion` ni Tema 03 hasta que Luis apruebe Tema 02.

## Datos mínimos de la tarjeta

La acción `Tarjeta de invitación` debe solicitar y validar:

1. nombre del cumpleañero o cumpleañera;
2. fecha del evento;
3. hora de inicio;
4. dirección donde se realizará.

`cc_invitations` es fuente de verdad de estos datos y `party_id` es nullable;
puede precargarlos desde una fiesta asociada, pero no depende de ella. Edad,
lugar/salón, RSVP y mensaje son opcionales. Se puede guardar un borrador
incompleto, pero compilar, generar, publicar o copiar el enlace debe fallar de
forma clara y segura si falta cualquiera de los cuatro campos. Nunca rellenes
datos ausentes con valores de demostración ni publiques placeholders.

## Flujo de generación

### Imagen

Para cada slug, arma el prompt como:

```text
PROMPT_MAESTRO_IMAGEN
+ THEME_SCENE del tema
+ restricciones de safe area
```

Usa además el `NEGATIVE_PROMPT_MAESTRO`. Los textos completos y los 15
`THEME_SCENE` están en el plan, pero en el ticket activo ejecuta solo
`familia-canina` y detente en cada gate definido por su contrato específico.

Después de aprobar la imagen genérica, compila `invitation.personalize.v1` con
`[NOMBRE_DEL_CUMPLEAÑERO]`, `[FECHA_Y_HORA]` y `[DIRECCIÓN]`, genera la segunda
imagen y exige coincidencia exacta antes de aprobar. El video parte de esa
imagen personalizada y debe preservar su texto; no parte de la genérica.

QA mínimo por imagen:

- 1080×1920 y 9:16 real;
- panel superior totalmente vacío y legible;
- cero letras, números, logos o watermark;
- personajes íntegros, sin duplicaciones/deformaciones;
- composición original, coherente con la temática y apta para niños;
- texto de prueba largo cabe dentro del `textBox` en tablet de 10 pulgadas;
- miniatura y checksum generados solo después de aprobación.

### Video Higgsfield

1. Usa la imagen base aprobada como `start_image`.
2. Compón `PROMPT_MAESTRO_HIGGSFIELD + THEME_MOTION` del plan.
3. Solicita cotización con `get_cost:true` y no generes aún.
4. Registra modelo, duración, resolución, costo estimado y saldo sanitizado.
5. Obtén aprobación de Luis.
6. Genera un solo piloto (`dinos`).
7. Revisa frames inicial/medio/final, panel vacío y consistencia.
8. Si se aprueba, continúa de uno en uno: `hielo`, `cachorros`, `arcade`,
   `fashion`.

Parámetros de partida: 5 segundos, 720p, 9:16, `kling3_0_turbo`. Puedes
proponer un modelo mejor con comparación A/B y cotización, pero no cambiarlo
ni consumir un segundo intento sin gate. Rechaza presets que sustituyan la
composición o agreguen personas/escenas ajenas.

Referencia de presupuesto, no cotización vigente: el último saldo escrito en
el handoff es ~9 créditos y el piloto anterior costó 7,5 créditos por 5 s.
Para cinco videos se requieren aproximadamente 37,5 créditos; no inicies el
lote sin saldo mínimo de 45 créditos (20% de margen) y aprobación de Luis.

QA mínimo por video:

- 720×1280, duración 5–7 s, MP4 H.264 reproducible en Chrome/Android;
- diseño y cantidad de personajes estables;
- panel superior vacío, fijo y sin objetos que lo crucen;
- movimiento suave, sin cortes, deformaciones, texto ni flashes agresivos;
- último frame suficientemente cercano al primero para loop;
- fallback a imagen si el MP4 falta o falla.

## Implementación administrativa

No conviertas las cards de `Temáticas` en un formulario interminable. Mantén
el resumen en cards y abre una ficha de detalle para:

- preview de imagen/video;
- inventario y versiones;
- prompt de imagen, negativo y movimiento;
- historial de revisiones;
- proveedor/modelo/job/costo sanitizado;
- safe area y estilo de texto;
- aprobación/rechazo/subida de reemplazo.

La vista `Invitaciones` por fiesta debe usar el layout de tres zonas en desktop
y pasos apilados en tablet. Prioridad visual: tablet física de 10 pulgadas,
pero validar también 7, 8 y 9 pulgadas.

## Pruebas obligatorias

- Migraciones up/down en SQLite y MySQL/MariaDB; rollback con snapshot.
- CRUD y transacciones; una plantilla activa por tema; invitación independiente
  con asociación opcional a una fiesta.
- Validación de JSON normalizado y rutas allowlisted.
- Auth, CSRF, XSS, SQLi, path traversal, MIME falso, tamaño y duración.
- Token público: publicación, acceso, revocación y no enumeración.
- Prompts privados ausentes de API pública y HTML no autenticado.
- Auditor que rechace términos reservados antes de generación.
- Render con nombres/direcciones cortos, largos, acentos y caracteres chilenos.
- Gate de publicación para cada combinación ausente de nombre, fecha, hora o
  dirección; verificar que el borrador sí se puede conservar.
- Responsive: 600×960, 768×1024, 800×1280, 900×1440, 1280×800 y viewport
  real de la tablet de 10 pulgadas.
- Chrome: consola limpia, PNG correcto, video/fallback y regresión completa del
  kiosco Carreras.
- Build desde `dist` limpio, verificación de paridad y Graphify actualizado.

## Entregables y evidencia

- Diff limitado al ticket.
- Migraciones + down migrations.
- Pruebas y logs sanitizados.
- Matriz de 15 temas con estado `image/video/prompt/QA`.
- Contact sheet de las 15 imágenes y filmstrip de cada uno de los 5 videos.
- Costos estimados/reales y job refs sanitizados.
- Capturas admin desktop y tablet 10 pulgadas.
- Documentación y `docs/FTP-MANIFEST.md` con lista exacta; no deploy.
- Reporte final: criterios cumplidos, fallos, riesgos, rollback y estado Git.

## Condiciones para detenerse

Detente y pide decisión si:

- un prompt podría revelar un nombre protegido;
- el saldo no cubre el lote más 20% de margen;
- el proveedor recomienda un preset que cambia el concepto;
- una imagen contiene texto o el video invade el panel;
- el cambio exige modificar el flujo del kiosco ya aprobado;
- hay conflicto con cambios ajenos o se requiere una acción externa no autorizada.
