"""Pruebas de adaptar_prompt() y prompt_instagram() sin red: python -I -X utf8 N8N/instagram/test_adaptar_prompt.py"""
import os
import sys

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.dont_write_bytecode = True
sys.path.insert(0, AQUI)
os.environ.setdefault('AT_N8N_SIN_RED', '1')
import aplicar  # noqa: E402
import build_agente_instagram as build  # noqa: E402

fallas = 0


def ok(cond, msg):
    global fallas
    print(('ok    ' if cond else 'FALLA ') + msg)
    fallas += not cond


BASE = """### 🧠 CEREBRO AT (inicio)
Somos AutomatizaTech.
{planes}### 💲 PRECIOS: SIEMPRE AL FINAL
El precio va al final.
### 🧠 CEREBRO AT (fin)
### 🛠️ TUS CAPACIDADES
{cap2}4.  Guiar al usuario para agendar citas mediante el formulario.
### 📅 AGENDAMIENTO DE REUNIONES
Formulario <<SHOW_DEMO_FORM>>
### 💰 REGLAS CRÍTICAS DE PRECIOS (IMPORTANTE)
El dólar hoy es ${{{{ $json.rate }}}} CLP. Ejemplo: $99 USD.
### 💬 REGLAS DE COMUNICACIÓN
Si piden hablar con un humano o datos de contacto, entrega SOLO estos:
"""
PLANES = "### 📦 PLANES DEL PORTAL OMNICLIENTE (precios en USD por mes)\n*   **Básico ($99 USD/mes)**\n"
CAP2 = "2.  Calcular precios en tiempo real (USD a CLP).\n"

# Prompt web de hoy (con planes y capacidad 2) y el de Eje 925223e/b1d1e23 (sin planes ni capacidad 2 en el cerebro).
for nombre, planes, cap2 in (('hoy', PLANES, CAP2), ('cerebro nuevo sin planes', '', ''), ('sin planes con capacidad 2', '', CAP2)):
    try:
        p = aplicar.adaptar_prompt(BASE.format(planes=planes, cap2=cap2))
        ok(not aplicar.MONTO.search(p) and 'REGLAS CRÍTICAS DE PRECIOS' not in p and 'Calcular precios' not in p
           and 'formulario' not in p and 'SHOW_DEMO_FORM' not in p, 'adaptar_prompt (%s): sin precios ni formulario' % nombre)
        ok('PRECIOS: SIEMPRE AL FINAL' in p and 'derivar_a_persona' in p, 'adaptar_prompt (%s): conserva el resto' % nombre)
    except ValueError as e:
        ok(False, 'adaptar_prompt (%s): no debía detenerse: %s' % (nombre, e))

for nombre, texto in (('sin marca de agendamiento', BASE.format(planes='', cap2='').replace('### 📅 AGENDAMIENTO DE REUNIONES\n', '')),
                      ('monto fuera de los tramos', BASE.format(planes='', cap2='').replace('Somos AutomatizaTech.', 'Desde $49 USD.'))):
    try:
        aplicar.adaptar_prompt(texto)
        ok(False, 'adaptar_prompt (%s): debía detenerse' % nombre)
    except ValueError:
        ok(True, 'adaptar_prompt (%s): se detiene sin subir' % nombre)

# La regla de precios vive en personalidad-instagram.md (Eje); el bloque del canal solo la agrega si falta.
REGLA = 'Precios: por Instagram no se dan'
dir_original = build.CEREBRO
try:
    for nombre, con_regla in (('personalidad con regla', True), ('personalidad sin regla', False)):
        tmp = os.path.join(os.environ.get('TEMP', '/tmp'), 'cerebro-prueba-' + str(os.getpid()) + nombre[-5:])
        os.makedirs(tmp, exist_ok=True)
        texto = '### 🎭 TECH INSTAGRAM\n*   **Tono:** cercano.\n' + (
            '*   **Precios:** aquí nunca das precios, tampoco de los planes.\n' if con_regla else '')
        with open(os.path.join(tmp, 'personalidad-instagram.md'), 'w', encoding='utf-8') as f:
            f.write(texto)
        build.CEREBRO = tmp
        blk = build.prompt_instagram()
        ok((REGLA in blk) is (not con_regla), 'prompt_instagram (%s): la regla del canal %s' % (
            nombre, 'no se duplica' if con_regla else 'se agrega de respaldo'))
        os.remove(os.path.join(tmp, 'personalidad-instagram.md'))
        os.rmdir(tmp)
finally:
    build.CEREBRO = dir_original

print('\n%d FALLAS' % fallas if fallas else '\nTODO OK')
sys.exit(1 if fallas else 0)
