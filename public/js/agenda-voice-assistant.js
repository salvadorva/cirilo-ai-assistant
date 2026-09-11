// Agenda Voice Assistant
document.addEventListener('DOMContentLoaded', function () {
    const startRecordingBtn = document.getElementById('startRecordingBtn');
    const voiceStatus       = document.getElementById('voiceStatus');
    const voiceResponse     = document.getElementById('voiceResponse');
    const responseText      = document.getElementById('responseText');

    // El modal puede no estar en la página si se llama desde otro contexto
    if (!startRecordingBtn) return;

    let recognition;
    let isRecording = false;

    // ─── Reconocimiento de voz ────────────────────────────────────────────────

    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
        recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
        recognition.lang = 'es-ES';
        recognition.continuous = false;
        recognition.interimResults = false;

        recognition.onstart = () => {
            isRecording = true;
            voiceStatus.innerHTML = '<p class="text-success"><i class="fas fa-circle text-danger me-1"></i>Escuchando... habla ahora</p>';
            startRecordingBtn.classList.replace('btn-success', 'btn-danger');
            startRecordingBtn.innerHTML = '<i class="fas fa-stop"></i>';
        };

        recognition.onend = () => {
            isRecording = false;
            voiceStatus.innerHTML = '<p>Presiona el botón para hablar</p>';
            startRecordingBtn.classList.replace('btn-danger', 'btn-success');
            startRecordingBtn.innerHTML = '<i class="fas fa-microphone"></i>';
        };

        recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            processVoiceCommand(transcript);
        };

        recognition.onerror = (event) => {
            console.error('Error reconocimiento de voz:', event.error);
            voiceStatus.innerHTML = `<p class="text-danger">Error: ${event.error}. Intenta de nuevo.</p>`;
        };

        startRecordingBtn.addEventListener('click', () => {
            if (!isRecording) recognition.start();
            else recognition.stop();
        });
    } else {
        voiceStatus.innerHTML = '<p class="text-warning">Tu navegador no soporta reconocimiento de voz. Usa Chrome.</p>';
        startRecordingBtn.disabled = true;
    }

    // ─── Cola de audio (evita solapamiento) ──────────────────────────────────

    const audioQueue = {
        queue: [],
        playing: false,
        add(item) {
            this.queue.push(item);
            if (!this.playing) this.process();
        },
        process() {
            if (!this.queue.length) { this.playing = false; return; }
            this.playing = true;
            const item = this.queue.shift();
            const p = item.url ? playAudioUrl(item.url) : speakText(item.text);
            p.finally(() => setTimeout(() => this.process(), 400));
        }
    };

    function playAudioUrl(url) {
        return new Promise((resolve) => {
            const audio = new Audio(url);
            audio.oncanplay = () => audio.play().catch(resolve);
            audio.onended = resolve;
            audio.onerror = resolve;
        });
    }

    function speakText(text) {
        return new Promise((resolve) => {
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'es-ES';
            utterance.onend = resolve;
            utterance.onerror = resolve;
            window.speechSynthesis.speak(utterance);
        });
    }

    // ─── Procesamiento del comando ────────────────────────────────────────────

    function processVoiceCommand(transcript) {
        voiceStatus.innerHTML = `<p class="text-muted">Procesando: "<em>${transcript}</em>"</p>`;
        voiceResponse.style.display = 'none';

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        fetch('/agenda/process-voice', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ voice_command: transcript })
        })
        .then(r => r.json())
        .then(data => {
            if (data.voice_message) {
                responseText.textContent = data.voice_message;
                voiceResponse.style.display = 'block';
            }

            if (data.audio_url) {
                audioQueue.add({ url: data.audio_url });
            } else if (data.voice_message) {
                audioQueue.add({ text: data.voice_message });
            }

            handleIntent(data);
        })
        .catch(err => {
            console.error('Error al procesar comando:', err);
            responseText.textContent = 'Error de conexión. Intenta de nuevo.';
            voiceResponse.style.display = 'block';
        });
    }

    // ─── Manejo de intenciones ────────────────────────────────────────────────

    function handleIntent(data) {
        if (data.intent === 'create_event' && data.partial_event) {
            const p = data.partial_event;

            // Cerrar modal de voz y abrir modal de creación con datos pre-llenados
            const voiceModalEl = document.getElementById('voiceAssistantModal');
            const voiceModal = voiceModalEl ? bootstrap.Modal.getInstance(voiceModalEl) : null;

            if (voiceModal) {
                voiceModalEl.addEventListener('hidden.bs.modal', () => {
                    openCreateModalWithData({ title: p.title || '', date: p.date || '', time: p.time || '' });
                }, { once: true });
                voiceModal.hide();
            } else {
                openCreateModalWithData({ title: p.title || '', date: p.date || '', time: p.time || '' });
            }
        }
        // query_events: la respuesta ya se muestra en el área de texto del modal (voice_message)
    }

    // ─── Pre-llenar modal de creación ─────────────────────────────────────────

    function openCreateModalWithData(data) {
        const modalEl = document.getElementById('createEventModal');
        if (!modalEl) return;

        // Resetear el formulario
        document.getElementById('createEventForm')?.reset();

        const titleEl  = document.getElementById('createEventTitle');
        const dateEl   = document.getElementById('createEventStartDate');
        const timeEl   = document.getElementById('createEventStartTime');
        const allDayEl = document.getElementById('createEventAllDay');
        const timeFields = document.getElementById('createEventTimeFields');

        if (titleEl) titleEl.value = data.title;
        if (dateEl && data.date) dateEl.value = data.date;
        if (timeEl && data.time) {
            timeEl.value = data.time;
            // Si hay hora, desmarcar "todo el día" y mostrar campos de hora
            if (allDayEl) allDayEl.checked = false;
            if (timeFields) timeFields.classList.remove('d-none');
        }

        new bootstrap.Modal(modalEl).show();
    }
});
