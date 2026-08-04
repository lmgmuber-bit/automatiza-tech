"""Construye assets deterministas de la temática familia-canina.

No llama proveedores externos ni contiene nombres de franquicias. El marco de
fondo-sala y frameBox comparten exactamente la misma geometría normalizada.
"""

from __future__ import annotations

import math
import wave
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageOps


ROOT = Path(__file__).resolve().parents[1]
THEME = ROOT / "public" / "themes" / "familia-canina"
WIDTH, HEIGHT = 1080, 1920
FRAME_BOX = {"x": 0.25, "y": 0.17, "w": 0.50, "h": 0.28125}


def fit_vertical(path: Path) -> Image.Image:
    with Image.open(path) as source:
        return ImageOps.fit(
            source.convert("RGB"),
            (WIDTH, HEIGHT),
            method=Image.Resampling.LANCZOS,
            centering=(0.5, 0.5),
        )


def rounded_layer(
    image: Image.Image,
    box: tuple[int, int, int, int],
    radius: int,
    fill: tuple[int, int, int, int],
) -> None:
    ImageDraw.Draw(image).rounded_rectangle(box, radius=radius, fill=fill)


def draw_paw(draw: ImageDraw.ImageDraw, x: int, y: int, color: tuple[int, int, int, int]) -> None:
    draw.ellipse((x - 10, y - 3, x + 10, y + 15), fill=color)
    for dx, dy in ((-15, -12), (-5, -18), (6, -18), (16, -11)):
        draw.ellipse((x + dx - 5, y + dy - 6, x + dx + 5, y + dy + 6), fill=color)


def build_room() -> None:
    room = fit_vertical(THEME / "portrait-stage-v2.png").convert("RGBA")

    side = round(FRAME_BOX["w"] * WIDTH)
    left = round(FRAME_BOX["x"] * WIDTH)
    top = round(FRAME_BOX["y"] * HEIGHT)
    outer = (left, top, left + side, top + side)

    shadow = Image.new("RGBA", room.size, (0, 0, 0, 0))
    rounded_layer(shadow, (left + 10, top + 18, left + side + 10, top + side + 18), 42, (23, 50, 77, 105))
    shadow = shadow.filter(ImageFilter.GaussianBlur(22))
    room.alpha_composite(shadow)

    frame = Image.new("RGBA", room.size, (0, 0, 0, 0))
    rounded_layer(frame, outer, 44, (117, 207, 255, 255))
    rounded_layer(frame, (left + 15, top + 15, left + side - 15, top + side - 15), 34, (240, 190, 76, 255))
    rounded_layer(frame, (left + 28, top + 28, left + side - 28, top + side - 28), 25, (255, 241, 211, 255))
    rounded_layer(frame, (left + 46, top + 46, left + side - 46, top + side - 46), 9, (255, 250, 238, 255))

    details = ImageDraw.Draw(frame)
    for px, py in (
        (left + 28, top + 33),
        (left + side - 28, top + 33),
        (left + 28, top + side - 25),
        (left + side - 28, top + side - 25),
    ):
        details.ellipse((px - 19, py - 19, px + 19, py + 19), fill=(22, 137, 216, 255))
        draw_paw(details, px, py, (255, 241, 211, 255))

    room.alpha_composite(frame)
    room.convert("RGB").save(THEME / "fondo-sala.jpg", quality=95, subsampling=0, optimize=True)


def build_banner() -> None:
    banner = fit_vertical(THEME / "invitation" / "invitation-base-v1.png")
    banner.save(THEME / "fondo-banner.jpg", quality=95, subsampling=0, optimize=True)


def place_character(canvas: Image.Image, name: str, x: int, bottom: int, target_height: int) -> None:
    with Image.open(THEME / f"{name}-cut.png") as source:
        character = source.convert("RGBA")
    scale = target_height / character.height
    width = round(character.width * scale)
    character = character.resize((width, target_height), Image.Resampling.LANCZOS)
    canvas.alpha_composite(character, (x, bottom - target_height))


