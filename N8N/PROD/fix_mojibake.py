#!/usr/bin/env python3
"""
Fix mojibake (GBK-decoded UTF-8) in N8N workflow JSON files.
The files had UTF-8 content processed through a GBK decoder, creating CJK garbage characters.
"""
import os
import json

# Files to fix
FILES = [
    'Email_Reminder_8PM.json',
    'Followup_Meeting_Scheduler_v3.json',
    'Followup_Reschedule_Handler.json',
    'Tech_Calendar_Subworkflow.json',
    'WhatsApp_Reminder_8PM.json',
]

# Replacement mapping: garbled → correct
# Order matters: multi-char patterns first, then single-char
REPLACEMENTS = [
    # Separator line (10 repetitions of ━━)
    ('鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣鈹佲攣', '━━━━━━━━━━━━━━━━━━━━'),
    # Individual separator unit
    ('鈹佲攣', '━━'),

    # 4-byte emoji (2 CJK chars each) - GBK(F0 xx) + GBK(yy zz)
    ('馃搮', '📅'),  # calendar
    ('馃搯', '📆'),  # calendar with date
    ('馃搵', '📋'),  # clipboard
    ('馃搶', '📌'),  # pushpin
    ('馃搷', '📍'),  # location pin
    ('馃晲', '🕐'),  # clock
    ('馃挕', '💡'),  # lightbulb
    ('馃挰', '💬'),  # speech bubble
    ('馃攧', '🔄'),  # refresh/reschedule
    ('馃殌', '🚀'),  # rocket
    ('馃捇', '💻'),  # laptop
    ('馃敆', '🔗'),  # link
    ('馃摟', '📧'),  # email
    ('馃摫', '📱'),  # phone
    ('馃摴', '📹'),  # video
    ('馃寪', '🌐'),  # globe

    # 3-byte sequences (1 CJK char + orphan byte → ?)
    ('鉁?', '✅'),  # check mark (E2 9C 85)
    ('鉂?', '❌'),  # cross mark (E2 9D 8C)
    ('鈴?', '⏰'),  # alarm clock (E2 8F B0)
    ('鈥?', '•'),   # bullet (E2 80 A2)

    # Spanish accented characters (2-byte UTF-8 → GBK pair)
    ('贸', 'ó'),
    ('谩', 'á'),
    ('铆', 'í'),
    ('茅', 'é'),
    ('煤', 'ú'),
    ('帽', 'ñ'),
    ('脡', 'É'),
    ('脫', 'Ó'),
    ('脷', 'Ú'),
    ('隆', '¡'),
    ('漏', '©'),
    ('驴', '¿'),
]


def fix_file(filepath):
    """Fix mojibake in a single file."""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original = content
    for garbled, correct in REPLACEMENTS:
        content = content.replace(garbled, correct)

    if content != original:
        # Validate JSON before saving
        try:
            json.loads(content)
        except json.JSONDecodeError as e:
            print(f"  WARNING: JSON validation failed after fix: {e}")
            print(f"  File NOT saved: {filepath}")
            return False

        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        
        # Count changes
        changes = sum(
            original.count(garbled) 
            for garbled, _ in REPLACEMENTS 
            if garbled in original
        )
        print(f"  FIXED: {filepath} ({changes} replacements)")
        return True
    else:
        print(f"  CLEAN: {filepath} (no changes needed)")
        return False


def main():
    script_dir = os.path.dirname(os.path.abspath(__file__))
    total_fixed = 0
    
    for filename in FILES:
        filepath = os.path.join(script_dir, filename)
        if not os.path.exists(filepath):
            print(f"  SKIP: {filename} (not found)")
            continue
        if fix_file(filepath):
            total_fixed += 1
    
    print(f"\nDone: {total_fixed}/{len(FILES)} files fixed.")


if __name__ == '__main__':
    main()
