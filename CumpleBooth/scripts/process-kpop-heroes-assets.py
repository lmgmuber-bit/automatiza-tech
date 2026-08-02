"""Procesa assets de K-Pop y Heroes: normaliza fondos/retratos, genera puzzles,
intenta generar recortes con rembg. Ejecucion local, sin red ni IA externa."""

from __future__ import annotations

import hashlib
import os
import sys
from pathlib import Path

from PIL import Image, ImageOps

ROOT = Path(__file__).resolve().parents[1]
RAW_KPOP = ROOT / "design" / "renders" / "kpop-images-raw"
RAW_HEROES = ROOT / "design" / "renders" / "heroes-images-raw"
THEME_KPOP = ROOT / "public" / "themes" / "kpop"
THEME_HEROES = ROOT / "public" / "themes" / "heroes"
WIDTH, HEIGHT = 1080, 1920
PUZZLE_SIZE = 900
QUALITY = 95

HAS_REMBG = False

KPOP_PORTRAITS = [
    ("rumi-v2.png", "rumi.jpg"),
    ("mira-v2.png", "mira.jpg"),
    ("zoey-v2.png", "zoey.jpg"),
    ("luna.png", "luna.jpg"),
    ("derpy.png", "derpy.jpg"),
    ("sussie.png", "sussie.jpg"),
]

HEROES_PORTRAITS = [
    ("capitan.png", "capitan.jpg"),
    ("arana.png", "arana.jpg"),
    ("gigante.png", "gigante.jpg"),
    ("hierro.png", "hierro.jpg"),
    ("trueno.png", "trueno.jpg"),
    ("pantera-v2.png", "pantera.jpg"),
]


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with open(path, "rb") as f:
        while chunk := f.read(65536):
            h.update(chunk)
    return h.hexdigest()


def fit_1080x1920(source: Path, target: Path) -> None:
    """Convierte cualquier imagen a JPG 1080x1920 sin deformar (crop + resize)."""
    with Image.open(source) as img:
        converted = ImageOps.fit(
            img.convert("RGB"),
            (WIDTH, HEIGHT),
            method=Image.Resampling.LANCZOS,
            centering=(0.5, 0.5),
        )
        converted.save(target, format="JPEG", quality=QUALITY, optimize=True, subsampling=0)


def build_puzzle(source: Path, target: Path) -> None:
    """Deriva puzzle cuadrado 900x900 centrado en rostro/rasgos."""
    with Image.open(source) as img:
        w, h = img.size

        target_ratio = 1.0  # square
        img_ratio = w / h

        if img_ratio > target_ratio:
            new_w = int(h * target_ratio)
            left = (w - new_w) // 2
            crop = img.crop((left, 0, left + new_w, h))
        else:
            new_h = int(w / target_ratio)
            top = int(h * 0.05)  # slight bias toward upper body/face
            top = max(0, min(top, h - new_h))
            crop = img.crop((0, top, w, top + new_h))

        result = crop.resize((PUZZLE_SIZE, PUZZLE_SIZE), Image.Resampling.LANCZOS)
        result.save(target, format="JPEG", quality=QUALITY, optimize=True, subsampling=0)


