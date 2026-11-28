# -*- coding: utf-8 -*-
"""Imagen para compartir cumpleclick.com (og:image, 1200 × 630): la vista previa que muestra WhatsApp.

Pedido de Luis del 25-09-2026, antes de la feria: la portada no tenía og:image y el enlace se compartía sin foto.
Logo oficial en vector, Baloo 2 incrustada, el mensaje del volante ("Mucho más que una cabina de fotos") y el arte
de Higgsfield del volante B (request 557e7a55…, ya pagado: no gasta créditos). Chrome sin cabeza la fotografía.
Sale en JPEG de menos de 300 KB, que es lo que WhatsApp muestra sin problemas. El fondo es #FEF8E7, el crema
medido en los bordes del arte (no #FFF8EC): con el crema de la marca se veía el rectángulo de la escena.
Uso: python og_cumpleclick.py  ->  sitio/assets/img/og-cumpleclick.jpg
"""
import base64
import io
import os
import subprocess
import tempfile

import numpy as np
from PIL import Image

AQUI = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.abspath(os.path.join(AQUI, "..", "..", ".."))  # CumpleBooth/
SALIDA = os.path.join(REPO, "sitio", "assets", "img", "og-cumpleclick.jpg")
MARCA = os.path.join(REPO, "public", "brand", "cumpleclick-mark.svg")
FUENTE = os.path.join(REPO, "public", "admin", "fonts", "baloo2-800.woff2")
FUENTE_700 = os.path.join(REPO, "public", "admin", "fonts", "baloo2-700.woff2")
HEROE = os.environ.get("CC_ARTE_HEROE", r"C:\Users\luis_\Documents\CumpleClick\impresos\volantes-2026-09-25"
                       r"\arte-higgsfield\heroe-557e7a55.png")
CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"


def b64(ruta):
    with open(ruta, "rb") as f:
        return base64.b64encode(f.read()).decode("ascii")


def heroe():
    """Recorta el fondo crema alrededor de la escena y la entrega como PNG en base64."""
    im = Image.open(HEROE).convert("RGB")
    a = np.asarray(im).astype(int)
    fondo = np.median(np.concatenate([a[:8].reshape(-1, 3), a[-8:].reshape(-1, 3)]), axis=0)
    ys, xs = np.where(np.abs(a - fondo).sum(axis=2) > 30)
    m = 20
    im = im.crop((max(0, xs.min() - m), max(0, ys.min() - m), min(im.width, xs.max() + m), min(im.height, ys.max() + m)))
    im.thumbnail((1100, 1100), Image.LANCZOS)
    buf = io.BytesIO()
    im.save(buf, "PNG")
    return base64.b64encode(buf.getvalue()).decode("ascii")


HTML = """<!doctype html><html lang="es-CL"><head><meta charset="utf-8"><style>
@font-face{font-family:'Baloo 2';font-weight:800;src:url(data:font/woff2;base64,%(f800)s) format('woff2');}
@font-face{font-family:'Baloo 2';font-weight:700;src:url(data:font/woff2;base64,%(f700)s) format('woff2');}
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { width: 1200px; height: 630px; overflow: hidden; background: #FEF8E7; font-family: 'Baloo 2', sans-serif; }
.lienzo { position: relative; width: 1200px; height: 630px; }
.punto { position: absolute; border-radius: 50%%; }
.izq { position: absolute; left: 64px; top: 58px; width: 560px; }
.marca { display: flex; align-items: center; gap: 18px; }
.marca img { width: 104px; height: 104px; }
.palabra { font-weight: 800; font-size: 76px; line-height: 1; color: #4C2882; }
.palabra span { color: #D6307F; }
.titular { margin-top: 34px; font-weight: 800; font-size: 58px; line-height: 1.02; color: #4C2882; }
.titular span { color: #D6307F; }
.bajada { margin-top: 18px; font-weight: 700; font-size: 29px; line-height: 1.2; color: #8B5CF6; }
.web { position: absolute; left: 64px; bottom: 50px; background: #D6307F; color: #fff; font-weight: 800; font-size: 30px;
  line-height: 1; padding: 16px 30px 12px; border-radius: 40px; }
.heroe { position: absolute; right: 26px; top: 50%%; transform: translateY(-50%%); width: 560px; }
</style></head><body><div class="lienzo">
<i class="punto" style="left:28px;top:26px;width:16px;height:16px;background:#8B5CF6"></i>
<i class="punto" style="left:1150px;top:34px;width:14px;height:14px;background:#FBBF24"></i>
<i class="punto" style="left:640px;top:560px;width:12px;height:12px;background:#D6307F"></i>
<i class="punto" style="left:1160px;top:585px;width:18px;height:18px;background:#A78BFA"></i>
<div class="izq">
  <div class="marca"><img src="data:image/svg+xml;base64,%(marca)s" alt=""><div class="palabra">Cumple<span>Click</span></div></div>
  <div class="titular">Mucho más que<br><span>una cabina de fotos</span></div>
  <div class="bajada">Invitación, cabina, juegos y álbum<br>para tu cumple</div>
</div>
<div class="web">cumpleclick.com</div>
<img class="heroe" src="data:image/png;base64,%(heroe)s" alt="">
</div></body></html>"""


def main():
    html = HTML % dict(f800=b64(FUENTE), f700=b64(FUENTE_700), marca=b64(MARCA), heroe=heroe())
    with tempfile.TemporaryDirectory() as tmp:
        ruta = os.path.join(tmp, "og.html")
        png = os.path.join(tmp, "og.png")
        with open(ruta, "w", encoding="utf-8") as f:
            f.write(html)
        subprocess.run([CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars", "--force-device-scale-factor=1",
                        "--window-size=1200,630", "--virtual-time-budget=5000", "--screenshot=" + png,
                        "file:///" + ruta.replace("\\", "/")], check=True, capture_output=True)
        im = Image.open(png).convert("RGB")
    assert im.size == (1200, 630), im.size
    for q in (88, 84, 80, 76):
        im.save(SALIDA, "JPEG", quality=q, optimize=True, progressive=True)
        if os.path.getsize(SALIDA) < 300 * 1024:
            break
    print(SALIDA, im.size, "%d KB" % (os.path.getsize(SALIDA) // 1024), "calidad", q)


if __name__ == "__main__":
    main()
