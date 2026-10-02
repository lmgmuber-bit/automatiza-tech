# Notas de revisión y coherencia (Etapa 1 del plan de trabajo)

## grupo-A.md

#### Notas de revisión (grupo A)

Cómo se verificó esta versión. Cada bloque de código de esta sección es una pieza exacta: se extrae del Markdown y se prueba etapa por etapa en una copia vacía del repo. En cada etapa, la prueba nueva falla primero por el motivo que se anota (require o función indefinida, exit 255) y pasa después de agregar la pieza, y las pruebas anteriores se vuelven a correr para detectar regresiones. Resultado: fechas 33; validación 19 → 36 → 91 → 116 → 137; cronograma 38; render 14 → 37. Total: 245 aserciones. Pasan en PHP 8.4.15 y en 8.3.28 (la versión de PROD) con `error_reporting=-1`, sin avisos de PHP. Se armaron además 8 mutantes, cada uno deshaciendo un arreglo, y cada uno hace fallar exactamente las pruebas nuevas de su arreglo. Por último, se corrieron dos pruebas al azar: 50.000 repartos (0 violaciones de proporcionalidad, suma y mínimo intactos) y 3.000 planes validados después de la tabla (0 no idempotentes).

**Altas y medias**

1. (alta, Task 2) En «Pedir cambios», lo que la IA cambiaba o agregaba quedaba con origen `luis`. Con eso se congelaban los días en las rondas siguientes. **Aplicado.** Nueva firma: `at_pt_marcar_ediciones(array $anterior, array $nuevo, string $marca = 'luis')`, y el orden de cambios usa `'ia'`. En el Ciclo E, «Carrito de compras» y «Revisión de textos» quedan `ia`. Una segunda ronda puede volver a cambiar lo que estimó la IA, y los 7 días de Luis se mantienen. Una marca desconocida vale `luis`. Mutante («marca siempre luis»): fallan 2 aserciones. El esqueleto (líneas 190-191 y 290-291) debe corregirse: va en las discrepancias.
2. (alta, contrato entre grupos) `at_pt_respetar_dias_luis` no está en el esqueleto, y la Task 7 tenía su propia función. **Aplicado en lo que corresponde al grupo A.** Las firmas nuevas y un «Orden de uso único» para borrador, cambios y panel quedan escritos en Interfaces de la Task 2. Se agregó una prueba pura del camino de borrador: una marca `luis` inventada por la IA baja a `ia`, y el par (sitio web, pruebas) recibe los 3 días de la tabla (2 y 1, origen `tabla`). Queda para otros: el orquestador copia firmas y orden al esqueleto. La versión actual de la Task 7 ya llama a `at_pt_respetar_dias_luis` (grupo-B.md:2382) y `at_pt_rest_conservar_luis` ya no aparece. Le faltan `at_pt_validar_entrada`, la marca `'ia'`, `at_pt_luis_perdidas` y la segunda validación. Además, su prueba «6)» (grupo-B.md:2288) espera `luis` y debe esperar `ia`. Todo eso va en las discrepancias.
3. (media, Task 2) El reparto no era proporcional (`at_pt_repartir(5, [3, 2, 1])` daba `[2, 1, 2]`). **Aplicado** con el código del revisor: la actividad que sube al mínimo de 1 ya no compite por los días que sobran. Pruebas nuevas: `[5, [3, 2, 1]] → [2, 2, 1]` y la proporcionalidad en los cuatro casos del revisor. Los casos anteriores y los días de las pruebas de las Tasks 3 y 4 no cambian. Mutante: fallan 2.
4. (media, Tasks 2 y 3) La tabla puede llevar el plan más allá del tope de días. **Aplicado.** Se agregó una segunda `at_pt_validar_plan` antes de calcular, en los tres caminos del orden de uso. Prueba nueva del proyecto combinado: 57 días hábiles de la IA pasan a 138 con la tabla, con el error exacto y sin plan. Otra prueba confirma que validar después de la tabla conserva días y orígenes.
5. (media, Task 2 → Task 13) Los topes no llegan a la IA. **Aplicado en Interfaces de la Task 2**, con una nota «Para la Task 13» que trae las cifras exactas: 13 bloques, 10 por bloque, 58 actividades (60 con el Arranque), 130 días hábiles, 1 a 60 días, 10 hitos, claves, y la instrucción de que la IA no mande el Arranque. Hoy grupo-E.md no menciona estos topes en los prompts: va en las discrepancias.
6. (alta, igual al 1, segundo revisor) **Aplicado junto con el 1.** Se usaron sus tres aserciones y la segunda ronda que propuso el primer revisor.
7. (media, Task 2) Si la IA renombra o quita una actividad de Luis, los días se perdían sin aviso. **Aplicado.** Nueva función `at_pt_luis_perdidas(array $anterior, array $nuevo): array`, con la prueba del revisor. En el orden de cambios, sus textos se suman a los avisos. Mutante: falla 1.
8. (media, Task 4) Una persona natural sin empresa dejaba `company_name` vacío, y el renderer lo rechaza. **Aplicado**: se usa el nombre del cliente y, si falta, el del proyecto, con la prueba del revisor. Verificado en `origin/main:renderer/src/schema.js:1-9` (exige `company_name` no vacío) y en esqueleto.md:349-350. Mutante: falla 1.
9. (media, Task 2) No había una función pura que convirtiera JSON inválido o ausente en un error. **Aplicado**: nueva función `at_pt_validar_entrada(mixed $plan, bool $borrador = false): array`, con las pruebas del revisor más una entrada de otro tipo (`42`). La Task 7 la llama con `$body['plan'] ?? null`.
10. (media, Task 2) El paso y el commit del ciclo de validación eran demasiado grandes. **Aplicado**: el ciclo se partió en B y C, así que la Task 2 queda en cinco ciclos de 5 pasos.
    - B cubre topes, ayudas de texto, JSON de la IA, listas de siempre y días por bloque: 17 aserciones y su propio commit. Falla por `at_pt_plan_de_json()`.
    - C cubre la validación: 55 aserciones. Falla por `at_pt_validar_plan()`.
    - Los dos ciclos solo agregan al final del archivo.
    - Las ayudas de texto (`at_pt_texto`, `at_pt_mostrar`, `at_pt_booleano`, `at_pt_clave_nombre`), que antes solo se probaban de rebote, ahora tienen pruebas directas.

**Bajas**

- **Nombres repetidos y actividades renombradas: aplicado.** La función es la del punto 7. El límite de los nombres repetidos queda documentado en el docblock de `at_pt_recorrer_actividades`: si la IA inserta otra actividad con el mismo nombre antes de una de Luis, la marca pasa a la primera. No hay otra forma de reconocerla, porque la IA no conserva identificadores.
- **Sin tope de avisos ni errores: aplicado.** Se guardan 25 de cada tipo, más «… y N más» con el separador de miles chileno. Dos pruebas: 1.502 avisos y 30 errores. Mutante: fallan 2.
- **Las pruebas puras no se llaman `puras-test.php` como dice el esqueleto:22: no se cambia el esqueleto** (es regla). Va en las discrepancias, y el Step 11 de la Task 4 lo aclara y da el comando real.
- **Faltaban firmas completas en Consumes: aplicado** en las Tasks 3 y 4.
- **Arranque que manda la IA: aplicado de forma pura.** `at_pt_validar_entrada(…, true)` quita los bloques «Arranque» de la IA en cualquier fase y avisa. En cambios y en el panel se conserva el Arranque guardado, porque Luis puede editarlo. Dos pruebas; mutante: fallan 2. La nota para la Task 13 pide que la IA no lo mande.
- **La aserción del tope era floja: aplicado.** Ahora exige 27 barras, 23 semanas y fin el 9-mar-2027. Se agregó el fixture del peor caso para la Task 12: 27 barras, 130 días hábiles, 27 semanas, del 5-oct-2026 al 8-abr-2027. Con un día más, error.
- **Una lista que envuelve un objeto: aplicado** (`!str_starts_with($t, '[')`), con prueba. Mutante: falla 1.
- **«Entrega estimada» cae antes de «Tu revisión»: se sigue el esqueleto.** Según esqueleto.md:183, es el fin del último bloque de Implementación, y es coherente: es el día en que AT entrega, y la revisión del cliente viene después y se ve como barra. Una prueba nueva fija ese comportamiento: entrega el 12-oct y revisión del 13 al 19-oct. Escalado al orquestador en las discrepancias. Si decide usar el fin de la revisión, el cambio es `$fin_implementacion = $termina;` y la prueba pasa a esperar el 2026-10-19.
- **Etiquetas en vez de claves: aplicado, sin aviso.** `at_pt_clave_de()` acepta la clave o la etiqueta para fase y responsable («Tú», «AutomatizaTech», «Implementación»). No lleva aviso porque el valor es inequívoco, y un aviso por actividad taparía los que importan (el tope es 25). Dos pruebas; mutante: fallan 2. Lo desconocido («el equipo», «marketing») sigue siendo error.

#### Notas de coherencia (grupo A, 29-sep)

Aplicación de las «DECISIONES DE COHERENCIA DEL ORQUESTADOR» de esqueleto.md (29-sep 16:10), que mandan sobre lo anterior. Las cuentas de las «Notas de revisión» de arriba (render 14 → 37, total 245) son de la versión anterior; las vigentes son las de esta nota.

- **D1 (marca de `at_pt_marcar_ediciones`): ya coincidía.** La firma de la Task 2 es `at_pt_marcar_ediciones(array $anterior, array $nuevo, string $marca = 'luis'): array`: `'luis'` en el panel e `'ia'` en «Pedir cambios». Las pruebas del Ciclo E lo fijan. Solo cambió el texto de Interfaces, que ahora cita D2 en vez de «lo copia el orquestador».
- **D2 (funciones adicionales y orden de uso único): ya coincidía.** Están en la Task 2 › Interfaces con la firma exacta que cita el esqueleto:
  - `at_pt_validar_entrada`, `at_pt_plan_de_json`, `at_pt_respetar_dias_luis`, `at_pt_luis_perdidas`, `at_pt_clave_de` y `at_pt_repartir`.
  - `at_pt_semanas`, en la Task 3.
  - Los topes `AT_PT_MAX_*`, con `AT_PT_MAX_DIAS_PLAN = 130`.

  El «Orden de uso único» (borrador, cambios y panel) es el mismo que en D2, paso por paso.
- **D3 (cuatro archivos de prueba): aplicado.** El Step 11 de la Task 4 ya no dice que el esqueleto nombra `puras-test.php`. Ahora nombra los cuatro archivos, `tests/plan/{fechas,validacion,cronograma,render}-test.php`. Todos los comandos del grupo ya usaban esos cuatro archivos; ningún comando usa `puras-test.php`. La única mención que queda es `tests/cierre/puras-test.php` (Task 1), y es el patrón del cierre, no un archivo de este plan.
- **D4 («Entrega estimada»): ya coincidía.** Es el fin del último bloque de Implementación, sin la revisión. La prueba de `cronograma-test.php` lo fija: entrega el 12-oct y «Tu revisión» del 13 al 19-oct. La Task 3 › Interfaces ahora cita D4 en vez de «escalada al orquestador». El renderer busca el hito por ese nombre exacto (`h.nombre === 'Entrega estimada'` en `renderGanttSlide`, grupo-D.md), y es el que produce `at_pt_calcular_fechas`.
- **D8 (garantía del contrato): aplicado.**
  - **Cambio en el código:** `at_pt_armar_render` lee `$datos['garantia_meses']` con `at_pt_entero`, que acepta un número o un texto de dígitos. Si es un entero mayor que 0, reemplaza `soporte.garantia_meses` del plan, y `soporte.mensuales` no cambia. Si falta, es 0, es negativo o no es entero, queda el valor del plan (3 por defecto). Si el plan no trae `soporte`, la garantía del contrato llega igual.
  - **Documentación:** se actualizaron Interfaces (Consumes suma `at_pt_entero` de la Task 2; Produces documenta `$datos` con sus siete claves) y el docblock.
  - **Pruebas:** 4 aserciones nuevas en el Ciclo B de la Task 4.
    - 6 meses del contrato, con 3 en el plan, da 6, y los mensuales quedan intactos.
    - `'12'` como texto da 12.
    - 0, -2, `'tres'`, `''`, `null` y 2.5 dejan el valor del plan.
    - Un plan sin `soporte` con 6 del contrato da `{garantia_meses: 6, mensuales: []}`.
  - **Mutante** (sin la línea que reemplaza): fallan exactamente 3 aserciones.
