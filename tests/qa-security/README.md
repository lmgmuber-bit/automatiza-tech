# QA Security Tests — Sprint 4 Hardening

Tests automatizados para verificar los cambios del Sprint 4.

## Setup (una sola vez)

Las dependencias están en el skill de Playwright ya instalado.
No necesitas `npm install` aquí.

## Configurar credenciales

```bash
# Copia el template
cp .env.test.example .env.test

# Edita .env.test y llena los campos vacíos:
#   WP_ADMIN_PASSWORD=
#   AGENT_EMAIL=
#   AGENT_PASSWORD=
#   (opcional para area2) CLIENT_TEST_EMAIL= CLIENT_TEST_ID=
```

## Ejecutar tests

```bash
# Desde el directorio del skill
cd C:\Users\luis_\.claude\skills\playwright-skill

# Área 1 — Portal React (A5.2 secret masking, A5.3 reset URL, A5.4 AI chat)
node run.js C:\wamp64\www\automatiza-tech\tests\qa-security\specs\area1-portal.spec.js

# Área 6 — Upload de archivos
node run.js C:\wamp64\www\automatiza-tech\tests\qa-security\specs\area6-uploads.spec.js

# Área 2 — Contratos firma doble
node run.js C:\wamp64\www\automatiza-tech\tests\qa-security\specs\area2-contracts.spec.js
```

## Tests cubiertos

| Archivo | Tests | Depende de |
|---------|-------|------------|
| `area1-portal.spec.js` | A5.2, A5.3, A5.4 | WP_ADMIN_PASSWORD + AGENT_PASSWORD |
| `area6-uploads.spec.js` | E3 upload filtering | AGENT_PASSWORD |
| `area2-contracts.spec.js` | Firma doble | WP_ADMIN_PASSWORD + CLIENT_TEST_ID |

## Estado actual — QA Sprint 4

Ver `Docs/QA_STATUS_SPRINT4.md` para el checklist completo.

## Notas

- Browser visible por defecto (`headless: false`) para poder ver la ejecución
- `slowMo: 150-200ms` para que las acciones sean legibles
- Screenshots automáticos en `/tmp/area*.png` cuando hay errores o puntos de interés
- `.env.test` está en `.gitignore` — **nunca commitear con credenciales reales**
