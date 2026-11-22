<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MCP Console · Adire Lane</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0b1430; --panel: #111c3d; --panel-2: #16234a; --line: #24335f;
            --text: #e9edf6; --muted: #9aa6c4; --gold: #d9b45a; --green: #6fcfb3;
            --green-bg: rgba(47, 143, 117, .18); --red: #ef7d8c; --red-bg: rgba(194, 59, 78, .2);
            --blue: #8fb3ff; --radius: 14px;
            --mono: "JetBrains Mono", "DejaVu Sans Mono", ui-monospace, monospace;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body { background: var(--bg); color: var(--text); font: 14px/1.5 Inter, system-ui, sans-serif; }
        button, input { font: inherit; color: inherit; }

        .top { display: flex; align-items: center; gap: 14px; padding: 14px 22px; border-bottom: 1px solid var(--line); flex-wrap: wrap; }
        .logo { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: linear-gradient(135deg, #1f3a8a, #2f8f75); font-weight: 700; color: #fff; }
        .brand b { display: block; font-size: 15px; }
        .brand span { color: var(--muted); font-size: 12px; }
        .chips { display: flex; gap: 8px; flex-wrap: wrap; margin-left: auto; }
        .chip { font-family: var(--mono); font-size: 11.5px; padding: 4px 10px; border-radius: 999px; border: 1px solid var(--line); color: var(--muted); background: var(--panel); white-space: nowrap; }
        .chip.gold { color: var(--gold); border-color: rgba(217, 180, 90, .45); }
        .chip.green { color: var(--green); border-color: rgba(111, 207, 179, .4); }

        .demo { background: rgba(217, 180, 90, .12); border-bottom: 1px solid rgba(217, 180, 90, .3); color: var(--gold); padding: 7px 22px; font-size: 13px; }

        .grid { display: grid; grid-template-columns: 290px minmax(0, 1fr) 380px; gap: 16px; padding: 16px 22px; height: calc(100vh - 100px); min-height: 560px; }
        .card { background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius); display: flex; flex-direction: column; min-height: 0; }
        .card h2 { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); margin: 0; padding: 14px 16px 8px; display: flex; justify-content: space-between; align-items: center; }
        .scroll { overflow-y: auto; min-height: 0; flex: 1; }

        .cap { padding: 0 12px 12px; }
        .item { padding: 9px 10px; border-radius: 10px; background: var(--panel-2); margin-bottom: 7px; }
        .item .name { font-family: var(--mono); font-size: 12.5px; display: flex; justify-content: space-between; gap: 8px; align-items: center; }
        .item p { margin: 3px 0 0; color: var(--muted); font-size: 12px; line-height: 1.4; }
        .badge { font-family: Inter, sans-serif; font-size: 10.5px; padding: 1px 7px; border-radius: 6px; white-space: nowrap; }
        .badge.read { background: var(--green-bg); color: var(--green); }
        .badge.write { background: var(--red-bg); color: var(--red); }
        .sub { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .08em; margin: 12px 4px 6px; }

        .chat { padding: 4px 18px 10px; display: flex; flex-direction: column; gap: 12px; }
        .empty { margin: auto; text-align: center; color: var(--muted); max-width: 460px; padding: 30px 0; }
        .empty h3 { color: var(--text); font-size: 20px; margin: 0 0 6px; }
        .msg { max-width: 86%; padding: 10px 14px; border-radius: 14px; white-space: pre-wrap; word-wrap: break-word; }
        .msg.user { align-self: flex-end; background: #2a4bb0; border-bottom-right-radius: 4px; }
        .msg.bot { align-self: flex-start; background: var(--panel-2); border: 1px solid var(--line); border-bottom-left-radius: 4px; }
        .msg.bot b { color: var(--gold); }
        .tool { align-self: flex-start; max-width: 86%; font-family: var(--mono); font-size: 12px; border: 1px dashed var(--line); border-radius: 10px; padding: 8px 12px; background: rgba(22, 35, 74, .6); }
        .tool .head { color: var(--blue); }
        .tool .head .ok { color: var(--green); }
        .tool .head .err { color: var(--red); }
        .tool .res { color: var(--muted); margin-top: 3px; white-space: pre-wrap; word-break: break-word; }
        .typing { align-self: flex-start; color: var(--muted); font-size: 13px; }
        .typing i { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--muted); margin-right: 3px; animation: blink 1s infinite; }
        .typing i:nth-child(2) { animation-delay: .2s; } .typing i:nth-child(3) { animation-delay: .4s; }
        @keyframes blink { 50% { opacity: .2; } }

        .suggest { display: flex; gap: 8px; flex-wrap: wrap; padding: 0 16px 10px; }
        .suggest button { background: transparent; border: 1px solid var(--line); border-radius: 999px; padding: 5px 12px; font-size: 12.5px; color: var(--muted); cursor: pointer; }
        .suggest button:hover { color: var(--text); border-color: var(--gold); }
        form.ask { display: flex; gap: 8px; padding: 12px 16px 16px; border-top: 1px solid var(--line); }
        form.ask input { flex: 1; min-width: 0; background: var(--bg); border: 1px solid var(--line); border-radius: 10px; padding: 11px 14px; outline: none; }
        form.ask input:focus { border-color: var(--gold); }
        form.ask button { background: var(--gold); color: #1a1405; border: 0; border-radius: 10px; padding: 0 18px; font-weight: 600; cursor: pointer; }
        form.ask button:disabled { opacity: .5; cursor: default; }

        .log { padding: 0 12px 12px; font-family: var(--mono); font-size: 11.5px; }
        .rpc { border-radius: 10px; background: var(--panel-2); margin-bottom: 7px; padding: 7px 10px; border-left: 3px solid var(--blue); }
        .rpc.in { border-left-color: var(--green); }
        .rpc.bad { border-left-color: var(--red); }
        .rpc .meta { display: flex; justify-content: space-between; color: var(--muted); gap: 8px; }
        .rpc .meta b { color: var(--text); font-weight: 500; }
        .rpc pre { margin: 4px 0 0; white-space: pre-wrap; word-break: break-all; color: #c8d1ea; max-height: 96px; overflow: hidden; }
        .count { font-family: var(--mono); color: var(--gold); text-transform: none; letter-spacing: 0; }

        @media (max-width: 1180px) {
            .grid { grid-template-columns: 260px minmax(0, 1fr); height: auto; }
            .grid .traffic { grid-column: 1 / -1; max-height: 420px; }
            .grid .center { height: 640px; }
        }
        @media (max-width: 760px) {
            .top { padding: 12px 16px; }
            .chips { margin-left: 0; }
            .demo { padding: 7px 16px; }
            .grid { grid-template-columns: 1fr; padding: 12px 16px; gap: 12px; }
            .grid .center { order: -1; height: 600px; }
            .grid .caps { max-height: 340px; }
            .msg, .tool { max-width: 94%; }
            .chat { padding: 4px 12px 10px; }
            .suggest { padding: 0 12px 10px; }
            form.ask { padding: 10px 12px 12px; }
        }
    </style>
</head>
<body>
<header class="top">
    <div class="logo">AL</div>
    <div class="brand"><b>Adire Lane · MCP server</b><span>Laravel app data and actions, exposed over the Model Context Protocol</span></div>
    <div class="chips">
        <span class="chip gold">MCP {{ $server['protocolVersion'] }}</span>
        <span class="chip">POST /mcp</span>
        <span class="chip">php artisan mcp:serve</span>
        <span class="chip green">{{ $demo ? 'demo mode' : $provider.' · '.$model }}</span>
    </div>
</header>
@if ($demo)
    <div class="demo">Demo mode: scripted AI replies. The tool calls and data are real; set a provider key in .env for a live model.</div>
@endif

<main class="grid">
    <section class="card caps">
        <h2>Server capabilities <span class="count">{{ count($tools) }} tools</span></h2>
        <div class="scroll cap">
            @foreach ($tools as $tool)
                <div class="item">
                    <div class="name">{{ $tool['name'] }}
                        @if ($tool['annotations']['readOnlyHint'])
                            <span class="badge read">read-only</span>
                        @else
                            <span class="badge write">writes data</span>
                        @endif
                    </div>
                    <p>{{ $tool['description'] }}</p>
                </div>
            @endforeach
            @unless ($allowWrites)
                <p class="sub" style="text-transform:none;letter-spacing:0">Write tools hidden (MCP_ALLOW_WRITES=false)</p>
            @endunless
            <div class="sub">Resources</div>
            @foreach ($resources as $resource)
                <div class="item"><div class="name">{{ $resource['uri'] }}</div><p>{{ $resource['description'] }}</p></div>
            @endforeach
            <div class="sub">Prompts</div>
            @foreach ($prompts as $prompt)
                <div class="item"><div class="name">{{ $prompt['name'] }}</div><p>{{ $prompt['description'] }}</p></div>
            @endforeach
        </div>
    </section>

    <section class="card center">
        <h2>Agent chat <span class="count">MCP client → this server</span></h2>
        <div class="scroll chat" id="chat">
            <div class="empty" id="empty">
                <h3>Ask the shop anything</h3>
                The agent discovers this server's tools over MCP, then calls them to answer. Watch the JSON-RPC traffic on the right.
            </div>
        </div>
        <div class="suggest" id="suggest">
            @foreach ($suggestions as $suggestion)
                <button type="button">{{ $suggestion }}</button>
            @endforeach
        </div>
        <form class="ask" id="ask">
            <input id="input" name="message" autocomplete="off" maxlength="500" placeholder="e.g. What's the status of order AL-1042?">
            <button id="send">Send</button>
        </form>
    </section>

    <section class="card traffic">
        <h2>JSON-RPC traffic <span class="count" id="count">0 messages</span></h2>
        <div class="scroll log" id="log"></div>
    </section>
</main>

<script>
    const $ = (id) => document.getElementById(id);
    const chat = $('chat'), log = $('log'), input = $('input'), send = $('send');
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const md = (s) => esc(s).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>');
    let total = 0;

    function add(cls, html) {
        $('empty')?.remove();
        const el = document.createElement('div');
        el.className = cls;
        el.innerHTML = html;
        chat.appendChild(el);
        chat.scrollTop = chat.scrollHeight;
        return el;
    }

    function logMessage({ direction, message }) {
        const fromClient = direction.startsWith('client');
        const label = message.method ?? (message.error ? 'error' : 'result');
        const el = document.createElement('div');
        el.className = 'rpc ' + (fromClient ? 'out' : 'in') + (message.error || message.result?.isError ? ' bad' : '');
        el.innerHTML = `<div class="meta"><span>${fromClient ? '→' : '←'} <b>${esc(label)}</b></span><span>${message.id !== undefined ? 'id ' + esc(message.id) : 'notification'}</span></div>`
            + `<pre>${esc(JSON.stringify(message.params ?? message.result ?? message.error ?? {}))}</pre>`;
        log.appendChild(el);
        log.scrollTop = log.scrollHeight;
        $('count').textContent = `${++total} messages`;
    }

    async function ask(text) {
        if (!text.trim()) return;
        input.value = '';
        send.disabled = true;
        add('msg user', esc(text));
        const typing = add('typing', '<i></i><i></i><i></i> agent is working');

        try {
            const res = await fetch('{{ route('console.ask') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ message: text }),
            });
            const data = await res.json();
            typing.remove();
            if (!res.ok) { add('msg bot', esc(data.error ?? data.message ?? 'Request failed')); return; }

            // Replay the run in order: handshake, then each tool call with its traffic.
            const traffic = [...data.traffic];
            const pull = (n) => traffic.splice(0, n).forEach(logMessage);
            pull(5); // initialize, its reply, notifications/initialized, tools/list and its reply
            for (const step of data.steps) {
                await sleep(450);
                pull(2);
                const args = JSON.stringify(step.arguments);
                const result = step.result.length > 170 ? step.result.slice(0, 170) + '…' : step.result;
                add('tool', `<div class="head">🔧 ${esc(step.tool)}(${esc(args)}) <span class="${step.is_error ? 'err' : 'ok'}">${step.is_error ? '✗ error' : '✓'}</span></div><div class="res">${esc(result)}</div>`);
            }
            pull(traffic.length);
            await sleep(350);
            add('msg bot', md(data.answer));
        } catch (e) {
            typing.remove();
            add('msg bot', 'Network error: ' + esc(e.message));
        } finally {
            send.disabled = false;
            input.focus();
        }
    }

    $('ask').addEventListener('submit', (e) => { e.preventDefault(); ask(input.value); });
    document.querySelectorAll('#suggest button').forEach((b) => b.addEventListener('click', () => ask(b.textContent.trim())));
</script>
</body>
</html>