def build_cuts_with_rembg(theme_path: Path, raw_path: Path, portraits: list[tuple[str, str]]) -> list[str]:
    """Intenta generar *-cut.png con rembg. Retorna lista de exitos."""
    from io import BytesIO
    from rembg import new_session, remove

    successes = []
    session = new_session("u2net")
    for src_name, jpg_name in portraits:
        cut_name = jpg_name.replace(".jpg", "-cut.png")
        source = raw_path / src_name
        target = theme_path / cut_name

        with Image.open(source) as img:
            img_1080 = ImageOps.fit(
                img.convert("RGB"), (WIDTH, HEIGHT),
                method=Image.Resampling.LANCZOS, centering=(0.5, 0.5),
            )
            buf = BytesIO()
            img_1080.save(buf, format="PNG")
            png_bytes = buf.getvalue()

        result = remove(
            png_bytes,
            session=session,
            alpha_matting=True,
            alpha_matting_foreground_threshold=245,
            alpha_matting_background_threshold=15,
            alpha_matting_erode_size=8,
        )
        cut = Image.open(BytesIO(result)).convert("RGBA")

        alpha = cut.getchannel("A")
        bounds = alpha.getbbox()
        if bounds:
            cut = cut.crop(bounds)
            padding = max(18, round(max(cut.size) * 0.035))
            canvas = Image.new("RGBA", (cut.width + padding * 2, cut.height + padding * 2), (0, 0, 0, 0))
            canvas.alpha_composite(cut, (padding, padding))
            cut = canvas

        cut.save(target, optimize=True)
        successes.append(cut_name)
        print(f"  CUT OK: {cut_name} ({cut.size[0]}x{cut.size[1]})")
    return successes