def build_group() -> None:
    canvas = Image.new("RGBA", (1800, 1100), (0, 0, 0, 0))
    # Adultos atrás; las cuatro figuras pequeñas adelante. La composición
    # funciona como watermark y no altera ninguno de los recortes aprobados.
    place_character(canvas, "papa-marino", 250, 1040, 940)
    place_character(canvas, "mama-coral", 1080, 1040, 940)
    place_character(canvas, "muffin", 50, 1080, 660)
    place_character(canvas, "azulita", 475, 1080, 760)
    place_character(canvas, "chispa", 870, 1080, 700)
    place_character(canvas, "chloe", 1390, 1080, 660)
    canvas.save(THEME / "grupo-personajes.png", optimize=True)


def midi_frequency(note: int) -> float:
    return 440.0 * (2.0 ** ((note - 69) / 12.0))


def add_pluck(track: np.ndarray, start: int, length: int, note: int, gain: float) -> None:
    end = min(track.size, start + length)
    if end <= start:
        return
    t = np.arange(end - start, dtype=np.float64) / 44100.0
    frequency = midi_frequency(note)
    envelope = np.exp(-4.8 * t / max(t[-1] if t.size > 1 else 1.0, 0.08))
    tone = (
        np.sin(2 * math.pi * frequency * t)
        + 0.34 * np.sin(2 * math.pi * frequency * 2 * t)
        + 0.12 * np.sin(2 * math.pi * frequency * 3 * t)
    )
    track[start:end] += gain * envelope * tone


def build_music() -> Path:
    sample_rate = 44100
    bpm = 120
    beat_seconds = 60.0 / bpm
    beats = 64
    total_samples = round(beats * beat_seconds * sample_rate)
    track = np.zeros(total_samples, dtype=np.float64)

    chords = ((60, 64, 67), (65, 69, 72), (57, 60, 64), (55, 59, 62))
    melody = (72, 74, 76, 79, 76, 74, 72, 67, 69, 72, 74, 76, 74, 72, 69, 67)
    for beat in range(beats):
        start = round(beat * beat_seconds * sample_rate)
        chord = chords[(beat // 4) % len(chords)]
        for note in chord:
            add_pluck(track, start, round(beat_seconds * 1.9 * sample_rate), note, 0.055)
        add_pluck(track, start, round(beat_seconds * 0.88 * sample_rate), melody[beat % len(melody)], 0.15)
        add_pluck(track, start, round(beat_seconds * 0.95 * sample_rate), chord[0] - 12, 0.11)

        # Percusión original, suave y apta para un kiosco infantil.
        drum_len = min(round(0.10 * sample_rate), total_samples - start)
        if drum_len > 0:
            dt = np.arange(drum_len, dtype=np.float64) / sample_rate
            kick = np.sin(2 * math.pi * (90 - 40 * dt / 0.10) * dt) * np.exp(-30 * dt)
            track[start : start + drum_len] += 0.11 * kick

    peak = max(float(np.max(np.abs(track))), 1e-6)
    track = np.clip(track / peak * 0.82, -1.0, 1.0)
    stereo = np.column_stack((track, np.roll(track, 95) * 0.96))
    pcm = (stereo * 32767.0).astype("<i2")
    wav_path = THEME / "musica-fondo.wav"
    with wave.open(str(wav_path), "wb") as output:
        output.setnchannels(2)
        output.setsampwidth(2)
        output.setframerate(sample_rate)
        output.writeframes(pcm.tobytes())
    return wav_path


def main() -> None:
    THEME.mkdir(parents=True, exist_ok=True)
    build_banner()
    build_room()
    build_group()
    wav_path = build_music()
    print(f"frameBox={FRAME_BOX}")
    print(f"WAV={wav_path}")


if __name__ == "__main__":
    main()
