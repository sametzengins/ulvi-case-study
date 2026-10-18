@extends('demo.layout')

@section('title', 'Demo')

@section('body')
<header class="mb-6 flex flex-wrap items-center gap-4">
    <h1 class="grow text-base font-medium">Ulvi Case Study</h1>
    <span class="muted">WebSocket: <span id="ws" class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]">not connected</span></span>
</header>

<form id="login" class="card mx-auto flex max-w-sm flex-col gap-3">
    <h2 class="font-medium">Sign in</h2>
    <input name="email" class="input" value="demo@ulvi.test">
    <input name="password" type="password" class="input" value="password">
    <button class="btn-primary">Sign in</button>
</form>

<div id="app" class="grid gap-4 lg:grid-cols-3" hidden>
    <section class="card flex flex-col gap-3 lg:col-span-2">
        <h2 class="font-medium">Chat</h2>
        <div class="flex gap-2">
            <select id="character" class="input min-w-0 grow"></select>
            <button id="start" class="btn">Start conversation</button>
        </div>
        <ul id="messages" class="flex flex-col gap-2"></ul>
        <form id="composer" class="flex flex-col gap-2" hidden>
            <input id="text" class="input" placeholder="Message">
            <div class="flex flex-wrap gap-2">
                <button class="btn-primary">Send</button>
                <button type="button" id="twice" class="btn">Send twice (same client_message_id)</button>
            </div>
        </form>
    </section>

    <div class="flex flex-col gap-4">
        <section class="card">
            <h2 class="muted">Balance</h2>
            <p class="my-2 text-2xl font-medium"><span id="balance"></span> <span class="muted text-sm">tokens</span></p>
            <p class="muted mb-3" id="who"></p>
            <button id="refresh" class="btn">Refresh</button>
        </section>

        <section class="card flex flex-col gap-2">
            <h2 class="font-medium">Buy tokens</h2>
            <div id="packs" class="flex flex-col gap-2"></div>
        </section>
    </div>

    <section class="card lg:col-span-3">
        <h2 class="mb-3 font-medium">Ledger</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="muted">
                    <tr><th class="py-1 pr-4 font-normal">#</th><th class="py-1 pr-4 font-normal">type</th><th class="py-1 pr-4 text-right font-normal">amount</th><th class="py-1 pr-4 font-normal">before → after</th><th class="py-1 font-normal">idempotency key</th></tr>
                </thead>
                <tbody id="ledger"></tbody>
            </table>
        </div>
    </section>

    <section class="card lg:col-span-3">
        <h2 class="mb-3 font-medium">Event log</h2>
        <pre id="log" class="max-h-80 overflow-auto font-mono text-xs leading-5 whitespace-pre-wrap break-all"></pre>
    </section>
</div>

<script>
const $ = (id) => document.getElementById(id);
const number = (n) => Number(n).toLocaleString('en-US');
let token = null, conversation = null, echo = null;

function log(line) {
    $('log').textContent = `${new Date().toLocaleTimeString()}  ${line}\n` + $('log').textContent;
}

async function api(method, path, body) {
    const res = await fetch('/api/v1' + path, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { Authorization: 'Bearer ' + token } : {}) },
        body: body ? JSON.stringify(body) : undefined,
    });
    const json = res.status === 204 ? null : await res.json();
    if (method !== 'GET') {
        const replayed = res.headers.get('Idempotent-Replayed') === 'true' ? ' (replayed)' : '';
        log(`${method} ${path} → ${res.status}${replayed} ${JSON.stringify(json)}`);
    }
    return { ok: res.ok, json };
}

$('login').onsubmit = async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const res = await api('POST', '/auth/login', { email: form.get('email'), password: form.get('password') });
    if (!res.ok) return;
    token = res.json.data.token;
    $('who').textContent = res.json.data.user.email;
    $('login').hidden = true;
    $('app').hidden = false;
    echo = window.connectEcho(token);
    echo.connector.pusher.connection.bind('state_change', ({ current }) => { $('ws').textContent = current; });
    loadCharacters();
    loadPacks();
    refreshWallet();
};