- **D9 (nombre que se muestra): aplicado en la documentación. El código ya lo cumplía.**
  - `company_name` se toma de `$datos['company_name']` tal cual. Ese valor ya viene en el orden de D9: propuesta → `nombre_proyecto`/`razon_social_cliente` → cliente. La versión de grupo-B.md de las 16:10 lo arma así en `at_pt_db_partes`.
  - El respaldo se mantiene, para que el renderer no lo rechace si llega vacío: nombre del cliente → proyecto → «Tu proyecto».
  - Interfaces y el docblock de `at_pt_armar_render` ahora explican el origen y el orden.
- **Claves del cuerpo contra el renderer (grupo-D.md): coinciden.** Sin cambios.
  - `at_pt_armar_render` produce estas 20 claves, en el orden del esqueleto: `document_type, unique_id, draft, company_name, client_name, proyecto, fecha_firma_larga, fecha_inicio, fecha_fin, semanas, metodo, fases, cronograma, necesitamos_de_ti, reuniones, soporte, portal_url, agenda, image_briefs, images`. Es la misma lista de la Task 11 › Interfaces › Consumes.
  - `validatePlanPayload` exige `unique_id`, `company_name` y `proyecto` como textos no vacíos; `fases` como arreglo no vacío; `cronograma.inicio`/`fin` como fechas AAAA-MM-DD con fin ≥ inicio; `cronograma.barras` como arreglo; e `image_briefs` como arreglo si viene. Todo eso sale de `at_pt_calcular_fechas` y de este armado.
  - `renderPlanHtml` lee lo siguiente, y todo llega con esas claves:
    - `metodo.hechas` y `metodo.actual`;
    - `soporte.garantia_meses` y `soporte.mensuales`;
    - `agenda.whatsapp_url` y `agenda.web_url`;
    - `portal_url`, `semanas`, `fecha_firma_larga`, `necesitamos_de_ti` y `reuniones[].nombre/detalle`;
    - `cronograma.barras[].{fase, etiqueta, tipo, responsable, desde, hasta}` y `cronograma.hitos[].{nombre, fecha}`;
    - `fases[].{clave, titulo, descripcion, bloques[].{nombre, entregable, entrega, actividades[].{nombre, detalle, responsable, dias_habiles, en_paralelo, desde, hasta}}}`.
  - **Comprobación real:** el bloque `validatePlanPayload` de grupo-D.md, corrido con `node` sobre cuerpos armados por el `puras.php` de este Markdown (`tmp-A3/sonda-schema.py`), dio estos resultados:
    - versión final con empresa y cliente vacíos y garantía `'6'`: `valid: true`, `company_name` = el proyecto, `soporte.garantia_meses` = 6 e `images` como objeto;
    - vista previa: `valid: true`;
    - plan vacío: rechazado con «fases» y «cronograma», como anota la prueba.
- **Verificación de esta versión** (`tmp-A3/extraer.py` y `tmp-A3/verificar.py`):
  - Se extrajeron del Markdown los 18 bloques `php` y las 8 anclas; cada ancla es el final real de `puras.php` en su etapa y aparece una sola vez.
  - En una copia vacía del repo, cada etapa falla primero por el motivo anotado (exit 255) y pasa después:
    - fechas: 33;
    - validación: 19 → 36 → 91 → 116 → 137;
    - cronograma: 38;
    - render: 14 → 41.
  - Total: **249 aserciones**, sin regresiones y con `php -l` limpio.
  - Las cuatro pruebas dan `TODO OK` en PHP 8.4.15 y 8.3.28 con `error_reporting=-1`, sin avisos.
- **Pendiente para el orquestador (fuera del grupo A):**
  - **El campo «Garantía» del panel (Task 9) no llega al documento.** El panel deja editar `garantia_meses` (0 a 24), según grupo-C.md:1386 y la prueba de :2096. Pero con D8 el documento siempre muestra la garantía del contrato, porque la Task 5 manda siempre un entero: 3 si el contrato no la dice. El valor que Luis edite no aparece en el documento, salvo que el contrato diga 0. Hay dos salidas: dejar el campo de solo lectura con la leyenda «viene del contrato», o que la Task 5 mande 0 cuando el contrato no traiga el marcador, para que gane el del plan.
  - **Garantía 0.** `at_pt_db_garantia` (grupo-B.md) acepta 0 («sin garantía»). D8 dice «entero > 0», así que un contrato con 0 deja el valor del plan. La IA recibe ese 0 en `/contexto`, así que el plan normalmente también dice 0. Si se quiere que 0 del contrato gane siempre, el cambio es `>= 0` en `at_pt_armar_render`, y su prueba pasa a esperar 0.
  - **Menor, no bloquea:** el fixture del renderer (grupo-D.md:244) escribe `(c%C3%B3digo …)` con los paréntesis sin codificar. `rawurlencode` de WordPress los manda como `%28`/`%29`. Las dos URL son válidas y `urlSegura` acepta ambas, porque solo exige https. No hace falta cambiar nada.
- **D8 ajustada por el orquestador (29-sep, «el contrato manda», también con 0): aplicado.** `at_pt_armar_render` ahora reemplaza `soporte.garantia_meses` con cualquier entero `>= 0` de `$datos['garantia_meses']` (número o texto de dígitos), **incluido el 0**: si el contrato no promete garantía, el documento tampoco. Solo si la clave falta o no es un entero válido (negativo, `'tres'`, `''`, `null`, 2.5) queda el valor del plan. Se ajustaron el código, el docblock e Interfaces (que ahora dicen que el campo del panel es de solo lectura, Task 9). Pruebas del Ciclo B: el caso 0 sale de la lista «queda la del plan» (quedan 5 valores) y hay 2 aserciones nuevas: contrato 0 (y `'0'`) → garantía 0 aunque el plan diga 3, con los mensuales intactos; y sin la clave `garantia_meses` → la del plan. Cuentas vigentes (reemplazan las de la nota D8 de arriba: «mayor que 0», 4 aserciones, render 14 → 41 y 249): render **14 → 43**, total **33 + 137 + 38 + 43 = 251**, verificadas con `tmp-A3/extraer.py` + `tmp-A3/verificar.py` sobre este Markdown (18 bloques, 8 anclas bien, cada etapa falla primero con exit 255 y pasa después, sin regresiones, `php -l` limpio) y con las cuatro pruebas en `TODO OK` en PHP 8.4.15 y 8.3.28 con `error_reporting=-1`. Mutante (volver a `> 0`): falla exactamente 1 aserción, la del contrato 0. Con esto quedan resueltos los dos pendientes de garantía de arriba (campo del panel y garantía 0).

## grupo-B.md

#### Notas de revisión (grupo B)

Corrección del 29-sep sobre las dos revisiones independientes. Verificación: los 78 bloques de código de esta sección
pasan `php -l` / `py_compile`; los archivos armados desde los bloques (Create + «Agregar al final») corren en un WordPress
falso en memoria (ganchos, opciones con `pre_option_*`, servidor REST con clave, JSON inválido → 400 y un `$wpdb` con las
consultas del módulo) junto con el `puras.php` reconstruido desde `grupo-A.md` vigente (11:23). Resultado: `datos-guardar`
19, `datos-contexto` 17, `disparador` 17, `firma` 10, `rest-contexto` 8, `rest-borrador` 47 y `rest-render` 23 líneas
`ok`, todas `TODO OK`. Las mismas pruebas nuevas contra el código anterior fallan donde deben (`datos-contexto` 6,
`rest-borrador` 5 y `rest-render` 2 FALLAS), y la mutación «calcular fechas sin feriados» hace fallar la prueba 1) de la
maqueta del 19 al 22. `n8n-local.py` y `gancho-firma.py` se probaron sobre copias (CRLF respetado, `php -l` limpio, la
segunda corrida se niega); la expresión de la prueba 0 calza 1 vez en la copia editada y 0 en el original; el agregado a
`cargar.php` es idempotente también si el archivo no termina en salto de línea. `datos-wp-test`, `datos-ajustes-wp-test` y
`firma-contrato-wp-test` necesitan MySQL y `ContractService` reales: se lintearon, no se corrieron.

1. **Alta · Task 7 no seguía el «Orden de uso único» del Grupo A** — Aplicado. `at_pt_rest_borrador` usa
   `at_pt_plan_de_json` + `at_pt_validar_entrada($entrada, $origen === 'borrador')`, `at_pt_respetar_dias_luis`, en
   «cambios» suma `at_pt_luis_perdidas` y marca con `at_pt_marcar_ediciones(…, 'ia')`, en «borrador» aplica la tabla, y
   **siempre** valida otra vez antes de calcular fechas (con `$v2['plan']`). Además, el rechazo de la segunda validación
   lleva su propio prefijo («El plan no cabe con la tabla de tiempos y los días que fijó Luis: …»), porque ahí la IA no tuvo
   la culpa y «Reintentar» daría lo mismo. Pruebas: 6) Maqueta queda `ia`; 8) tope de 130 → 422, nada guardado; 9) el
   «Arranque» de la IA se cambia por el fijo, con aviso; 10) (nueva) la IA renombra una actividad de Luis → aviso en la
   respuesta y en la nota. Interfaces de la Task 7 con las firmas del Grupo A.
2. **Media · `/error` tumbaba borradores buenos y planes «aprobando»; borrador tardío en «error» con contenido** —
   Aplicado. `at_pt_rest_error` solo pasa a `error` desde `generando` o `cambios` (verificado: los flujos 1 y 2 llaman a
   `/error` ante cualquier respuesta que no sea 200 con ok, `grupo-E.md:1210-1212` y `:1404`; el 3 nunca lo llama). Un
   `borrador` que llega con el plan en `error` y con contenido responde 409 (el panel, `grupo-C.md:2118-2128`, nunca pide
   un borrador en ese caso). Prueba 7) de `rest-borrador` reemplazada; dos pruebas nuevas en `rest-render` (borrador y
   «aprobando», que después sí pasa a «listo»). Es más estricto que `esqueleto.md:298`: queda en discrepancias.
3. **Media · la firma por línea de comandos avisaba al n8n de PROD** — (Superado por D14: el paso vive ahora en la Task 0,
   Step 4b, y la Task 6 solo lo referencia.) Aplicado como Step 0 de la Task 6
   (`n8n-local.py`, fuera del repo, con `if (!defined(…))`). Verificado: `wp-config.php` de la raíz tiene el ancla una vez,
   ningún `AT_N8N_PLAN_*`, y está en **CRLF** (no LF: se corrigió el Expected del Step 0 tras probarlo sobre una copia).
4. **Media · la empresa salía del nombre del proyecto o de la propuesta** — (Superado por D9: ver «Notas de coherencia».)
   Aplicado: razón social del contrato → empresa
   de la ficha → empresa de la propuesta → nombre de la ficha; nunca `nombre_proyecto`. `pt_contrato()` trae
   `'razon_social_cliente' => '[PRUEBA] Razón Social SpA'`; `datos-contexto` 1), 2) y 3) lo afirman. Ningún otro grupo
   usa `pt_contrato()` ni `at_pt_db_partes()` (Grupo C arma su propia agenda).
