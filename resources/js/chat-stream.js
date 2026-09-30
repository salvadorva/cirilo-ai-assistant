// F5-03/F5-05: chat con streaming (Server-Sent Events sobre fetch) y cancelación.
// send() devuelve lo mismo que devolvía axios (response.data), así el render existente no cambia.

/** Separa bloques SSE completos; devuelve los eventos y el resto sin terminar. */
export function parseSse(buffer) {
    const events = [];
    const normalized = buffer.replace(/\r\n/g, '\n');
    const blocks = normalized.split('\n\n');
    const rest = blocks.pop();
    for (const block of blocks) {
        let event = 'message';
        const data = [];
        for (const line of block.split('\n')) {
            if (line.startsWith('event: ')) event = line.slice(7).trim();
            else if (line.startsWith('data: ')) data.push(line.slice(6));
        }
        if (!data.length) continue;
        try {
            events.push({ event, data: JSON.parse(data.join('\n')) });
        } catch {
            // Bloque corrupto: se ignora; el evento `done` trae el contrato completo.
        }
    }
    return { events, rest };
}

/** Clave por mensaje: si se reintenta el mismo mensaje, el servidor reutiliza lo ya guardado (F2-05). */
export function newIdempotencyKey() {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
    return 'k-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
}

/**
 * Envía el mensaje y resuelve con el JSON final. handlers: onDelta(textoAcumulado), onAgenda(contrato), onStatus(etapa).
 * Con options.signal (AbortController) se puede detener; en ese caso rechaza con { aborted: true, agenda }.
 */
export async function send(payload, handlers = {}, options = {}) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const response = await fetch('/generate-text', {
        method: 'POST',
        credentials: 'same-origin',
        signal: options.signal,
        headers: {
            'Accept': 'text/event-stream',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'Idempotency-Key': options.idempotencyKey ?? newIdempotencyKey(),
        },
        body: JSON.stringify(payload),
    });

    const type = response.headers.get('Content-Type') || '';
    if (!type.includes('text/event-stream')) {
        // Grok o un error de validación/límite: respuesta JSON clásica.
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw Object.assign(new Error(data.message || 'Error en la solicitud'), { status: response.status, data });
        return data;
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let text = '';
    let agenda = null;
    try {
        for (;;) {
            const { value, done } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            const parsed = parseSse(buffer);
            buffer = parsed.rest;
            for (const { event, data } of parsed.events) {
                if (event === 'delta') {
                    text += data.text ?? '';
                    handlers.onDelta?.(text);
                } else if (event === 'agenda') {
                    agenda = data;
                    handlers.onAgenda?.(data);
                } else if (event === 'status') {
                    handlers.onStatus?.(data.stage);
                } else if (event === 'done') {
                    return data;
                } else if (event === 'error') {
                    throw Object.assign(new Error(data.message || 'Error al generar la respuesta'), { agenda });
                }
            }
        }
    } catch (error) {
        if (error?.name === 'AbortError') throw { aborted: true, agenda };
        throw error;
    }
    throw Object.assign(new Error('La respuesta terminó sin completarse'), { agenda });
}

if (typeof window !== 'undefined') {
    window.CiriloChat = { send, parseSse, newIdempotencyKey };
}
