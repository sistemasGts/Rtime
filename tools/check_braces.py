from pathlib import Path
text = Path(r'c:\xampp\htdocs\Rtime\proceso\procesar_calculo_local.php').read_text(encoding='utf-8')
stack = []
line = 1
in_string = None
escape = False
i = 0
while i < len(text):
    ch = text[i]
    if in_string:
        if escape:
            escape = False
        elif ch == '\\':
            escape = True
        elif ch == in_string:
            in_string = None
    else:
        if ch in ('"', "'"):
            in_string = ch
        elif ch == '#':
            while i < len(text) and text[i] != '\n':
                i += 1
            if i < len(text):
                line += 1
                i += 1
            continue
        elif ch == '/' and i + 1 < len(text) and text[i + 1] == '/':
            while i < len(text) and text[i] != '\n':
                i += 1
        elif ch == '/' and i + 1 < len(text) and text[i + 1] == '*':
            i += 2
            while i + 1 < len(text) and not (text[i] == '*' and text[i + 1] == '/'):
                if text[i] == '\n':
                    line += 1
                i += 1
            i += 2
            continue
        elif ch == '{':
            stack.append(line)
        elif ch == '}':
            if not stack:
                print('extra close at line', line)
                raise SystemExit
            stack.pop()
    if ch == '\n':
        line += 1
    i += 1
print('remaining opens', stack[-20:])
