#!/usr/bin/env python
"""
Cliente minimo de la API REST de Higgsfield (open.higgsfield.ai) para agentes.

Uso rapido (desde la raiz del repo):
  python scratchpad/higgsfield-api/hf_api.py models [--filtro kling]
  python scratchpad/higgsfield-api/hf_api.py estimar <slug> [--segundos 5] [--imagenes 1]
  python scratchpad/higgsfield-api/hf_api.py generar <slug> --prompt "..." [--param aspect_ratio=16:9] [--segundos 5] --si
  python scratchpad/higgsfield-api/hf_api.py estado <request_id>
  python scratchpad/higgsfield-api/hf_api.py bajar <request_id> --a salida.png

Reglas que este cliente hace cumplir:
  - Las claves se leen por rotulo del archivo de claves de Luis y nunca se imprimen.
  - No existe endpoint de saldo ni de costo previo: `estimar` usa la tabla de precios de lista
    (open.higgsfield.ai/pricing, 2026-09-20) y `generar` exige `--si` despues de mostrar el estimado.
  - Sin `--si`, `generar` solo valida el cuerpo contra la API (422 gratis) y muestra que falta.
"""
import argparse
import json
import re
import sys
import time
import urllib.error
import urllib.request

KEYS_FILE = r"C:\Users\luis_\OneDrive\Documentos\APIS KEy\APIS KEY.txt"
API = "https://api.higgsfield.ai"
UA = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36",
    "Accept": "application/json, text/plain, */*",
    "Accept-Language": "es-CL,es;q=0.9",
}

# Precio de LISTA en USD (open.higgsfield.ai/pricing, 2026-09-20). La cuenta "API KEY Higgsfield + N8N"
# tiene 15 % de descuento sobre todos y puede fijar hasta 50 % en 3 modelos: el estimado es un techo.
PRECIOS = {  # prefijo de slug -> (unidad, usd)
    "higgsfield-ai/soul/v2/standard": ("img", 0.0032),
    "higgsfield-ai/soul/standard": ("img", 0.0938),
    "soul": ("img", 0.0938),
    "z-image/turbo": ("img", 0.015),
    "recraft/v4.1": ("img", 0.035),
    "ideogram/v4.0": ("img", 0.03),
    "alibaba/qwen-image-3": ("img", 0.04),
    "xai/grok-imagine-image-2.0": ("img", 0.04),
    "marketing-studio/image": ("img", 0.0162),
    "kling-video/v2.5-turbo": ("s", 0.042),
    "kling-video/v2.6": ("s", 0.07),
    "kling-video/v3.0/std": ("s", 0.063),
    "kling-video/v3.0/pro": ("s", 0.084),
    "kling-video/v3.0/4k": ("s", 0.21),
    "kling-video/v3.0-turbo": ("s", 0.07),
    "kling-video/omni": ("s", 0.084),
    "kling-video/o3": ("s", 0.084),
    "kling-video/motion-control": ("s", 0.07),
    "kling-video/v3/motion-control": ("s", 0.084),
    "bytedance/seedance-2.0": ("s", 0.1407),
    "bytedance/seedance-2.5": ("s", 0.2057),
    "minimax/hailuo-2.3": ("s", 0.0467),
    "minimax/h3": ("s", 0.13),
    "alibaba/wan-3.0-prime": ("s", 0.068),
    "alibaba/wan-3.0": ("s", 0.05),
    "wan/v2.6": ("s", 0.10),
    "wan/v2.7": ("s", 0.10),
    "alibaba/happy-horse": ("s", 0.14),
    "lightricks/ltx-2.5/text-to-video/fast": ("s", 0.09),
    "lightricks/ltx-2.5/image-to-video/fast": ("s", 0.09),
    "lightricks/ltx-2.5": ("s", 0.12),
    "pixverse/v6": ("s", 0.115),
    "xai/grok-imagine-video": ("s", 0.08),
    "higgsfield/cinema-studio/4.0": ("s", 0.2057),
    "higgsfiled/genjutsu": ("s", 0.318),
}
# Lo que el MCP tiene y la API NO: nano_banana_*, veo3_*, 3D (sam_3_3d, meshy, rigging), audio/voz,
# lipsync, upscale, remove_background, marketing_studio_video. Para eso: MCP con saldo o budgetpixel.


