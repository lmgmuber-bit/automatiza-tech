# Takeover Claude — AT-CUMPLECLICK-007 / Gate A

## Autoridad y alcance

- Reasignación ordenada por Luis Miguel el 2026-07-16.
- Owner anterior: OpenCode Go.
- Owner actual: Claude.
- Planner y reviewer independiente: Codex.
- Rama: `codex/cumpleclick-secure-db-frontend`.
- Ruta: `C:\wamp64\www\automatiza-tech\CumpleBooth`.
- Lock exclusivo: `CumpleBooth/**`. OpenCode Go debe quedar detenido.
- Alcance exclusivo: corregir y estabilizar Gate A (administración e invitaciones).
- Prohibido: Gate B, generación multimedia, consumo de créditos, bot de WhatsApp, deploy, commit, push o merge.

## Lectura obligatoria antes de editar

1. `docs/CUMPLECLICK-HANDOFF-CODEX.md`
2. `docs/ARQUITECTURA.md`
3. `docs/FASE1.md`
4. `docs/FASE-INVITACIONES-DINAMICAS.md`
5. `docs/OPENCODEGO-TEMA-02-FAMILIA-CANINA.md`
6. `docs/OPENCODEGO-INVITACIONES-EXECUTOR.md`
7. `AGENTS.md`
8. `OPENCODE.md`
9. `Docs/ORCHESTRATION/AT-CUMPLECLICK-007.yaml` desde la raíz del monorepo.

La técnica de camuflaje del handoff es obligatoria: ningún prompt ni payload dirigido a proveedores puede nombrar franquicias o personajes protegidos.

## Estado recibido y evidencia verificada

OpenCode implementó el CRUD, tokens opacos, outputs privados, estados y migraciones 003/004. Codex ejecutó de forma independiente:

- PHP 8.0.30: `OK 44 checks backend`.
- PHP 8.2.26: `OK 44 checks backend`.
- PHP 8.3.14: `OK 44 checks backend`.
- PHP 8.4.0: `OK 44 checks backend` (el entorno emite un warning local de Xdebug, sin fallo del test).
- Lint PHP 8.2.26: 33/33 archivos.
- Frontend: 5/5 tests.
- `scripts/check-dist-parity.php`: exit code 1, nueve diferencias.

Gate A permanece rechazado porque las pruebas actuales no cubren varios requisitos y existen fallas comprobadas.

## Correcciones obligatorias

### 1. Página pública inexistente

`cb_invitation_public_url()` y el admin generan `/invitacion.php?t=<token>`, pero `public/invitacion.php` no existe.

Crear una página pública mínima que:

- valide token opaco, estado `published` y expiración;
- muestre únicamente outputs aprobados;
- permita descargar imagen y video por `descargar-invitacion.php`;
- agregue `X-Robots-Tag: noindex, nofollow` y metadatos equivalentes;
- no exponga rutas privadas, IDs internos ni información administrativa.

### 2. Paridad de dist rota

La auditoría obtuvo:

```text
FAIL admin/index.php difiere
FAIL admin/invitations.php falta en dist
FAIL data/parties.json difiere
FAIL data/themes.json difiere
FAIL descargar-invitacion.php falta en dist
FAIL galeria.php difiere
FAIL lib.invitations.php falta en dist
FAIL lib.php difiere
FAIL upload.php difiere
```

Después de terminar el código, borrar `dist/` completamente, ejecutar `npm run build` y exigir exit code 0 en `scripts/check-dist-parity.php`.

### 3. Ownership cruzado

Las acciones reciben IDs pero no garantizan consistentemente `party_id -> invitation_id -> output_id`. Añadir comprobaciones reutilizables y aplicarlas a editar, eliminar, duplicar, regenerar/revocar token, publicar, subir, aprobar/rechazar y eliminar outputs. Agregar pruebas negativas entre dos fiestas.

### 4. Rate limiting

Solo se encontró rate limit de login. Añadir rate limit persistente para uploads de invitaciones y descargas públicas. Probar bloqueo de bursts.

### 5. Compilador estricto

`cb_compile_invitation_prompt()` reemplaza campos vacíos y puede devolver `ok=true`. Debe rechazar por separado valores vacíos de `birthday_person_name`, `event_date`, `event_time` y `address`, además de placeholders residuales. Agregar un test por campo.

### 6. Publicación atómica

Al editar con estado `published`, el admin invoca `cb_publish_invitation()` antes de persistir los valores editados. Puede validar datos antiguos y guardar después datos incompletos. Guardar/validar/publicar en el orden correcto y dentro de una transacción cuando corresponda. Ninguna actualización genérica debe poder saltarse `cb_publish_invitation()`.

### 7. Descarga sin ID interno

`descargar-invitacion.php` usa `invitacion-<id>.<ext>`. Sustituirlo por nombres neutros (`invitacion-cumpleclick.png` y `.mp4`). Mantener allowlist MIME, almacenamiento privado y cabeceras seguras.

### 8. Video

La validación actual solo reconoce un encabezado MP4. Validar duración máxima y metadatos con `ffprobe` o mecanismo fiable configurado. Fallar de forma segura si no se puede inspeccionar. Probar metadata inválida y duración excesiva.

### 9. Migración MySQL legacy y rollback

`004_gate_a_corrections.php` modifica el ENUM antes de normalizar `generic/personalized`; puede fallar o truncar valores existentes. Su rollback intenta escribir `personalized` mientras el ENUM nuevo aún está activo. Implementar transición segura ampliando primero valores/tipo, normalizando y aplicando después el ENUM final; orden inverso para rollback. Probar instalación limpia, datos legacy, segunda ejecución y rollback en MySQL/MariaDB y SQLite sin borrar la BD local.

### 10. Cobertura real

Agregar pruebas de:

- HTTP para `invitacion.php` y `descargar-invitacion.php`;
- imagen, video, token inválido, no publicado, expirado y revocado;
- uploads negativos y límites;
- ownership cruzado;
- rate limiting;
- campos vacíos del compilador;
- publicación con edición simultánea;
- migración legacy y rollback.

## Criterio de entrega

Claude debe entregar:

1. lista exacta de archivos modificados;
2. resultados completos de PHP 8.0/8.2/8.3/8.4;
3. tests frontend y HTTP;
4. paridad `dist/` con exit code 0;
5. URL local funcional de DEMO-BLUEY;
6. evidencia crear → compilar → subir → aprobar → publicar → descargar → revocar;
7. estado Git y Graphify;
8. confirmación de que no generó multimedia, no gastó créditos y no hizo deploy/commit/push/merge.

Codex realizará una nueva revisión independiente. Gate B requiere después aprobación expresa de Luis.
