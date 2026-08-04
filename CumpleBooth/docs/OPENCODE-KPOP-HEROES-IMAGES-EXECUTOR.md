# OpenCode — ejecutor de imágenes K-Pop y Héroes

## Misión cerrada

Trabaja siempre en español y exclusivamente dentro de:

`C:\wamp64\www\automatiza-tech\CumpleBooth`

Ejecuta únicamente el **paso 3** del §9 de
`docs/CODEX-TEMATICAS-KPOP-Y-VENGADORES.md` para las temáticas `kpop` y
`heroes`.

Codex conserva el ownership de integración, QA final y aceptación. Tu función
es generar, descargar, validar técnicamente e instalar las imágenes.

## Lectura obligatoria antes de actuar

Lee completos, en este orden:

1. `docs/CODEX-TEMATICAS-KPOP-Y-VENGADORES.md`
2. `docs/CUMPLECLICK-HANDOFF-CODEX.md`
3. `AGENTS.md`
4. `OPENCODE.md`

Presta especial atención al recuadro **ESTADO LOCAL**, al §1 de camuflaje, a
los prompts del §5 y al orden del §9.

## Estado que debes respetar

- Rama actual: `codex/cumpleclick-admin-manual-assets`.
- El código, contratos, juegos `ritmo`/`escudo`, demos y panel ya existen.
- `public/themes/kpop/` todavía no existe.
- `public/themes/heroes/` existe pero no contiene assets.
- No crees ni cambies de rama.
- El árbol tiene trabajo no confirmado de otros agentes: no lo limpies, no lo
  reviertas y no sobrescribas cambios ajenos.

## Orden innegociable

Completa `kpop` y luego `heroes`. Dentro de cada temática:

1. `fondo-banner.jpg`
2. `fondo-sala.jpg`
3. Fondo real del juego:
   - K-Pop: `fondo-juego-escenario.jpg`
   - Héroes: `fondo-juego-ciudad.jpg`
4. Los seis retratos JPG, uno a uno, en el orden de `themes.json`.
5. Los seis `*-cut.png`, derivados localmente de cada retrato aprobado.
6. Los seis `puzzle-<personaje>.jpg`, derivados localmente del retrato
   aprobado como recorte cuadrado 900×900.

Nunca generes un video antes de que exista y sea aceptada su foto. En esta
misión **no debes generar ningún video**.

## Archivos exactos

### K-Pop

- `fondo-banner.jpg`
- `fondo-sala.jpg`
- `fondo-juego-escenario.jpg`
- `rumi.jpg`, `mira.jpg`, `zoey.jpg`, `luna.jpg`, `derpy.jpg`, `sussie.jpg`
- `rumi-cut.png`, `mira-cut.png`, `zoey-cut.png`, `luna-cut.png`,
  `derpy-cut.png`, `sussie-cut.png`
- `puzzle-rumi.jpg`, `puzzle-mira.jpg`, `puzzle-zoey.jpg`,
  `puzzle-luna.jpg`, `puzzle-derpy.jpg`, `puzzle-sussie.jpg`

### Héroes

- `fondo-banner.jpg`
- `fondo-sala.jpg`
- `fondo-juego-ciudad.jpg`
- `capitan.jpg`, `arana.jpg`, `gigante.jpg`, `hierro.jpg`, `trueno.jpg`,
  `pantera.jpg`
- `capitan-cut.png`, `arana-cut.png`, `gigante-cut.png`, `hierro-cut.png`,
  `trueno-cut.png`, `pantera-cut.png`
- `puzzle-capitan.jpg`, `puzzle-arana.jpg`, `puzzle-gigante.jpg`,
  `puzzle-hierro.jpg`, `puzzle-trueno.jpg`, `puzzle-pantera.jpg`

## Generación y ahorro de créditos

Proveedor autorizado para esta misión: **CLI local de Higgsfield**, ya
autenticado. No uses BudgetPixel: esa cuenta responde `Unauthorized`.

Comandos base:

```powershell
higgsfield account status --json
higgsfield generate list --image --size 100 --json
higgsfield generate cost nano_banana_pro --prompt "<PROMPT>" --aspect-ratio "9:16" --resolution "2k" --json
higgsfield generate create nano_banana_pro --prompt "<PROMPT>" --aspect-ratio "9:16" --resolution "2k" --wait --wait-timeout 20m --json
```

Estado verificado por Codex al delegar:

