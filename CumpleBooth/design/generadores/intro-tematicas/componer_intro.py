# -*- coding: utf-8 -*-
"""Videos de bienvenida de las temáticas nuevas (2026-09-26): Fiestas Patrias y Noche de Brujas.

Igual que las bienvenidas infantiles (720 × 1280, 24 fps, con voz, alrededor de −16 LUFS, sin logo): dos tomas de 5 s
de Kling 3.0 Pro unidas con un fundido, la voz de Alice (ElevenLabs) y encima, dibujado por código, un título breve
y, en Fiestas Patrias, la guirnalda de banderas exactas meciéndose (la IA deforma la bandera: nunca se le pide).

Uso: python componer_intro.py fiestas-patrias | noche-brujas
Entradas en ORIGEN (fuera del repo): <toma>.mp4 de Kling y voz-<tema>.mp3. Salida: public/themes/<tema>/welcome-*.mp4
"""
import math
import os
import shutil
import subprocess
import sys
import tempfile

from PIL import Image, ImageDraw, ImageFilter

AQUI = os.path.dirname(os.path.abspath(__file__))
CB = os.path.abspath(os.path.join(AQUI, "..", "..", ".."))
sys.path.insert(0, os.path.join(AQUI, "..", "fiestas-patrias"))
from fiestas_patrias import bandera_chile, con_relieve, fuente  # noqa: E402

ORIGEN = os.environ.get("INTRO_ORIGEN", r"C:/Users/luis_/Documents/CumpleClick/tematicas-adultos-2026-09-26/videos-intro")
W, H, FPS = 720, 1280, 24
FUNDIDO = 0.6
VOZ_INICIO = 0.7
LUFS = -16.0

TEMAS = {
    "fiestas-patrias": {"tomas": ("fp-1", "fp-2"), "voz": "voz-fiestas-patrias.mp3",
                        "salida": os.path.join(CB, "public", "themes", "fiestas-patrias", "welcome-fiestas-patrias.mp4"),
                        "titulo": ("Fiestas Patrias", 0.3, 3.6), "cierre": ("¡Viva Chile!", 7.3),
                        "relleno": (255, 255, 255), "borde": (0, 57, 166), "sombra": (213, 43, 30), "guirnalda": True},
    "noche-brujas": {"tomas": ("nb-1", "nb-2"), "voz": "voz-noche-brujas.mp3",
                     "salida": os.path.join(CB, "public", "themes", "adulto-noche-brujas", "welcome-noche-brujas.mp4"),
                     "titulo": ("Noche de Brujas", 0.4, 3.8), "cierre": None,
                     "relleno": (242, 180, 90), "borde": (42, 21, 48), "sombra": (20, 8, 24), "guirnalda": False},
}


def run(*args):
    subprocess.run(args, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)


def duracion(ruta):
    out = subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", ruta],
                         capture_output=True, text=True, check=True)
    return float(out.stdout.strip())


def lufs(ruta):
    out = subprocess.run(["ffmpeg", "-hide_banner", "-nostats", "-i", ruta, "-af", "ebur128", "-f", "null", "-"],
                         capture_output=True, text=True)
    lineas = [l for l in out.stderr.splitlines() if l.strip().startswith("I:")]
    return float(lineas[-1].split()[1])


def alfa(t, entra, sale, rampa=0.5):
    if t < entra or t > sale + rampa:
        return 0.0
    if t < entra + rampa:
        return (t - entra) / rampa
    if t > sale:
        return 1.0 - (t - sale) / rampa
    return 1.0