5. **Media · la prueba de feriados de la ruta no podía fallar** — Aplicado: feriados 12 y 20 de octubre y aserción de la
   maqueta del 19 al 22. Confirmado por mutación (sin feriados, esa línea falla).
6. **Media · «textos enormes» aceptaba dos resultados** — Aplicado: texto enorme → 422 con «demasiado largo»; texto de
   1500 → se acorta a 600 con el aviso exacto del Grupo A en la nota y en la respuesta (cubre la rama de avisos).
7. **Media · la defensa del 409 se anulaba sola** — Aplicado junto con el 2 (mismo arreglo y las pruebas de «borrador» y
   «aprobando»).
8. **Media · Step 0 en la Task 6 y `sin-red.php` que deja pasar localhost** — Aplicado: Step 0 (hallazgo 3) y
   `sin-red.php` ahora corta toda llamada, también a localhost (en `tests/cierre/` no hay `wp_remote`, `curl` ni
   `localhost` salvo `HTTP_HOST`; por línea de comandos las URL de n8n del cierre salen de `wp-config.php` o de su defecto,
   que son de PROD, así que nada legítimo iba a localhost). Lo que no se verificó: si el `AT_REST_SECRET` del
   `wp-config.php` local es distinto del de PROD (no se leen secretos); con el Step 0 ya no importa para el plan.
9. **Baja · rubro** — Aplicado en WordPress (barato y aditivo): `at_pt_contexto()` agrega `rubro` al final, desde
   `crm_clientes.rubro` (existe en `crm-ai-completo.php:231` y en la base local, `varchar(100)`). El modelo no lo recibe
   hasta que el Grupo E lo sume a su lista (`grupo-E.md:578` y `:1152`): decisión del orquestador (discrepancias).
10. **Baja · aviso síncrono de 15 s dentro de la firma** — Aplicado: `at_pt_llamar_n8n(…, int $timeout = 15)` y
    `at_pt_iniciar_borrador(…, int $timeout = 15)` (opcionales, compatibles con el esqueleto); el oyente usa 5 s y
    `firma-wp-test` 1) lo afirma. El `max_execution_time` de Hostinger no se verificó.
11. **Baja · `cargar.php` se reescribía entero** — Aplicado: las Tasks 6 y 7 agregan su línea con `grep -qxF` (probado
    idempotente y con archivo sin salto final).
12. **Baja · la prueba 0 miraba el texto fuente** — Aplicado: espía en `at_contrato_firmado` (prioridad 1) que exige el
    contrato ya `signed` y exactamente 2 correos (`contract-mailer.php:90` y `:113`, un `wp_mail` cada uno); la expresión
    queda de respaldo. Paso rojo: 5 FALLAS.
13. **Baja · la prueba 2 de `rest-render` no distinguía nada** — Aplicado: compara con la 1 (con propuesta hay
    `propuesta_uid`, sin ella no) y el mensaje dice que n8n no tiene de dónde reutilizar portada y cierre.
14. **Baja · mismo aviso síncrono (duplicado del 10)** — Aplicado con el 10.
15. **Baja · alcance de los commits y regresiones** — Aplicado: `pt_pedir()` pasa al Step 1 de la Task 7 y `pt_plan_ia()`,
    `pt_actividades()`, `pt_actividad()`, `pt_editar_actividad()` al Step 7 (cada una con la prueba que la usa; los
    commits suman `tests/plan/datos-prueba.php`); Consumes con firmas completas (incluye `at_pt_db_partes`); la Task 6
    corre solo `tests/cierre/contrato-wp-test.php` (la única que llama a `sign_as_client`) y la Task 7 suma `rest` y
    `contrato` del cierre con `sin-red.php`.

#### Notas de coherencia (grupo B, 29-sep)

Ronda sobre las «DECISIONES DE COHERENCIA DEL ORQUESTADOR» del esqueleto (D1-D17). Respaldo de la versión anterior:
`tmp-B2/grupo-B.antes-1610.md`.

**Verificación.** `tmp-B2/armar.py` extrae los 76 bloques de esta sección (eran 78: se fueron el Python y el bash del
Step 0 de la Task 6) y todos pasan `php -l` (PHP 8.4.15) o `py_compile`; arma los 16 archivos (Create + «Agregar al final»)
y todos pasan `php -l`. `tmp-B2/verificar-1610.py` corre las pruebas extraídas sobre el WordPress falso en memoria
(`correr2/`, mismo `fake-wp.php` de la ronda anterior) con el `puras.php` reconstruido desde el `grupo-A.md` de las 16:12
(que ya trae D8): `datos-guardar` 19, `datos-contexto` 22, `disparador` 17, `firma` 10, `rest-contexto` 8,
`rest-borrador` 49 y `rest-render` 27 líneas `ok`, todas `TODO OK`, sin avisos con `error_reporting=-1`, en PHP 8.4.15 y
8.3.28 (la de PROD). Rojo: las pruebas nuevas contra el código de la ronda anterior dan `datos-contexto` 9, `disparador` 1,
`rest-borrador` 1 y `rest-render` 4 FALLAS, cada una por lo que cambió. Mutantes, todos detectados: sin filtrar el aviso de
proyecto (falla 12), `marcar_ediciones` sin `'ia'` (fallan 6 y 10), sin la segunda validación (falla 8), tope de garantía en
99 (falla la de «36 meses»). Siguen sin correr (necesitan MySQL y `ContractService` reales): `datos-wp-test`,
`datos-ajustes-wp-test` y `firma-contrato-wp-test`; ninguna cambió en esta ronda.

**Qué cambió, por decisión.**
- **D2 (Task 7).** `at_pt_rest_borrador` ya no decodifica ni retoca el plan antes de validar: llama
  `at_pt_validar_entrada($p['plan'] ?? null, $origen === 'borrador')` y sigue el orden único al pie de la letra (borrador:
  días de Luis → tabla; cambios: días de Luis → `at_pt_luis_perdidas` → `at_pt_marcar_ediciones(…, 'ia')`, también si no
  hay plan guardado, sin la rama que antes aplicaba la tabla en ese caso) → `at_pt_validar_plan` otra vez (422 con la nota
  «El plan no cabe con la tabla de tiempos y los días que fijó Luis: …») → fechas. El nombre del proyecto que falte se
  completa con el del contrato **después** de validar y se quita el aviso «El plan no trae el nombre del proyecto.» (texto
  exacto del Grupo A: si A lo cambia, solo reaparece ese aviso). Pruebas nuevas: 11) plan en texto con cerco ```` ```json ````
  y 12) plan sin proyecto. La 6) ya esperaba `'ia'`.
- **D5.** Confirmado sin cambios: `/error` solo pasa a `error` desde `generando` o `cambios`; `borrador` tardío (plan que no
  está en `generando`, o en `error` con contenido) → 409.
- **D6.** Confirmado `at_pt_llamar_n8n(…, int $timeout = 15)`, `at_pt_iniciar_borrador(…, int $timeout = 15)` y el oyente
  con 5 s. Corregido: `at_pt_pedir_render` mandaba `{id, modo, aviso}`; ahora `{id, codigo, modo, aviso}` (pruebas
  `disparador` 4 y `rest-borrador` 1).
- **D7.** `/contexto` → `contrato.garantia_meses` (entero; el primer número de `garantia_meses_servicio` si está entre 0 y
  24; si no, 3), con `crm_cliente_id` y `rubro` que ya estaban. Nueva interna `at_pt_db_garantia(array $ph): int`.
- **D8.** `at_pt_datos_render()` entrega `garantia_meses` (séptima clave). `rest-render` 1) comprueba de punta a punta que
  un contrato de 6 meses llega como 6 al renderer aunque el plan diga 3 (depende de `at_pt_armar_render` del Grupo A, que
  ya lo hace en el `grupo-A.md` de las 16:12). `pt_contrato()` acepta `ph_mas` para sumar marcadores a los de defecto.
- **D9.** `at_pt_db_partes()`: empresa = `company_name` de la propuesta → `nombre_proyecto` → `razon_social_cliente` →
  nombre del cliente (que a su vez termina en «Cliente»): nunca vacía. Se quitaron de la cadena la empresa y el nombre de la
  ficha operativa. Vale igual para `company_name` del render y `empresa` del contexto. Pruebas 1), 2), 2b) y 3) de
  `datos-contexto`, con y sin propuesta. Nota: en PROD `nombre_proyecto` del contrato ya suele ser el nombre de la empresa
  (`inc/cierre-cliente/puras.php:420`), así que sin propuesta el título y la línea de empresa de la portada pueden repetirse;
  es lo que fija D9.
- **D10.** `GET /render` responde `{ok, render, propuesta_uid, crm_cliente_id, estado}` y la documentación usa `&modo=`
  (Produces y el comentario de `rest.php`). `POST /vista` con `modo: 'draft'` y el plan en `aprobando`, `listo` o `enviado`
  no toca nada —ni enlaces ni nota— y responde `{ok, estado}`: además de lo que pide D10, tampoco anota el fallo de una
  vista previa vieja, para no ensuciar la nota de un plan que espera o ya tiene su versión final. Pruebas en `rest-render` 1)
  y 4).
- **D14.** Se quitó el Step 0 de la Task 6 (script y comandos); queda una línea que referencia la Task 0, Step 4b, con una
  comprobación de solo lectura (`grep -c`). También se ajustaron «Files» y el párrafo «Ojo desde esta tarea».

**Cuerpos y respuestas contra `grupo-E.md` (lo que E debe cambiar).**
1. `grupo-E.md:76-77` (Consumes de la Task 13) dice que los webhooks 1 y 2 reciben `{"id"}`: son `{id, codigo}` (D6). Los
   flujos leen solo `body.id`, así que funcionan igual; es texto.
2. `grupo-E.md:1867-1868` (Consumes de la Task 14) dice `{id, modo, aviso}` para `plan-v1-render`: ahora
   `{id, codigo, modo, aviso}`. El flujo lee `id`, `modo` y `aviso`: compatible; es texto.
3. `grupo-E.md:1869-1870`: `/render` ahora trae `estado`. **Falta en E el comportamiento de D10:** en «3 Render», después de
   «Leer render», si `modo` es `draft` y `estado` ∈ `aprobando`, `listo`, `enviado`, terminar sin llamar al renderer (no
   pisar `/p/<codigo>/`) y sin `/vista` ni correo. WordPress ya ignora esa vista si llega, pero el renderer igual
   sobrescribiría los archivos publicados.
4. `/contexto`: `contrato.garantia_meses` ya llega; `CODE_PEDIDO` pasa `contrato` entero y `PROMPT_PLAN` ya dice
   «garantia_meses = contrato.garantia_meses si viene», así que no hay que tocar nada. Conviene actualizar la lista de
   `grupo-E.md:78-82`.
5. Sugerencia (no obligatoria): «¿Espera respuesta?» de «1 Borrador» (`grupo-E.md:1372`) acepta `error`; WordPress responde
   409 a un borrador con el plan en `error` **y con contenido** (D5). Sumar `&& !(estado === 'error' && plan_actual)` ahorraría
   una llamada a OpenAI en ese caso; hoy no rompe nada porque E trata el 409 sin `/error` ni correo.
6. Sin diferencias en `POST /borrador` (cuerpo `{plan, origen}`; 200/422/409/500 con `{ok, errores, avisos}`), `POST /vista`
   (`{modo, ok, view_url, pdf_url, faltan, nota}` → `{ok, estado}`) ni `POST /error` (`{nota}` → `{ok, estado}`).

**Para otros grupos.**
- **Grupo A:** nada nuevo; su `at_pt_armar_render` de las 16:12 ya prefiere `$datos['garantia_meses']` (D8). Si cambia el
  texto «El plan no trae el nombre del proyecto.», avisar aquí (la ruta lo filtra por texto exacto).
- **Grupo C:** sus pruebas miran solo `cuerpo.modo`/`cuerpo.aviso` del render y `cuerpo.id`/`cuerpo.codigo` del borrador y
  los cambios: el `codigo` nuevo en el render no las afecta. `at_pt_datos_agenda` (`grupo-C.md:3024`) arma su
  `company_name` desde la ficha del CRM y la razón social; es el formulario de la reunión, no la portada, y no está atado a
  D9: queda a criterio del orquestador si también debe preferir el nombre comercial de la propuesta.
- **Grupo F (Task 15):** el simulador (`grupo-F.md:152-185`) debería imitar D10: no renderizar un `draft` si `/render`
  devuelve `estado` `aprobando`, `listo` o `enviado`. Ya usa `&modo=`.

## grupo-C.md

#### Notas de revisión (grupo C)

Ronda del 29-sep. Cada arreglo se comprobó con el código **real** de los grupos A, B y C, sacado de los Markdown
actuales (`grupo-A.md` de las 11:23 y `grupo-B.md` de las 11:52), en un sitio de prueba propio en el scratchpad
(`plan-trabajo/tmp-C2/sitio`, copia del sitio del revisor con la tabla renombrada a `zz_c2_planes`). Resultados:
ajustes 21 `ok`, panel-render 32, panel-js 15, panel-acciones 48, ficha-crm 7 y agenda 23 (146 `ok`, 0 `FALLA`);
los pasos rojos dan exactamente la salida escrita en el plan; las dos pruebas del cierre con `sin-red.php`, `TODO OK`;
0 filas `[PRUEBA]` de estas pruebas en la base al terminar, también después de una corrida que se cayó con error fatal.

**Medias**

1. *Ficha de un id que no existe → `TypeError`* (Task 9). **Aplicado.** Los Steps 21 y 22 llevan
   `is_array($cliente) && …` en el botón y en el panel. La prueba de la ficha suma «ficha de un id que no existe: se
   dibuja como antes, sin la pestaña y sin error fatal». Steps 20 y 23 con 5 FALLAS y 7 `ok`. Mutante (sin
   `is_array`): la prueba termina con error fatal (exit 255) después de 5 `ok`.
2. *Detalle de cada actividad y `soporte.mensuales` sin campo en el panel (decisión 11)* (Task 9). **Aplicado.**
   - Campo `.at-pt-a-detalle` (máximo 300, el tope de la Task 2) en cada actividad, sin `data-detalle`, y su CSS.
   - El JS lo lee con `valor()`.
   - `textarea name="mensuales"` en los textos.
   - `at_pt_accion_guardar` y `at_pt_plan_desde_panel` lo guardan.
   - Pruebas: dos de render y dos de acciones (mensuales sin líneas vacías; detalle guardado sin cambiar el origen de
     los días). Mutante (ignorar `mensuales`): falla su aserción.
3. *Variables y carpeta que no pasan de un bloque a otro* (Tasks 8-10). **Aplicado.** Los 29 bloques `bash` empiezan
   con `cd /c/wamp64/www/automatiza-tech/.worktrees/plan-trabajo` y los que usan variables llevan la línea completa
   (`PHP`, `SCR`, `export AT_WP_LOAD`). Lo comprobó un script que recorre todos los bloques. Se quitó «Mismos comandos
   de entorno…».
4. *El proceso hijo apuntaba al n8n de PROD si fallaba su filtro* (Task 8). **Aplicado.** `accion-wp-test-run.php` fija
   `AT_N8N_PLAN_*` a `http://127.0.0.1:9/…` antes de cargar WordPress, igual que `wp-bootstrap.php`. Las aserciones
   siguen pasando: comparan con `plan-v1-…`.
