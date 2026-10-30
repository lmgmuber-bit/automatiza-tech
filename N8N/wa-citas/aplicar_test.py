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
        pub = {'activeVersion': {'nodes': [{'name': 'Get Exchange Rate', 'retryOnFail': True, 'maxTries': 3, 'waitBetweenTries': 1000}]}}
        self.assertEqual(aplicar.verificar_reintentos(pub, ('Get Exchange Rate',)), [])
        pub['activeVersion']['nodes'][0]['maxTries'] = 1
        self.assertTrue(aplicar.verificar_reintentos(pub, ('Get Exchange Rate',)))


class Reagendar(unittest.TestCase):
    """Tarea 8 + revisión B (B-CITAS-01, 02, 03, 06, 07): reprogramar sin perder la cita vieja, solo con una cita propia y vigente."""
    ET = {'selectedDay': '2026-11-10', 'selectedTime': '10:00', 'phoneNumber': '56900001111', 'phoneNumberId': '1', 'appointmentType': 'Demo'}
    DIAG = {'tipo': 'diagnostico', 'id': 12, 'nombre': 'Ana Prueba', 'correo': 'ana@prueba.cl', 'empresa': 'E', 'etiqueta': 'Diagnóstico gratis · mar 10-nov · 09:00'}
    SEG = {'tipo': 'seguimiento', 'id': 79, 'nombre': 'Ana Prueba', 'correo': 'ana@prueba.cl', 'empresa': 'E', 'contrato_id': 7, 'propuesta_id': 0,
           'notas': 'Plan de trabajo Ab3dE6gH9jK1. Agendada por el cliente por WhatsApp.', 'asunto': 'Seguimiento: Agenda',
           'etiqueta': 'Seguimiento: Proyecto: Agenda · mié 11-nov · 10:00'}

    @classmethod
    def setUpClass(cls):
        cls.antes, cls.w, cls.registro = aplicado('whatsapp', 'wa-actual.json')
        cls.nodos = {n['name']: n for n in cls.w['nodes']}
        cls.con = cls.w['connections']

    def salida(self, nodo, i):
        return [d['node'] for d in self.con[nodo]['main'][i]]

    def modo_sale(self, nodo_switch, modo):
        reglas = self.nodos[nodo_switch]['parameters']['rules']['values']
        i = next(k for k, r in enumerate(reglas) if r['conditions']['conditions'][0].get('rightValue') == modo)
        return self.salida(nodo_switch, i)

    def datos_vieja(self, marca, respuesta):
        return aplicar.reconoce.simular_nodo(self.nodos['Datos Cita Vieja']['parameters']['jsCode'],
                                             {'Extract Time': self.ET, 'Leer Marca Reagendar': {'marca': json.dumps(marca) if marca else None}}, respuesta)

    def datos_seg(self, marca, respuesta, cache=None):
        et = dict(self.ET, appointmentType='Seguimiento')
        return aplicar.reconoce.simular_nodo(self.nodos['Datos Seguimiento Viejo']['parameters']['jsCode'],
                                             {'Extract Time': et, 'Leer Marca (seguimiento)': {'marca': json.dumps(marca) if marca else None},
                                              'Get Reschedule Client (botón)': {'reschedClient': json.dumps(cache or {})}}, respuesta)

    def test_reprogramar_diagnostico_con_marca_no_se_pide_nombre(self):
        d = self.datos_vieja({'tipo': 'diagnostico', 'id': 12}, {'estado': 'ok', 'citas': [self.DIAG]})
        self.assertEqual(d['modo'], 'reagendar')
        self.assertEqual((d['name'], d['email'], d['date'], d['time'], d['appointmentType'], d['excluir_tipo'], d['excluir_id']),
                         ('Ana Prueba', 'ana@prueba.cl', '2026-11-10', '10:00', 'Demo', 'diagnostico', 12))
        self.assertEqual(self.modo_sale('Modo Reagendar', 'reagendar'), ['Crear Diagnóstico Reagendado'])
        self.assertEqual(self.salida('Is Seguimiento?', 1), ['Leer Marca Reagendar'])

    def test_sin_marca_el_diagnostico_sigue_a_ask_for_name_como_hoy(self):
        d = self.datos_vieja(None, {'estado': 'ok', 'citas': []})
        self.assertEqual(d['modo'], 'nuevo')
        self.assertEqual(d['phoneNumberId'], '1', 'Ask For Name necesita el phoneNumberId')
        self.assertEqual(self.modo_sale('Modo Reagendar', 'nuevo'), ['Ask For Name'])
        self.assertEqual(self.datos_vieja({'tipo': 'seguimiento', 'id': 79}, {'estado': 'ok', 'citas': [self.SEG]})['modo'], 'nuevo',
                         'una marca de otro tipo no convierte un diagnóstico nuevo en reprogramación')

    def test_b03_marca_con_consulta_caida_o_cita_que_ya_no_esta_no_pide_nombre(self):
        caida = self.datos_vieja({'tipo': 'diagnostico', 'id': 12}, {'estado': 'no_disponible', 'citas': []})
        self.assertEqual((caida['modo'], caida['avisarLuis']), ('aviso', True))
        self.assertIn('no pude revisar', json.dumps(caida['mensaje'], ensure_ascii=False))
        timeout = self.datos_vieja({'tipo': 'diagnostico', 'id': 12}, {'error': {'message': 'timeout'}})
        self.assertEqual(timeout['modo'], 'aviso')
        ya_no = self.datos_vieja({'tipo': 'diagnostico', 'id': 12}, {'estado': 'ok', 'citas': []})
        self.assertEqual(ya_no['modo'], 'aviso')
        self.assertIn('ya no está disponible', json.dumps(ya_no['mensaje'], ensure_ascii=False))
        self.assertEqual(self.modo_sale('Modo Reagendar', 'aviso'), ['Send Citas', '¿Avisar a Luis (citas)?'])

    def test_ocupado_no_se_cancela_la_vieja(self):
        # La agenda (PejBgr2LZQSAbbBy) devuelve event_id solo si creó el evento; ocupado o rechazado no lo trae.
        cond = self.nodos['¿Agendada nueva?']['parameters']['conditions']['conditions'][0]
        self.assertEqual((cond['leftValue'], cond['operator']['operation']), ('={{ $json.event_id }}', 'notEmpty'))
        self.assertEqual(self.salida('¿Agendada nueva?', 1), ['Send Reagendar Sin Agendar'])

    def test_agendada_se_cancela_la_vieja_con_motivo_reprogramada_y_se_borra_la_marca(self):
        self.assertEqual(self.salida('¿Agendada nueva?', 0), ['Cancelar Cita Vieja'])
        self.assertIn('Borrar Marca Reagendar', self.salida('Cancelar Cita Vieja', 0))
        cuerpo = aplicar.reconoce.evaluar(self.nodos['Cancelar Cita Vieja']['parameters']['jsonBody'], {},
                                          {'Datos Cita Vieja': {'excluir_tipo': 'diagnostico', 'excluir_id': 12, 'phone': '56900001111'}})
        self.assertEqual(json.loads(cuerpo), {'phone': '56900001111', 'tipo': 'diagnostico', 'id': 12, 'motivo': 'reprogramada'})

    def test_b01_b06_seguimiento_usa_la_cita_fresca_del_numero_y_conserva_la_nota(self):
        ajena = {'name': 'Otra Persona Prueba', 'email': 'other@example.com', 'contrato_id': 71}
        d = self.datos_seg({'tipo': 'seguimiento', 'id': 79}, {'estado': 'ok', 'citas': [self.SEG]}, cache=ajena)
        self.assertEqual(d['modo'], 'reagendar')
        self.assertEqual((d['email'], d['name'], d['contrato_id'], d['notes'], d['subject'], d['excluir_tipo'], d['excluir_id'], d['appointmentType']),
                         ('ana@prueba.cl', 'Ana Prueba', 7, self.SEG['notas'], 'Seguimiento: Agenda', 'seguimiento', 79, 'Seguimiento'),
                         'los datos salen de la cita propia validada, nunca de la caché')
        self.assertEqual(self.modo_sale('Modo Reagendar (seguimiento)', 'reagendar'), ['Create Seguimiento Direct'])
        self.assertEqual(self.salida('Get Reschedule Client (botón)', 0), ['Leer Marca (seguimiento)'])

    def test_b01_seguimiento_de_otro_numero_o_cancelado_no_se_agenda(self):
        otra = self.datos_seg({'tipo': 'seguimiento', 'id': 79}, {'estado': 'ok', 'citas': []}, cache={'email': 'other@example.com'})
        self.assertEqual(otra['modo'], 'aviso')
        self.assertNotIn('other@example.com', json.dumps(otra, ensure_ascii=False))
        caida = self.datos_seg({'tipo': 'seguimiento', 'id': 79}, {'estado': 'no_disponible', 'citas': []})
        self.assertEqual((caida['modo'], caida['avisarLuis']), ('aviso', True))
        self.assertEqual(self.modo_sale('Modo Reagendar (seguimiento)', 'aviso'), ['Send Citas', '¿Avisar a Luis (citas)?'])
        sin = self.datos_seg(None, {'estado': 'ok', 'citas': []}, cache={'email': 'propio@prueba.cl'})
        self.assertEqual(sin['modo'], 'nuevo')
        self.assertIn('reschedClient', sin, 'sin marca, el seguimiento sigue como hoy con su caché')
        self.assertEqual(self.modo_sale('Modo Reagendar (seguimiento)', 'nuevo'), ['Prepare Seguimiento Data Direct'])
        self.assertEqual(self.nodos['Prepare Seguimiento Data Direct']['parameters'], {n['name']: n for n in self.antes['nodes']}['Prepare Seguimiento Data Direct']['parameters'])

    def test_seguimiento_reagendado_cancela_la_vieja_solo_tras_exito(self):
        self.assertEqual(self.salida('¿Seguimiento botón agendado?', 0), ['¿Reagendando seguimiento?'])
        cond = self.nodos['¿Reagendando seguimiento?']['parameters']['conditions']['boolean'][0]['value1']
        self.assertIs(aplicar.reconoce.evaluar(cond, {}, {'Datos Seguimiento Viejo': {'modo': 'reagendar'}}), True)
        self.assertIs(aplicar.reconoce.evaluar(cond, {}, {'Datos Seguimiento Viejo': {'modo': 'nuevo'}}), False)
        self.assertEqual(self.salida('¿Reagendando seguimiento?', 0), ['Cancelar Cita Vieja (seguimiento)'])
        cuerpo = aplicar.reconoce.evaluar(self.nodos['Cancelar Cita Vieja (seguimiento)']['parameters']['jsonBody'], {},
                                          {'Datos Seguimiento Viejo': {'phone': '56900001111', 'excluir_id': 79}})
        self.assertEqual(json.loads(cuerpo), {'phone': '56900001111', 'tipo': 'seguimiento', 'id': 79, 'motivo': 'reprogramada'})

    def test_b01_recordatorio_de_una_cita_ajena_no_abre_el_calendario(self):
        for extract, boton in (('Extract Reprogramar Lead ID', {'leadId': '12'}), ('Extract Followup Reschedule ID', {'meetingId': '79'})):
            self.assertEqual(self.salida(extract, 0), ['Citas Recordatorio'])
            stubs = {extract: dict(boton, phoneNumber='56900001111', phoneNumberId='1')}
            v = aplicar.reconoce.simular_nodo(self.nodos['Validar Recordatorio']['parameters']['jsCode'], stubs, {'estado': 'ok', 'citas': []})
            self.assertEqual(v['ruta'], 'aviso', extract)
        ok = aplicar.reconoce.simular_nodo(self.nodos['Validar Recordatorio']['parameters']['jsCode'],
                                           {'Extract Followup Reschedule ID': {'meetingId': '79', 'phoneNumber': '569', 'phoneNumberId': '1'}}, {'estado': 'ok', 'citas': [self.SEG]})
        self.assertEqual(ok['ruta'], 'seguimiento')
        self.assertEqual(self.modo_sale('Ruta Recordatorio', 'diagnostico'), ['Marcar Reagendar (recordatorio)'])
        self.assertEqual(self.modo_sale('Ruta Recordatorio', 'seguimiento'), ['Marcar Reagendar (recordatorio seg)'])
        self.assertEqual(self.modo_sale('Ruta Recordatorio', 'aviso'), ['Send Citas', '¿Avisar a Luis (citas)?'])
        self.assertEqual(self.salida('Marcar Reagendar (recordatorio)', 0), ['Prepare Reschedule Data'])
        self.assertNotIn('Cancel Followup For Reschedule', json.dumps(self.con['Marcar Reagendar (recordatorio seg)']))

    def test_b02_una_agenda_nueva_olvida_la_marca_de_reprogramar(self):
        self.assertIn('¿Agenda nueva?', self.salida('Parse Response Actions', 0))
        self.assertIn('Route Action', self.salida('Parse Response Actions', 0))
        cond = self.nodos['¿Agenda nueva?']['parameters']['conditions']['boolean'][0]['value1']
        self.assertIs(aplicar.reconoce.evaluar(cond, {'action': 'show_calendar'}, {}), True)
        self.assertIs(aplicar.reconoce.evaluar(cond, {'action': 'reschedule_appointment'}, {}), False)
        self.assertEqual(self.salida('¿Agenda nueva?', 0), ['Olvidar Marca Reagendar'])
        self.assertEqual(sorted(self.salida('Prepare Followup Data', 0)), ['Get Appointments Config', 'Olvidar Marca Reagendar'])
        self.assertEqual(self.nodos['Olvidar Marca Reagendar']['parameters']['operation'], 'delete')

    def test_b07_otra_fecha_durante_un_reprogramado_no_abre_la_fecha_libre(self):
        pi = {'buttonId': 'other_date_seg', 'phoneNumber': '56900001111', 'phoneNumberId': '1'}
        con_marca = aplicar.reconoce.simular_nodo(self.nodos['Decidir Otra Fecha']['parameters']['jsCode'],
                                                  {'Process Interactive': pi, 'Leer Marca (otra fecha)': {'marca': json.dumps({'tipo': 'seguimiento', 'id': 79})}}, {})
        self.assertIs(con_marca['reagendar'], True)
        self.assertIn('elige uno de los días', json.dumps(con_marca['mensaje'], ensure_ascii=False))
        sin = aplicar.reconoce.simular_nodo(self.nodos['Decidir Otra Fecha']['parameters']['jsCode'],
                                            {'Process Interactive': pi, 'Leer Marca (otra fecha)': {'marca': None}}, {})
        self.assertEqual((sin['reagendar'], sin['phoneNumberId'], sin['buttonId']), (False, '1', 'other_date_seg'))
        self.assertEqual(self.salida('¿Reagendando (otra fecha)?', 0), ['Send Citas', '¿Avisar a Luis (citas)?'])
        self.assertEqual(self.salida('¿Reagendando (otra fecha)?', 1), ['Send Other Date Options'])
        valores = self.nodos['Button Action']['parameters']['rules']['values']
        i = next(k for k, r in enumerate(valores) if r['conditions']['conditions'][0].get('rightValue') == 'other_date')
        self.assertEqual(self.salida('Button Action', i), ['Leer Marca (otra fecha)'])

    def test_reprogramar_seguimiento_conserva_contrato_id_y_propuesta_id(self):
        v = aplicar.reconoce.evaluar(self.nodos['Guardar Cliente Reagendar']['parameters']['value'], {}, {'Decidir Botón Cita': {'cita': self.SEG}})
        g = json.loads(v)
        self.assertEqual((g['email'], g['contrato_id'], g['propuesta_id'], g['subject'], g['notes']), ('ana@prueba.cl', 7, 0, 'Seguimiento: Agenda', self.SEG['notas']))

    def test_reprogramar_desde_el_boton_abre_el_calendario_del_tipo(self):
        self.assertEqual(self.salida('Ruta Botón Cita', 2), ['Marcar Reagendar'])
        tipo = aplicar.reconoce.evaluar(self.nodos['Prepare Reagendar Cita']['parameters']['jsonOutput'], {},
                                        {'Decidir Botón Cita': {'phoneNumber': '569', 'phoneNumberId': '1', 'cita': {'tipo': 'seguimiento'}}})
        self.assertEqual(tipo['appointmentType'], 'Seguimiento')
        self.assertEqual(self.nodos['Generate Days']['parameters']['jsCode'].count("$('Prepare Reagendar Cita')"), 1)

    def test_b04_cancelada_no_promete_el_correo_si_no_salio(self):
        cuerpo = self.nodos['Send Cita Cancelada']['parameters']['jsonBody']
        stubs = {'Decidir Botón Cita': {'phoneNumber': '569', 'cita': {'etiqueta': 'X'}}}
        con = json.loads(aplicar.reconoce.evaluar(cuerpo, {'ok': True, 'efectos': {'correo': True}}, stubs))['text']['body']
        sin = json.loads(aplicar.reconoce.evaluar(cuerpo, {'ok': True, 'efectos': {'correo': False}}, stubs))['text']['body']
        self.assertIn('correo', con)
        self.assertNotIn('correo', sin)


class Reintentos2(unittest.TestCase):
    def test_b09_reparar_solo_los_reintentos_se_detecta_como_cambio(self):
        _, w, _ = aplicado('web', 'web-actual.json')
        roto = copy.deepcopy(w)
        {n['name']: n for n in roto['nodes']}['Get Exchange Rate']['maxTries'] = 1
        reparado = copy.deepcopy(roto)
        aplicar.cambiar('web', reparado, aplicar.reconoce.bloque(), aplicar.bloque(), [])
        tocados, fallas = aplicar.comprobar('web', reparado, roto, aplicar.reconoce.bloque(), aplicar.bloque())
        self.assertEqual((tocados, fallas), (['Get Exchange Rate'], []))
        pub = {'activeVersion': {'nodes': [{'name': 'Get Exchange Rate', 'retryOnFail': True, 'maxTries': 3, 'waitBetweenTries': 0}]}}
        self.assertTrue(aplicar.verificar_reintentos(pub, ('Get Exchange Rate',)), 'waitBetweenTries también se verifica')


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