- Plan Higgsfield: `basic`.
- Saldo inicial: `57.17` créditos.
- Costo cotizado de `nano_banana_pro`, 9:16, 2K: `2` créditos por imagen.
- Son **18 generaciones pagadas**: 6 fondos + 12 retratos.
- Los 12 recortes y 12 puzzles son derivados locales sin generación IA.
- Presupuesto máximo para este lote: **36 créditos**. No superar ese total ni
  regenerar sin aplicar la política de fallo objetivo.

Reglas:

1. Antes de generar, consulta el historial de imágenes del proveedor y pagina
   todos los resultados relevantes. Reutiliza solo un resultado que
   corresponda inequívocamente al prompt camuflado, tema, asset y formato
   requeridos.
2. Si no existe un resultado reutilizable, genera exactamente un candidato.
3. No regeneres por preferencias menores. Solo repite cuando haya un fallo
   objetivo del checklist.
4. Si un prompt es bloqueado, no agregues nombres protegidos. Refuerza
   originalidad y simplifica rasgos. Después de tres rechazos para el mismo
   asset, detente y repórtalo.
5. Haz preflight de costo antes de cada generación. Si el costo deja de ser
   `2` créditos o el saldo no alcanza para terminar el lote, detente.
6. Registra por generación: proveedor/modelo, job ID sanitizado, costo real,
   saldo anterior/posterior, dimensiones, peso y SHA-256.

## Regla absoluta de prompts

- Usa literalmente los prompts camuflados del §5 como base.
- Nunca escribas en un prompt nombres de franquicias, películas, estudios o
  personajes protegidos.
- Los nombres reales solo son metadatos del admin y nombres visibles de la
  aplicación; no se envían al generador.
- Fondos sin texto, logos, letras, números, marcas de agua ni nombres.
- Personajes individuales: una sola figura, cuerpo completo, centrada, sin
  otras figuras, sin texto, sin logo y sin recortes corporales.
- No generes imágenes finales con el logo CumpleClick pintado dentro del
  universo temático.

## QA técnico y visual antes de instalar

Para cada fondo y retrato:

- Imagen válida y decodificable.
- Vertical 9:16; destino final 1080×1920.
- Sin texto ni marcas de agua.
- Sin miembros cortados, duplicados o anatomía rota.
- Coherencia visual dentro de la temática.
- El `fondo-sala.jpg` contiene un marco dorado frontal con área interior
  blanca, rectangular y medible para la foto.
- El fondo del juego deja una zona central legible para la UI.

Para cada `*-cut.png`:

- PNG real con canal alfa.
- Fondo exterior transparente, no checker pintado.
- Sin halo blanco/gris visible.
- No eliminar ojos, dientes, manos, cabello, accesorios o bordes del traje.
- Cuerpo completo y encuadre útil para la composición.

Para cada puzzle:

- JPG válido de 900×900.
- Derivado del retrato aprobado, sin IA adicional.
- Rostro y rasgos principales reconocibles; sin deformar ni estirar.

Guarda los originales descargados y cualquier evidencia de QA en
`design/renders/kpop-images-raw/` y `design/renders/heroes-images-raw/`.
Instala únicamente los resultados aprobados en
`public/themes/<slug>/`.

## Prohibiciones

- No generar videos, voces ni música.
- No consumir créditos de video.
- No tocar lip-sync; queda para un gate posterior y exige aprobación de voz
  por Luis.
- No cambiar `src/`, PHP, migraciones, `themes.json`, fiestas demo, admin,
  contratos, juegos ni prompts históricos.
- No ejecutar build, deploy, commit, push, merge ni borrar la BD.
- No copiar assets desde otra temática.
- No declarar éxito si falta algún archivo o el QA visual no se realizó.

## Entrega obligatoria a Codex

Al terminar, responde con:

1. Tabla de los 42 archivos esperados (21 por tema), ruta, dimensiones,
   tamaño, SHA-256 y estado.
2. Tabla de generaciones/reutilizaciones con costos y saldo.
3. Lista de rechazos, regeneraciones o assets bloqueados.
4. Rutas de originales y evidencias.
5. Confirmación explícita de que no generaste video/audio, no tocaste código,
   no hiciste build/deploy/commit/push/merge y no usaste nombres protegidos en
   prompts.

Si necesitas aprobación de Luis, detente sin seguir gastando créditos y
explica exactamente qué asset y qué decisión necesita.
