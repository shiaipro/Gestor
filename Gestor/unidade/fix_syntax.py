import os
import re
import glob

# Regex to match the missing semicolon after var(--fs-something)
# We capture the specific variable name and what comes immediately after (a space or quote)
regex = re.compile(r'(font-size:\s*var\(--fs-[a-z]+\))([\s"])')

files = glob.glob('/Users/RMRX/Documents/AGENCIARMRZ/Alunos/SHIAIPRO/unidade/*.php')
changed_files = 0

for file in files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    new_content, count = regex.subn(r'\1;\2', content)
    
    if count > 0:
        with open(file, 'w', encoding='utf-8') as f:
            f.write(new_content)
        changed_files += 1

print(f"Fixed CSS syntax in {changed_files} PHP files.")
