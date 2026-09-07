# Handoff de resultado — predicción Baby Shower

**Fecha:** 2026-08-25  
**Marca pública:** CumpleClick  
**Rama local:** `codex/baby-shower-predicciones`  
**Base:** `ed60fdb80be45c3a5e0f0e47d418a7a7308782db`  
**Worktree:** `C:\wamp64\www\automatiza-tech\.worktrees\codex-baby-shower-predicciones`  
**Estado:** implementado y validado en local; sin commit, push, merge, deploy ni migración en PROD.

## Resultado funcional

- El admin de eventos permite seleccionar `child_birthday` o `baby_shower`.
- Las invitaciones nuevas heredan la modalidad del evento.
- El kiosco baby shower mantiene la portada temática y cambia a este recorrido:
  predicción → Atrapa los chupetes → guardado confirmado → foto → revelación con
  apuesta y puntaje → QR → recuerdito → cierre.
- Los cumpleaños infantiles conservan su router y sus textos anteriores.
- Baby shower ignora `service_plan` para el kiosco y no recibe Show 3D.
- El admin de invitaciones puede emitir, copiar y revocar el enlace privado del
  tablero de los papás. El token se muestra una sola vez y sólo se almacena su
  hash.
- El tablero privado muestra totales, distribución y tarjetas imprimibles;
  contempla enlace inválido/revocado y estado vacío.

## Persistencia y seguridad

La migración `010_baby_shower_predictions.php` es aditiva e idempotente. Agrega
`cc_invitations.event_type` con default `child_birthday`, crea las tablas futuras
de regalos y tokens por invitación, y crea `cc_predictions` por `party_id`.

La decisión que cambia el borrador original está documentada en
`docs/DECISION-PREDICCIONES-POR-EVENTO-2026-08-25.md` y fue aprobada por Luis.
El endpoint valida listas blancas, tamaño, modalidad, fiesta activa y rate limit;
no confía en ids enviados por el cliente. El tablero usa `noindex`,
`no-referrer`, cache privada desactivada, token opaco y estado fail-closed.
Cada recorrido genera además una clave de idempotencia de 128 bits; la base guarda
sólo su hash y una restricción única por evento evita duplicados por doble toque,
reintento o respuesta de red perdida.

La retención elimina las predicciones del evento antes de anonimizarlo. También
se corrigieron en ese script dos nombres de columnas heredados que no coincidían
con el esquema vigente: `public_slug` y `birthday_person_name`.

## Archivos de implementación

- `database/migrations/010_baby_shower_predictions.php`
- `database/migrations/010_baby_shower_predictions.down.php`
- `public/lib.predictions.php`
- `public/prediction-api.php`
- `public/predicciones.php`
- `public/lib.php`
- `public/lib.invitations.php`
- `public/admin/index.php`
- `public/admin/invitations.php`
- `public/ver.php`
- `scripts/retention.php`
- `src/App.jsx`
- `src/predictions.js`
- `src/styles.css`
- `tests/backend/predictions.php`
- `tests/frontend/predictions.test.mjs`
- ajustes de los runners backend para aplicar la migración 010.

## Evidencia local

- Migración 010 aplicada dos veces, rollback explícito y reaplicación: cubierto
  por pruebas backend.
- Tres predicciones distintas reunidas en un mismo tablero: cubierto por pruebas
  backend.
- Idempotencia y sincronización de modalidad: 28 pruebas backend específicas;
  el reintento con la misma clave conserva exactamente tres filas.
- Recorrido Chrome móvil 430×932: portada, formulario, juego omitido, guardado,
  cámara simulada, revelación, primer QR, recuerdito, segundo QR, apertura del
  recuerdito en una pestaña móvil y tablero privado completados sin errores JS.
- El recorrido detectó y corrigió un error real de clasificación: el prefijo
  `recuerdito-` tiene 11 caracteres, pero `ver.php` comparaba 12 y rotulaba el
  archivo como foto. La repetición completa confirmó `Guardar recuerdito` y el
  texto final de predicción en la vista móvil.
- La SQLite y las capturas de QA viven sólo en rutas ignoradas (`storage/` y
  `qa-output/`) y no se deben subir.
- Retención `--apply` probada sólo contra la SQLite de QA marcada como vencida:
  eliminó la predicción, desactivó la fiesta y la anonimizó como
  `Evento archivado`.

Suites de regresión: frontend 104/104, álbum 157 checks, perfiles 32, leads 11,
lint 77 PHP + smoke de 8 entrypoints y paridad `public`→`dist` de 365 archivos.

## Observaciones que no pertenecen a este cambio

El runner backend completo conserva una expectativa antigua de `mundo3d` para
Carreras, mientras esta rama base publica `concierto3d`. No se corrigió porque
Luis prohibió modificar Show 3D y la lógica aprobada de Carreras/Rayo. Las
pruebas específicas de predicciones, álbum, perfiles, leads, lint y frontend sí
quedan como fuente de validación de esta entrega.

Durante el smoke visual se observaron dos 404 preexistentes de la temática Hielo:
`themes/hielo/grupo-personajes.png` y `audio/captura.mp3`. El flujo usa sus
fallbacks y termina; no se alteraron assets fuera del alcance.

## Para continuar

1. Revisar el diff de `codex/baby-shower-predicciones`.
2. Si Luis lo autoriza, hacer commit en esta rama.
3. Unirla sólo después de resolver con el propietario de la rama destino la
   expectativa heredada de Show 3D.
4. En producción, respaldar primero, subir los archivos autorizados y ejecutar
   la 010 una sola vez mediante el procedimiento habitual. Esta tarea no la
   ejecutó.
