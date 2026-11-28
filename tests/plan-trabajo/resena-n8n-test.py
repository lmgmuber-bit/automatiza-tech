"""Contrato offline del generador. Sin n8n, red, credenciales ni temporales."""
import contextlib
import copy
import importlib.util
import io
import json
from pathlib import Path
import sys
import subprocess
import unittest
from unittest.mock import patch

source = Path(__file__).resolve().parents[2] / 'N8N/resena-google/aplicar.py'
spec = importlib.util.spec_from_file_location('resena_aplicar', source)
m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m)

class MemoryPath:
    def __init__(self, files=None, key=''):
        self.files = files if files is not None else {}; self.key = key
    def mkdir(self, **kw): self.files['mkdir'] = True
    def __truediv__(self, name): return MemoryPath(self.files, name)
    def write_text(self, value, **kw): self.files[self.key] = value

class WorkflowTests(unittest.TestCase):
    def test_dry_run_no_network_or_files(self):
        out = io.StringIO()
        with patch('urllib.request.urlopen', side_effect=AssertionError('red prohibida')), patch.object(Path, 'write_text', side_effect=AssertionError('archivo prohibido')), contextlib.redirect_stdout(out):
            self.assertEqual(m.main(['--dry-run']), 0)
        data = json.loads(out.getvalue())
        self.assertEqual(len(data['workflows']), 2)
        self.assertTrue(all(f['active'] is False for f in data['workflows']))

    def test_schedule_chile_and_shared_dispatch(self):
        daily, wa = m.workflows()
        self.assertEqual(daily['settings']['timezone'], 'America/Santiago')
        self.assertEqual(daily['nodes'][0]['parameters']['rule']['interval'][0]['expression'], '0 10 * * *')
        self.assertEqual(sum('graph.facebook.com' in str(n) for n in daily['nodes']), 0)
        self.assertEqual(sum('graph.facebook.com' in str(n) for n in wa['nodes']), 1)

    def test_no_retries_or_pii_persistence(self):
        for w in m.workflows():
            self.assertEqual(w['settings']['saveDataSuccessExecution'], 'none')
            self.assertEqual(w['settings']['saveDataErrorExecution'], 'none')
            self.assertFalse(w['settings']['saveManualExecutions'])
            for n in w['nodes']:
                self.assertIs(n['retryOnFail'], False)
                self.assertNotIn('X-AT-Secret', json.dumps(n['parameters'].get('headerParameters', {})))

    def test_authorization_before_meta(self):
        wa = m.workflows()[1]
        self.assertEqual(wa['connections']['Webhook autenticado']['main'][0][0]['node'], 'Autorizar despacho')
        self.assertEqual(wa['connections']['Solo despacho nuevo']['main'][1], [])
        send = next(n for n in wa['nodes'] if n['name'].startswith('Enviar plantilla'))
        self.assertIn('"at_resena_google_cierre_v2"', send['parameters']['jsonBody'])
        self.assertIn('payload:"' + m.BAJA_PAYLOAD + '"', send['parameters']['jsonBody'].replace(' ', ''))
        self.assertIn('index:"1"', send['parameters']['jsonBody'].replace(' ', ''))  # botón 0 = URL, 1 = respuesta rápida
        self.assertIn('code:"es"', send['parameters']['jsonBody'].replace(' ', ''))
        self.assertNotIn('}}', send['parameters']['jsonBody'][3:-2])  # n8n corta en el primer «}}» interno
        self.assertNotIn('$env', send['parameters']['url'])
        self.assertEqual(wa['nodes'][0]['parameters']['authentication'], 'headerAuth')

    def test_active_version_rejected(self):
        m.verify_inactive({'active': False, 'activeVersionId': None, 'activeVersion': None})
        for w in [{'active': False}, {'active': True}, {'active': False, 'activeVersionId': 'version'}, {'active': False, 'activeVersion': {}}]:
            with self.assertRaises(RuntimeError): m.verify_inactive(w)

    def test_backup_before_creates_and_verify_each(self):
        directory = MemoryPath(); calls = []
        def api(method, path, body=None):
            calls.append((method, path))
            if path.startswith('/workflows?'): return {'data': []}
            if method == 'POST':
                self.assertIn('propuestos-inactivos.json', directory.files)
                self.assertNotIn('active', body)
                return {'id': str(len(calls))}
            return {'active': False, 'activeVersion': None, 'activeVersionId': None}
        result = m.apply_inactive(m.workflows(), directory, api)
        self.assertEqual(len(result), 2)
        self.assertEqual([c[0] for c in calls], ['GET', 'POST', 'GET', 'POST', 'GET'])
        self.assertEqual(len(json.loads(directory.files['creados.json'])), 2)

    def test_existing_flow_never_overwritten(self):
        with self.assertRaises(RuntimeError):
            m.apply_inactive(m.workflows(), MemoryPath(), lambda *a: {'data': [{'name': 'Reseña · Diario 10:00'}]})

    @staticmethod
    def bot_export():
        regla = lambda v: {'conditions': {'options': {'caseSensitive': True, 'leftValue': '', 'typeValidation': 'loose', 'version': 2},
                                          'conditions': [{'leftValue': '={{ $json.buttonId }}', 'rightValue': v, 'operator': {'type': 'string', 'operation': 'startsWith'}}],
                                          'combinator': 'and'}, 'renameOutput': True, 'outputKey': v}
        return {'nodes': [{'name': 'Merge Data'}, {'name': 'Contexto Propuesta'}, {'name': 'Process Interactive'},
                          {'name': 'Button Action', 'position': [100, 200], 'parameters': {'rules': {'values': [regla('btn_a'), regla('btn_propuesta_')]}, 'options': {'fallbackOutput': 'extra'}}},
                          {'name': 'A'}, {'name': 'Propuesta'}],
                'connections': {'Merge Data': {'main': [[m.edge('Contexto Propuesta')]]},
                                'Button Action': {'main': [[m.edge('A')], [m.edge('Propuesta')]]}}}

    def test_bot_adapter_preserves_original_and_wires_both_paths(self):
        original = self.bot_export(); snapshot = copy.deepcopy(original)
        w = m.patch_bot(original, 'REST', 'WA')
        self.assertEqual(original, snapshot)
        c = w['connections']
        self.assertEqual(c['Merge Data']['main'], [[m.edge('Reseña · ¿Menciona BAJA?')]])
        self.assertEqual(c['Reseña · ¿Menciona BAJA?']['main'][1], [m.edge('Contexto Propuesta')])  # lo normal sigue igual
        self.assertEqual(c['Reseña · ¿BAJA registrada?']['main'][1], [m.edge('Reseña · Seguir al agente')])
        self.assertEqual(c['Reseña · Seguir al agente']['main'], [[m.edge('Contexto Propuesta')]])
        rules = w['nodes'][3]['parameters']['rules']['values']
        self.assertEqual(rules[2]['conditions']['conditions'][0]['rightValue'], 'btn_resena_baja')
        self.assertEqual(rules[2]['conditions']['conditions'][0]['operator']['operation'], 'equals')
        self.assertEqual(c['Button Action']['main'][:2], [[m.edge('A')], [m.edge('Propuesta')]])
        self.assertEqual(c['Button Action']['main'][2], [m.edge('Reseña · Datos BAJA (botón)')])
        by = {n['name']: n for n in w['nodes']}
        for n in ('Reseña · Registrar BAJA (texto)', 'Reseña · Registrar BAJA (botón)'):
            self.assertTrue(by[n]['parameters']['url'].endswith('/resena/baja'))
            self.assertEqual(by[n]['credentials']['httpHeaderAuth']['id'], 'REST')
            self.assertNotIn('}}', by[n]['parameters']['jsonBody'][3:-2])
        for n in ('Reseña · Enviar confirmación (texto)', 'Reseña · Enviar confirmación (botón)'):
            self.assertEqual(by[n]['credentials']['whatsAppApi']['id'], 'WA')
        with self.assertRaises(ValueError): m.patch_bot(w)

    def test_bot_adapter_keeps_connected_fallback_last(self):
        original = self.bot_export(); original['connections']['Button Action']['main'].append([m.edge('A')])  # salida extra
        w = m.patch_bot(original)
        self.assertEqual(w['connections']['Button Action']['main'][2], [m.edge('Reseña · Datos BAJA (botón)')])
        self.assertEqual(w['connections']['Button Action']['main'][3], [m.edge('A')])

    def test_bot_baja_executed_in_node(self):
        w = m.patch_bot(self.bot_export())
        code = r"""
const w=JSON.parse(require('fs').readFileSync(0,'utf8'));
const by=Object.fromEntries(w.nodes.map(n=>[n.name,n]));
const expr=s=>new Function('$json','return ('+s.slice(3,-2)+');');
const gate=expr(by['Reseña · ¿Menciona BAJA?'].parameters.conditions.conditions[0].leftValue);
const ok=expr(by['Reseña · ¿BAJA registrada?'].parameters.conditions.conditions[0].leftValue);
const regT=expr(by['Reseña · Registrar BAJA (texto)'].parameters.jsonBody);
for(const text of ['¡BÁJA!','stop por favor','Darme de baja','NO ME ESCRIBAN más','No quiero recibir','¿Cómo es la baja?']){
  const json={chatInput:text,phoneNumber:'56900000000'};
  if(gate(json)!==true) throw Error('No va a WordPress: '+text);
  const b=JSON.parse(regT(json)); if(b.texto!==text||b.telefono!=='+56900000000') throw Error('Se pierde texto o teléfono');
}
if(gate({chatInput:'Hola, necesito ayuda'})!==false) throw Error('Desvía un mensaje normal');
if(ok({body:{ok:true,baja:true}})!==true||ok({body:{ok:true,revision:true}})!==false||ok({statusCode:503,body:{}})!==false) throw Error('Puerta de confirmación');
const code=(n,refs,input)=>new Function('$','$input',by[n].parameters.jsCode)(name=>refs[name],{first:()=>({json:input})});
const md={first:()=>({json:{phoneNumber:'56900000000',chatInput:'baja'}}),all:()=>[{json:{phoneNumber:'56900000000',chatInput:'baja',rate:950}}]};
let out=code('Reseña · Confirmar BAJA (texto)',{'Merge Data':md},{body:{ok:true,baja:true}});
let body=JSON.parse(out[0].json.whatsappBody); if(body.to!=='56900000000'||!/no te enviaremos/.test(body.text.body)||body.type!=='text') throw Error('Confirmación texto');
out=code('Reseña · Seguir al agente',{'Merge Data':md},{});
if(out[0].json.chatInput!=='baja'||out[0].json.rate!==950) throw Error('El agente no recibe los datos originales');
const pi={first:()=>({json:{phoneNumber:'56900000000'}})};
out=code('Reseña · Datos BAJA (botón)',{'Process Interactive':pi},{});
if(out[0].json.texto!=='baja'||out[0].json.telefono!=='+56900000000') throw Error('Datos botón');
const regB=expr(by['Reseña · Registrar BAJA (botón)'].parameters.jsonBody);
if(JSON.parse(regB(out[0].json)).texto!=='baja') throw Error('Cuerpo botón');
const datos={first:()=>({json:{telefono:'+56900000000'}})};
out=code('Reseña · Confirmar BAJA (botón)',{'Reseña · Datos BAJA (botón)':datos},{statusCode:503,body:{}});
body=JSON.parse(out[0].json.whatsappBody); if(!/No pudimos registrar/.test(body.text.body)) throw Error('Falla botón sin aviso');
out=code('Reseña · Confirmar BAJA (botón)',{'Reseña · Datos BAJA (botón)':datos},{body:{ok:true,baja:true}});
if(!/no te enviaremos/.test(JSON.parse(out[0].json.whatsappBody).text.body)) throw Error('OK botón');
console.log('PASS baja texto y botón');
"""
        r = subprocess.run(['node', '-e', code], input=json.dumps(w), text=True, encoding='utf-8', capture_output=True)
        self.assertEqual(r.returncode, 0, r.stderr)
        self.assertIn('PASS', r.stdout)

    def test_statuses_tap_preserves_webhook_and_batches(self):
        original = {'nodes': [{'name': 'Webhook'}, {'name': 'Bot'}], 'connections': {'Webhook': {'main': [[m.edge('Bot')]]}}}
        w = m.patch_statuses(original, 'Webhook')
        self.assertEqual(w['connections']['Webhook']['main'][0][0], m.edge('Bot'))
        self.assertEqual(len(w['connections']['Webhook']['main'][0]), 2)
        code = w['nodes'][-2]['parameters']['jsCode']
        self.assertIn('estados.slice(n,n+20)', code)
        self.assertIn('/propuesta-whatsapp-estado', w['nodes'][-1]['parameters']['url'])
        self.assertEqual(len(original['nodes']), 2)
if __name__ == '__main__': unittest.main()