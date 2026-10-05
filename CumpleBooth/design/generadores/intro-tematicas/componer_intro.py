# -*- coding: utf-8 -*-
"""Videos de bienvenida de las temáticas nuevas (2026-09-26): Fiestas Patrias y Noche de Brujas. Desde el 29-09 también
las infantiles brujitas y navidad, con sus saludos y su despedida (una toma de 5 s más la voz del personaje).

Igual que las bienvenidas infantiles (720 × 1280, 24 fps, con voz, alrededor de −16 LUFS, sin logo): dos tomas de 5 s
de Kling 3.0 Pro unidas con un fundido, la voz de Alice (ElevenLabs) y encima, dibujado por código, un título breve
y, en Fiestas Patrias, la guirnalda de banderas exactas meciéndose (la IA deforma la bandera: nunca se le pide).

Uso: python componer_intro.py fiestas-patrias | noche-brujas | brujitas | navidad      (la bienvenida)
     python componer_intro.py brujitas --clips                                     (saludos y despedida)
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
NUEVAS = os.environ.get("INTRO_NUEVAS", r"C:/Users/luis_/Documents/CumpleClick/tematicas-2026-10")
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
    # Infantiles del 29-09: tomas y voces fuera del repo, en tematicas-2026-10/<slug>/ (videos/ y voces/).
    "brujitas": {"tomas": ("intro-1", "intro-2"), "voz": "../voces/intro.mp3", "voz_inicio": 0.3,
                 "origen": NUEVAS + "/brujitas/videos", "carpeta": os.path.join(CB, "public", "themes", "brujitas"),
                 "salida": os.path.join(CB, "public", "themes", "brujitas", "welcome-brujitas.mp4"),
                 "titulo": ("Noche de Brujas", 0.4, 3.8), "cierre": None,
                 "relleno": (255, 201, 74), "borde": (46, 26, 71), "sombra": (20, 8, 24), "guirnalda": False},
    "navidad": {"tomas": ("intro-1", "intro-2"), "voz": "../voces/intro.mp3", "voz_inicio": 0.3,
                "origen": NUEVAS + "/navidad/videos", "carpeta": os.path.join(CB, "public", "themes", "navidad"),
                "salida": os.path.join(CB, "public", "themes", "navidad", "welcome-navidad.mp4"),
                "titulo": ("Navidad", 0.4, 3.8), "cierre": None,
                "relleno": (255, 255, 255), "borde": (198, 40, 40), "sombra": (31, 61, 43), "guirnalda": False},
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
    origen = cfg.get("origen", ORIGEN)
    voz_inicio = cfg.get("voz_inicio", VOZ_INICIO)
    tmp = tempfile.mkdtemp(prefix="intro-")
    try:
        # 1) Las dos tomas a 720x1280 y 24 fps, unidas con un fundido.
        normal = []
        for i, toma in enumerate(cfg["tomas"]):
            dst = os.path.join(tmp, "t%d.mp4" % i)
            run("ffmpeg", "-y", "-i", os.path.join(origen, toma + ".mp4"), "-an", "-vf",
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
        voz = os.path.join(origen, cfg["voz"])
        ganancia = LUFS - lufs(voz)
        salida = cfg["salida"]
        if voz_inicio + duracion(voz) > total:
            raise SystemExit("%s: la voz (%.2f s desde %.1f s) no cabe en %.2f s de video" % (tema, duracion(voz), voz_inicio, total))
        os.makedirs(os.path.dirname(salida), exist_ok=True)
        run("ffmpeg", "-y", "-i", base, "-framerate", str(FPS), "-i", os.path.join(cuadros, "%04d.png"), "-i", voz,
            "-filter_complex",
            "[0:v][1:v]overlay=0:0:format=auto,format=yuv420p[v];"
            "[2:a]volume=%.2fdB,alimiter=limit=0.89,adelay=%d|%d,apad,atrim=0:%.3f,aformat=channel_layouts=stereo[a]"
            % (ganancia, voz_inicio * 1000, voz_inicio * 1000, total),
            "-map", "[v]", "-map", "[a]", "-c:v", "libx264", "-crf", "23", "-preset", "slow", "-profile:v", "high",
            "-r", str(FPS), "-c:a", "aac", "-b:a", "128k", "-ar", "44100", "-movflags", "+faststart", "-shortest", salida)
        print("%s: %.2f s, %d KB, voz %+.1f dB" % (os.path.relpath(salida, CB), duracion(salida), os.path.getsize(salida) // 1024, ganancia))
    finally:
        shutil.rmtree(tmp, ignore_errors=True)


def componer_clips(tema):
    """Saludos y despedida: una toma de 5 s a 720x1280 y 24 fps con la voz nivelada, sin título encima (el nombre
    del niño lo escribe el kiosco). El nombre del archivo es convención: saludo-<personaje>.mp4 y despedida-<slug>.mp4."""
    cfg = TEMAS[tema]
    origen = cfg["origen"]
    voces = os.path.join(origen, "..", "voces")
    for mp3 in sorted(os.listdir(voces)):
        clip = os.path.splitext(mp3)[0]
        if clip == "intro" or not mp3.endswith(".mp3"):
            continue
        toma = os.path.join(origen, clip + ".mp4")
        if not os.path.isfile(toma):
            print("%s: falta la toma, se omite" % clip)
            continue
        voz = os.path.join(voces, mp3)
        total = duracion(toma)
        inicio = 0.5
        if inicio + duracion(voz) > total:
            inicio = max(0.1, total - duracion(voz) - 0.1)
        if inicio + duracion(voz) > total:
            raise SystemExit("%s: la voz no cabe en la toma" % clip)
        ganancia = LUFS - lufs(voz)
        salida = os.path.join(cfg["carpeta"], ("despedida-%s.mp4" % tema) if clip == "despedida" else clip + ".mp4")
        run("ffmpeg", "-y", "-i", toma, "-i", voz, "-filter_complex",
            "[0:v]scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d,fps=%d,format=yuv420p[v];"
            "[1:a]volume=%.2fdB,alimiter=limit=0.89,adelay=%d|%d,apad,atrim=0:%.3f,aformat=channel_layouts=stereo[a]"
            % (W, H, W, H, FPS, ganancia, inicio * 1000, inicio * 1000, total),
            "-map", "[v]", "-map", "[a]", "-c:v", "libx264", "-crf", "23", "-preset", "slow", "-profile:v", "high",
            "-r", str(FPS), "-c:a", "aac", "-b:a", "128k", "-ar", "44100", "-movflags", "+faststart", "-shortest", salida)
        print("%s: %.2f s, %d KB, voz %+.1f dB desde %.1f s" % (os.path.relpath(salida, CB), duracion(salida),
                                                               os.path.getsize(salida) // 1024, ganancia, inicio))


if __name__ == "__main__":
    temas = [a for a in sys.argv[1:] if not a.startswith("--")]
    for tema in temas or list(TEMAS):
        if "--clips" in sys.argv:
            componer_clips(tema)
        else:
            componer(tema)
