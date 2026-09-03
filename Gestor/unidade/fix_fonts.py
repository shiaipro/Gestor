import os
import re
import glob

# Mapping old px absolute sizes to modern fluid variables
mapping = {
    '8px': 'var(--fs-xs)',
    '9px': 'var(--fs-xs)',
    '10px': 'var(--fs-xs)',
    '11px': 'var(--fs-xs)',
    '12px': 'var(--fs-sm)',
    '13px': 'var(--fs-sm)',
    '14px': 'var(--fs-base)',
    '15px': 'var(--fs-base)',
    '16px': 'var(--fs-base)'
}

def replace_fonts(match):
    px_val = match.group(1).lower()
    if px_val in mapping:
        return f"font-size: {mapping[px_val]}"
    return match.group(0)

# Regex to catch font-size: 11px; or font-size:11px
regex = re.compile(r'font-size:\s*(\d+px);?', re.IGNORECASE)

files = glob.glob('/Users/RMRX/Documents/AGENCIARMRZ/Alunos/SHIAIPRO/unidade/*.php')
changed_files = 0

for file in files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    new_content, count = regex.subn(replace_fonts, content)
    
    if count > 0:
        with open(file, 'w', encoding='utf-8') as f:
            f.write(new_content)
        changed_files += 1

print(f"Fixed fonts in {changed_files} PHP files.")