5. *El JS no tenía prueba automatizada* (Task 9). **Aplicado.** Se agregó `tests/plan/panel-js-wp-test.php` con tres
   pasos: rojo en el Step 9 y verde en el Step 11, antes y después de escribir el JS en el Step 10.
   - Dibuja la pestaña con el servidor real y la abre en Chrome o Edge sin ventana (`--headless=new --dump-dom`), sin
     servidor web.
   - Revisa 13 cosas en el navegador. Entre ellas, que `serializar()` sea idéntico a `ptc_plan_json()`: lo que manda la
     prueba de acciones, que ahora vive en las fixtures en vez de `plan_json_de()`.
   - También revisa el bloqueo con cambios sin guardar, el `confirm` de Aprobar, «+ Actividad», «+ Bloque», ✕, que los
     días viajen como número y que se abra la pestaña con `#tab-plan`.
   - Tres mutantes del JS la hacen fallar: días como texto, origen fijo y sin bloqueo.
6. *Pruebas sin red de seguridad sobre la base compartida* (Tasks 9-10). **Aplicado.**
   - `fixtures-panel.php` registra `ptc_limpiar()` al cerrar el proceso, salvo que se defina `PTC_CONSERVAR`, que usa
     `verificar-panel.php`. Además corta `wp_mail` con `pre_wp_mail` y vacía `ptc_creado` al limpiar.
   - `ajustes-wp-test.php` devuelve `at_pt_duraciones` en una función de cierre.
   - `panel-acciones-wp-test.php` suma el feriado a los que ya había, en vez de reemplazarlos, y los devuelve en una
     función de cierre.
   - La duplicación con las `pt_*` de la Task 5 se mantiene, explicada en el Step 1: la pestaña y la agenda necesitan
     teléfono en el CRM y en el contrato, planes sembrados con la tabla y reuniones que borrar.
7. *`at_pt_marcar_ediciones(…, 'ia')` en «Pedir cambios» de la REST* (Task 7). **No aplica al grupo C.** El grupo B ya
   lo corrigió en `grupo-B.md:2561`. La pestaña muestra el origen tal como viene, con las etiquetas de
   `at_pt_origenes()`.

**Bajas**

1. *Orden validar → marcar → validar → fechas; listas de A* (Tasks 8-9). **Aplicado.**
   - `at_pt_accion_guardar` valida otra vez después de marcar.
   - El origen se muestra con `at_pt_origenes()`: «Tabla de tiempos», «IA · revisar», «Editado por Luis».
   - `at_pt_columnas_duracion()` se arma con `at_pt_etapas_tabla()` y `at_pt_etapas()`.
2. *Nota vieja, `at_pt_guardar()` y `at_pt_cambiar_estado()` sin revisar* (Task 9). **Aplicado.**
   - Guardar escribe `nota = ''`.
   - Si la escritura falla, vuelve con `error_guardar`, un mensaje nuevo.
   - Cambios, Aprobar, Guardar desde «listo» o «error» y Reintentar con plan vuelven con `transicion` si el cambio de
     estado se perdió.
   - Guardar cambia el estado antes de escribir el plan. Así un plan «listo» nunca queda con el contenido cambiado.
   - Prueba: Guardar con n8n caído deja la nota; el siguiente Guardar con n8n arriba la borra. El mutante sin
     `'nota' => ''` falla.
3. *Fragilidad de las fixtures* (Task 9). **Aplicado.** Es lo mismo que el hallazgo medio 6; `verificar-panel.php`
   define `PTC_CONSERVAR`.
4. *Dos maneras de saber el cliente del CRM de un plan* (Tasks 9-10). **Aplicado.**
   - `at_pt_crm_de_plan()` se usa en `at_pt_accion_plan` y en `at_pt_datos_agenda`.
   - Pruebas: un plan con `crm_cliente_id` viejo vuelve a la ficha del cliente enlazado hoy, y un plan guardado sin
     cliente del CRM precarga sus datos. El mutante que usa solo el valor guardado falla.
5. *Textos que no calzan con B; arreglos del sitio de prueba en la Task 9* (Tasks 8-9). **Aplicado en parte.**
   - Aplicado: Interfaces con `{id, codigo}` y los parámetros `$timeout` y `$marca`; `cargar.php` «lo crea la Task 5 y
     las Tasks 6 y 7 le suman una línea» (la Task 7 no lo reemplaza: agrega `rest.php` con el mismo patrón, que ahora
     usan también las Tasks 8 y 9); Reintentar ya no cambia el estado antes de `at_pt_iniciar_borrador()`.
   - Cambios manda `{id, codigo}` con la nota «No se pudo pedir los cambios: …», como fija la Task 6.
   - No aplicado aquí: mover (a) y (b) del Step 25 a la Task 0, porque la Task 0 no es de este grupo. Quedan como
     comprobación idempotente y van en las discrepancias para el orquestador.
6. *Aviso de «Tabla guardada» que prometía de más* (Task 8). **Aplicado.** Se usa el texto propuesto.
7. *Nota vieja al guardar en borrador* (Task 9). **Aplicado.** Es lo mismo que la baja 2.
8. *Aserciones débiles* (Task 9). **Aplicado.**
   - El selector exige cuál plan va marcado.
   - Nuevas pruebas: «Guardar desde listo vuelve a borrador» y «una fase desconocida: no se guarda».
   - El encabezado de las acciones es cierto: ahora todas corren con un cliente sin correo y un contrato sin propuesta.
9. *`verificar-panel.php` sin revisar la validación; plan de 18 semanas; regresión incompleta* (Tasks 9-10). **Aplicado.**
   - Revisa `$v['ok']` y termina con exit 1 y los errores.
   - Módulos de 2 y 3 días: 16 barras y 14 semanas, del 5-oct-2026 al 6-ene-2027, medido con el código real.
   - Guarda el estado antes de lo que puede fallar.
   - El Step 9 de la Task 10 corre `tests/plan/*-test.php` entero y las del cierre con `sin-red.php`.
   - En el arnés propio fallaron dos pruebas de B, y ninguna por el grupo C. `datos-wp-test` busca la opción
     `at_plan_schema`, que el arnés renombró. `firma-contrato-wp-test` necesita el gancho de `contract-service.php`, que
     el arnés no aplica.
10. *Pasos demasiado grandes; firmas inexactas* (Task 9). **Firmas: aplicado. Partir los Steps 4 y 16: rechazado.** Son
    bloques de código ya probados que se copian enteros. Partirlos en tres ciclos multiplica los pasos rojos sin
    agregar ninguna aserción que falle por un motivo distinto. El ciclo rojo-verde por comportamiento sí se agregó
    donde faltaba: assets (Step 6), JS (Steps 8-11), ficha (Steps 19-23) y agenda (Steps 1-8).

#### Notas de coherencia (grupo C, 29-sep)

Se aplicaron las «DECISIONES DE COHERENCIA DEL ORQUESTADOR» de `esqueleto.md` (29-sep 16:10). Todo se comprobó con el
arnés `plan-trabajo/tmp-C2/armar.py`, que arma un sitio con el código real de los grupos A, B y C sacado de los Markdown
actuales:
- `php -l` sin errores en los 14 archivos armados. Son 9 pruebas y ayudas, `ajustes.php`, `panel.php` y `cargar.php`,
  más `crm-ai-completo.php` y `admin-followup-meetings.php` ya parchados.
- También pasan `php -l` dos bloques sueltos: el del Step 16 y `verificar-panel.php`.
- `node --check` pasa en `plan-trabajo.js`.
- Pruebas: ajustes 21, panel-render 32, panel-js 15, panel-acciones 48, ficha-crm 7 y agenda 23. Son 146 `ok`, 0
  `FALLA` y `TODO OK` en las seis.

- **D1/D2 (orden del panel): confirmado y hecho explícito.**
  - `at_pt_accion_guardar()` ya seguía el orden `validar_plan` → `marcar_ediciones` → `validar_plan` →
    `calcular_fechas`.
  - Ahora pasa la marca explícita, `at_pt_marcar_ediciones($anterior, (array) $v['plan'], 'luis')`, con la firma de 3
    parámetros de grupo-A.md:1617. La introducción y las Interfaces de la Task 9 dicen lo mismo.
  - Mutante `'ia'`: la prueba de acciones da 2 FALLAS («Guardar: la actividad editada queda con 12 días y origen
    «luis»» y «volver a guardar…»).
