"""Pruebas de aplicar.py sin tocar n8n, sobre una copia de los flujos vivos guardada FUERA del repositorio (es público):
AT_WA_FIXTURE=<carpeta con wa-actual.json, web-actual.json y agenda-actual.json>. Sin esa carpeta, las pruebas de flujo quedan NOT_RUN.
Correr: python -I -B -X utf8 N8N/wa-citas/aplicar_test.py
"""
import copy
import json
import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import aplicar  # noqa: E402

CARPETA = os.environ.get('AT_WA_FIXTURE', '')


def cargar(nombre):
    ruta = os.path.join(CARPETA, nombre)
    if not CARPETA or not os.path.exists(ruta):
        raise unittest.SkipTest(f'NOT_RUN: falta {nombre} en AT_WA_FIXTURE')
    with open(ruta, encoding='utf-8') as f:
        return json.load(f)


def aplicado(flujo, archivo):
    w = cargar(archivo)
    antes = copy.deepcopy(w)
    registro = []
    aplicar.cambiar(flujo, w, aplicar.reconoce.bloque(), aplicar.bloque(), registro)
    return antes, w, registro


class WhatsApp(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.antes, cls.w, cls.registro = aplicado('whatsapp', 'wa-actual.json')
        cls.nodos = {n['name']: n for n in cls.w['nodes']}

    def test_rutas_de_cancelar_y_reprogramar_ya_no_van_a_ask_data(self):
        reglas = self.nodos['Route Action']['parameters']['rules']['values']
        for accion in aplicar.ACCIONES:
            i = next(k for k, r in enumerate(reglas) if r['conditions']['conditions'][0].get('rightValue') == accion)
            self.assertEqual([d['node'] for d in self.w['connections']['Route Action']['main'][i]], ['Citas del Número'], accion)

    def test_comprobaciones_sin_fallas(self):
        # Conexiones a nodos que existen, todo $('…') a un nodo que existe, bloques idénticos y node --check, consulta caída,
        # botón viejo, regla cita_ antes del fallback, tipo de cambio protegido, y lo de wa-reconoce sigue en pie.
        tocados, fallas = aplicar.comprobar('whatsapp', self.w, self.antes, aplicar.reconoce.bloque(), aplicar.bloque())
        self.assertEqual(fallas, [])
        self.assertIn('Citas del Número', tocados)

    def test_consulta_caida_mensaje_citas_da_no_disponible_y_avisa(self):
        m = aplicar.reconoce.simular_nodo(self.nodos['Mensaje Citas']['parameters']['jsCode'],
                                          {'Route Action': {'action': 'reschedule_followup', 'phoneNumber': '56900001111', 'phoneNumberId': '1'}}, {'estado': 'no_disponible', 'citas': []})
        self.assertIs(m['avisarLuis'], True)
        self.assertEqual(m['accion'], 'reprogramar')
        self.assertTrue(m['motivoLuis'])

    def test_cuatro_nodos_de_tipo_de_cambio_protegidos(self):
        self.assertEqual(aplicar.comprobar_cambio(self.w, aplicar.CAMBIO_WA), [])
        texto = json.dumps(self.nodos['Agente IA - Tech WhatsApp']['parameters'], ensure_ascii=False)
        self.assertEqual(texto.count('nunca escribas \\"$0 CLP\\"'), 1)

    def test_button_action_regla_cita_antes_del_fallback(self):
        valores = self.nodos['Button Action']['parameters']['rules']['values']
        i = next(k for k, r in enumerate(valores) if r['conditions']['conditions'][0].get('rightValue') == 'cita_')
        main = self.w['connections']['Button Action']['main']
        self.assertEqual([d['node'] for d in main[i]], ['Citas Vigentes'])
        antes_main = self.antes['connections']['Button Action']['main']
        n_antes = len({n['name']: n for n in self.antes['nodes']}['Button Action']['parameters']['rules']['values'])
        self.assertEqual(main[len(valores):], antes_main[n_antes:], 'la salida del fallback (si tiene) sigue después de las reglas')
        self.assertEqual([d['node'] for d in main[len(valores) - 1]], ['Contexto Elegido'], 'seg_ sigue siendo la última regla')

    def test_idempotente(self):
        otra, registro = copy.deepcopy(self.w), []
        aplicar.cambiar('whatsapp', otra, aplicar.reconoce.bloque(), aplicar.bloque(), registro)
        self.assertEqual(json.dumps(otra, sort_keys=True), json.dumps(self.w, sort_keys=True))
        self.assertFalse([n for n, q in registro if q == 'agregado'])

    def test_una_ruta_mal_conectada_se_detecta(self):
        # La comprobación debe poder fallar: se desconecta la ruta de cancelar.
        w = copy.deepcopy(self.w)
        reglas = {n['name']: n for n in w['nodes']}['Route Action']['parameters']['rules']['values']
        i = next(k for k, r in enumerate(reglas) if r['conditions']['conditions'][0].get('rightValue') == 'cancel_appointment')
        w['connections']['Route Action']['main'][i] = [{'node': 'Ask Cancel Data', 'type': 'main', 'index': 0}]
        self.assertTrue(any('cancel_appointment' in f for f in aplicar.comprobar_citas(w, aplicar.bloque())))


class Web(unittest.TestCase):
    def test_tipo_de_cambio_y_linea_del_prompt(self):
        antes, w, _ = aplicado('web', 'web-actual.json')
        tocados, fallas = aplicar.comprobar('web', w, antes, aplicar.reconoce.bloque(), aplicar.bloque())
        self.assertEqual(fallas, [])
        self.assertEqual(sorted(tocados), ['Agente IA - Tech', 'Get Exchange Rate'])


class Reintentos(unittest.TestCase):
    def test_reintentos_publicados(self):
        pub = {'activeVersion': {'nodes': [{'name': 'Get Exchange Rate', 'retryOnFail': True, 'maxTries': 3}]}}
        self.assertEqual(aplicar.verificar_reintentos(pub, ('Get Exchange Rate',)), [])
        pub['activeVersion']['nodes'][0]['maxTries'] = 1
        self.assertTrue(aplicar.verificar_reintentos(pub, ('Get Exchange Rate',)))


if __name__ == '__main__':
    unittest.main(verbosity=1)
