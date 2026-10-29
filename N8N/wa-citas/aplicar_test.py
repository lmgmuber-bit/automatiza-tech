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


class Reagendar(unittest.TestCase):
    """Tarea 8: reprogramar sin perder la cita vieja (se cancela recién cuando la nueva queda agendada)."""
    @classmethod
    def setUpClass(cls):
        cls.antes, cls.w, cls.registro = aplicado('whatsapp', 'wa-actual.json')
        cls.nodos = {n['name']: n for n in cls.w['nodes']}
        cls.con = cls.w['connections']

    def salida(self, nodo, i):
        return [d['node'] for d in self.con[nodo]['main'][i]]

    def datos_vieja(self, marca, citas):
        et = {'selectedDay': '2026-11-10', 'selectedTime': '10:00', 'phoneNumber': '56900001111', 'phoneNumberId': '1', 'appointmentType': 'Demo'}
        return aplicar.reconoce.simular_nodo(self.nodos['Datos Cita Vieja']['parameters']['jsCode'],
                                             {'Extract Time': et, 'Leer Marca Reagendar': {'marca': json.dumps(marca) if marca else None}},
                                             {'estado': 'ok', 'citas': citas})

    def test_reprogramar_diagnostico_con_marca_no_se_pide_nombre(self):
        cita = {'tipo': 'diagnostico', 'id': 12, 'nombre': 'Ana Prueba', 'correo': 'ana@prueba.cl', 'empresa': 'E', 'etiqueta': 'x'}
        d = self.datos_vieja({'tipo': 'diagnostico', 'id': 12}, [cita])
        self.assertIs(d['reagendar'], True)
        self.assertEqual((d['name'], d['email'], d['date'], d['time'], d['appointmentType'], d['excluir_tipo'], d['excluir_id']),
                         ('Ana Prueba', 'ana@prueba.cl', '2026-11-10', '10:00', 'Demo', 'diagnostico', 12))
        self.assertEqual(self.salida('¿Reagendando diagnóstico?', 0), ['Crear Diagnóstico Reagendado'])
        self.assertEqual(self.salida('Is Seguimiento?', 1), ['Leer Marca Reagendar'])

    def test_sin_marca_el_diagnostico_sigue_a_ask_for_name_como_hoy(self):
        d = self.datos_vieja(None, [])
        self.assertIs(d['reagendar'], False)
        self.assertEqual(d['phoneNumberId'], '1', 'Ask For Name necesita el phoneNumberId')
        self.assertEqual(self.salida('¿Reagendando diagnóstico?', 1), ['Ask For Name'])
        otra = self.datos_vieja({'tipo': 'diagnostico', 'id': 99}, [{'tipo': 'diagnostico', 'id': 12, 'correo': 'a@prueba.cl'}])
        self.assertIs(otra['reagendar'], False, 'una marca de una cita que ya no está no reagenda')

    def test_ocupado_no_se_cancela_la_vieja(self):
        # La agenda (PejBgr2LZQSAbbBy) devuelve event_id solo si creó el evento; ocupado o rechazado no lo trae.
        cond = self.nodos['¿Agendada nueva?']['parameters']['conditions']['conditions'][0]
        self.assertEqual((cond['leftValue'], cond['operator']['operation']), ('={{ $json.event_id }}', 'notEmpty'))
        self.assertEqual(aplicar.reconoce.evaluar('={{ $json.event_id ?? null }}', {'result': 'Lo siento, ese horario ya está ocupado.'}, {}), None)
        self.assertEqual(self.salida('¿Agendada nueva?', 1), ['Send Reagendar Sin Agendar'])

    def test_agendada_se_cancela_la_vieja_con_motivo_reprogramada_y_se_borra_la_marca(self):
        self.assertEqual(self.salida('¿Agendada nueva?', 0), ['Cancelar Cita Vieja'])
        self.assertIn('Borrar Marca Reagendar', self.salida('Cancelar Cita Vieja', 0))
        cuerpo = aplicar.reconoce.evaluar(self.nodos['Cancelar Cita Vieja']['parameters']['jsonBody'], {},
                                          {'Datos Cita Vieja': {'excluir_tipo': 'diagnostico', 'excluir_id': 12, 'phone': '56900001111'}})
        self.assertEqual(json.loads(cuerpo), {'phone': '56900001111', 'tipo': 'diagnostico', 'id': 12, 'motivo': 'reprogramada'})

    def test_reprogramar_seguimiento_conserva_contrato_id_y_propuesta_id(self):
        cita = {'tipo': 'seguimiento', 'id': 9, 'nombre': 'Ana', 'correo': 'ana@prueba.cl', 'empresa': 'E', 'contrato_id': 5, 'propuesta_id': 0,
                'etiqueta': 'Seguimiento: Proyecto: Agenda · mié 04-nov · 10:00'}
        v = aplicar.reconoce.evaluar(self.nodos['Guardar Cliente Reagendar']['parameters']['value'], {}, {'Decidir Botón Cita': {'cita': cita}})
        g = json.loads(v)
        self.assertEqual((g['email'], g['contrato_id'], g['propuesta_id'], g['subject']), ('ana@prueba.cl', 5, 0, 'Seguimiento: Proyecto: Agenda'))
        pr = aplicar.reconoce.evaluar(self.nodos['Prepare Seguimiento Data Direct']['parameters']['jsonOutput'],
                                      {'reschedClient': v, 'marca': json.dumps({'tipo': 'seguimiento', 'id': 9})},
                                      {'Extract Time': {'selectedDay': '2026-11-10', 'selectedTime': '10:00', 'phoneNumber': '56900001111'}})
        self.assertEqual((pr['contrato_id'], pr['excluir_tipo'], pr['excluir_id']), (5, 'seguimiento', 9))
        self.assertEqual(self.salida('Get Reschedule Client (botón)', 0), ['Leer Marca (seguimiento)'])
        self.assertEqual(self.salida('¿Seguimiento botón agendado?', 0), ['¿Reagendando seguimiento?'])
        self.assertEqual(self.salida('¿Reagendando seguimiento?', 0), ['Cancelar Cita Vieja (seguimiento)'])
        self.assertEqual(self.salida('¿Reagendando seguimiento?', 1), [], 'sin marca no pasa nada más: la confirmación ya sale en paralelo')
        self.assertIn('Delete Seguimiento State', self.salida('Create Seguimiento Direct', 0))

    def test_boton_de_recordatorio_de_reprogramar_ya_no_cancela_antes(self):
        self.assertEqual(self.salida('Extract Reprogramar Lead ID', 0), ['Marcar Reagendar (recordatorio)'])
        self.assertEqual(self.salida('Marcar Reagendar (recordatorio)', 0), ['Prepare Reschedule Data'])
        self.assertEqual(self.salida('Extract Followup Reschedule ID', 0), ['Marcar Reagendar (recordatorio seg)'])
        self.assertNotIn('Cancel Followup For Reschedule', json.dumps(self.con['Marcar Reagendar (recordatorio seg)']))

    def test_reprogramar_desde_el_boton_abre_el_calendario_del_tipo(self):
        self.assertEqual(self.salida('Ruta Botón Cita', 2), ['Marcar Reagendar'])
        tipo = aplicar.reconoce.evaluar(self.nodos['Prepare Reagendar Cita']['parameters']['jsonOutput'], {},
                                        {'Decidir Botón Cita': {'phoneNumber': '569', 'phoneNumberId': '1', 'cita': {'tipo': 'seguimiento'}}})
        self.assertEqual(tipo['appointmentType'], 'Seguimiento')
        self.assertEqual(self.nodos['Generate Days']['parameters']['jsCode'].count("$('Prepare Reagendar Cita')"), 1)


