"""Contrato offline del flujo «Reseña · Resumen» y del parche al diario. Sin n8n, red, credenciales ni temporales."""
import contextlib
import copy
import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

base = Path(__file__).resolve().parents[2] / 'N8N/resena-google'
spec = importlib.util.spec_from_file_location('resena_resumen', base / 'resumen.py')
r = importlib.util.module_from_spec(spec); spec.loader.exec_module(r)
spec_a = importlib.util.spec_from_file_location('resena_aplicar_t4', base / 'aplicar.py')
a = importlib.util.module_from_spec(spec_a); spec_a.loader.exec_module(a)

RECLAMAR = 'Reclamar (WP envía correo y dispara WA)'
PEDIR_UNA = 'Pedir resumen (una vez)'
ORDEN = ['Webhook resumen', 'Esperar 15 minutos', 'Pedir resumen', 'Armar correo', '¿Hubo envíos?', 'Enviar correo a Luis']


def flujo():
    return r.workflow('CONFIGURAR_AT_REST', 'CONFIGURAR_AT_REST', 'CONFIGURAR_SMTP', 'CONFIGURAR_REMITENTE')


def por_nombre(w):
    return {n['name']: n for n in w['nodes']}


def correr_separar(code, pendientes):
    js = ("const code = require('fs').readFileSync(0, 'utf8');\n"
          "const input = {first: () => ({json: {pendientes: " + json.dumps(pendientes) + "}})};\n"
          "console.log(JSON.stringify(new Function('$input', code)(input)));")
    p = subprocess.run(['node', '-e', js], input=code, text=True, encoding='utf-8', capture_output=True)
    if p.returncode != 0:
        raise AssertionError(p.stderr)
    return json.loads(p.stdout)


