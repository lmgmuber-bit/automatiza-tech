#!/usr/bin/env python3
"""Verify mojibake fix quality."""

with open('WhatsApp_Reminder_8PM.json', 'r', encoding='utf-8') as f:
    content = f.read()

checks = [
    'reunión de seguimiento',
    'mañana',
    'Recibirás',
    'Asegúrate',
    'conexión',
    '\U0001F4C5',  # 📅
    '\U0001F4CB',  # 📋
    '\U0001F4A1',  # 💡
    '\u2705',      # ✅
    '\U0001F504',  # 🔄
    '\u274C',      # ❌
    '\u2501\u2501', # ━━
    '\u2022',      # •
]
labels = [
    'reunión de seguimiento',
    'mañana', 'Recibirás', 'Asegúrate', 'conexión',
    '📅', '📋', '💡', '✅', '🔄', '❌', '━━', '•'
]

for phrase, label in zip(checks, labels):
    count = content.count(phrase)
    status = 'OK' if count > 0 else 'MISSING'
    print(f'  {status}: "{label}" ({count}x)')
