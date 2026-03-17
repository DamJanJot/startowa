<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

$initialCwd = (string)($_GET['cwd'] ?? '');
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Hub Terminal</title>
    <style>
        * { box-sizing: border-box; }

        :root {
            --bg: #070d18;
            --bg2: #0c1528;
            --line: rgba(255, 255, 255, 0.16);
            --panel: rgba(255, 255, 255, 0.05);
            --txt: #e5e7eb;
            --muted: #94a3b8;
            --acc: #38bdf8;
            --ok: #86efac;
            --err: #fca5a5;
        }

        html, body {
            margin: 0;
            height: 100%;
            font-family: Segoe UI, Tahoma, sans-serif;
            background: radial-gradient(circle at 20% 0%, #1e293b, var(--bg) 55%);
            color: var(--txt);
        }

        .wrap {
            height: 100%;
            display: grid;
            grid-template-rows: auto auto 1fr auto;
        }

        .top {
            padding: 10px 12px;
            border-bottom: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .title {
            font-size: 18px;
            font-weight: 700;
        }

        .sub {
            color: var(--muted);
            font-size: 12px;
        }

        .bar {
            display: flex;
            gap: 8px;
            align-items: center;
            padding: 10px 12px;
            border-bottom: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.03);
            flex-wrap: wrap;
        }

        .input {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
            color: var(--txt);
            padding: 8px 10px;
            min-width: 180px;
        }

        .input.cmd {
            flex: 1;
            min-width: 260px;
            font-family: Consolas, monospace;
        }

        .btn {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
            color: var(--txt);
            padding: 8px 10px;
            cursor: pointer;
        }

        .btn:hover {
            border-color: var(--acc);
        }

        .out {
            overflow: auto;
            padding: 10px 12px;
            font: 13px/1.55 Consolas, monospace;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .line-cmd { color: #7dd3fc; }
        .line-ok { color: var(--ok); }
        .line-err { color: var(--err); }

        .foot {
            border-top: 1px solid var(--line);
            padding: 8px 12px;
            color: var(--muted);
            font-size: 12px;
            background: rgba(255, 255, 255, 0.03);
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="top">
            <div>
                <div class="title">Terminal</div>
                <div class="sub">Osobna karta tylko dla terminala</div>
            </div>
            <button id="closeBtn" class="btn">Zamknij karte</button>
        </div>

        <div class="bar">
            <input id="cwdInput" class="input" type="text" value="<?php echo htmlspecialchars($initialCwd, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Katalog roboczy (opcjonalnie)">
            <input id="cmdInput" class="input cmd" type="text" placeholder="Polecenie...">
            <button id="runBtn" class="btn">Wykonaj</button>
            <button id="clearBtn" class="btn">Wyczysc</button>
        </div>

        <div id="out" class="out"><div class="line-ok">Tryb terminala: host moze blokowac shell. Zawsze dzialaja: pwd, ls, dir, date, whoami, echo, php -v</div></div>

        <div class="foot">Skróty: Enter = wykonaj, Ctrl+L = wyczysc</div>
    </div>

    <script>
        async function apiPost(action, data = {}) {
            const fd = new FormData();
            fd.append('action', action);
            Object.entries(data).forEach(([k, v]) => fd.append(k, v));
            const res = await fetch('api.php', { method: 'POST', body: fd });
            return res.json();
        }

        function appendLine(cls, text) {
            const out = document.getElementById('out');
            const div = document.createElement('div');
            div.className = cls;
            div.textContent = text;
            out.appendChild(div);
            out.scrollTop = out.scrollHeight;
        }

        async function runCommand() {
            const cmdInput = document.getElementById('cmdInput');
            const cwdInput = document.getElementById('cwdInput');
            const cmd = cmdInput.value.trim();
            if (!cmd) return;

            appendLine('line-cmd', `$ ${cmd}`);
            cmdInput.value = '';

            const data = await apiPost('execute', {
                cmd,
                cwd: cwdInput.value.trim(),
            });

            if (data.error) {
                appendLine('line-err', `[blad] ${data.error}`);
                return;
            }

            appendLine('line-ok', String(data.output || ''));
        }

        document.getElementById('runBtn').addEventListener('click', runCommand);
        document.getElementById('clearBtn').addEventListener('click', () => {
            document.getElementById('out').innerHTML = '';
        });
        document.getElementById('cmdInput').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                runCommand();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && (e.key === 'l' || e.key === 'L')) {
                e.preventDefault();
                document.getElementById('out').innerHTML = '';
            }
        });

        document.getElementById('closeBtn').addEventListener('click', () => window.close());
        document.getElementById('cmdInput').focus();
    </script>
</body>
</html>