- **D14 (Step 25).**
  - Los puntos (a), `wp-admin` como copia, y (b), `auth_redirect()` en el router, ya no se aplican en esta tarea.
    Quedan como una referencia a la Task 0, Steps 3 y 4, con una comprobación que no cambia nada:
    `test -L …/wp-admin` y `grep -c "function auth_redirect() {}" router.php`, con Expected `wp-admin copia` y `1`.
  - Se probó que `test -L` de Git Bash reconoce una unión de Windows: se armó una unión de prueba y se borró.
  - (c) y lo demás no se tocan.
- **D15: ya estaba.** Los Steps 21 y 22 llaman `at_pt_render_pestana($cliente)` solo con
  `is_array($cliente) && function_exists('at_pt_render_pestana') && current_user_can('manage_options')`.
- **D16.**
  - El Step 8 dice que `panel-js-wp-test.php` necesita Chrome o Edge (variable `CHROME` o las rutas de Windows) y que
    sin navegador sale con exit 2. El Step 9 explica cómo distinguir eso del paso rojo.
  - El Step 16 dejó de usar heredoc, porque su bloque tiene 8 barras invertidas en tres expresiones regulares. Ahora
    se escribe con Write en `$SCR/plan-trabajo/panel-acciones.php.txt` y se anexa con `echo >> … && cat … >> …`.
  - Un `grep -cF '\r\n|\r|\n'` comprueba que las barras llegaron (Expected `1`).
  - El heredoc del Step 3 de la Task 10 se deja: no tiene barras invertidas.
- **Llamadas a los grupos A y B.** `tmp-C2/aridad.py` revisó las 91 llamadas del código de grupo-C.md a las 30
  funciones de A y B contra sus firmas en grupo-A.md y grupo-B.md: 0 no calzan. Las 30 existen con el nombre y los
  tipos que dicen las Interfaces de las Tasks 8, 9 y 10. Ninguna función que produce el grupo C está definida también
  en A o en B.

Discrepancias pendientes (para el orquestador):
1. **D2, segunda validación en el panel.** D2 dice que, si la segunda validación falla, el plan queda en `error` con
   una nota. En el panel no se hizo así: guarda los errores con `at_pt_guardar_detalles()`, vuelve con
   `pt_msg=invalido` y no cambia el estado ni el plan guardado.
   - Motivo: ahí está Luis, que corrige y vuelve a guardar; pasar a `error` por un número mal escrito le quitaría el
     borrador.
   - La prueba «días 200: no se guarda, no llama a n8n y avisa» fija este comportamiento.
   - Se entiende que la frase de D2 vale para la REST (borrador y cambios de la IA). Si el orquestador la quiere
     también en el panel, se cambian dos líneas de `at_pt_accion_guardar()` y esa aserción.
2. **`verificar-panel.php` (Step 25 c).** Es un script de datos del scratchpad, no el panel ni la REST, y todavía
   llama `at_pt_marcar_ediciones($pl, $v['plan'])` con la marca por defecto y sin segunda validación.
   - Se dejó así porque está medido (16 barras y 14 semanas) y no guarda nada que escriba Luis.
   - Si se quiere el orden único también ahí, basta con agregar `'luis'` y un `at_pt_validar_plan()` antes de
     `calcular_fechas`.
3. **Task 0, Step 3 (grupo-0).** Copia `wp-admin` solo si no existe (`if (-not (Test-Path …))`). Si una corrida vieja
   dejó una unión, la Task 0 no la arregla.
   - La comprobación del Step 25 lo detecta y manda de vuelta a la Task 0.
   - Conviene que el Step 3 borre la unión antes de copiar, como hacía el antiguo (a):
     `if ($j.LinkType -eq 'Junction') { $j.Delete() }`.
4. **Nota vieja superada.** El punto 5 de «Bajas» en las «Notas de revisión (grupo C)» dice que (a) y (b) del Step 25 «no se
   aplicaron aquí». D14 ya los movió a la Task 0: esa frase queda superada por esta nota.

- **Garantía de solo lectura y orden D2 en `verificar-panel.php` (decisión del orquestador, 29-sep): aplicado.** En la Task 9 el campo «Meses de garantía» ya no se edita ni se guarda: es un `<input type="number" class="at-pt-garantia" readonly>` **sin `name`** (no viaja en el POST) con la leyenda «Viene del contrato», y muestra la garantía del contrato (`at_pt_db_partes($fila)['garantia_meses']`, Task 5; agregada a Interfaces › Consumes) y no la del plan. `at_pt_accion_guardar()` dejó de leer `$_POST['garantia_meses']` y `at_pt_plan_desde_panel()` ya no toca `soporte.garantia_meses` (conserva la del plan); el JS no cambió porque nunca serializó ese campo. Pruebas: en panel-render, la vieja aserción «garantía y mensuales se pueden editar» queda solo para mensuales y una nueva guarda 9 en el plan y afirma que la pestaña muestra `value="3" readonly` (el contrato sin marcador), sin `name="garantia_meses"` y con «Viene del contrato»; en panel-acciones, `post_guardar()` ya no manda la garantía, la prueba de textos sigue mandando `garantia_meses=6` a propósito y una aserción nueva afirma que la garantía guardada es la de antes. Step 25 (c): `verificar-panel.php` sigue el orden único D2 (`at_pt_validar_plan` → `at_pt_marcar_ediciones($pl, $v['plan'], 'luis')` → `at_pt_validar_plan` → `at_pt_calcular_fechas`), con lo que se cierra la discrepancia 2 de arriba; corrido contra el sitio del arnés dio `{"barras":16,"semanas":14}` (22 actividades con sus orígenes intactos: 13 `tabla` y 9 `ia`) y `limpio`. Verificación con `tmp-C2/armar.py` (código real de A, B y C desde los Markdown actuales, incluida la garantía `>= 0` de A): `php -l` limpio en todos los archivos armados y en `verificar-panel.php`, `node --check` pasa y `tmp-C2/aridad.py` da 96 llamadas a A/B y 0 que no calzan. Cuentas vigentes, que reemplazan las de arriba: ajustes 21, panel-render **33**, panel-js 15, panel-acciones **49**, ficha-crm 7 y agenda 23; en total **148 `ok`**, 0 `FALLA`, las seis en `TODO OK`, y las dos del cierre con `sin-red.php` en `TODO OK`. Se actualizaron los Expected de los Steps 9 y 17 y de la regresión de la Task 10. Mutantes (`tmp-C2/mutantes-garantia.py`): mostrar la garantía del plan, devolverle el `name` al campo o volver a leerla del POST hacen fallar exactamente 1 aserción cada uno.

## grupo-D.md

#### Notas de revisión (grupo D)

Dos revisores aplicaron este plan paso a paso sobre `origin/main` (`86021c1`). Qué se hizo con cada hallazgo; la
versión corregida se volvió a aplicar entera en una copia limpia (todas las salidas esperadas calzan, 155/155 al final,
8 commits, `git status` limpio) y se comprobó con mutaciones que cada prueba nueva detecta la falla que cubre.

1. **Alta — la tarjeta «Qué aprobamos juntos» se salía con el peor caso de la Task 3.** Aplicado. `paginarBloques`
   deja a lo más `MAX_ENTREGAS_LAMINA = 5` entregas por lámina (Step 13), con prueba unitaria en el Step 11
   (`[5, 5, 3]` entregas) y el caso en Chromium en `template-plan-layout.test.js`. Mutación con el tope en 99: la
   prueba de diseño falla con `aside.plan-tarjeta termina a 1118 px` y `p.aprobamos-nota termina a 1087 px`.
2. **Media — «Reuniones y soporte» y «Qué necesitamos de ti» se salían con listas en sus topes.** Aplicado: en
   `ESTILO_CIERRE`, dos líneas por punto en cuanto la lista se compacta y sin margen al final de la última lista de
   cada columna. Cubierto por `planListasAlTope` en la prueba de diseño; sin la regla, falla con la lámina 9 hasta
   1219 px y la 8 hasta 1128 px.
3. **Media — la garantía omitía la exclusión de la cláusula del contrato.** Aplicado: «…a la entrega final, salvo
   los que vengan de cambios hechos por terceros o por ti». Verificado contra
   `Docs/CONTRATO_SERVICIO_DESARROLLO.md:130` del worktree `plan-trabajo`.
4. **Alta — ninguna prueba del repositorio comprobaba que la carta Gantt quepa.** Aplicado: nueva
   `renderer/test/template-plan-layout.test.js` en el Step 21 (plan largo, plan extremo de 14 bloques en 27 semanas,
   peor caso de la Task 3, listas al tope y 11 páginas del PDF). En el Step 22 fallan las cinco; en el Step 24
   pasan. Mutación con la carta en `top: 330px`: fallan las dos pruebas de la carta. El script de capturas quedó
   como revisión a ojo complementaria.
5. **Media — la prueba de fotos del servidor no podía fallar.** Aplicado: la 4.ª prueba de `server-plan.test.js` se
   reemplazó por dos con `image_briefs` reales y un Higgsfield falso que anota los prompts (con y sin propuesta).
   Mutación en `server.js` que pide la foto aunque venga en `images`: falla «con propuesta…». Conteos: Step 14
   5/1/4, Step 17 5/5, Step 18 110.
6. **Media — «cláusula 13.1» fija, cuando la garantía era la 12.1 antes de `6c2dc62`.** Aplicado: se cita «cláusula
   de Garantía de tu contrato», sin número, y la prueba exige que no aparezca `13.1`. No se agregó
   `soporte.clausula_garantia` porque el esqueleto es la fuente única del JSON. Las cláusulas 4.2 y 6.1 tienen el
   mismo texto y número en `b572664` y `6c2dc62`, así que se mantienen. Queda para el orquestador (Tasks 4 y 5) que
   `soporte.garantia_meses` salga del placeholder `garantia_meses_servicio` del contrato firmado y no de la IA.
7. **Baja — la prueba de fotos no probaba nada.** Aplicado junto con el punto 5.
8. **Baja — la prueba del borrador no miraba el botón del PDF.** Aplicado: exige el `<a class="pdf-button" …>` y la
   regla `body.is-draft .pdf-button { display: none !important; }` de `STYLE`.
9. **Baja — la salida esperada del Step 27 estaba incompleta.** Aplicado con la salida real completa (nueve líneas).
10. **Baja — el Step 22 nombraba mal las pruebas que ya pasan.** Aplicado: con la prueba del borrador reforzada, ahora
    pasan dos (el degradado sin fotos y el escape) y el texto lo dice. Se corrigió además «28 filas» por «22 filas».
11. **Baja — `details` no llega a la nota de n8n.** Aplicado en la interfaz de la Task 11 (un 400 con `details` no se
    reintenta y sus motivos van a la nota). El cambio de código es de la Task 14 (grupo E): queda avisado al
    orquestador.
12. **Baja — dos bloques con el mismo nombre se pisaban la revisión.** Aplicado: clave `nombre#ocurrencia` en
    `revisionesPorBloque` y `clavesBloques`, y `ocurrencia` en cada bloque que devuelve `paginarBloques` (los trozos
    «(sigue)» comparten la suya). Prueba nueva en el Step 11; con la clave vieja falla.
13. **Baja — el Step 2 no decía cómo detectar un cambio en `template.js`.** Aplicado: comando `git diff --quiet
    86021c1 HEAD -- renderer/src/template.js` y cómo leer la huella nueva en `actual:`. Hoy da `IGUAL` contra
    `origin/main`.
14. **Baja — `urlSegura` deja sin botón el portal `http://` de la prueba local.** Rechazado como cambio de código
    (aceptar `http://` en el renderer de producción no aporta nada al cliente) y aplicado como aviso en las
    decisiones de diseño de la Task 12, para quien haga la Task 15.

