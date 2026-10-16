# Ficha del CRM: Plan de trabajo y Entregables

AT-CRM-001 · base `e68b648` · rama `codex/crm-ficha-orden`.
Entrega local; revisión B a cargo de Eje (Claude). Sin despliegue.

Al activar Plan de trabajo o Entregables, ambas columnas de la ficha ocupan todo
el ancho: el módulo seleccionado queda arriba y las métricas debajo. Las otras
pestañas mantienen las dos columnas en escritorio.

Plan empieza con «Qué hacer ahora» (estado, fechas, documento y accesos a las
acciones) y un índice. Sus secciones plegables son Plan, Cierres de hito,
Nuevo/editar e importar, Bloques, Cambios y alcance, y Presentación. Los
formularios largos empiezan cerrados; la edición y los enlaces a una sección
abren los desplegables necesarios. Las anclas de las redirecciones se conservan.

Cada entregable muestra título, estado, versión y acciones. Vista previa del
correo, edición, nueva versión e historial tienen desplegables independientes.
Los formularios, avisos, permisos, nonces y acciones del servidor se conservan.

## Validación y entorno

- PHP 8.4.15. `AT_WP_LOAD` apunta al WordPress local indicado en el encargo.
- El bootstrap de las suites fija `HTTP_HOST=localhost:8093`. Una comprobación
  directa debe fijarlo antes de `wp-load.php` y cargar WordPress completo.
- Ejecutar los 35 `tests/plan/*-test.php` y 11 `tests/entregables/*-test.php`;
  registrar código de salida y marcador de fin por suite. `correr.sh` no
  propaga todos los códigos individuales.
- Baseline observado: 42/46 PASS. Fallas previas: `agenda-web` (límite de IP),
  `datos-contexto` (portal), `panel-render` (aviso) y `firma-contrato` (exit 255).
- Resultado posterior: 43/46 PASS, sin nuevas fallas. Persisten las tres
  históricas de contexto, panel y firma; `agenda-web` pasó esta vez, sin
  atribuir esa variación al cambio de presentación.
- Navegador: ancho de ambas pestañas 100 % del contenedor. `scrollWidth` de
  página igual a 1440, 1024, 768 y 390 px respectivamente. Diez comprobaciones
  de interacción PASS, incluidos formularios abiertos a 390 px y teclado.
- Capturas: antes/después a 1440, 768 y 390 px. Se bloqueó tráfico externo y
  POST durante QA; los gráficos y emojis de CDN no se cargaron. Esa limitación
  del entorno está registrada y no acredita QA real de dispositivos ni PROD.
- Evidencia visual privada, mediciones y resultados finales: sección «Rondas»
  del encargo compartido. Datos anónimos, sin correos ni llamadas externas.
- Superficie afectada: CRM privado en `wp-admin`. Visibilidad SEO/IA:
  `NOT_APPLICABLE`; no se agregaron rutas públicas ni permisos nuevos.

## Archivos para una eventual subida autorizada

Todos son **OBLIGATORIO**, con los siguientes destinos desde la raíz de PROD:

| Ruta local exacta | Destino relativo PROD | Orden |
| --- | --- | --- |
| `C:/wamp64/www/automatiza-tech/.worktrees/crm-ficha-orden/wp-content/themes/automatiza-tech/assets/css/plan-trabajo.css` | `wp-content/themes/automatiza-tech/assets/css/plan-trabajo.css` | 1 |
| `C:/wamp64/www/automatiza-tech/.worktrees/crm-ficha-orden/wp-content/themes/automatiza-tech/assets/js/plan-trabajo.js` | `wp-content/themes/automatiza-tech/assets/js/plan-trabajo.js` | 1 |
| `C:/wamp64/www/automatiza-tech/.worktrees/crm-ficha-orden/wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/panel.php` | 2 |
| `C:/wamp64/www/automatiza-tech/.worktrees/crm-ficha-orden/wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php` | `wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-panel.php` | 2 |
| `C:/wamp64/www/automatiza-tech/.worktrees/crm-ficha-orden/wp-content/themes/automatiza-tech/inc/entregables/panel.php` | `wp-content/themes/automatiza-tech/inc/entregables/panel.php` | 2 |
| `C:/wamp64/www/automatiza-tech/.worktrees/crm-ficha-orden/wp-content/mu-plugins/crm-ai-completo.php` | `wp-content/mu-plugins/crm-ai-completo.php` | 3 |

No subir tests, documentación, scratch, capturas, fixtures, registros de Aseo,
archivos de configuración ni credenciales. La autorización y ejecución de PROD
corresponden al flujo de entrega del encargo; este ticket sólo prepara cambios
locales para revisión. Para revertir, restaurar los mismos seis archivos desde
la revisión anterior aprobada.