class AgendaExcluir(unittest.TestCase):
    def test_pejbgr_el_objeto_del_diagnostico_sin_excluir_es_identico_al_de_hoy(self):
        antes, w, _ = aplicado('agenda', 'agenda-actual.json')
        a = {n['name']: n for n in antes['nodes']}
        d = {n['name']: n for n in w['nodes']}
        self.assertEqual(a['Prepare Payload']['parameters'], d['Prepare Payload']['parameters'])
        self.assertEqual(a['Save Appointment to WP']['parameters']['jsonBody'], d['Save Appointment to WP']['parameters']['jsonBody'])
        q = {p['name']: p['value'] for p in d['Save Appointment to WP']['parameters']['queryParameters']['parameters']}
        self.assertEqual(aplicar.reconoce.evaluar(q['excluir_tipo'], {}, {'Execute Workflow Trigger': {}}), '')
        self.assertEqual(aplicar.reconoce.evaluar(q['excluir_tipo'], {}, {'Execute Workflow Trigger': {'excluir_tipo': 'diagnostico'}}), 'diagnostico')
        self.assertEqual(d['Save Appointment to WP']['parameters']['genericAuthType'], 'httpHeaderAuth')
        tocados, fallas = aplicar.comprobar('agenda', w, antes, aplicar.reconoce.bloque(), aplicar.bloque())
        self.assertEqual((tocados, fallas), (['Save Appointment to WP'], []))


if __name__ == '__main__':
    unittest.main(verbosity=1)
