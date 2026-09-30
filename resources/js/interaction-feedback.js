// No model/user content is sent: feedback belongs to an authenticated interaction.
export function mountFeedback(container, interactionId) {
    if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(interactionId ?? '')) return;
    const details = document.createElement('details');
    const summary = document.createElement('summary');
    summary.textContent = 'Valorar esta respuesta (opcional)';
    details.append(summary);
    const form = document.createElement('form');
    for (const [name, text] of [['useful', '¿Fue útil?'], ['task_achieved', '¿Lograste la tarea?']]) {
        const label = document.createElement('label');
        label.textContent = text + ' ';
        const select = document.createElement('select');
        select.name = name;
        select.required = true;
        for (const [value, title] of [['', 'Selecciona'], ['1', 'Sí'], ['0', 'No']]) {
            select.add(new Option(title, value));
        }
        label.append(select);
        form.append(label, document.createElement('br'));
    }
    const label = document.createElement('label');
    label.textContent = 'Correcciones necesarias ';
    const corrections = document.createElement('input');
    Object.assign(corrections, { type: 'number', name: 'corrections', min: '0', max: '100', step: '1', required: true });
    label.append(corrections);
    const button = document.createElement('button');
    button.type = 'submit';
    button.textContent = 'Guardar valoración';
    const status = document.createElement('span');
    status.setAttribute('role', 'status');
    form.append(label, document.createElement('br'), button, status);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (button.disabled || !form.reportValidity()) return;
        button.disabled = true;
        status.textContent = ' Guardando…';
        try {
            const response = await fetch(`/interactions/${interactionId}/feedback`, {
                method: 'POST', credentials: 'same-origin', headers: {
                    'Accept': 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ useful: form.elements.useful.value === '1', task_achieved: form.elements.task_achieved.value === '1', corrections: Number(corrections.value) }),
            });
            if (!response.ok) throw new Error('feedback_failed');
            status.textContent = ' Valoración guardada.';
        } catch {
            status.textContent = ' No se pudo guardar. Puedes reintentar.';
        } finally {
            button.disabled = false;
        }
    });
    details.append(form);
    container.append(details);
}

window.CiriloFeedback = { mount: mountFeedback };
