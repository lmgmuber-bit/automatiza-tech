# SEO técnico de automatizatech.cl

**EN PROD desde el 2026-11-05 07:19** (PR #129; respaldo `~/respaldos/seo-tecnico-antes-20261105-0719.tar.gz`; vuelta atrás: `deploy_seo.py rollback` y `wp post update 1 2 --post_status=publish`).

Fuente única de cómo el tema arma título, descripción, canonical, sitemap y robots.txt. Responsables: Eje (plataforma) con Faro (SEO). Origen: prueba manual del 5-nov-2026 con la checklist de claude-seo-ai, usada solo como referencia (Aduana no autorizó instalarla). Estándar aplicado: `Shared-Tools/Estandar-Visibilidad-Web-IA.md`.

## Cómo funciona

- **Plantillas con `<head>` propio** (home premium, las siete landings y `template-legal.php`) escriben su `<title>`, su descripción y su canonical; el home deja el canonical y las etiquetas `og:` a `automatiza_tech_home_premium_head()`. En esas plantillas `inc/seo-tecnico/cargar.php` saca `_wp_render_title_tag` y `rel_canonical` de `wp_head`, para que WordPress no imprima los suyos. Una plantilla nueva con `<head>` propio se agrega a `at_seo_plantillas_propias()` (`inc/seo-tecnico/puras.php`), o volverá a salir duplicada.
- **Las páginas comunes** (las que usan `header.php`) siguen con el `<title>` y el canonical de WordPress.
- **Sitemap** (`wp-sitemap.xml`, el del núcleo; no hay plugin de SEO): sin el sitemap de usuarios, sin «Hola mundo», «Página de ejemplo», `validar-factura/` ni la categoría «Sin categoría». Lo excluido, el archivo de autor y esa categoría llevan `noindex, follow`. Luis pidió el 5-nov-2026 pasar «Hola mundo» y «Página de ejemplo» a borrador (recuperables desde wp-admin); se hizo al desplegar (ids 1 y 2; ahora responden 404). `validar-factura/` sigue publicada (no se pidió despublicarla); los QR de las facturas apuntan a `/validar-factura.php`, no a esa página.
- **robots.txt** es el virtual de WordPress: el núcleo escribe el grupo `*` y una sola línea `Sitemap`. El tema agrega un grupo para la búsqueda con IA (OAI-SearchBot, ChatGPT-User, Claude-SearchBot, Claude-User) con el mismo acceso que el resto, y el `llms.txt` como comentario (la línea `LLMS:` no era una directiva). GPTBot, ClaudeBot y Google-Extended llevan `Disallow: /` (decisión de Luis del 5-nov-2026: búsqueda sí, entrenamiento no); Googlebot, Bingbot, PerplexityBot y el resto siguen bajo el grupo `*`. Para revertir, `at_seo_bloquea_entrenamiento()` en `cargar.php` devuelve `false`.
- Se eliminó `automatiza_tech_seo_optimizations()` de `functions.php`: imprimía en el inicio la descripción y las `og:` del home antiguo («bots inteligentes») encima de las de la plantilla.

## Pruebas

```bash
php tests/seo-tecnico/puras-test.php
php tests/seo-tecnico/ganchos-test.php
```

Después de desplegar, contar etiquetas por URL pública (debe dar una de cada una): el script `contar.sh` del despliegue del 5-nov-2026 hace un GET por URL y cuenta `<title>`, descripción, canonical y meta robots.

## Estado por control (V1–V6 del estándar)

| Control | Estado | Evidencia o motivo |
|---|---|---|
| V1 Alcance | PASS | Públicas: inicio, siete landings y tres páginas legales. Fuera del índice: relleno de WordPress, `validar-factura/`, autor y «Sin categoría». Privadas y fuera de este alcance: wp-admin, CRM, portal OmniCliente, propuestas y demos. |
| V2 Acceso | PASS | Las 10 URLs públicas del sitemap y `llms.txt` responden 200 antes y después del despliegue (GET del 5-nov-2026). |
| V3 Descubrimiento | PASS | 5-nov 07:19: el índice del sitemap lista solo `wp-sitemap-posts-page-1.xml`, con las 10 páginas públicas; usuarios, entradas y categorías responden 404. Un canonical por página. |
| V4 Presentación | PASS | GET del 5-nov tras el despliegue: un `<title>`, una descripción y un canonical en las 10 URLs públicas, los de cada plantilla. Pendiente aparte: las `og:` y el JSON-LD del home van sin tildes. |
| V5 Motores con IA | PASS técnico | robots.txt en PROD: búsqueda con IA permitida, GPTBot/ClaudeBot/Google-Extended con `Disallow: /` (decisión de Luis del 5-nov-2026). Estado de la inclusión generativa en Search Console: NOT_RUN, sin acceso. |
| V6 Medición | NOT_RUN | Sin acceso a Search Console ni a Bing Webmaster Tools. |
