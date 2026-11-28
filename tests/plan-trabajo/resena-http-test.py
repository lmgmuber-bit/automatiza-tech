"""Comprobación HTTP de solo lectura, únicamente contra una instalación local de pruebas.
AT_RESENA_TEST_URL=http://localhost/sitio (sin usuario, contraseña ni token).
El destino de Google debe estar vacío para comprobar el bloqueo. No llama REST autenticado ni envía nada.
"""
import os
import sys
import urllib.error
import urllib.parse
import urllib.request

base = os.environ.get('AT_RESENA_TEST_URL', '').rstrip('/')
p = urllib.parse.urlparse(base)
if p.hostname not in ('localhost', '127.0.0.1', '::1') or p.scheme not in ('http', 'https') or p.username or p.password or p.query or p.fragment:
    print('NOT_RUN: configurar AT_RESENA_TEST_URL de un WordPress local aislado.'); sys.exit(2)

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs): return None

opener = urllib.request.build_opener(NoRedirect())

def fetch(path, method='GET', body=None):
    req = urllib.request.Request(base + path, method=method, data=body)
    try:
        with opener.open(req, timeout=10) as r: return r.status, r.headers, r.read()
    except urllib.error.HTTPError as e: return e.code, e.headers, e.read()

checks = [
    ('/?rest_route=/at/v1/resena/pendientes', 'GET', None, 401),
    ('/?rest_route=/at/v1/resena/proyecto/1/reclamar', 'POST', b'{}', 401),
    ('/?rest_route=/at/v1/resena/proyecto/1/resultado', 'POST', b'{}', 401),
    ('/?rest_route=/at/v1/resena/proyecto/1/autorizar-whatsapp', 'POST', b'{}', 401),
    ('/?rest_route=/at/v1/resena/baja', 'POST', b'{}', 401),
    ('/resena/', 'GET', None, 404),
    ('/resena/baja/?token=invalido', 'GET', None, 400),
]
failed = 0
for path, method, body, expected in checks:
    status, headers, content = fetch(path, method, body)
    ok = status == expected
    if path.startswith('/resena/'):
        ok = ok and 'noindex' in headers.get('X-Robots-Tag', '') and headers.get('Referrer-Policy') == 'no-referrer' and not headers.get('Location')
    failed += not ok
    print(('PASS' if ok else 'FAIL') + ' ' + method + ' ' + path.split('?token=')[0] + ' HTTP ' + str(status))
sys.exit(1 if failed else 0)