# Draws the Barons ANSI logo art (60 x 30 cells) into tools/logo-art.html; then run tools/logo-to-svg.py.
# Usage: python tools/make-logo.py [out.html]
import html, sys
W, H = 60, 30
grid = [[(' ', '') for _ in range(W)] for _ in range(H)]
def put(r, c, s, cls, blank=False):
    for i, ch in enumerate(s):
        if (ch != ' ' or blank) and 0 <= c + i < W: grid[r][c + i] = (ch, cls if ch != ' ' else '')
def put_map(r, c, rows, cmap, key):
    for dr, line in enumerate(rows):
        for dc, ch in enumerate(line):
            if ch == ' ': continue
            grid[r + dr][c + dc] = (ch, key.get(cmap[dr][dc], ''))

FONT = {
 'I': ['█████','  █  ','  █  ','  █  ','█████'],
 'M': ['█   █','██ ██','█ █ █','█   █','█   █'],
 'P': ['████ ','█   █','████ ','█    ','█    '],
 'E': ['█████','█    ','████ ','█    ','█████'],
 'R': ['████ ','█   █','████ ','█  █ ','█   █'],
 'A': [' ███ ','█   █','█████','█   █','█   █'],
 'L': ['█    ','█    ','█    ','█    ','█████'],
 'B': ['████ ','█   █','████ ','█   █','████ '],
 'O': [' ███ ','█   █','█   █','█   █',' ███ '],
 'N': ['█   █','██  █','█ █ █','█  ██','█   █'],
 'S': [' ████','█    ',' ███ ','    █','████ '],
}
def word(r, text, cls, gap=2):
    w = len(text) * 5 + (len(text) - 1) * gap
    c0 = (W - w) // 2
    solid = set()
    for i, ch in enumerate(text):
        for dr, line in enumerate(FONT[ch]):
            for dc, px in enumerate(line):
                if px == '█':
                    solid.add((r + dr, c0 + i * (5 + gap) + dc))
    for (y, x) in solid:                      # drop shadow, down-right
        if (y + 1, x + 1) not in solid and grid[y + 1][x + 1][0] == ' ':
            grid[y + 1][x + 1] = ('░', 'ib-shd')
    for (y, x) in solid: grid[y][x] = ('█', cls)

# Frame
put(0, 0, '╔' + '═' * 58 + '╗', 'ib-brd'); put(H - 1, 0, '╚' + '═' * 58 + '╝', 'ib-brd')
for r in range(1, H - 1): put(r, 0, '║', 'ib-brd'); put(r, W - 1, '║', 'ib-brd')
ramp = ('░▒▓█▓▒░░▒▓█▓▒░' * 6)[:58]
put(1, 1, ramp, 'ib-ramp'); put(H - 2, 1, ramp[::-1], 'ib-ramp')

# Starfield
for r, c, ch in [(2,6,'·'),(2,19,'+'),(2,33,'·'),(2,47,'*'),(2,54,'·'),(9,4,'*'),(9,15,'·'),(9,29,'+'),(9,42,'·'),(9,55,'*'),
                 (16,8,'·'),(16,24,'*'),(16,38,'·'),(16,51,'+'),(19,20,'·'),(19,38,'*'),(25,4,'*'),(25,55,'·'),(20,57,'*'),(26,2,'·'),(27,30,'·')]:
    put(r, c, ch, 'ib-star')

word(3, 'IMPERIAL', 'ib-mid', gap=1)     # 8*5+7 = 47 wide
word(10, 'BARONS', 'ib-gold', gap=2)     # 6*5+10 = 40 wide
put(17, 1, '═' * 58, 'ib-brd'); put(17, 0, '╠', 'ib-brd'); put(17, W - 1, '╣', 'ib-brd')
put(17, 22, '  O N L I N E  ', 'ib-red', blank=True)

# Scene: a ringed world, the Crown, and a trader running for port.
planet = ['    ▄▄███▄▄    ',
          '  ▄█▓▓▒▒░░▀█▄  ',
          '══█▓▓▓▒▒░░░░█══',
          '  ▀█▓▓▒▒░░▄█▀  ',
          '    ▀▀███▀▀    ']
pmap   = ['    ppppppp    ',
          '  ppppppppppp  ',
          'rrppppppppppprr',
          '  ppppppppppp  ',
          '    ppppppp    ']
put_map(20, 3, planet, pmap, {'p': 'ib-planet', 'r': 'ib-ring'})

crown = ['▲   ▲   ▲',
         '█▄ ▄█▄ ▄█',
         '█▓█████▓█',
         '█▀▀▀▀▀▀▀█']
cmap  = ['g   j   g',
         'ggggggggg',
         'gjgggggjg',
         'ggggggggg']
put_map(20, 25, crown, cmap, {'g': 'ib-gold', 'j': 'ib-gem'})
put(24, 24, '▀▀▀▀▀▀▀▀▀▀▀', 'ib-gold')

ship = ['    ▄▄▄▄     ',
        '≡≡▄█▓▓███▄▄▄▶',
        '   ▀▀▀▀▀▀    ']
smap = ['    ssss     ',
        'eessssssssssn',
        '   ssssss    ']
put_map(21, 42, ship, smap, {'s': 'ib-hull', 'e': 'ib-eng', 'n': 'ib-gem'})
put(22, 37, '· · ', 'ib-eng')

tag = [('T R A D E', 'ib-w1'), ('S E T T L E', 'ib-w2'), ('C O N Q U E R', 'ib-w3'), ('R U L E', 'ib-w4')]
text = '   '.join(t for t, _ in tag); c = (W - len(text)) // 2
for t, cls in tag:
    put(26, c, t, cls); c += len(t) + 3

# Emit
out = ['<!-- The ANSI block art behind assets/img/ib-logo.svg. Edit it (or re-run tools/make-logo.py), then run tools/logo-to-svg.py. -->',
       '<div class="ib-logo"><pre>']
lines = []
for row in grid:
    s, cur, buf = '', None, ''
    def flush():
        global s
    parts = []
    run_cls, run = None, ''
    for ch, cls in row:
        if cls != run_cls:
            if run: parts.append((run_cls, run))
            run_cls, run = cls, ''
        run += ch
    if run: parts.append((run_cls, run))
    lines.append(''.join(html.escape(t, quote=False) if not k else '<i class="%s">%s</i>' % (k, html.escape(t, quote=False)) for k, t in parts))
out[-1] += '\n'.join(lines) + '</pre></div>'
open(sys.argv[1] if len(sys.argv) > 1 else __import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)), 'logo-art.html'), 'w', encoding='utf-8', newline='\n').write('\n'.join(out) + '\n')
for row in grid: print(''.join(ch for ch, _ in row))
