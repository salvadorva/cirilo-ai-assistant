import { test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';

// El módulo se empaqueta como ESM y se importa desde memoria (el paquete no es "type": "module").
const bundle = await build({ entryPoints: ['resources/js/chat-stream.js'], bundle: true, write: false, format: 'esm' });
const { parseSse, send } = await import('data:text/javascript;base64,' + Buffer.from(bundle.outputFiles[0].text).toString('base64'));

test('parseSse keeps incomplete blocks for the next chunk', () => {
    const first = parseSse('event: delta\ndata: {"text":"Ho"}\n\nevent: delta\ndata: {"te');
    assert.deepEqual(first.events, [{ event: 'delta', data: { text: 'Ho' } }]);
    const second = parseSse(first.rest + 'xt":"la"}\r\n\r\n');
    assert.deepEqual(second.events, [{ event: 'delta', data: { text: 'la' } }]);
    assert.equal(second.rest, '');
});

function streamResponse(chunks) {
    const encoder = new TextEncoder();
    return {
        ok: true, status: 200,
        headers: { get: () => 'text/event-stream; charset=UTF-8' },
        body: new ReadableStream({ start(controller) { chunks.forEach(c => controller.enqueue(encoder.encode(c))); controller.close(); } }),
    };
}

globalThis.document = { querySelector: () => ({ getAttribute: () => 'csrf' }) };

test('send accumulates deltas, reports saved actions and resolves with the final contract', async () => {
    let request;
    globalThis.fetch = async (url, options) => { request = options; return streamResponse([
        'event: delta\ndata: {"text":"Hola, "}\n\n', 'event: agenda\ndata: {"status":"created"}\n\nevent: delta\ndata: {"text":"Salva"}\n\n',
        'event: done\ndata: {"choices":[{"message":{"content":"Hola, Salva"}}],"agenda":{"status":"created"}}\n\n']); };
    const previews = [];
    let agenda;
    const data = await send({ prompt: 'Hola', generateAudio: false }, { onDelta: t => previews.push(t), onAgenda: a => { agenda = a; } }, { idempotencyKey: 'k-1' });

    assert.deepEqual(previews, ['Hola, ', 'Hola, Salva']);
    assert.equal(agenda.status, 'created');
    assert.equal(data.choices[0].message.content, 'Hola, Salva');
    assert.equal(request.headers.Accept, 'text/event-stream');
    assert.equal(request.headers['Idempotency-Key'], 'k-1');
    assert.equal(JSON.parse(request.body).generateAudio, false);
});

test('an error event rejects without losing a saved action', async () => {
    globalThis.fetch = async () => streamResponse(['event: agenda\ndata: {"status":"created"}\n\n', 'event: error\ndata: {"message":"Falló"}\n\n']);
    await assert.rejects(send({ prompt: 'x' }), error => error.message === 'Falló' && error.agenda.status === 'created');
});

test('JSON responses (Grok, validation) are passed through', async () => {
    globalThis.fetch = async () => ({ ok: true, status: 200, headers: { get: () => 'application/json' }, json: async () => ({ choices: [{ message: { content: 'Hola' } }] }) });
    assert.equal((await send({ prompt: 'x' })).choices[0].message.content, 'Hola');
});