async function loadCharacters() {
    const res = await api('GET', '/characters');
    $('character').replaceChildren(...res.json.data.map((c) => new Option(`${c.name}: ${c.tagline}`, c.slug)));
}

async function loadPacks() {
    const res = await api('GET', '/billing/top-ups');
    $('packs').replaceChildren(...res.json.data.map((pack) => {
        const button = document.createElement('button');
        button.className = 'btn text-left';
        button.textContent = `${pack.name} · ${number(pack.tokens)} tokens · ₺${pack.price.amount / 100}`;
        button.onclick = async () => {
            const checkout = await api('POST', '/billing/top-ups/checkout', { pack: pack.key });
            if (checkout.ok) window.open(checkout.json.data.checkout_url, '_blank');
        };
        return button;
    }));
}

async function refreshWallet() {
    const [wallet, ledger] = await Promise.all([api('GET', '/billing/wallet'), api('GET', '/billing/ledger')]);
    $('balance').textContent = number(wallet.json.data.balance);
    $('ledger').replaceChildren(...ledger.json.data.map((e) => {
        const tr = document.createElement('tr');
        tr.className = 'border-t border-[#e3e3e0] dark:border-[#3E3E3A]';
        const cells = [e.id, e.type, (e.amount > 0 ? '+' : '') + number(e.amount), `${number(e.balance_before)} → ${number(e.balance_after)}`, e.idempotency_key];
        cells.forEach((value, i) => {
            const td = document.createElement('td');
            td.className = 'py-1 pr-4 ' + (i === 2 ? 'text-right ' + (e.amount < 0 ? 'text-[#f53003] dark:text-[#FF4433]' : '') : '') + (i === 4 ? 'font-mono text-xs' : '');
            td.textContent = value;
            tr.append(td);
        });
        return tr;
    }));
}
$('refresh').onclick = refreshWallet;
window.addEventListener('focus', () => token && refreshWallet());

$('start').onclick = async () => {
    const res = await api('POST', '/conversations', { character: $('character').value });
    if (!res.ok) return;
    if (conversation) echo.leave(`conversation.${conversation.id}`);
    conversation = res.json.data;
    $('messages').replaceChildren();
    $('composer').hidden = false;
    echo.private(`conversation.${conversation.id}`)
        .listen('.agent.responded', (e) => {
            log(`WS agent.responded ${JSON.stringify(e.message)}`);
            show(e.message);
            show({ id: e.message.reply_to_id, status: 'completed' });
            refreshWallet();
        })
        .listen('.agent.failed', (e) => {
            log(`WS agent.failed ${JSON.stringify(e.message)}`);
            show(e.message);
            refreshWallet();
        });
};

// The same event can arrive twice. Keying the list by message id makes that harmless.
function show(message) {
    let li = $('m' + message.id);
    if (!li) {
        if (!message.content) return;
        li = document.createElement('li');
        li.id = 'm' + message.id;
        li.dataset.role = message.role;
        const mine = message.role === 'user';
        li.className = 'max-w-[85%] rounded-lg px-3 py-2 ' + (mine ? 'self-end bg-[#fff2f2] dark:bg-[#1D0002]' : 'self-start bg-[#f5f5f3] dark:bg-[#232321]');
        li.innerHTML = '<div class="font-medium"></div><div class="content"></div><div class="status muted text-xs"></div>';
        li.querySelector('.font-medium').textContent = mine ? 'You' : conversation.character.name;
        li.querySelector('.content').textContent = message.content;
        $('messages').append(li);
    }
    if (li.dataset.role === 'user') li.querySelector('.status').textContent = message.status;
}

async function send(times) {
    const text = $('text').value.trim();
    if (!text) return;
    $('text').value = '';
    const body = { message: text, client_message_id: crypto.randomUUID() };
    const results = await Promise.all(Array.from({ length: times }, () => api('POST', `/conversations/${conversation.id}/messages`, body)));
    for (const res of results) if (res.ok) show(res.json.data);
    refreshWallet();
}
$('composer').onsubmit = (e) => { e.preventDefault(); send(1); };
$('twice').onclick = () => send(2);
</script>
@endsection
