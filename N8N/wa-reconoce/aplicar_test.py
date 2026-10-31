"""Pruebas de aplicar.py sin tocar n8n: la verificación de lo publicado (revisión B-05 de Codex).
Correr: python -I -X utf8 N8N/wa-reconoce/aplicar_test.py
"""
import copy
import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import aplicar  # noqa: E402


def flujo(params_b='nuevo', conexion='C'):
    nodos = [{'name': 'A', 'parameters': {'x': 1}}, {'name': 'B', 'parameters': {'url': params_b}}]
    return {'nodes': nodos, 'connections': {'A': {'main': [[{'node': 'B', 'type': 'main', 'index': 0}]]}, 'B': {'main': [[{'node': conexion, 'type': 'main', 'index': 0}]]}}}


def releido(publicado, activo=True, version='v2', activa='v2'):
    return {'active': activo, 'versionId': version, 'activeVersionId': activa, 'nodes': copy.deepcopy(publicado['nodes']),
            'connections': copy.deepcopy(publicado['connections']),
            'activeVersion': {'nodes': copy.deepcopy(publicado['nodes']), 'connections': copy.deepcopy(publicado['connections'])}}


class VerificarPublicacion(unittest.TestCase):
    def setUp(self):
        self.esperado = flujo()

    def test_publicacion_correcta(self):
        self.assertEqual(aplicar.verificar_publicacion(self.esperado, releido(self.esperado), ['B'], True, 200, 200), [])

    def test_activate_rechazado(self):
        self.assertTrue(aplicar.verificar_publicacion(self.esperado, releido(self.esperado), ['B'], True, 400, 200))

    def test_relectura_fallida(self):
        self.assertTrue(aplicar.verificar_publicacion(self.esperado, releido(self.esperado), ['B'], True, 200, 500))

    def test_version_activa_vieja_con_los_mismos_nombres(self):
        viejo = flujo(params_b='viejo')
        r = releido(viejo, version='v2', activa='v1')
        fallas = aplicar.verificar_publicacion(self.esperado, r, ['B'], True, 200, 200)
        self.assertTrue(any('B' in f for f in fallas), fallas)

    def test_parametros_viejos_aunque_la_version_coincida(self):
        self.assertTrue(aplicar.verificar_publicacion(self.esperado, releido(flujo(params_b='viejo')), ['B'], True, 200, 200))

    def test_conexiones_distintas(self):
        self.assertTrue(aplicar.verificar_publicacion(self.esperado, releido(flujo(conexion='Z')), ['B'], True, 200, 200))

    def test_quedo_inactivo(self):
        self.assertTrue(aplicar.verificar_publicacion(self.esperado, releido(self.esperado, activo=False), ['B'], True, 200, 200))

    def test_flujo_inactivo_se_compara_el_borrador(self):
        r = releido(self.esperado, activo=False, activa=None)
        r['activeVersion'] = None
        self.assertEqual(aplicar.verificar_publicacion(self.esperado, r, ['B'], False, None, 200), [])
        r['nodes'][1]['parameters']['url'] = 'viejo'
        self.assertTrue(aplicar.verificar_publicacion(self.esperado, r, ['B'], False, None, 200))


class NodosTocados(unittest.TestCase):
    """Revisión R2-05 de Codex: un cambio solo de conexiones o de onError también es un cambio que se sube y se verifica."""

    def test_cambio_solo_de_conexion(self):
        antes = flujo()
        despues = copy.deepcopy(antes)
        despues['connections']['A'] = {'main': [[{'node': 'C', 'type': 'main', 'index': 0}]]}
        self.assertEqual(aplicar.nodos_tocados(antes, despues), ['A'])

    def test_cambio_solo_de_onerror(self):
        antes = flujo()
        despues = copy.deepcopy(antes)
        despues['nodes'][1]['onError'] = 'continueRegularOutput'
        self.assertEqual(aplicar.nodos_tocados(antes, despues), ['B'])

    def test_nodo_nuevo_y_sin_cambios(self):
        antes = flujo()
        despues = copy.deepcopy(antes)
        despues['nodes'].append({'name': 'D', 'parameters': {}})
        self.assertEqual(aplicar.nodos_tocados(antes, despues), ['D'])
        self.assertEqual(aplicar.nodos_tocados(antes, copy.deepcopy(antes)), [])

    def test_conexion_vieja_de_un_nodo_con_los_mismos_parametros_no_se_acepta(self):
        antes = flujo()
        esperado = copy.deepcopy(antes)
        esperado['connections']['A'] = {'main': [[{'node': 'C', 'type': 'main', 'index': 0}]]}
        tocados = aplicar.nodos_tocados(antes, esperado)
        self.assertTrue(aplicar.verificar_publicacion(esperado, releido(antes), tocados, True, 200, 200))
        self.assertEqual(aplicar.verificar_publicacion(esperado, releido(esperado), tocados, True, 200, 200), [])

    def test_onerror_viejo_no_se_acepta(self):
        antes = flujo()
        esperado = copy.deepcopy(antes)
        esperado['nodes'][1]['onError'] = 'continueRegularOutput'
        self.assertTrue(aplicar.verificar_publicacion(esperado, releido(antes), aplicar.nodos_tocados(antes, esperado), True, 200, 200))


if __name__ == '__main__':
    unittest.main(verbosity=1)
