import json

# Leer el archivo
with open(r'c:\wamp64\www\automatiza-tech\N8N\PROD\Tech_Calendar_Subworkflow.json', 'r', encoding='utf-8') as f:
    content = f.read()

# Correcciones para caracteres corruptos (UTF-8 doble encoding)
replacements = {
    'Ú³': 'ó',
    'Ú¡': 'á',
    'Ú©': 'é',
    'Ú­': 'í',
    'Úº': 'ú',
    'Ú±': 'ñ',
    'Úš': 'Ú',
    'Â©': '©',
}

count = 0
for old, new in replacements.items():
    if old in content:
        occurrences = content.count(old)
        print(f'Reemplazando: "{old}" -> "{new}" ({occurrences} veces)')
        content = content.replace(old, new)
        count += occurrences

print(f'\nTotal reemplazos: {count}')

# Guardar
with open(r'c:\wamp64\www\automatiza-tech\N8N\PROD\Tech_Calendar_Subworkflow.json', 'w', encoding='utf-8') as f:
    f.write(content)

# Verificar JSON
try:
    json.loads(content)
    print('✅ JSON válido')
except json.JSONDecodeError as e:
    print(f'❌ Error JSON: {e}')
