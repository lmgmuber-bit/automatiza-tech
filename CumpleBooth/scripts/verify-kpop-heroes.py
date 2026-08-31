"""Verifica todos los assets de K-Pop y Heroes."""
from pathlib import Path
from PIL import Image
import hashlib

THEME_K = Path("C:/wamp64/www/automatiza-tech/CumpleBooth/public/themes/kpop")
THEME_H = Path("C:/wamp64/www/automatiza-tech/CumpleBooth/public/themes/heroes")


def sha256(p):
    h = hashlib.sha256()
    with open(p, "rb") as f:
        while chunk := f.read(65536):
            h.update(chunk)
    return h.hexdigest()


def check(path, name, exp_w, exp_h, exp_mode):
    f = path / name
    if not f.exists():
        return {"file": name, "dims": "-", "mode": "-", "kb": 0, "sha256": "-", "status": "FALTA"}
    try:
        with Image.open(f) as img:
            w, h = img.size
            m = img.mode
            kb = f.stat().st_size / 1024
            ok_dim = (exp_w is None or w == exp_w) and (exp_h is None or h == exp_h)
            ok_mode = m == exp_mode
            if ok_dim and ok_mode:
                status = "OK"
            elif not ok_dim:
                status = f"DIM: esperado {exp_w}x{exp_h}, real {w}x{h}"
            else:
                status = f"MODE: esperado {exp_mode}, real {m}"
            return {"file": name, "dims": f"{w}x{h}", "mode": m, "kb": round(kb, 1),
                    "sha256": sha256(f)[:24] + "...", "status": status}
    except Exception as e:
        return {"file": name, "dims": "-", "mode": "-", "kb": 0, "sha256": "-", "status": f"ERROR: {e}"}


all_checks = [
    ("K-POP", THEME_K, [
        ("fondo-banner.jpg", 1080, 1920, "RGB"),
        ("fondo-sala.jpg", 1080, 1920, "RGB"),
        ("fondo-juego-escenario.jpg", 1080, 1920, "RGB"),
        ("rumi.jpg", 1080, 1920, "RGB"),
        ("mira.jpg", 1080, 1920, "RGB"),
        ("zoey.jpg", 1080, 1920, "RGB"),
        ("luna.jpg", 1080, 1920, "RGB"),
        ("derpy.jpg", 1080, 1920, "RGB"),
        ("sussie.jpg", 1080, 1920, "RGB"),
        ("rumi-cut.png", None, None, "RGBA"),
        ("mira-cut.png", None, None, "RGBA"),
        ("zoey-cut.png", None, None, "RGBA"),
        ("luna-cut.png", None, None, "RGBA"),
        ("derpy-cut.png", None, None, "RGBA"),
        ("sussie-cut.png", None, None, "RGBA"),
        ("puzzle-rumi.jpg", 900, 900, "RGB"),
        ("puzzle-mira.jpg", 900, 900, "RGB"),
        ("puzzle-zoey.jpg", 900, 900, "RGB"),
        ("puzzle-luna.jpg", 900, 900, "RGB"),
        ("puzzle-derpy.jpg", 900, 900, "RGB"),
        ("puzzle-sussie.jpg", 900, 900, "RGB"),
    ]),
    ("HEROES", THEME_H, [
        ("fondo-banner.jpg", 1080, 1920, "RGB"),
        ("fondo-sala.jpg", 1080, 1920, "RGB"),
        ("fondo-juego-ciudad.jpg", 1080, 1920, "RGB"),
        ("capitan.jpg", 1080, 1920, "RGB"),
        ("arana.jpg", 1080, 1920, "RGB"),
        ("gigante.jpg", 1080, 1920, "RGB"),
        ("hierro.jpg", 1080, 1920, "RGB"),
        ("trueno.jpg", 1080, 1920, "RGB"),
        ("pantera.jpg", 1080, 1920, "RGB"),
        ("capitan-cut.png", None, None, "RGBA"),
        ("arana-cut.png", None, None, "RGBA"),
        ("gigante-cut.png", None, None, "RGBA"),
        ("hierro-cut.png", None, None, "RGBA"),
        ("trueno-cut.png", None, None, "RGBA"),
        ("pantera-cut.png", None, None, "RGBA"),
        ("puzzle-capitan.jpg", 900, 900, "RGB"),
        ("puzzle-arana.jpg", 900, 900, "RGB"),
        ("puzzle-gigante.jpg", 900, 900, "RGB"),
        ("puzzle-hierro.jpg", 900, 900, "RGB"),
        ("puzzle-trueno.jpg", 900, 900, "RGB"),
        ("puzzle-pantera.jpg", 900, 900, "RGB"),
    ]),
]

for theme, path, files in all_checks:
    print(f"\n{'='*100}")
    print(f"  {theme}  ({path})")
    print(f"{'='*100}")
    header = f"  {'ARCHIVO':28s} {'DIMENSIONES':12s} {'MODO':5s} {'KB':>8s}  {'SHA-256':30s} {'STATUS'}"
    print(header)
    print("  " + "-" * 108)

    for name, ew, eh, em in files:
        r = check(path, name, ew, eh, em)
        st = r["status"]
        dims = r["dims"]
        mode = r["mode"]
        kb = r["kb"]
        sha = r["sha256"]
        if st == "OK":
            print(f"  [OK] {name:25s} {dims:>12s} {mode:>5s} {kb:>8.1f}  {sha:30s}")
        elif st == "FALTA":
            print(f"  [--] {name:25s} {'-':>12s} {'-':>5s} {'-':>8s}  {'-':30s}  FALTA")
        else:
            print(f"  [!!] {name:25s} {dims:>12s} {mode:>5s} {kb:>8.1f}  {'-':30s}  {st}")

total_ok = 0
total_falta = 0
for theme, path, files in all_checks:
    for name, ew, eh, em in files:
        r = check(path, name, ew, eh, em)
        if r["status"] == "OK":
            total_ok += 1
        elif r["status"] == "FALTA":
            total_falta += 1

print(f"\n{'='*100}")
print(f"  TOTAL: {total_ok}/42 generados  |  {total_falta} faltantes  |  12 requieren rembg")
print(f"{'='*100}")