#### Notas de coherencia (grupo D, 29-sep)

Revisión contra el esqueleto (decisiones D1 a D17 de las 16:10) y contra las versiones de las 16:12-16:18 de
grupo-A.md (Task 4, `at_pt_armar_render`), grupo-B.md (Task 5, `at_pt_datos_render`) y grupo-E.md (Task 14, flujo
«3 Render»).

**Qué cambió en esta sección**

1. **D11: todo 400 de un plan trae `details`.** Antes, un `unique_id` con otra forma pasaba `validatePlanPayload` y
   lo rechazaba después el chequeo de `server.js` (línea 52 de `origin/main`) con `{error: {message: 'unique_id
   inválido'}}`, sin `details`. Ahora `validatePlanPayload` aplica la misma regla `/^[A-Za-z0-9_-]{6,64}$/` y
   responde con motivo en español (Step 10). Para la propuesta no cambia nada. El código del plan (12 letras y
   números) siempre pasa.
   - Pruebas: aserciones nuevas dentro de «exige unique_id…» (Step 8) y de la prueba del servidor, que ahora se llama
     «un plan inválido responde 400 con motivos legibles en details…» (Step 13). Las cuentas no cambian: 7, 11, 5,
     110 y 155.
   - Mutación, con la regla apagada en `schema.js`: fallan esas dos pruebas.
2. **D8: la garantía se muestra tal como llega.** Hay aserciones nuevas en «Reuniones y soporte» (Step 16): 6 meses
   dice 6 y no 3; 1 dice «1 mes» y «el mes siguiente»; 0 no promete garantía («Te acompañamos después de la
   entrega.»). El renderer no tiene valor propio (`Number(…) || 0`). Mutación con `|| 3`: falla esa prueba.
3. **Interfaces de la Task 11.** Ahora listan las cuatro respuestas de `/render` con sus tipos: 200, 400 con
   `details: string[]`, 401, y 502 con `details` como texto. También dicen de dónde salen `garantia_meses` (D8) y
   `company_name` (D9), y que a n8n solo le llega `render` de `GET /plan/{id}/render` (D10).

No cambió el HTML de ninguna lámina, así que las capturas del Step 27 no se rehicieron por eso. Igual se corrieron en
la verificación, con las 9 líneas esperadas.

**Campo por campo: `at_pt_armar_render` (grupo-A.md) frente a lo que valida o lee el renderer**

| Campo | Lo que produce WordPress | Qué hace el renderer | Estado |
|---|---|---|---|
| `document_type` | `'plan'` | `server.js` elige esquema y template | calza |
| `unique_id` | código del plan, 12 `[A-Za-z0-9]` | obligatorio + forma; carpeta `/p/<id>/`; «Código de tu plan» | calza |
| `draft` | `!$final` | `body.is-draft`, aviso y sin botón del PDF | calza |
| `company_name` | D9 (Task 5), nunca vacío | obligatorio; portada, tal cual | calza |
| `client_name` | nombre del cliente | «Preparado para …» (se omite si viene vacío) | calza |
| `proyecto` | nunca vacío | obligatorio; título | calza |
| `fecha_firma_larga` | «29 de septiembre de 2026» | Método AT («…del contrato» si viene vacío) | calza |
| `fecha_inicio`, `fecha_fin` | copias de `cronograma.inicio/fin` | no los lee: usa `cronograma` | informativos |
| `semanas` | entero | portada | calza |
| `metodo` | `{hechas, actual, proximas}` | usa `hechas` y `actual`; `proximas` sale de las seis fases | calza |
| `fases[].{clave, titulo, descripcion, bloques[].{nombre, entregable, entrega, actividades[].{nombre, detalle, responsable, dias_habiles, en_paralelo, desde, hasta}}}` | lo normaliza `at_pt_validar_plan` y lo llena `at_pt_calcular_fechas` | lee todos esos campos; `servicio`, `etapa` y `origen` no se muestran | calza |
| `cronograma.{inicio, fin, semanas, barras[].{fase, etiqueta, tipo, responsable, desde, hasta}, hitos[].{nombre, fecha}}` | «Tu revisión» sigue a su bloque, en la misma fase | valida `inicio`, `fin` y `barras`; la revisión va en la fila del bloque anterior de su fase | calza |
| `necesitamos_de_ti` | `string[]` | lista | calza |
| `reuniones` | `[{nombre, detalle}]` | lista (acepta también textos) | calza |
| `soporte` | `{garantia_meses: 0..24, mensuales: string[] (≤ 6)}` | tal cual, sin valor propio | calza |
| `portal_url` | http(s) o `''` | solo https (`urlSegura`) | calza en PROD; en local `http://` sale sin botón (Task 15, esperado) |
| `agenda.whatsapp_url` | `https://wa.me/<n>?text=…` o `''` | si viene `''`, pone `https://wa.me/56927002984` sin mensaje | calza: es el mismo número de `CONTACTS` y del respaldo de `at_pt_datos_render` |
| `agenda.web_url` | `''` (Etapa 1) | enlace solo si es https | calza |
| `image_briefs` | `[]` en vista previa; los del plan en la versión final | arreglo si viene; `requested` los cuenta | calza |
| `images` | `{}` (`stdClass`) | objeto (un `[]` se toma como `{}`) | calza |

**Cruce ejecutado.** Se usó el `puras.php` que sale de grupo-A.md (16:12, extraído con el script del grupo B) y se
corrió con PHP 8.3.28 y `error_reporting=-1`, sin avisos. Arma seis cuerpos reales: borrador, versión final,
persona natural, sin portal ni WhatsApp, garantía 0 del contrato y plan vacío. Luego pasaron por
`validatePlanPayload`, `renderPlanHtml` y `POST /render` del renderer ya aplicado. Resultado: 19 `ok` y 0 `FALLA`.
- Los cinco planes válidos pasan el esquema. El HTML muestra tal como llegan el proyecto, la empresa, el cliente,
  las fases, las actividades, los hitos, los insumos, las reuniones, la garantía (6, la del contrato), el portal y
  el WhatsApp.
- `POST /render` responde 200 con `view_url`, `pdf_url` e `images: {requested: 0, stored_local: 0, kept_remote: [],
  missing: [], reused: 0}`.
- El plan vacío responde 400 con `details: ['falta o está vacío el arreglo obligatorio: fases', 'falta el objeto
  obligatorio: cronograma']`.

**Respuesta de `/render` frente a lo que lee n8n (grupo-E.md, 16:17).** Calza en todo.
- `leerRespuestaRender()` (grupo-E.md:2235) lee `statusCode` y `body` porque el nodo «Render» usa `fullResponse` +
  `neverError`.
- Solo reintenta un error de red, un 5xx, o un 200 sin `view_url` o con `images.missing`.
- Lleva `details` a la nota, sea arreglo (400) o texto (502).
- No reintenta un 401.
- `images.requested` no cuenta portada ni cierre cuando llegan en `images` (fotos de la propuesta), así que el
  «X de N fotos nuevas» del correo calza con `at_pt_costo_fotos`.

**Discrepancias para el orquestador**

1. **Garantía 0 del contrato (D8, entre A y B).**
   - `at_pt_db_garantia` (grupo-B.md:1127) devuelve 0 si el contrato dice 0.
   - `at_pt_armar_render` (grupo-A.md, Task 4) solo reemplaza la garantía del plan si la del contrato es mayor que 0.
   - Resultado medido en el cruce: con 0 en el contrato, el documento muestra la del plan (3 meses por defecto).
   - D8 dice que «el contrato manda». Si 0 también debe mandar, el cambio va en el lado de A (`>= 0`), y el renderer
     ya lo dibuja bien: sin garantía y con «Te acompañamos después de la entrega.» (prueba nueva del Step 16).
   - El grupo A ya lo dejó anotado en sus notas de coherencia. Decide el orquestador. El renderer no cambia en
     ninguno de los dos casos.
2. **Sin otras contradicciones.** D9 (la Task 5 arma `company_name` en el orden de la decisión y la Task 4 lo usa
   tal cual), D10 y D11 están aplicadas en A, B y E, y el renderer calza con las tres.
3. **Menor, no bloquea (grupo E).** La prueba `leerRespuestaRender` de grupo-E.md:2144 usa como ejemplo de `details`
   el texto `'fases debe traer al menos una fase'`, pero el renderer escribe `'falta o está vacío el arreglo
   obligatorio: fases'`. Como el texto se pasa tal cual a la nota, la prueba vale igual. Solo conviene copiar el
   mensaje real si se quiere que el ejemplo sea fiel.

## grupo-E.md

#### Notas de revisión (grupo E)

Las citas a grupo-B.md son de su versión de las 12:00 del 29-sep (el grupo B se estaba reescribiendo en paralelo: si
se corren sus líneas, buscar por nombre de función). Cómo se verificó esta versión: el código de las Tasks 13 y 14 se reconstruyó desde este archivo y se rehízo paso a paso
en un repositorio git nuevo (`tmp-E3/replay.py` en el scratchpad): cada paso rojo falla con el mensaje exacto que dice
su «Expected»; cada paso verde corre la suite COMPLETA y termina en `TODO OK`; los 7 commits se hicieron de verdad y
después de cada uno la suite queda verde; al final `git diff --exit-code` confirma que los JSON commiteados son los que
generan los builders. Conteos: `puras` 47, `correos` 33, `borrador` 55, `cambios` 42 (Task 13 = 177), `render_puras` 17,
`correos_render` 64, `render` 47 (Task 14 = 128); total 305 `ok`, 0 fallas, `exit=0`; `probar_plan.py nada` da `exit=2`
(conteos de esa ronda; los vigentes, 341, están en «Notas de coherencia (grupo E, 29-sep)» al final).
Las pruebas nuevas se corrieron primero contra el código anterior: 37 líneas `FALLA` (409, respuesta parcial, actividad
movida, contexto tardío, correos), todas verdes con el código de este archivo. Los scripts de los revisores
(`revisor-E/adversarial.py`, `rev-tmp/E-rev/caso409.py` y `parcial.py`) sobre el código nuevo: con el contexto en
«borrador» no se llama a OpenAI ni a `/error` ni sale correo; un 409 en «1 Borrador» o «2 Cambios» no llama a
`/error`; una respuesta con solo `implementacion` y la foto `metodo` manda las tres fases, «Diseño de la portada» con
7 días «luis» y las fotos `['metodo', 'gantt']`; la actividad de Luis movida de bloque conserva 7 días.

Hallazgos altos y medios:

1. **409 tratado como falla y GPT-4o gastado en un pedido tardío (alta, Task 13). Aplicado.** Nodos nuevos
   «¿Espera respuesta?» (`p14` en «1 Borrador»: estado `generando` o `error`; `q15` en «2 Cambios»: `cambios` o `error`,
   las mismas reglas del 409 de `at_pt_rest_borrador`, grupo-B.md:2536) y «¿Llegó tarde?» (`p15`/`q19`: un 409 al
   guardar termina sin `/error` ni correo). En «2 Cambios» la revisión del plan guardado pasó a su propio nodo
   («¿Hay plan guardado?», `q16`) DESPUÉS de «¿Espera respuesta?»: si no, un pedido de cambios viejo sobre un plan que se
   está generando (sin payload) lo habría pasado a «error» (prueba C10 «generando»). `code_motivo` usa `errores` en
   todas las ramas (hallazgo bajo 8). Pruebas B12 (borrador, aprobando, listo; y «error» sí se procesa), B13, C10 y C11.
   Flujos: «1 Borrador» 17 nodos y «2 Cambios» 19 (incluye los del hallazgo bajo 1).