def cargar_claves(cuenta):
    txt = open(KEYS_FILE, encoding="utf-8", errors="ignore").read()
    if cuenta == "n8n":  # "API KEY Higgsfield + N8N": la cuenta CON saldo, la del renderer de propuestas
        i = txt.lower().find("api key higgsfield")
        b = txt[i:i + 600]
        kid = re.search(r"id[^\n]*?([A-Za-z0-9_\-]{30,40})\s*$", b, re.I | re.M).group(1)
        sec = re.search(r"secret[^\n]*?([A-Za-z0-9_\-]{60,70})\s*$", b, re.I | re.M).group(1)
    else:  # "api-n8n": el par HF_API_KEY_ID/SECRET del bloque "API N8N", cuenta SIN saldo al 2026-09-21
        kid = re.search(r"HF_API_KEY_ID\s*=\s*([A-Za-z0-9_\-]{20,})", txt).group(1)
        sec = re.search(r"HF_API_KEY_SECRET\s*=\s*([A-Za-z0-9_\-]{20,})", txt).group(1)
    return kid, sec


class Cliente:
    def __init__(self, cuenta="n8n"):
        self.kid, self.sec = cargar_claves(cuenta)
        self.h = dict(UA)
        self.h["Authorization"] = "Key %s:%s" % (self.kid, self.sec)

    def red(self, s):
        return str(s).replace(self.kid, "[ID]").replace(self.sec, "[SECRET]")

    def call(self, path, body=None, timeout=60):
        url = path if path.startswith("http") else API + path
        data = json.dumps(body, ensure_ascii=True).encode() if body is not None else None
        h = dict(self.h)
        if data is not None:
            h["Content-Type"] = "application/json"
        req = urllib.request.Request(url, data=data, headers=h, method="POST" if data is not None else "GET")
        try:
            r = urllib.request.urlopen(req, timeout=timeout)
            raw = r.read().decode("utf-8", "ignore")
            return r.status, (json.loads(raw) if raw.startswith(("{", "[")) else raw)
        except urllib.error.HTTPError as e:
            raw = e.read().decode("utf-8", "ignore")
            try:
                return e.code, json.loads(raw)
            except Exception:
                return e.code, self.red(raw)[:500]


def precio(slug):
    mejor = None
    for pref, (u, p) in PRECIOS.items():
        if slug.startswith(pref) and (mejor is None or len(pref) > len(mejor[0])):
            mejor = (pref, u, p)
    return mejor


def estimar(slug, segundos, imagenes):
    m = precio(slug)
    if not m:
        return None, "sin precio en la tabla: mirar open.higgsfield.ai/pricing"
    _, u, p = m
    cantidad = segundos if u == "s" else imagenes
    total = p * cantidad
    return total, "%s x %s %s (lista; la cuenta tiene 15%% de descuento)" % (p, cantidad, u)


def cmd_models(c, a):
    code, d = c.call("/models?limit=100")
    if code != 200:
        print("error", code, c.red(d))
        return 1
    for it in d.get("items", []):
        texto = (it["slug"] + " " + (it.get("title") or "")).lower()
        if a.filtro and a.filtro.lower() not in texto:
            continue
        m = precio(it["slug"])
        print("%-52s %-8s %-28s %s" % (it["slug"], it.get("output_type"), (it.get("title") or "")[:28],
                                        ("$%s/%s" % (m[2], m[1])) if m else "?"))
    print("total en el catalogo:", d.get("total"))
    return 0


def cmd_estimar(c, a):
    total, det = estimar(a.slug, a.segundos, a.imagenes)
    print("estimado: %s  (%s)" % ("$%.4f" % total if total is not None else "?", det))
    return 0