def texto(capa, cadena, cy, tam, t_alfa, cfg):
    if t_alfa <= 0:
        return
    f = fuente(800, tam)
    trazo = max(3, tam // 11)
    d = ImageDraw.Draw(capa)
    caja = d.textbbox((0, 0), cadena, font=f, stroke_width=trazo)
    x = (W - (caja[2] - caja[0])) / 2 - caja[0]
    y = cy - (caja[3] + caja[1]) / 2
    t = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    dt = ImageDraw.Draw(t)
    dt.text((x + tam * 0.05, y + tam * 0.07), cadena, font=f, fill=cfg["sombra"] + (255,), stroke_width=trazo, stroke_fill=cfg["sombra"] + (255,))
    dt.text((x, y), cadena, font=f, fill=cfg["relleno"] + (255,), stroke_width=trazo, stroke_fill=cfg["borde"] + (255,))
    halo = t.getchannel("A").filter(ImageFilter.GaussianBlur(tam * 0.14)).point(lambda a: int(a * 0.5))
    oscuro = Image.new("RGBA", (W, H), (10, 10, 30, 0))
    oscuro.putalpha(halo)
    capa.alpha_composite(oscuro)
    capa.alpha_composite(t)
    if t_alfa < 1:
        capa.putalpha(capa.getchannel("A").point(lambda a: int(a * t_alfa)))


class Guirnalda:
    """Banderitas exactas colgando de un hilo que se mece despacio. Se dibujan una vez y se rotan por cuadro."""

    def __init__(self, n=11, ancho=54, y0=36, caida=52):
        self.n, self.ancho, self.y0, self.caida = n, ancho, y0, caida
        self.banderas = [con_relieve(bandera_chile(ancho), i * 1.3) for i in range(n)]

    def y(self, x, t):
        u = x / W
        return self.y0 + (self.caida + 4 * math.sin(t * 1.4)) * 4 * u * (1 - u)

    def dibujar(self, capa, t):
        d = ImageDraw.Draw(capa)
        d.line([(x, self.y(x, t)) for x in range(-10, W + 11, 6)], fill=(60, 40, 25, 235), width=2)
        paso = W / self.n
        for i, flag in enumerate(self.banderas):
            x = i * paso + (paso - self.ancho) / 2
            y0, y1 = self.y(x, t), self.y(x + self.ancho, t)
            ang = math.degrees(math.atan2(y1 - y0, self.ancho)) + 3.0 * math.sin(t * 2.1 + i * 0.9)
            rot = flag.rotate(-ang, resample=Image.BICUBIC, expand=True)
            dx, dy = (rot.width - flag.width) / 2, (rot.height - flag.height) / 2
            sombra = Image.new("RGBA", rot.size, (0, 0, 0, 0))
            sombra.putalpha(rot.getchannel("A").point(lambda a: int(a * 0.3)))
            capa.alpha_composite(sombra.filter(ImageFilter.GaussianBlur(3)), (int(x - dx + 3), int(y0 - dy + 5)))
            capa.alpha_composite(rot, (int(x - dx), int(y0 - dy + 1)))


def componer(tema):
    cfg = TEMAS[tema]
    tmp = tempfile.mkdtemp(prefix="intro-")
    try:
        # 1) Las dos tomas a 720x1280 y 24 fps, unidas con un fundido.
        normal = []
        for i, toma in enumerate(cfg["tomas"]):
            dst = os.path.join(tmp, "t%d.mp4" % i)
            run("ffmpeg", "-y", "-i", os.path.join(ORIGEN, toma + ".mp4"), "-an", "-vf",
                "scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d,fps=%d,format=yuv420p" % (W, H, W, H, FPS),
                "-c:v", "libx264", "-crf", "14", "-preset", "fast", dst)
            normal.append(dst)
        d0 = duracion(normal[0])
        base = os.path.join(tmp, "base.mp4")
        run("ffmpeg", "-y", "-i", normal[0], "-i", normal[1], "-filter_complex",
            "[0:v][1:v]xfade=transition=fade:duration=%.2f:offset=%.3f,format=yuv420p" % (FUNDIDO, d0 - FUNDIDO),
            "-c:v", "libx264", "-crf", "14", "-preset", "fast", base)
        total = duracion(base)

        # 2) La capa de encima, cuadro por cuadro.
        cuadros = os.path.join(tmp, "capa")
        os.makedirs(cuadros)
        guirnalda = Guirnalda() if cfg["guirnalda"] else None
        n = int(round(total * FPS))
        for k in range(n):
            t = k / FPS
            capa = Image.new("RGBA", (W, H), (0, 0, 0, 0))
            if guirnalda:
                guirnalda.dibujar(capa, t)
            cad, entra, sale = cfg["titulo"]
            tit = Image.new("RGBA", (W, H), (0, 0, 0, 0))
            texto(tit, cad, H * 0.2, 84, alfa(t, entra, sale), cfg)
            capa.alpha_composite(tit)
            if cfg["cierre"]:
                cad, entra = cfg["cierre"]
                cie = Image.new("RGBA", (W, H), (0, 0, 0, 0))
                texto(cie, cad, H * 0.2, 96, alfa(t, entra, total + 1), cfg)
                capa.alpha_composite(cie)
            capa.save(os.path.join(cuadros, "%04d.png" % k))

        # 3) La voz nivelada a -16 LUFS, entrando a los 0,7 s.
        voz = os.path.join(ORIGEN, cfg["voz"])
        ganancia = LUFS - lufs(voz)
        salida = cfg["salida"]
        os.makedirs(os.path.dirname(salida), exist_ok=True)
        run("ffmpeg", "-y", "-i", base, "-framerate", str(FPS), "-i", os.path.join(cuadros, "%04d.png"), "-i", voz,
            "-filter_complex",
            "[0:v][1:v]overlay=0:0:format=auto,format=yuv420p[v];"
            "[2:a]volume=%.2fdB,alimiter=limit=0.89,adelay=%d|%d,apad,atrim=0:%.3f,aformat=channel_layouts=stereo[a]"
            % (ganancia, VOZ_INICIO * 1000, VOZ_INICIO * 1000, total),
            "-map", "[v]", "-map", "[a]", "-c:v", "libx264", "-crf", "23", "-preset", "slow", "-profile:v", "high",
            "-r", str(FPS), "-c:a", "aac", "-b:a", "128k", "-ar", "44100", "-movflags", "+faststart", "-shortest", salida)
        print("%s: %.2f s, %d KB, voz %+.1f dB" % (os.path.relpath(salida, CB), duracion(salida), os.path.getsize(salida) // 1024, ganancia))
    finally:
        shutil.rmtree(tmp, ignore_errors=True)


if __name__ == "__main__":
    for tema in sys.argv[1:] or list(TEMAS):
        componer(tema)