def compose_hero_banner() -> None:
    """Compone fondo-banner.jpg para Heroes sobre fondo-banner-empty.png
    usando los seis *-cut.png."""
    base = THEME_HEROES / "fondo-banner-empty.jpg"
    target = THEME_HEROES / "fondo-banner.jpg"

    if not base.exists():
        print("  FALTA fondo-banner-empty.jpg normalizado")
        return

    cut_names = ["capitan-cut.png", "arana-cut.png", "gigante-cut.png",
                 "hierro-cut.png", "trueno-cut.png", "pantera-cut.png"]
    missing = [c for c in cut_names if not (THEME_HEROES / c).exists()]
    if missing:
        print(f"  Faltan recortes para componer banner: {missing}")
        return

    canvas = Image.open(base).convert("RGBA")

    # Layout 2 filas: 3 arriba, 3 abajo
    positions = [
        (150, 380, 650),
        (540, 380, 650),
        (930, 380, 650),
        (200, 1100, 700),
        (540, 1100, 700),
        (880, 1100, 700),
    ]

    for (x, bottom, max_h), cut_name in zip(positions, cut_names):
        with Image.open(THEME_HEROES / cut_name) as char:
            char_img = char.convert("RGBA")
            scale = max_h / char_img.height
            new_w = round(char_img.width * scale)
            char_resized = char_img.resize((new_w, max_h), Image.Resampling.LANCZOS)
            canvas.alpha_composite(char_resized, (x - new_w // 2, bottom - max_h))

    canvas.convert("RGB").save(target, format="JPEG", quality=QUALITY, optimize=True, subsampling=0)
    print(f"  BANNER COMPUESTO: {target}")


def process_theme(theme_slug: str, theme_path: Path, raw_path: Path,
                  bgs: list[tuple[str, str]],
                  portraits: list[tuple[str, str]],
                  has_group_banner: bool = False) -> dict:
    """Procesa una tematica completa."""
    theme_path.mkdir(parents=True, exist_ok=True)
    results = {"ok": [], "fail": [], "cuts_skipped": []}

    # 1. Fondos
    for src_name, dst_name in bgs:
        try:
            src = raw_path / src_name
            dst = theme_path / dst_name
            if not src.exists():
                results["fail"].append(f"{dst_name}: fuente no encontrada {src_name}")
                continue
            fit_1080x1920(src, dst)
            results["ok"].append(dst_name)
            print(f"  OK: {dst_name} ({dst.stat().st_size/1024:.0f}KB)")
        except Exception as e:
            results["fail"].append(f"{dst_name}: {e}")

    # 2. Retratos
    for src_name, dst_name in portraits:
        try:
            src = raw_path / src_name
            dst = theme_path / dst_name
            if not src.exists():
                results["fail"].append(f"{dst_name}: fuente no encontrada {src_name}")
                continue
            fit_1080x1920(src, dst)
            results["ok"].append(dst_name)
            print(f"  OK: {dst_name} ({dst.stat().st_size/1024:.0f}KB)")
        except Exception as e:
            results["fail"].append(f"{dst_name}: {e}")

    # 3. Puzzles
    for src_name, dst_name in portraits:
        puzzle_name = "puzzle-" + dst_name.replace(".jpg", ".jpg")
        try:
            jpg_path = theme_path / dst_name
            puzzle_path = theme_path / puzzle_name
            if not jpg_path.exists():
                results["fail"].append(f"{puzzle_name}: falta retrato {dst_name}")
                continue
            build_puzzle(jpg_path, puzzle_path)
            results["ok"].append(puzzle_name)
            print(f"  OK: {puzzle_name} ({puzzle_path.stat().st_size/1024:.0f}KB)")
        except Exception as e:
            results["fail"].append(f"{puzzle_name}: {e}")

    # 4. Recortes (requiere rembg)
    if HAS_REMBG:
        try:
            build_cuts_with_rembg(theme_path, raw_path, portraits)
            for _, dst_name in portraits:
                cut_name = dst_name.replace(".jpg", "-cut.png")
                if (theme_path / cut_name).exists():
                    results["ok"].append(cut_name)
                else:
                    results["fail"].append(f"{cut_name}: fallo en generacion")
        except Exception as e:
            results["cuts_skipped"] = [f"{p[1].replace('.jpg','-cut.png')}: {e}"]
    else:
        for _, dst_name in portraits:
            cut_name = dst_name.replace(".jpg", "-cut.png")
            results["cuts_skipped"].append(cut_name)

    return results


def verify_assets(theme_path: Path, expected: list[str]) -> list[dict]:
    """Verifica dimensiones y formatos de los archivos esperados."""
    rows = []
    for name in expected:
        p = theme_path / name
        if p.exists():
            try:
                with Image.open(p) as img:
                    rows.append({
                        "file": name,
                        "path": str(p),
                        "size": f"{img.size[0]}x{img.size[1]}",
                        "mode": img.mode,
                        "kb": round(p.stat().st_size / 1024, 1),
                        "sha256": sha256(p)[:16] + "...",
                        "status": "OK",
                    })
            except Exception as e:
                rows.append({"file": name, "path": str(p), "status": f"ERROR: {e}"})
        else:
            rows.append({"file": name, "path": str(p), "status": "FALTA"})
    return rows


def main() -> None:
    global HAS_REMBG
    try:
        import rembg  # noqa: F401
        HAS_REMBG = True
    except ImportError:
        HAS_REMBG = False
        print("*** ADVERTENCIA: rembg no instalado. Los *-cut.png NO se generaran. ***")
        print("*** Dependencia exacta requerida: pip install rembg (usa onnxruntime ya presente) ***")
        print()

    # ===== K-POP =====
    print("=" * 60)
    print("TEMATICA: K-POP")
    print("=" * 60)

    kpop_bgs = [
        ("fondo-banner.png", "fondo-banner.jpg"),   # grupo aprobado
        ("fondo-sala.png", "fondo-sala.jpg"),
        ("fondo-juego-escenario.png", "fondo-juego-escenario.jpg"),
    ]
    kpop_results = process_theme("kpop", THEME_KPOP, RAW_KPOP, kpop_bgs, KPOP_PORTRAITS)

    # ===== HEROES =====
    print()
    print("=" * 60)
    print("TEMATICA: HEROES")
    print("=" * 60)

    heroes_bgs = [
        ("fondo-banner-empty.png", "fondo-banner-empty.jpg"),
        ("fondo-sala.png", "fondo-sala.jpg"),
        ("fondo-juego-ciudad.png", "fondo-juego-ciudad.jpg"),
    ]
    heroes_results = process_theme("heroes", THEME_HEROES, RAW_HEROES, heroes_bgs, HEROES_PORTRAITS)

    # Componer banner Heroes si hay recortes
    if HAS_REMBG:
        print()
        print("--- Composicion banner Heroes ---")
        compose_hero_banner()
        if (THEME_HEROES / "fondo-banner.jpg").exists():
            heroes_results["ok"].append("fondo-banner.jpg (compuesto)")

    # ===== VERIFICACION =====
    print()
    print("=" * 60)
    print("VERIFICACION FINAL")
    print("=" * 60)

    kpop_expected = [
        "fondo-banner.jpg", "fondo-sala.jpg", "fondo-juego-escenario.jpg",
        "rumi.jpg", "mira.jpg", "zoey.jpg", "luna.jpg", "derpy.jpg", "sussie.jpg",
        "rumi-cut.png", "mira-cut.png", "zoey-cut.png",
        "luna-cut.png", "derpy-cut.png", "sussie-cut.png",
        "puzzle-rumi.jpg", "puzzle-mira.jpg", "puzzle-zoey.jpg",
        "puzzle-luna.jpg", "puzzle-derpy.jpg", "puzzle-sussie.jpg",
    ]
    heroes_expected = [
        "fondo-banner.jpg", "fondo-sala.jpg", "fondo-juego-ciudad.jpg",
        "capitan.jpg", "arana.jpg", "gigante.jpg", "hierro.jpg", "trueno.jpg", "pantera.jpg",
        "capitan-cut.png", "arana-cut.png", "gigante-cut.png",
        "hierro-cut.png", "trueno-cut.png", "pantera-cut.png",
        "puzzle-capitan.jpg", "puzzle-arana.jpg", "puzzle-gigante.jpg",
        "puzzle-hierro.jpg", "puzzle-trueno.jpg", "puzzle-pantera.jpg",
    ]

    print()
    print("--- K-POP ---")
    kpop_rows = verify_assets(THEME_KPOP, kpop_expected)
    for r in kpop_rows:
        status_icon = "OK" if r["status"] == "OK" else "!!"
        extra = f'  {r["size"]:>10s} {r["mode"]:>5s} {r["kb"]:>8.1f}KB {r["sha256"]}' if r["status"] == "OK" else ""
        print(f"  [{status_icon}] {r['file']:35s} {r['status']}{extra}")

    print()
    print("--- HEROES ---")
    heroes_rows = verify_assets(THEME_HEROES, heroes_expected)
    for r in heroes_rows:
        status_icon = "OK" if r["status"] == "OK" else "!!"
        extra = f'  {r["size"]:>10s} {r["mode"]:>5s} {r["kb"]:>8.1f}KB {r["sha256"]}' if r["status"] == "OK" else ""
        print(f"  [{status_icon}] {r['file']:35s} {r['status']}{extra}")

    # ===== RESUMEN =====
    print()
    print("=" * 60)
    print("RESUMEN")
    print("=" * 60)
    total_ok = len(kpop_results["ok"]) + len(heroes_results["ok"])
    total_fail = len(kpop_results["fail"]) + len(heroes_results["fail"])
    total_cuts = len(kpop_results.get("cuts_skipped", [])) + len(heroes_results.get("cuts_skipped", []))
    print(f"  OK: {total_ok}")
    print(f"  Fallos: {total_fail}")
    print(f"  Recortes pendientes (falta rembg): {total_cuts}")
    print(f"  Total esperado: 42 (21 por tema)")
    print(f"  Generados: {total_ok}")

    if not HAS_REMBG:
        print()
        print("*** DEPENDENCIA FALTANTE: rembg ***")
        print("    pip install rembg")
        print("    onnxruntime ya esta instalado (v1.20.1)")
        print("    El script remove-familia-canina-backgrounds.py usa el mismo patron.")
        print()

    if total_fail > 0:
        print("FALLOS DETALLADOS:")
        for r in kpop_results["fail"]:
            print(f"  [KPop] {r}")
        for r in heroes_results["fail"]:
            print(f"  [Heroes] {r}")


if __name__ == "__main__":
    main()