class ResumenTests(unittest.TestCase):
    def test_flujo_resumen(self):
        w = flujo(); by = por_nombre(w)
        self.assertEqual(w['name'], 'Reseña · Resumen')
        self.assertIs(w['active'], False)
        # Mismos settings que el diario, salvo el tope: la espera de 15 min no cabe en 300 s.
        self.assertEqual(w['settings']['executionTimeout'], 1800)
        sin = {k: v for k, v in w['settings'].items() if k != 'errorWorkflow'}
        self.assertEqual(dict(sin, executionTimeout=300), a.workflows()[0]['settings'])
        # Solo el resumen avisa si falla (el flujo común de avisos de error de plan-trabajo).
        self.assertEqual(w['settings']['errorWorkflow'], 'm7TOfKznVSBGz4Nd')
        self.assertTrue(all('errorWorkflow' not in x['settings'] for x in a.workflows()))
        self.assertTrue(all(x['settings']['executionTimeout'] == 300 for x in a.workflows()))
        self.assertEqual([n['name'] for n in w['nodes']], ORDEN)
        c = w['connections']
        for origen, destino in zip(ORDEN[:4], ORDEN[1:5]):
            self.assertEqual(c[origen]['main'], [[a.edge(destino)]])
        self.assertEqual(c['¿Hubo envíos?']['main'], [[a.edge('Enviar correo a Luis')], []])  # solo la salida verdadera
        wh = by['Webhook resumen']
        self.assertEqual(wh['type'], 'n8n-nodes-base.webhook')
        self.assertEqual(wh['parameters']['path'], 'at-resena-resumen')
        self.assertEqual(wh['parameters']['httpMethod'], 'POST')
        self.assertEqual(wh['parameters']['responseMode'], 'onReceived')
        self.assertEqual(wh['parameters']['authentication'], 'headerAuth')
        self.assertEqual(wh['webhookId'], str(__import__('uuid').uuid5(__import__('uuid').NAMESPACE_URL, 'at-resena-resumen')))
        espera = by['Esperar 15 minutos']
        self.assertEqual((espera['type'], espera['typeVersion']), ('n8n-nodes-base.wait', 1.1))
        self.assertEqual((espera['parameters']['amount'], espera['parameters']['unit']), (15, 'minutes'))
        pedir = by['Pedir resumen']
        self.assertNotEqual(pedir.get('onError'), 'continueRegularOutput')
        self.assertIsNot(pedir['parameters']['options']['response']['response'].get('neverError'), True)
        self.assertEqual(pedir['parameters']['method'], 'GET')
        self.assertIs(pedir['retryOnFail'], True)
        self.assertEqual((pedir['maxTries'], pedir['waitBetweenTries']), (3, 5000))
        self.assertEqual(pedir['parameters']['url'],
                         '={{ "' + a.WP + '/resumen?desde=" + encodeURIComponent($(\'Webhook resumen\').first().json.body.desde) }}')
        gate = by['¿Hubo envíos?']['parameters']['conditions']['conditions'][0]
        self.assertEqual(gate['leftValue'], '={{ $json.enviar }}')
        self.assertIs(gate['rightValue'], True)
        self.assertEqual(gate['operator'], {'type': 'boolean', 'operation': 'equals'})
        mail = by['Enviar correo a Luis']
        self.assertEqual((mail['type'], mail['typeVersion']), ('n8n-nodes-base.emailSend', 2.1))
        self.assertEqual(mail['parameters']['fromEmail'], 'CONFIGURAR_REMITENTE')
        self.assertEqual(mail['parameters']['toEmail'], '={{ $json.aviso_a }}')
        self.assertEqual(mail['parameters']['subject'], '={{ $json.asunto }}')
        self.assertEqual(mail['parameters']['emailFormat'], 'html')
        self.assertEqual(mail['parameters']['html'], '={{ $json.html }}')
        self.assertEqual(mail['credentials']['smtp']['id'], 'CONFIGURAR_SMTP')
        self.assertEqual(mail['parameters']['options'], {'appendAttribution': False})

    def test_codigo_incluye_armar_correo(self):
        code = por_nombre(flujo())['Armar correo']['parameters']['jsCode']
        self.assertIn('function armarCorreo', code)
        self.assertNotIn('module.exports', code)
        # En «Run Once for All Items» (modo por defecto) n8n no define $json.
        self.assertIn('$input.first().json', code)
        self.assertNotIn('armarCorreo($json', code)
        self.assertIn("return [{ json: armarCorreo($input.first().json, $('Webhook resumen').first().json.body.origen) }];", code)
        # El código del nodo corre en Node con un resumen ficticio y devuelve el correo.
        js = ("const code = require('fs').readFileSync(0, 'utf8');\n"
              "const $ = () => ({first: () => ({json: {body: {origen: 'manual', desde: '2026-11-24T13:00:00Z'}}})});\n"
              "const $input = {first: () => ({json: {desde: '2026-11-24T13:00:00Z', aviso_a: 'x', total: 1, ok: 1, filas: []}})};\n"
              "const out = new Function('$input', '$', code)($input, $);\n"
              "console.log(JSON.stringify(out));")
        p = subprocess.run(['node', '-e', js], input=code, text=True, encoding='utf-8', capture_output=True)
        self.assertEqual(p.returncode, 0, p.stderr)
        out = json.loads(p.stdout)[0]['json']
        self.assertIs(out['enviar'], True)
        self.assertTrue(out['asunto'].endswith('(envío manual)'))

    def test_sin_datos_privados(self):
        out = io.StringIO()
        with patch('urllib.request.urlopen', side_effect=AssertionError('red prohibida')), \
                patch.object(Path, 'write_text', side_effect=AssertionError('archivo prohibido')), contextlib.redirect_stdout(out):
            self.assertEqual(r.main(['--dry-run']), 0)
        data = json.loads(out.getvalue())
        self.assertEqual([w['name'] for w in data['workflows']], ['Reseña · Resumen', 'Reseña · Diario 10:00'])
        texto = json.dumps(data['workflows'][0], ensure_ascii=False)
        self.assertNotIn('@', texto)
        self.assertNotIn('WA_EMISOR', texto)
        self.assertNotIn(a.WA_EMISOR, texto)
        self.assertTrue(all(w['active'] is False for w in data['workflows']))

    def test_patch_daily(self):
        sin = a.workflows(resumen=False)[0]; foto = copy.deepcopy(sin)
        w = r.patch_daily(sin, 'REST')
        self.assertEqual(sin, foto)  # no toca el original
        self.assertEqual(len(w['nodes']), len(sin['nodes']) + 1)
        nuevos = [n for n in w['nodes'] if n['name'] == PEDIR_UNA]
        self.assertEqual(len(nuevos), 1)
        n = nuevos[0]
        self.assertIs(n['executeOnce'], True)
        self.assertEqual(n['parameters']['method'], 'POST')
        self.assertEqual(n['parameters']['url'], 'https://n8n-n8n.kchiba.easypanel.host/webhook/at-resena-resumen')
        self.assertEqual(n['parameters']['jsonBody'], "={{ {desde: $('Separar proyectos').first().json.desde, origen: 'diario'} }}")
        self.assertEqual(n['credentials']['httpHeaderAuth']['id'], 'REST')
        self.assertEqual(w['connections'][RECLAMAR]['main'], [[a.edge(PEDIR_UNA)]])
        self.assertIn('desde', por_nombre(w)['Separar proyectos']['parameters']['jsCode'])
        with self.assertRaises(ValueError):
            r.patch_daily(w, 'REST')
        # aplicar.py describe lo que corre: su diario ya viene parchado.
        self.assertEqual(a.workflows()[0], r.patch_daily(a.workflows(resumen=False)[0], 'CONFIGURAR_AT_REST'))

    def test_diario_sin_pendientes_no_llama(self):
        w = a.workflows()[0]
        self.assertEqual(correr_separar(por_nombre(w)['Separar proyectos']['parameters']['jsCode'], []), [])
        self.assertIs(por_nombre(w)[PEDIR_UNA]['executeOnce'], True)

    def test_diario_tres_pendientes_una_llamada(self):
        w = a.workflows()[0]
        items = correr_separar(por_nombre(w)['Separar proyectos']['parameters']['jsCode'],
                               [{'proyecto_id': 1}, {'proyecto_id': 2}, {'proyecto_id': 3}])
        self.assertEqual([i['json']['proyecto_id'] for i in items], [1, 2, 3])
        self.assertEqual(len({i['json']['desde'] for i in items}), 1)
        self.assertRegex(items[0]['json']['desde'], r'^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$')
        self.assertIs(por_nombre(w)[PEDIR_UNA]['executeOnce'], True)

    def test_guarda_webroot_en_respaldo(self):
        # Una carpeta llamada www en la ruta del respaldo se rechaza por la guarda, no por falta de entorno.
        fuera = str(Path(tempfile.gettempdir()) / 'www' / 'resena-no-se-crea')
        casos = [(r.main, ['--apply', '--rest-credential', 'r1', '--webhook-credential', 'w1', '--smtp-credential', 's1',
                           '--remitente', 'remitente', '--daily-id', 'd1', '--backup-dir', fuera]),
                 (a.main, ['--apply', '--rest-credential', 'r1', '--wa-credential', 'w1', '--webhook-credential', 'w2',
                           '--backup-dir', fuera])]
        for main, args in casos:
            err = io.StringIO()
            with patch.dict(os.environ, {}, clear=False):
                os.environ.pop('AT_N8N_API_KEY', None); os.environ.pop('AT_N8N_URL', None)
                with patch('urllib.request.urlopen', side_effect=AssertionError('red prohibida')), contextlib.redirect_stderr(err):
                    with self.assertRaises(SystemExit):
                        main(args)
            self.assertIn('fuera del repo y webroot', err.getvalue())
        self.assertFalse(Path(fuera).exists())

    def test_apply_sin_entorno(self):
        fuera = str(Path(tempfile.gettempdir()) / 'resena-resumen-no-se-crea')
        args = ['--apply', '--rest-credential', 'r1', '--webhook-credential', 'w1', '--smtp-credential', 's1',
                '--remitente', 'remitente', '--daily-id', 'd1', '--backup-dir', fuera]
        with patch.dict(os.environ, {}, clear=False):
            os.environ.pop('AT_N8N_API_KEY', None); os.environ.pop('AT_N8N_URL', None)
            with patch('urllib.request.urlopen', side_effect=AssertionError('red prohibida')), \
                    patch('urllib.request.OpenerDirector.open', side_effect=AssertionError('red prohibida')), \
                    contextlib.redirect_stderr(io.StringIO()):
                with self.assertRaises(SystemExit) as e:
                    r.main(args)
        self.assertEqual(e.exception.code, 2)
        self.assertFalse(Path(fuera).exists())

    def test_apply_respalda_crea_inactivo_y_parcha(self):
        diario = a.workflows(resumen=False)[0]
        remoto = dict(copy.deepcopy(diario), id='d1', active=True, versionId='v1', activeVersionId='v1')
        remoto['settings'] = dict(remoto['settings'], timeSavedMode='fixed', availableInMCP=False)
        calls = []; directorio = {}

        class Dir:
            def mkdir(self, **kw): directorio['mkdir'] = True
            def __truediv__(self, name):
                d = self
                class F:
                    def write_text(self, v, **kw): directorio[name] = v
                return F()

        def api(method, path, body=None):
            calls.append((method, path))
            if path.startswith('/workflows?'): return {'data': [{'name': 'Reseña · Diario 10:00'}]}
            if method == 'GET' and path == '/workflows/d1':
                return remoto if not any(c[0] == 'PUT' for c in calls) else dict(remoto, versionId='v2', activeVersionId='v2')
            if method == 'POST':
                self.assertIn('diario-antes.json', directorio)
                self.assertNotIn('active', body)
                return {'id': 'n1'}
            if method == 'GET' and path == '/workflows/n1':
                return {'active': False, 'activeVersion': None, 'activeVersionId': None}
            if method == 'PUT':
                self.assertEqual(set(body), {'name', 'nodes', 'connections', 'settings'})
                self.assertNotIn('timeSavedMode', body['settings']); self.assertNotIn('availableInMCP', body['settings'])
                self.assertIn(PEDIR_UNA, [n['name'] for n in body['nodes']])
                return {'id': 'd1'}
            raise AssertionError((method, path))

        res = r.aplicar_flujos(flujo(), 'd1', 'REST', Dir(), api)
        self.assertEqual(res, {'resumen_id': 'n1', 'diario_id': 'd1'})
        self.assertEqual([c[0] for c in calls], ['GET', 'GET', 'POST', 'GET', 'PUT', 'GET'])
        self.assertNotIn('/activate', ' '.join(c[1] for c in calls))

    def test_apply_falla_si_existe_o_version_no_publicada(self):
        with self.assertRaises(RuntimeError):
            r.aplicar_flujos(flujo(), 'd1', 'REST', None, lambda *x: {'data': [{'name': 'Reseña · Resumen'}]})
        diario = dict(a.workflows(resumen=False)[0], versionId='v1', activeVersionId='v1')
        estado = {'put': False}

        class Dir:
            def mkdir(self, **kw): pass
            def __truediv__(self, name):
                class F:
                    def write_text(self, v, **kw): pass
                return F()

        def api(method, path, body=None):
            if path.startswith('/workflows?'): return {'data': []}
            if method == 'POST': return {'id': 'n1'}
            if path == '/workflows/n1': return {'active': False, 'activeVersion': None, 'activeVersionId': None}
            if method == 'PUT': estado['put'] = True; return {}
            return dict(diario, versionId='v2') if estado['put'] else diario
        with self.assertRaises(RuntimeError):
            r.aplicar_flujos(flujo(), 'd1', 'REST', Dir(), api)


if __name__ == '__main__': unittest.main()
