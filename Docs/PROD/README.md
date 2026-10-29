# Copias de configuración de PROD

Archivos copiados tal cual de PROD para que el repositorio diga lo que de verdad corre allí. No se suben desde aquí: si cambia PROD, se vuelve a copiar.

| Archivo | Origen en PROD | Copiado |
|---|---|---|
| `htaccess-raiz-automatizatech.txt` | `public_html/.htaccess` de automatizatech.cl | 28-oct-2026, después de bloquear `xmlrpc.php` |

Para cotejar: md5 del archivo de PROD sin `` contra el de aquí. LiteSpeed y WordPress reescriben sus propios bloques (`LSCACHE`, `WordPress`, `LiteSpeed`); los bloques `AT …` del final son nuestros. Contexto en [`Docs/HARDENING_HTACCESS_DEPLOY.md`](../HARDENING_HTACCESS_DEPLOY.md).