2. **Respuesta parcial en «2 Cambios» borraba fases y fotos (alta, Task 13). Aplicado.** `unirPor(antes, nuevos, campo)`
   en `plan_js`: las fases se unen por `clave` y las fotos por `slide`, cada una en su lugar; lo nuevo va al final; una
   lista vacía o que no es lista pasa tal cual (la validación decide: `fases: []` sigue siendo error). Pruebas
   `cambios_parcial` (puras) y C9 (flujo).
3. **`?modo=` en el simulador de la Task 15 y en el esqueleto (alta, cruce Task 15). Rechazado en esta sección, reportado.**
   No está en el grupo E y no puedo editar otras secciones: grupo-F.md:153 (`render?modo=`) y esqueleto.md:294 y :329
   deben pasar a `&modo=`. La Task 14 ya usa `&modo=` (nodo «Leer render», pruebas R1, R4 y R12) y la Task 7 también lo
   documenta (grupo-B.md:1993-1995). La sugerencia opcional (que el simulador reutilice `reutilizarFotosPlan` y
   `fotosDelPlan`) queda para quien redacte la Task 15.
4. **`protegerLuis` perdía los días de Luis si el modelo movía la actividad de bloque (media, Task 13). Aplicado, con otra
   clave.** En vez de la clave `fase|bloque|nombre` con respaldo por nombre único que proponía el revisor, se usa la
   MISMA clave que WordPress: nombre normalizado + número de aparición (`at_pt_recorrer_actividades`,
   grupo-A.md:1592-1611). Así n8n y WordPress reconocen igual las actividades; mover una de bloque no la hace nueva, y
   dos con el mismo nombre se emparejan por orden en vez de «no adivinar». Pruebas `cambios_movida` y `cambios_repetida`.
   Renombrarla sigue siendo el límite (lo prohíbe el prompt y WordPress lo avisa con `at_pt_luis_perdidas`); quedó
   escrito en la tarea.
5. **Ningún correo enlazaba la pestaña del plan: faltaba `crm_cliente_id` (media, cruce Tasks 5 y 7). Aplicado de mi
   lado; ya resuelto en el grupo B.** El grupo B vigente ya lo entrega: `at_pt_contexto` (grupo-B.md:1240, con su
   prueba en grupo-B.md:1069) y `/render` (grupo-B.md:2776, prueba en grupo-B.md:2659). En esta sección pasó a
   «Consumes» como obligatorio (0 = ficha sin enlazar → lista de clientes), `contexto()` y `leido()` lo traen (5) y R1 y
   R4 exigen el botón `automatiza-crm-ficha&id=5&pt=9#tab-plan`.
6. **En «2 Cambios» WordPress marca «luis» lo que agregó o cambió la IA (media, cruce Task 7/esqueleto). Ya resuelto
   en los grupos A y B; queda el esqueleto; n8n alineado.** El grupo A definió `at_pt_marcar_ediciones(array $anterior,
   array $nuevo, string $marca = 'luis')` y su «Orden de uso único» pide `'ia'` en cambios (grupo-A.md:242-244); la
   Task 7 vigente ya la llama así (`at_pt_marcar_ediciones($anterior, $plan, 'ia')`, grupo-B.md:2561; en su versión de
   las 11:51 faltaba el `'ia'`). Falta corregir esqueleto.md:190-191 y :291 (hoy dicen que en cambios se marca como
   al guardar desde el panel). De mi lado, n8n ahora manda la misma regla (nuevo o con días cambiados → `'ia'`; sin cambio de días → conserva su origen) y el comentario de C1 dice
   que la prueba mira lo que n8n MANDA, no lo que queda en la base.
7. **409 (alta, Task 13; segundo revisor).** Mismo hallazgo que el 1: aplicado con los nodos «¿Espera respuesta?» y
   «¿Llegó tarde?» en vez de contar el 409 como «guardado», porque así además no se gasta GPT-4o cuando el contexto ya
   dice que el pedido llegó tarde. B13 y C11 son las pruebas que proponía este revisor.
8. **Actividad de Luis movida de bloque (media, Task 13; segundo revisor).** Mismo hallazgo que el 4: aplicado.
9. **Cinco de siete commits dejaban la suite en rojo (media, Tasks 13 y 14). Aplicado.** Las dos tareas usan la línea
   marcador: la Task 13 crea `probar_plan.py` con el arnés y `puras`, y cada sección (`correos`, `borrador`, `cambios`,
   `render_puras`, `correos_render`, `render`) se inserta en su propio paso rojo. Cada paso verde corre la suite completa;
   el replay en git lo confirma para los 7 commits. El código de las pruebas no cambió de lugar lógico, solo se repartió.
10. **`crm_cliente_id` en `/contexto` (media, cruce Task 5).** Mismo hallazgo que el 5: ya está en el grupo B; aplicado de
    mi lado.
11. **Origen `'ia'` en «2 Cambios» (media, cruce Task 7).** Mismo hallazgo que el 6: ya resuelto en la Task 7 vigente; n8n alineado.

Hallazgos bajos:

1. **Guardado sin vista previa y sin aviso. Aplicado.** «¿Vista previa pedida?» (`p16`/`q17`) mira si WordPress devolvió
   el aviso `No se pudo pedir la vista previa: …` (grupo-B.md:2592); si lo devolvió, «Armar correo sin vista»
   (`p17`/`q18`, `correos_plan.correo_sin_vista`) le escribe a Luis con «Guardar y recalcular». Pruebas B14 (otros
   avisos no mandan correo), C12 y la sección `correos`.
2. **El correo final decía «listo» sin mirar el estado. Aplicado.** `correo_render` lee `estado` de `/vista`
   (grupo-B.md:2829): si no es `listo`, asunto «la versión final salió, pero el plan quedó en «error»», etiqueta «Con
   problemas» y una caja que lo explica. Caso «final ok con el plan en error».
3. **Carrera entre vista previa y versión final en el mismo `/p/<codigo>/`. Rechazado en esta sección, reportado.** «3
   Render» no conoce el estado del plan (`/render` no lo devuelve) y el arreglo mínimo es de WordPress: que
   `POST /vista` con `modo: 'draft'` no guarde `view_url`/`pdf_url` si el plan está en `aprobando`, `listo` o `enviado`
   (Task 7), o cambiar el contrato para renderizar la vista previa con otro `unique_id`. Queda como riesgo conocido para
   la Task 7 y la Task 15.
4. **Claves ajenas de primer nivel en borrador. Aplicado.** En borrador se descartan (WordPress arma el plan solo con
   las claves conocidas); en cambios siguen siendo error, también junto a las fases (prueba `cambios_ajena`).
5. **Pie del correo con «el envío lo haces tú desde el panel». Aplicado.** `ROTULOS` reemplaza la frase entera por
   «Aviso automático del plan de trabajo de AutomatizaTech. Nada de esto le llega al cliente.»; `marco_plan` sigue
   exigiendo una sola aparición (verificada contra `email_tpl.py:73` de `origin/claude/propuestas-json-robusto`).
6. **Prompts sin tope de largo de foto y `garantia_meses` que no llega. Aplicado lo primero; lo segundo reportado.** Los
   dos prompts piden de 40 a 60 palabras (máximo 400 caracteres) por foto: con los ~95 a ~160 caracteres que suma
   `fotos_guard` quedan bajo los 1.200 que acepta WordPress (grupo-A.md:1159-1177). `PROMPT_PLAN` ya dice «garantia_meses
   = contrato.garantia_meses si viene; si no, 3», así que hoy funciona con 3 y funcionará si la Task 5 agrega
   `'garantia_meses'` a `contrato` (propuesta del revisor, no aplicada por ser de otra sección). Además, por la nota del
   grupo A para la Task 13 (grupo-A.md:247), los prompts dicen el tope de 130 días hábiles en secuencia; los de bloques
   y actividades ya estaban dentro (7 bloques y 5 actividades frente a 13 y 10).
7. **Asunto «plan de trabajo listo» cuando WordPress no guardó. Aplicado.** Sin guardar: asunto «el resultado del plan
   no quedó guardado en WordPress», etiqueta «Con problemas» y «La presentación salió bien, pero WordPress no registró el
   resultado.»; la prueba exige que no aparezca «quedó <strong>listo</strong>».
8. **`code_motivo` perdía `errores` en un 500. Aplicado.** Prueba B11b con el 500 propio de `/borrador`
   (`errores: ['No se pudo guardar el plan.']`).
9. **Falla de OpenAI sin detalle (`{}`). Aplicado.** El simulador entrega `{}` cuando la respuesta simulada no trae
   `content` ni `error`, y B7b exige «El modelo no respondió (ejecución SIM-1)» y el correo. Supuesto no verificado
   contra n8n: que el nodo `openAi` v1 con `continueRegularOutput` deje el error fuera de `json`; con cualquiera de las
   dos formas el plan termina en «error».
10. **Los JSON commiteados nunca se comparaban con los builders. Aplicado.** Task 13 Step 20 y Task 14 Step 15 terminan
    con `git diff --exit-code` (salida `JSON commiteados = builders`).
11. **Prompts: máximo 60 palabras por foto.** Mismo que el bajo 6: aplicado.
12. **«Un plan recién generado trae a lo sumo 15 barras». Aplicado.** El texto de la Task 14 ahora dice que 7 bloques es
    solo una instrucción del prompt y que la Task 12 debe probar con `AT_PT_MAX_BLOQUES = 14` (hasta 27 barras).
13. **Verificación independiente. Sin cambios de código.** Se mantiene `&modo=`. Se corrigió la nota para la Task 16:
    una ruta `/c/…` de Git Bash SÍ funciona con `deploy.py` (MSYS la convierte a `C:/…` antes de llegar a Python;
    probado el 29-sep), y el grupo F la usa así.

Otros ajustes de esta ronda: el grupo B agregó `rubro` a `/contexto` (de `crm_clientes.rubro`, `''` si no hay, «para
las fotos por rubro»); «1 Borrador» y «2 Cambios» lo pasan al modelo y los prompts lo usan para las fotos (B1, B2 y C1
lo prueban). El `how_it_works` de la propuesta de prueba pasó a lista de textos, como lo entrega
`at_pt_contexto` (grupo-B.md:1195-1213); y el tope del motivo bajó a 900 caracteres, porque `/error` guarda 1000
(grupo-B.md:2844) y el flujo le suma « (ejecución N)».

#### Notas de coherencia (grupo E, 29-sep)

Aplicadas las «DECISIONES DE COHERENCIA DEL ORQUESTADOR» del esqueleto (29-sep 16:10) que tocan a las Tasks 13 y 14.

**Qué cambió en esta sección**

- **D6 (cuerpos de los webhooks).** «Consumes» dice `{id, codigo}` para «1 Borrador» y «2 Cambios» y `{id, codigo, modo,
  aviso}` para «3 Render»; las pruebas mandan el `codigo`. Los flujos usan solo `id` (`modo` y `aviso` en el 3), así que
  un cuerpo sin `codigo` sigue funcionando.
- **D5 (409 del borrador tardío y `/error`).** «¿Espera respuesta?» de «1 Borrador» ahora es la regla exacta del 409 de
  `at_pt_rest_borrador` (grupo-B.md:2536-2538): `generando`, o `error` **sin contenido**. Con el plan en «error» y un
  borrador ya guardado no se gasta GPT-4o (prueba nueva en B12). Un 409 al guardar sigue terminando sin `/error` ni
  correo; y aunque llegara a `/error`, WordPress solo pasa a «error» un plan en `generando` o `cambios`.
- **D7 (rubro y garantía).** El modelo ya recibía `rubro`; ahora las pruebas exigen también `contrato.garantia_meses`
  (B1 y C1; `CONTRATO` de prueba trae 6). `CODE_PEDIDO` pasa `contrato` entero, así que no hubo que tocar código;
  `PROMPT_PLAN` nombra los meses de garantía entre los datos del contrato y `PROMPT_CAMBIOS` dice que
  `soporte.garantia_meses` sale de `contrato.garantia_meses` (igual el render usa el del contrato, D8).