def cmd_generar(c, a):
    body = {"prompt": a.prompt} if a.prompt else {}
    for kv in a.param or []:
        k, v = kv.split("=", 1)
        if v[:1] in "[{":  # listas u objetos JSON (video_urls, image_urls)
            body[k] = json.loads(v)
        elif v.isdigit():
            body[k] = int(v)
        elif v.lower() in ("true", "false"):
            body[k] = v.lower() == "true"
        else:
            body[k] = v
    total, det = estimar(a.slug, a.segundos, a.imagenes)
    print("modelo:", a.slug, "| cuerpo:", json.dumps(body, ensure_ascii=False))
    print("estimado: %s (%s)" % ("$%.4f" % total if total is not None else "?", det))
    if not a.si:
        print("sin --si no se genera: valido el cuerpo contra la API (gratis)...")
        # Sin `prompt` la API responde 422 "Field required" y lista los valores validos de los
        # demas campos. OJO: un prompt VACIO ("") si se acepta y encola un trabajo real (paso el
        # 2026-09-21), por eso se quita del cuerpo y, si igual entrara, se cancela en el acto.
        # 2026-09-21: image-to-video NO valida image_url ni enums antes de encolar (entraron
        # "validacion" y aspect_ratio "abc" como trabajos reales) y Kling no se deja cancelar.
        # Por eso se quita tambien toda clave *url*: sin un campo obligatorio la API contesta
        # 400/422 y no encola nada. Para probar el tipo de un campo, mandar un valor de OTRO tipo
        # (duration=abc da 400 sin encolar); nunca un valor "invalido" del tipo correcto.
        cuerpo_prueba = {k: v for k, v in body.items() if k != "prompt" and "url" not in k.lower()}
        code, d = c.call("/" + a.slug, cuerpo_prueba)
        if code == 200 and isinstance(d, dict) and d.get("request_id"):
            print("OJO: la API encolo un trabajo real en la validacion; request_id:", d["request_id"])
            cc, cd = c.call("/requests/%s/cancel" % d["request_id"], {})
            print("cancelar ->", cc, c.red(json.dumps(cd))[:200], "| si no se pudo, seguirlo con: estado", d["request_id"])
            return 1
        print("validacion ->", code, json.dumps(d, ensure_ascii=False)[:600] if not isinstance(d, str) else d)
        return 0
    code, d = c.call("/" + a.slug, body)
    if code != 200:
        print("submit fallo ->", code, json.dumps(d, ensure_ascii=False)[:600] if not isinstance(d, str) else d)
        return 1
    rid, st_url = d.get("request_id"), d.get("status_url")
    print("enviado:", rid, "| estado:", d.get("status"))
    t0 = time.time()
    while time.time() - t0 < a.espera:
        code, s = c.call(st_url)
        st = s.get("status") if isinstance(s, dict) else None
        if st in ("completed", "failed", "nsfw", "canceled"):
            print("terminal: %s a los %.0fs" % (st, time.time() - t0) + ((" | error: " + str(s.get("error"))) if isinstance(s, dict) and s.get("error") else ""))
            for k in ("images", "videos", "video", "output"):
                if isinstance(s, dict) and s.get(k):
                    print(k + ":", json.dumps(s[k], ensure_ascii=False)[:800])
            return 0 if st == "completed" else 1
        time.sleep(a.intervalo)
    print("sigue en cola tras %ds; consultar con: estado %s" % (a.espera, rid))
    return 2


def cmd_estado(c, a):
    code, d = c.call("/requests/%s/status" % a.request_id)
    print(code, json.dumps(d, ensure_ascii=False)[:1200] if not isinstance(d, str) else d)
    return 0


def cmd_bajar(c, a):
    code, d = c.call("/requests/%s/status" % a.request_id)
    if code != 200 or not isinstance(d, dict):
        print("no se pudo leer el estado:", code)
        return 1
    url = None
    for k in ("images", "videos"):
        if d.get(k):
            url = d[k][0].get("url")
            break
    url = url or (d.get("video") or {}).get("url")
    if not url:
        print("sin archivo en el estado:", json.dumps(d)[:300])
        return 1
    urllib.request.urlretrieve(url, a.a)
    print("guardado en", a.a)
    return 0


def main():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--cuenta", choices=["n8n", "api-n8n"], default="n8n",
                   help="n8n = 'API KEY Higgsfield + N8N' (con saldo); api-n8n = bloque 'API N8N' (sin saldo)")
    sp = p.add_subparsers(dest="cmd", required=True)
    s = sp.add_parser("models")
    s.add_argument("--filtro")
    s.set_defaults(f=cmd_models)
    s = sp.add_parser("estimar")
    s.add_argument("slug")
    s.add_argument("--segundos", type=float, default=5)
    s.add_argument("--imagenes", type=int, default=1)
    s.set_defaults(f=cmd_estimar)
    s = sp.add_parser("generar")
    s.add_argument("slug")
    s.add_argument("--prompt")
    s.add_argument("--param", action="append")
    s.add_argument("--segundos", type=float, default=5)
    s.add_argument("--imagenes", type=int, default=1)
    s.add_argument("--si", action="store_true", help="confirma el gasto (sin esto solo valida)")
    s.add_argument("--espera", type=int, default=240)
    s.add_argument("--intervalo", type=int, default=4)
    s.set_defaults(f=cmd_generar)
    s = sp.add_parser("estado")
    s.add_argument("request_id")
    s.set_defaults(f=cmd_estado)
    s = sp.add_parser("bajar")
    s.add_argument("request_id")
    s.add_argument("--a", required=True)
    s.set_defaults(f=cmd_bajar)
    a = p.parse_args()
    c = Cliente(a.cuenta)
    try:
        sys.exit(a.f(c, a))
    except SystemExit:
        raise
    except Exception as e:  # nunca dejar escapar una clave en un traceback
        print("error:", c.red(repr(e))[:400])
        sys.exit(1)


if __name__ == "__main__":
    main()
