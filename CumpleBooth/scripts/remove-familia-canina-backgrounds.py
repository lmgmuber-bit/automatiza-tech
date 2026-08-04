"""Crea los seis recortes transparentes desde los retratos aprobados."""

from __future__ import annotations

from io import BytesIO
from pathlib import Path

from PIL import Image
from rembg import new_session, remove


ROOT = Path(__file__).resolve().parents[1]
THEME = ROOT / "public" / "themes" / "familia-canina"
NAMES = ("azulita", "chispa", "papa-marino", "mama-coral", "muffin", "chloe")


def trim_transparent(image: Image.Image) -> Image.Image:
    alpha = image.getchannel("A")
    bounds = alpha.getbbox()
    if not bounds:
        raise RuntimeError("El modelo no encontró primer plano")
    cropped = image.crop(bounds)
    padding = max(18, round(max(cropped.size) * 0.035))
    canvas = Image.new("RGBA", (cropped.width + padding * 2, cropped.height + padding * 2), (0, 0, 0, 0))
    canvas.alpha_composite(cropped, (padding, padding))
    return canvas


def main() -> None:
    session = new_session("u2net")
    for name in NAMES:
        source = THEME / f"{name}.jpg"
        target = THEME / f"{name}-cut.png"
        result = remove(
            source.read_bytes(),
            session=session,
            alpha_matting=True,
            alpha_matting_foreground_threshold=245,
            alpha_matting_background_threshold=15,
            alpha_matting_erode_size=8,
        )
        image = Image.open(BytesIO(result)).convert("RGBA")
        trim_transparent(image).save(target, optimize=True)
        print(target)


if __name__ == "__main__":
    main()