- **D12 (topes y «Arranque» en los prompts).** Los dos prompts traen una regla «TOPES» con las cifras de grupo-A.md
  (Task 2, «Para la Task 13»): 13 bloques, 10 actividades por bloque, 58 en total, de 1 a 60 días hábiles por
  actividad, 130 días hábiles con 5 de revisión por cada bloque con entrega, 10 hitos, fases `"diseno_desarrollo",
  "implementacion" y "soporte"` y responsable `"at", "cliente" o "ambos"`, y los dos dicen «NO incluyas el bloque
  «Arranque»». Una lista `TOPES_PROMPT` en `probar_plan.py` exige cada frase en los dos prompts. En «2 Cambios» esto
  cambió también el código: antes el prompt decía «Mantén el bloque «Arranque» tal como viene». Ahora
  `CODE_PEDIDO` le quita el «Arranque» a `plan_actual` (`quitarArranque`) y `leerPlanModelo` en cambios lo
  **restituye** tal como estaba guardado (`restituirArranque`), con lo que Luis haya editado en él, y descarta el que
  mande el modelo, esté en la fase que esté. Sin esto, una respuesta con la fase `diseno_desarrollo` sin «Arranque»
  habría reemplazado la fase guardada (`unirPor` une por clave). WordPress habría puesto el «Arranque» fijo y se habrían
  perdido los días que Luis le cambió. Pruebas nuevas en `puras` (cuatro) y C1 (dos).
- **D10 (render con `&modo=` y estado).** «Leer render» ya usaba `&modo=`. Nuevo nodo «¿Toca renderizar?» (`r15`)
  después de «¿Render leído?»: si la vista previa llega con el plan en `aprobando`, `listo` o `enviado`
  (`ESTADOS_SIN_VISTA_PREVIA` en `JS_RENDER`), el flujo termina ahí. No llama al renderer ni a `/vista` y no manda correo:
  el webhook ya respondió al recibir. La versión final no se filtra por estado. `crm_cliente_id` y `estado` se leen
  de `/render` y `correo_render` sigue usando el `estado` de `/vista`. Flujo «3 Render»: 15 nodos (antes 14). Pruebas R13.
- **D11 (400 con `details`).** El nodo «Render» pide la respuesta completa (`fullResponse` + `neverError`, como las
  llamadas a WordPress; antes recibía solo el cuerpo y un 400 llegaba como `{error}` sin el código ni los `details`).
  `leerRespuestaRender()` (en `JS_RENDER`) la lee y decide:
  - Se reintenta con red caída o tiempo vencido, con un 5xx, o con un 200 al que le faltan la presentación o fotos.
  - Un 4xx no se reintenta.
  - Los `details` van a la nota como «el renderer rechazó el plan (HTTP 400: …)»: los del 400 son un arreglo, los del
    502 del renderer (`origin/main:renderer/src/server.js`) son texto, y como mucho van 5 motivos de 200 caracteres,
    porque `/vista` guarda 500.

  `CODE_REINTENTAR` ahora carga `LIB`. Pruebas: 11 en `render_puras` y R14 y R15 en `render`.
- **D13 y D17.** `plan_js.py` figura como archivo del esqueleto. En la cabecera de la Task 14 y en la nota para la
  Task 16 queda escrito que «3 Render» se despliega primero.
- **D1.** Sin cambios: «2 Cambios» ya mandaba lo nuevo o con días cambiados como `'ia'` (misma regla que
  `at_pt_marcar_ediciones(…, 'ia')`) y restituye los días `'luis'`.

**Verificación (reproducida desde este Markdown, fuera del repo).** El script `tmp-E2/verificar.py` hace lo siguiente:
- Extrae los 15 bloques de código de este archivo.
- Copia `json_guard.py`, `fotos_guard.py` y `email_tpl.py` de `origin/claude/propuestas-json-robusto` con `git show`,
  sin tocar el repo ni sus worktrees.
- Arma `tmp-E2/repo/N8N/plan-trabajo` siguiendo los pasos, con las inserciones en la línea marcador.

Resultados:
- Los 7 pasos rojos fallan con el mensaje exacto de su Expected: `ModuleNotFoundError` o `ImportError`, `1 FALLA(S)`.
- Los 7 pasos verdes corren la suite completa en `TODO OK`, con 0 FALLA y estos conteos acumulados:

| Sección | `ok` de la sección | Acumulado |
|---|---|---|
| `puras` | 51 | 51 |
| `correos` | 33 | 84 |
| `borrador` | 59 | 143 |
| `cambios` | 45 | 188 |
| `render_puras` | 28 | 216 |
| `correos_render` | 64 | 280 |
| `render` | 61 | 341 |

- Los builders escriben 17, 19 y 15 nodos. Los tres JSON son válidos y reconstruirlos da el mismo archivo byte a byte.
- `probar_plan.py nada` responde `exit=2`.
- La suite completa tarda 82 s.
- El Markdown no tiene marcadores vagos ni nombres de clientes.

`tmp-E2/mutantes.py` deshace uno por uno los arreglos de esta ronda y la sección que lo prueba lo detecta en los 9 casos
(0 vivos): el «Arranque» restituido, D5 en «¿Espera respuesta?», «¿Toca renderizar?», el no reintentar un 400, los
`details` en la nota, el «Arranque» fuera del pedido de cambios, un tope borrado de cada prompt y la respuesta completa
del nodo «Render».

**Contraste campo por campo con grupo-B.md (versión de las 12:00; el esqueleto manda desde las 16:10).**

| Ruta | Estado | Qué se comprobó en grupo-B.md |
|---|---|---|
| `GET /contexto` | Coincide, salvo lo de abajo | Claves en `at_pt_contexto`, grupo-B.md:1218-1243. |
| `POST /borrador` | Coincide | Cuerpo `{plan, origen}`. 200 `{ok, errores: [], avisos}` y aviso `No se pudo pedir la vista previa: …` en :2590-2594. 409 con «llegó tarde» en :2536-2541. 422 en :2510. 500 con `errores: ['No se pudo guardar el plan.']` en :2588. |
| `POST /error` | Coincide | `{nota}` → `{ok, estado}`. Guarda 1000 caracteres y solo pasa a error desde `generando` o `cambios` (:2844-2854). |
| `GET /render` | Coincide, salvo lo de abajo | `{ok, render, propuesta_uid, crm_cliente_id}` en :2771-2777. Lee `modo` con `get_param`, así que `&modo=` funciona. |
| `POST /vista` | Coincide, salvo lo de abajo | Cuerpo `{modo, ok, view_url, pdf_url, faltan, nota}` → `{ok, estado}`, nota de 500 caracteres (:2791-2829). |

Discrepancias de grupo-B.md con el esqueleto, que debe arreglar quien redacte el grupo B:
1. **D7:** `contrato` de `at_pt_contexto` no trae `garantia_meses` (grupo-B.md:1227-1233). Mientras falte, el modelo usa
   3, como dice el prompt, y el render usa la del contrato (D8).
2. **D10:** `at_pt_rest_render` no devuelve `estado` (grupo-B.md:2771-2777). Sin él, «¿Toca renderizar?» deja pasar toda
   vista previa: `String(undefined)` no está en la lista, así que se comporta como antes y la carrera vista previa /
   versión final sigue abierta.
3. **D10:** `at_pt_rest_vista` con `modo: 'draft'` guarda `view_url` y `pdf_url` sin mirar el estado
   (grupo-B.md:2808-2810). El esqueleto pide no guardarlos con el plan en `aprobando`, `listo` o `enviado`.
4. **D6:** `at_pt_pedir_render` manda `{id, modo, aviso}` sin `codigo` (grupo-B.md:1389, :1394 y :1422). n8n no lo
   necesita, pero el esqueleto lo fija.

El simulador de la Task 15 (grupo-F.md:153-157) ya usa `&modo=` y omite la vista previa atrasada (D10), igual que
«3 Render».

**Supuesto no verificado contra n8n real.** Que el nodo HTTP Request 4.2 con `fullResponse` y `neverError` entregue un
400 del renderer como `{statusCode: 400, body: {error, details}}`. Así lo hace con las llamadas a WordPress que ya
corren en PROD (`N8N/propuestas-v3`, `FULL`), pero con este nodo «Render» nunca se probó. Si el cuerpo llegara como
texto, `leerRespuestaRender` lo parsea (probado); si llegara como `{error}` sin código, se trata como falla de red y se
reintenta (3 renders en vez de 1), sin trabar el plan. Se comprueba en la Task 16 con un POST real mal formado al
renderer desde el flujo.

#### Enmienda D18 (29-sep): revisión de texto de las fotos con GPT-4o en el flujo 3

Luis cambió D18: el plan lleva la misma revisión de texto que las propuestas. Lo que se enmendó y cómo se probó:
- **Task 14, ciclo D (Steps 16-21):** `probar_revision_plan.py` (nuevo), `plan_js.py` (`INSTRUCCION_TEXTO`, `RETOMA` y
  `JS_SHA256` leídos con `ast` de `N8N/propuestas-v3/build_3_final.py`, más `JS_REVISION`) y `build_plan_3_render.py`
  entero (24 nodos). En los Steps 1 y 11, `MANIFEST_PLAN`, `SIN_TEXTO` y tres valores por defecto de `sim()` para que la
  sección `render` siga igual con los nodos nuevos. Materializados desde el plan en una carpeta temporal (con
  `N8N/propuestas-v3` de `origin/main` = PR #51) y corridos el 29-sep: `probar_plan.py` 341 `ok` y `TODO OK` antes y
  después del ciclo D; `probar_revision_plan.py` 103 `ok` y `TODO OK`; sin el Step 18 falla con el `ImportError` del
  Step 17. Mutaciones que la prueba detecta: quitar la condición «queda un render», quitar `retomasPrevias` o revisar
  con fotos faltantes (15 fallas).
- **Decisiones del ciclo D:** la retoma cuenta dentro de los 3 renders de D11 (las propuestas le dan 2 más); se revisa
  solo la versión completa; portada y cierre de la propuesta no se revisan de nuevo; los textos se importan, no se
  duplican.
- **Task 4:** `AT_PT_USD_REVISION = 0.026` y `usd_revision` en el retorno de `at_pt_costo_fotos` (la forma de
  `at_propuesta_costo_fotos` en `claude/cierre-cliente`, `inc/proposals-flow.php:114-128`). Con propuesta: 8 fotos =
  0,0256 + 0,026 = US$0,0516 (máximo 0,0512 + 0,052 = 0,1032); sin propuesta: 10 fotos = 0,032 + 0,026 = US$0,058
  (máximo 0,116). Las cinco aserciones del ciclo A se corrieron con PHP 8.4.15 contra el bloque nuevo (con
  `at_pt_slides_foto()` de 10 láminas): `TODO OK`; siguen siendo 14 `ok` en el ciclo A y 43 en el archivo.
- **Task 9:** etiqueta «(N fotos + revisión ≈ US$X)» y `confirm` «…, se revisará que no tengan texto (…)», como el panel
  de propuestas; las cadenas se generaron con PHP: «(8 fotos + revisión ≈ US$0,0516)» y «(10 fotos + revisión ≈
  US$0,0580)». Sin fotos nuevas, la etiqueta queda «(0 fotos ≈ US$0,0000)».
- **Tasks 15 y 16:** textos del botón; el simulador de la Task 15 no hace la revisión (lo dice); la Task 16 corre
  `probar_revision_plan.py` antes de desplegar y, en la prueba real, verifica que la revisión corrió y anota el gasto.
- **Supuesto:** la cifra de US$0,026 por consulta es la de las propuestas (documentación de OpenAI para 9 fotos en
  `detail: high`, no medida en una factura); con 8 o 10 fotos se usa la misma, como en el panel de propuestas. Se mide
  en la Task 16.
