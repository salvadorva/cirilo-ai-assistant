@extends('../layout.app')
@section('title', 'Conversar con Cirilo')

@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    /* ── Layout principal — ocupa toda la pantalla disponible ── */
    .conversar-wrapper {
        display: flex;
        flex-direction: column;
        height: calc(100dvh - 56px); /* descontar la navbar */
        overflow: hidden;
        background: #f8f9fa;
    }

    /* ── Cabecera mínima ── */
    .conv-header {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.6rem 1rem;
        background: linear-gradient(135deg, #4e54c8 0%, #8f94fb 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .conv-header a {
        color: rgba(255,255,255,0.85);
        text-decoration: none;
        font-size: 0.9rem;
    }
    .conv-header h5 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
    }

    /* ── Área de Cirilo ── */
    .cirilo-area {
        flex: 0 0 auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1rem 1rem 0.5rem;
    }
    .cirilo-video-wrap {
        position: relative;
        width: min(140px, 35vw);
        height: min(140px, 35vw);
    }
    .cirilo-video, .cirilo-fallback, .cirilo-listening-img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        transition: filter 0.4s ease, transform 0.3s ease;
    }
    .cirilo-listening-img {
        position: absolute;
        inset: 0;
        display: none;
        animation: breathe 2.4s ease-in-out infinite;
    }
    /* Cuando está escuchando, ocultar el video y mostrar la imagen */
    .cirilo-listening .cirilo-video { display: none; }
    .cirilo-listening .cirilo-listening-img { display: block; }
    @keyframes breathe {
        0%, 100% { transform: scale(1); }
        50%       { transform: scale(1.04); }
    }
    /* Halo rojo = escuchando (aplica también a la imagen de listening) */
    .cirilo-listening .cirilo-video,
    .cirilo-listening .cirilo-fallback,
    .cirilo-listening .cirilo-listening-img {
        filter: drop-shadow(0 0 14px rgba(231, 76, 60, 0.9));
    }
    /* Halo azul = hablando */
    .cirilo-speaking .cirilo-video,
    .cirilo-speaking .cirilo-fallback {
        filter: drop-shadow(0 0 14px rgba(74, 144, 226, 0.9));
        animation: pulseBlue 1s ease-in-out infinite;
    }
    /* Halo amarillo = pensando */
    .cirilo-thinking .cirilo-video,
    .cirilo-thinking .cirilo-fallback {
        filter: drop-shadow(0 0 10px rgba(243, 156, 18, 0.8));
    }
    @keyframes pulseRed {
        0%, 100% { filter: drop-shadow(0 0 8px rgba(231,76,60,0.6)); }
        50%       { filter: drop-shadow(0 0 20px rgba(231,76,60,1)); }
    }
    @keyframes pulseBlue {
        0%, 100% { filter: drop-shadow(0 0 8px rgba(74,144,226,0.6)); }
        50%       { filter: drop-shadow(0 0 20px rgba(74,144,226,1)); }
    }

    .status-label {
        margin-top: 0.4rem;
        font-size: 0.82rem;
        font-weight: 600;
        letter-spacing: 0.03em;
        color: #555;
        text-transform: uppercase;
        height: 1.1rem;
    }

    /* ── Área de burbujas ── */
    .chat-area {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 0.75rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
    }
    .bubble-row-user { display: flex; justify-content: flex-end; }
    .bubble-row-assistant { display: flex; justify-content: flex-start; align-items: flex-end; gap: 0.4rem; }

    .bubble {
        max-width: 82%;
        padding: 0.6rem 0.9rem;
        border-radius: 1.2rem;
        font-size: 0.92rem;
        line-height: 1.45;
        word-break: break-word;
    }
    .bubble-user {
        background: #0d6efd;
        color: white;
        border-bottom-right-radius: 0.25rem;
    }
    .bubble-assistant {
        background: white;
        border: 1px solid #dee2e6;
        border-bottom-left-radius: 0.25rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    }
    .bubble-assistant p:last-child { margin-bottom: 0; }
    .bubble-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
    .bubble-time {
        font-size: 0.68rem;
        color: #aaa;
        margin-top: 0.2rem;
        text-align: right;
    }
    .bubble-time-left { text-align: left; margin-left: 36px; }

    /* ── Área de controles inferiores ── */
    .mic-area {
        flex: 0 0 auto;
        padding: 0.75rem 1rem 1rem;
        background: white;
        border-top: 1px solid #dee2e6;
    }

    /* Texto interim / transcript preview */
    .transcript-preview {
        min-height: 1.8rem;
        font-style: italic;
        color: #6c757d;
        font-size: 0.85rem;
        text-align: center;
        padding: 0.2rem 0.5rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Fila del countdown */
    .countdown-row {
        display: none;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }
    .countdown-progress {
        flex: 1;
        height: 6px;
        background: #dee2e6;
        border-radius: 3px;
        overflow: hidden;
    }
    .countdown-bar {
        height: 100%;
        width: 100%;
        background: #0d6efd;
        border-radius: 3px;
        transition: width 2s linear;
    }

    /* Botón de mic — grande y fácil de tocar */
    .btn-mic {
        width: 100%;
        height: 3.4rem;
        font-size: 1.05rem;
        font-weight: 600;
        border-radius: 2rem;
        border: none;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .btn-mic:active { transform: scale(0.96); }
    .btn-mic-idle     { background: #198754; color: white; }
    .btn-mic-idle:hover { background: #157347; color: white; }
    .btn-mic-listening { background: #dc3545; color: white; }
    .btn-mic-listening:hover { background: #bb2d3b; color: white; }
    .btn-mic-disabled  { background: #adb5bd; color: white; pointer-events: none; }
    .btn-mic-muted    { background: white; color: #198754; border: 2px solid #198754; }
    .btn-mic-muted:hover { background: #f0fff4; color: #198754; }

    /* Toggle voz — discreto, abajo del mic */
    .voice-toggle-row {
        display: flex;
        justify-content: center;
        margin-top: 0.5rem;
        gap: 0.75rem;
    }
    .btn-voice-toggle {
        font-size: 0.8rem;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
    }

    /* Estado muted banner */
    .muted-banner {
        display: none;
        background: #f8d7da;
        color: #842029;
        border-radius: 0.5rem;
        padding: 0.4rem 0.75rem;
        font-size: 0.82rem;
        text-align: center;
        margin-bottom: 0.4rem;
    }

    /* Typing indicator */
    .typing-dots {
        display: flex;
        gap: 4px;
        align-items: center;
        padding: 0.6rem 0.9rem;
    }
    .typing-dots span {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #adb5bd;
        animation: bounce 1.2s ease infinite;
    }
    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes bounce {
        0%, 80%, 100% { transform: translateY(0); }
        40%           { transform: translateY(-6px); }
    }

    /* Responsive: en desktop añadir un poco más de espacio */
    @media (min-width: 768px) {
        .conversar-wrapper { max-width: 600px; margin: 0 auto; }
        .cirilo-video-wrap { width: 160px; height: 160px; }
    }
</style>
@endsection

@section('content')
<div class="conversar-wrapper">

    {{-- ── Header ── --}}
    <div class="conv-header">
        <a href="{{ route('idex_home') }}">
            <i class="fas fa-arrow-left me-1"></i>Inicio
        </a>
        <h5><i class="fas fa-microphone me-1"></i>Conversar con Cirilo</h5>
        <a href="{{ route('preguntas') }}" title="Modo texto">
            <i class="fas fa-keyboard"></i>
        </a>
    </div>

    {{-- ── Cirilo ── --}}
    <div class="cirilo-area">
        <div class="cirilo-video-wrap" id="cirilo-wrap">
            <video id="cirilo-video" class="cirilo-video" autoplay loop muted playsinline>
                <source src="{{ asset('resources/cirilo-animation.mp4') }}" type="video/mp4">
                <source src="{{ asset('resources/cirilo-animation.webm') }}" type="video/webm">
                <img class="cirilo-fallback" src="{{ asset('resources/assistant.png') }}" alt="Cirilo">
            </video>
            <img class="cirilo-listening-img" src="{{ asset('resources/cirilo-escucha.png') }}" alt="Cirilo escuchando">
        </div>
        <div class="status-label" id="status-label">Listo</div>
    </div>

    {{-- ── Área de burbujas ── --}}
    <div class="chat-area" id="chat-area">
        <div class="text-center py-3 text-muted" id="empty-state" style="font-size:0.88rem;">
            <i class="fas fa-microphone-alt fa-2x mb-2 d-block" style="opacity:0.4"></i>
            Di algo para comenzar la conversación
        </div>
    </div>

    {{-- ── Controles inferiores ── --}}
    <div class="mic-area">

        {{-- Banner de silencio ── --}}
        <div class="muted-banner" id="muted-banner">
            <i class="fas fa-microphone-slash me-1"></i>
            Conversación en pausa — di "Cirilo continúa" o presiona el botón
        </div>

        {{-- Preview de lo que se está diciendo ── --}}
        <div class="transcript-preview" id="transcript-preview"></div>

        {{-- Countdown ── --}}
        <div class="countdown-row" id="countdown-row">
            <div class="countdown-progress">
                <div class="countdown-bar" id="countdown-bar"></div>
            </div>
            <button class="btn btn-sm btn-outline-secondary" id="btn-edit" style="white-space:nowrap;border-radius:1rem;font-size:0.82rem;">
                <i class="fas fa-pencil-alt me-1"></i>Editar
            </button>
        </div>

        {{-- Botón principal de mic ── --}}
        <button class="btn btn-mic btn-mic-idle" id="btn-mic">
            <i class="fas fa-microphone"></i> Hablar
        </button>

        {{-- Fila de controles secundarios ── --}}
        <div class="voice-toggle-row">
            <button class="btn btn-outline-secondary btn-voice-toggle" id="btn-voice-toggle" title="Activar/desactivar respuesta en voz">
                <i class="fas fa-volume-up me-1"></i>Voz
            </button>
            <a href="{{ route('preguntas') }}" class="btn btn-outline-secondary btn-voice-toggle">
                <i class="fas fa-keyboard me-1"></i>Texto
            </a>
        </div>
    </div>

</div>

{{-- ── Modal de edición ── --}}
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="editModalLabel">
                    <i class="fas fa-pencil-alt me-1"></i>Editar mensaje
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <textarea id="edit-textarea" class="form-control" rows="4" placeholder="Edita tu mensaje aquí..."></textarea>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-send-edited">
                    <i class="fas fa-paper-plane me-1"></i>Enviar
                </button>
            </div>
        </div>
    </div>
</div>

<audio id="audio-player" class="d-none"></audio>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── CSRF ──
    axios.defaults.headers.common['X-CSRF-TOKEN'] =
        document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // ── Referencias DOM ──
    const ciriloWrap       = document.getElementById('cirilo-wrap');
    const statusLabel      = document.getElementById('status-label');
    const chatArea         = document.getElementById('chat-area');
    const emptyState       = document.getElementById('empty-state');
    const transcriptEl     = document.getElementById('transcript-preview');
    const countdownRow     = document.getElementById('countdown-row');
    const countdownBarEl   = document.getElementById('countdown-bar');
    const btnEdit          = document.getElementById('btn-edit');
    const btnMic           = document.getElementById('btn-mic');
    const btnVoiceToggle   = document.getElementById('btn-voice-toggle');
    const mutedBanner      = document.getElementById('muted-banner');
    const audioPlayer      = document.getElementById('audio-player');
    const editModal        = new bootstrap.Modal(document.getElementById('editModal'));
    const editTextarea     = document.getElementById('edit-textarea');
    const btnSendEdited    = document.getElementById('btn-send-edited');

    // ── Estado ──
    let currentState       = 'idle';   // idle | listening | thinking | speaking | muted
    let isRecording        = false;
    // F5-01: misma preferencia de voz que /preguntas
    let voiceEnabled       = (function () {
        try { return localStorage.getItem('cirilo_voice_enabled') !== '0'; } catch (e) { return true; }
    })();
    let muted              = false;
    let currentRecognition = null;
    let silenceTimer       = null;
    let countdownTimer     = null;
    let historial          = [];
    let currentConversationId = null;
    let pendingNextcloudSync   = null; // {id, series_id, title} — en espera de respuesta personal/trabajo

    const userHasNextcloud = @json(Auth::check() && Auth::user()->hasNextcloud());

    @if(isset($user) && !empty($user->prompt))
    historial.push({ pregunta: @json($user->prompt), respuesta: '' });
    @endif

    // ── Definición de estados visuales ──
    const STATES = {
        idle:      { label: 'Listo',          wrap: '',                 btnClass: 'btn-mic-idle',      btnIcon: 'fa-microphone', btnText: 'Hablar' },
        listening: { label: 'Escuchando…',    wrap: 'cirilo-listening', btnClass: 'btn-mic-listening', btnIcon: 'fa-stop',       btnText: 'Detener' },
        thinking:  { label: 'Procesando…',    wrap: 'cirilo-thinking',  btnClass: 'btn-mic-disabled',  btnIcon: 'fa-spinner fa-spin', btnText: 'Un momento…' },
        speaking:  { label: 'Respondiendo…',  wrap: 'cirilo-speaking',  btnClass: 'btn-mic-disabled',  btnIcon: 'fa-volume-up',  btnText: 'Escucha…' },
        muted:     { label: 'Pausado',         wrap: '',                 btnClass: 'btn-mic-muted',     btnIcon: 'fa-microphone', btnText: 'Reanudar' },
    };

    function setState(s) {
        currentState = s;
        const def = STATES[s];
        // Video wrap
        ciriloWrap.className = `cirilo-video-wrap ${def.wrap}`;
        // Label
        statusLabel.textContent = def.label;
        // Botón
        btnMic.className = `btn btn-mic ${def.btnClass}`;
        btnMic.innerHTML = `<i class="fas ${def.btnIcon}"></i> ${def.btnText}`;
        // Banner de mute
        mutedBanner.style.display = s === 'muted' ? 'block' : 'none';
    }

    // ── Función para refrescar el CSRF token ──
    async function refreshCsrfToken() {
        try {
            const res = await axios.get('/auth/csrf-token');
            const newToken = res.data.token;
            document.querySelector('meta[name="csrf-token"]').setAttribute('content', newToken);
            axios.defaults.headers.common['X-CSRF-TOKEN'] = newToken;
            return newToken;
        } catch (e) {
            console.warn('No se pudo refrescar el CSRF token:', e);
            return null;
        }
    }

    // ── Keepalive: ping cada 4 min para mantener la sesión PHP activa (crítico en PWA) ──
    setInterval(() => refreshCsrfToken(), 4 * 60 * 1000);

    // ── Auto-inicio ──
    setTimeout(() => startRecognition(), 800);

    // ── Botón principal de mic ──
    btnMic.addEventListener('click', function () {
        if (currentState === 'listening') {
            stopRecognition();
            setState('idle');
        } else if (currentState === 'muted') {
            unmute();
        } else if (currentState === 'idle') {
            startRecognition();
        }
    });

    // ── Toggle de voz (TTS on/off) ──
    btnVoiceToggle.addEventListener('click', function () {
        voiceEnabled = !voiceEnabled;
        try { localStorage.setItem('cirilo_voice_enabled', voiceEnabled ? '1' : '0'); } catch (e) {}
        btnVoiceToggle.innerHTML = voiceEnabled
            ? '<i class="fas fa-volume-up me-1"></i>Voz'
            : '<i class="fas fa-volume-mute me-1"></i>Silencio';
        btnVoiceToggle.classList.toggle('btn-outline-secondary', voiceEnabled);
        btnVoiceToggle.classList.toggle('btn-outline-warning', !voiceEnabled);
    });

    if (!voiceEnabled) {
        btnVoiceToggle.innerHTML = '<i class="fas fa-volume-mute me-1"></i>Silencio';
        btnVoiceToggle.classList.remove('btn-outline-secondary');
        btnVoiceToggle.classList.add('btn-outline-warning');
    }

    // ── Botón Editar (cancela countdown y abre modal) ──
    btnEdit.addEventListener('click', cancelCountdown);

    // ── Botón Enviar en modal de edición ──
    btnSendEdited.addEventListener('click', function () {
        const text = editTextarea.value.trim();
        editModal.hide();
        if (text) sendMessage(text);
    });

    // ═════════════════════════════════════════
    //  RECONOCIMIENTO DE VOZ
    // ═════════════════════════════════════════

    function startRecognition() {
        if (isRecording || currentState === 'thinking' || currentState === 'speaking') return;

        if (!('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) {
            Swal.fire({
                icon: 'warning',
                title: 'Navegador no soportado',
                html: '<p>El reconocimiento de voz requiere <strong>Google Chrome</strong>.</p>',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        const isEdge   = navigator.userAgent.indexOf('Edg') !== -1;
        const isChrome = navigator.userAgent.indexOf('Chrome') !== -1 && !isEdge;

        if (!isChrome) {
            if (!sessionStorage.getItem('voiceWarnShown')) {
                sessionStorage.setItem('voiceWarnShown', '1');
                Swal.fire({
                    icon: 'warning',
                    title: 'Navegador no óptimo',
                    html: '<p>El reconocimiento de voz funciona mejor en <strong>Google Chrome</strong>.</p>',
                    confirmButtonText: 'Entendido'
                });
            }
            return;
        }

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const recognition = new SpeechRecognition();
        currentRecognition = recognition;

        recognition.lang            = 'es-ES';
        // Android Chrome con continuous:true acumula ciclos internos duplicando el transcript.
        // Con continuous:false el navegador detiene la sesión tras cada frase (sin ciclos),
        // y reopenMic() se encarga de reiniciar para mantener la escucha continua.
        const isAndroid = /Android/i.test(navigator.userAgent);
        recognition.continuous      = !isAndroid;
        recognition.interimResults  = true;
        recognition.maxAlternatives = 1;

        let accumulatedTranscript = '';

        try {
            recognition.start();
            isRecording = true;
            setState('listening');
        } catch (e) {
            console.error('Error al iniciar reconocimiento:', e);
            setState('idle');
            return;
        }

        recognition.onresult = function (event) {
            try {
                let finalTranscript   = '';
                let interimTranscript = '';
                // Reconstruir desde i=0 y sobreescribir: correcto para continuous=true (desktop)
                // donde event.results acumula todo, y para continuous=false (Android) donde
                // hay un único bloque de resultados por sesión.
                for (let i = 0; i < event.results.length; i++) {
                    if (event.results[i].isFinal) {
                        finalTranscript += event.results[i][0].transcript + ' ';
                    } else {
                        interimTranscript += event.results[i][0].transcript;
                    }
                }
                if (finalTranscript.trim()) {
                    accumulatedTranscript = finalTranscript.trim();
                }

                transcriptEl.textContent = (accumulatedTranscript || interimTranscript).trim().substring(0, 120);

                // Detectar comandos de pausa
                const combined = (accumulatedTranscript + ' ' + interimTranscript).toLowerCase();
                const muteCommands = ['cirilo mute', 'cirilo para', 'cirilo silenciar', 'cirilo pausa', 'cirilo detente'];
                if (muteCommands.some(cmd => combined.includes(cmd))) {
                    accumulatedTranscript = '';
                    transcriptEl.textContent = '';
                    try { recognition.stop(); } catch (e) {}
                    mute();
                    return;
                }

                // Timer de silencio
                clearTimeout(silenceTimer);
                silenceTimer = setTimeout(() => {
                    try { recognition.stop(); } catch (e) {}
                }, 1500);

            } catch (e) {
                console.error('Error en onresult:', e);
            }
        };

        recognition.onspeechend = function () {
            // El silenceTimer ya maneja la parada
        };

        recognition.onend = function () {
            // Ignorar onend de sesiones anteriores (Android puede dispararlo con retraso)
            if (currentRecognition !== recognition) return;
            clearTimeout(silenceTimer);
            isRecording = false;
            currentRecognition = null;

            const transcript = accumulatedTranscript.trim();
            accumulatedTranscript = '';

            if (!transcript || muted) {
                if (!muted) setState('idle');
                return;
            }

            transcriptEl.textContent = transcript.substring(0, 120);
            showCountdownAndSend(transcript);
        };

        recognition.onerror = function (event) {
            if (currentRecognition !== recognition) return;
            clearTimeout(silenceTimer);
            isRecording = false;
            currentRecognition = null;

            if (event.error === 'aborted' || event.error === 'no-speech') {
                if (!muted) setState('idle');
                return;
            }

            setState('idle');

            let msg = 'Ocurrió un error con el micrófono.';
            if (event.error === 'not-allowed') {
                msg = 'Permiso de micrófono denegado. Habilítalo en la configuración del navegador.';
            } else if (event.error === 'network') {
                msg = 'Error de red en el reconocimiento de voz.';
            } else if (event.error === 'audio-capture') {
                msg = 'No se encontró micrófono. Verifica que esté conectado.';
            }

            Swal.fire({ icon: 'error', title: 'Error de micrófono', text: msg, timer: 4000, showConfirmButton: false });
        };
    }

    function stopRecognition() {
        if (currentRecognition && isRecording) {
            try { currentRecognition.stop(); } catch (e) {}
            isRecording = false;
            currentRecognition = null;
        }
    }

    // ═════════════════════════════════════════
    //  MUTE / UNMUTE
    // ═════════════════════════════════════════

    function mute() {
        muted = true;
        stopRecognition();
        setState('muted');
        transcriptEl.textContent = '';
    }

    function unmute() {
        muted = false;
        setState('idle');
        setTimeout(() => startRecognition(), 300);
    }

    // ═════════════════════════════════════════
    //  COUNTDOWN ANTES DE ENVIAR
    // ═════════════════════════════════════════

    function showCountdownAndSend(transcript) {
        // Cancelar cualquier countdown anterior
        clearCountdown();

        // Mostrar fila de countdown
        countdownRow.style.display = 'flex';
        countdownBarEl.style.transition = 'none';
        countdownBarEl.style.width = '100%';

        // Arrancar animación en el siguiente frame
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                countdownBarEl.style.transition = 'width 2s linear';
                countdownBarEl.style.width = '0%';
            });
        });

        // Botón Editar: abre modal
        btnEdit.onclick = () => {
            cancelCountdown();
            editTextarea.value = transcript;
            editModal.show();
        };

        // Auto-envío tras 2 segundos
        countdownTimer = setTimeout(() => {
            clearCountdown();
            sendMessage(transcript);
        }, 2000);
    }

    function clearCountdown() {
        if (countdownTimer) {
            clearTimeout(countdownTimer);
            countdownTimer = null;
        }
        countdownRow.style.display = 'none';
        countdownBarEl.style.width = '100%';
    }

    function cancelCountdown() {
        clearCountdown();
        // El onclick del btn-edit ya se encarga de abrir el modal
    }

    // ═════════════════════════════════════════
    //  ENVÍO DE MENSAJE
    // ═════════════════════════════════════════

    // ── Palabras clave para detectar trabajo/personal en la respuesta de voz ──
    function detectSyncChoice(text) {
        const t = text.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        if (/\b(trabajo|laboral|work|empresa|oficina|nextcloud|sincroniza|nube|cloud)\b/.test(t)) return 'trabajo';
        if (/\b(personal|privado|solo aqui|no sincron|local)\b/.test(t)) return 'personal';
        if (/^\s*(si|s[ií]|yes|ok|dale|claro|adelante|sincroniza|sincronizar)\s*$/.test(t)) return 'trabajo';
        if (/^\s*(no|nop|nope|no gracias|no sincron)\s*$/.test(t)) return 'personal';
        return null;
    }

    function sendMessage(prompt) {
        if (!prompt.trim()) return;

        // ── Interceptar respuesta de personal/trabajo si hay un evento pendiente ──
        if (pendingNextcloudSync) {
            const choice = detectSyncChoice(prompt);
            if (choice) {
                addBubble('user', prompt);
                const pending = pendingNextcloudSync;
                pendingNextcloudSync = null;

                if (choice === 'trabajo') {
                    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    const url  = pending.series_id
                        ? `/agenda/series/${pending.series_id}/sync-nextcloud`
                        : `/agenda/events/${pending.id}/sync-nextcloud`;

                    setState('thinking');
                    fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' } })
                    .then(r => r.json())
                    .then(sync => {
                        const ok  = sync.success || sync.nextcloud_synced;
                        const msg = ok
                            ? `Listo, el evento "${pending.title}" quedó sincronizado con tu Nextcloud. ☁️`
                            : `Guardé el evento, pero hubo un problema al sincronizar con Nextcloud.`;
                        addBubble('assistant', msg);
                        historial.push({ respuesta: msg });
                        if (voiceEnabled) {
                            setState('speaking');
                            speakText(cleanForTTS(msg), () => reopenMic());
                        } else {
                            setState('idle');
                        }
                    })
                    .catch(() => {
                        const msg = `Guardé el evento, pero no pude conectarme a Nextcloud ahora.`;
                        addBubble('assistant', msg);
                        if (voiceEnabled) { setState('speaking'); speakText(cleanForTTS(msg), () => reopenMic()); }
                        else setState('idle');
                    });
                } else {
                    // personal: sin sync
                    const msg = `De acuerdo, el evento "${pending.title}" queda guardado como personal.`;
                    addBubble('assistant', msg);
                    historial.push({ respuesta: msg });
                    if (voiceEnabled) {
                        setState('speaking');
                        speakText(cleanForTTS(msg), () => reopenMic());
                    } else {
                        setState('idle');
                    }
                }
                return; // no enviar al backend
            }
            // Respuesta ambigua: limpia el estado y deja pasar el mensaje al backend normalmente
            pendingNextcloudSync = null;
        }

        clearCountdown();
        transcriptEl.textContent = '';
        setState('thinking');

        // Ocultar estado vacío
        if (emptyState) emptyState.style.display = 'none';

        // Burbuja de usuario
        addBubble('user', prompt);

        // Agregar al historial
        historial.push({ pregunta: prompt });

        // Preparar historial para el backend
        const conversationHistory = historial.map(item => {
            if (item.pregunta) return { role: 'user', content: item.pregunta };
            if (item.respuesta) return { role: 'assistant', content: item.respuesta };
            return null;
        }).filter(Boolean);

        // Indicador de typing
        const typingId = addTypingIndicator();

        // Llamada al backend — generateAudio:false para que el cliente maneje TTS
        axios.post('/generate-text', {
            prompt:          prompt,
            history:         conversationHistory,
            conversation_id: currentConversationId,
            generateAudio:   false
        })
        .then(response => {
            removeTypingIndicator(typingId);

            let respuesta = '';
            if (response.data.choices?.[0]?.message?.content) {
                respuesta = response.data.choices[0].message.content;
            }
            if (!respuesta.trim()) {
                setState('idle');
                setTimeout(() => reopenMic(), 600);
                return;
            }

            historial.push({ respuesta });
            addBubble('assistant', respuesta);

            // Auto-guardar
            if (historial.length >= 2) autoSaveConversation();

            // Si Cirilo creó un evento y el usuario tiene Nextcloud, preparar pregunta encadenada
            let nextcloudPregunta = null;
            if (response.data.calendar_event_created && userHasNextcloud) {
                const ev = response.data.calendar_event_created;
                pendingNextcloudSync  = { id: ev.id, series_id: ev.series_id ?? null, title: ev.title };
                nextcloudPregunta = `¿Lo guardo como evento de trabajo y lo sincronizo con tu Nextcloud, o lo dejo como personal?`;
            }

            // Callback que se ejecuta al terminar el TTS principal:
            // si hay pregunta de Nextcloud, la agrega y la habla antes de reabrir el mic
            function afterMainTTS() {
                if (nextcloudPregunta) {
                    addBubble('assistant', nextcloudPregunta);
                    historial.push({ respuesta: nextcloudPregunta });
                    if (voiceEnabled) {
                        setState('speaking');
                        speakText(cleanForTTS(nextcloudPregunta), () => reopenMic());
                        return;
                    }
                }
                reopenMic();
            }

            // TTS + reabrir mic
            if (voiceEnabled) {
                const audioUrl = response.data.audioUrl;
                if (audioUrl) {
                    setState('speaking');
                    playAudio(audioUrl, afterMainTTS);
                } else {
                    const cleanText = cleanForTTS(respuesta);
                    if (cleanText) {
                        setState('speaking');
                        speakText(cleanText, afterMainTTS);
                    } else {
                        afterMainTTS();
                    }
                }
            } else {
                // Modo texto: agregar la pregunta como burbuja sin audio
                if (nextcloudPregunta) {
                    addBubble('assistant', nextcloudPregunta);
                    historial.push({ respuesta: nextcloudPregunta });
                }
                setTimeout(() => reopenMic(), 600);
            }
        })
        .catch(async error => {
            removeTypingIndicator(typingId);
            console.error('Error al generar respuesta:', error);

            // 419 = CSRF token expirado (sesión PHP caducó, común en PWA de larga duración)
            if (error.response && error.response.status === 419) {
                const newToken = await refreshCsrfToken();
                if (newToken) {
                    // Token renovado — reintentar el mismo mensaje automáticamente
                    console.info('CSRF renovado, reintentando mensaje...');
                    setState('idle');
                    setTimeout(() => sendMessage(prompt), 500);
                } else {
                    // No se pudo renovar — la sesión expiró del todo
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sesión expirada',
                        text: 'La sesión ha expirado. Recargando la página...',
                        timer: 3000,
                        showConfirmButton: false
                    });
                    setTimeout(() => location.reload(), 3000);
                }
                return;
            }

            setState('idle');
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo obtener respuesta. Intenta de nuevo.',
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
            // Reabrir mic aunque hubo error, para no dejar la conversación muerta
            setTimeout(() => reopenMic(), 2000);
        });
    }

    function reopenMic() {
        if (!muted) {
            setState('idle');
            setTimeout(() => startRecognition(), 400);
        }
    }

    // ═════════════════════════════════════════
    //  BURBUJAS
    // ═════════════════════════════════════════

    function getCurrentTime() {
        return new Date().toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
    }

    function addBubble(role, text) {
        const time = getCurrentTime();

        if (role === 'user') {
            const row = document.createElement('div');
            row.className = 'bubble-row-user';
            row.innerHTML = `
                <div>
                    <div class="bubble bubble-user">${escapeHtml(text)}</div>
                    <div class="bubble-time">${time}</div>
                </div>`;
            chatArea.appendChild(row);
        } else {
            const row = document.createElement('div');
            row.className = 'bubble-row-assistant';
            row.innerHTML = `
                <img src="{{ asset('resources/assistant.png') }}" class="bubble-avatar" alt="Cirilo">
                <div>
                    <div class="bubble bubble-assistant">${window.CiriloContent.renderMarkdown(text)}</div>
                    <div class="bubble-time bubble-time-left">${time}</div>
                </div>`;
            chatArea.appendChild(row);
        }

        chatArea.scrollTop = chatArea.scrollHeight;
    }

    function escapeHtml(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    let typingCounter = 0;

    function addTypingIndicator() {
        const id = 'typing-' + (++typingCounter);
        const row = document.createElement('div');
        row.className = 'bubble-row-assistant';
        row.id = id;
        row.innerHTML = `
            <img src="{{ asset('resources/assistant.png') }}" class="bubble-avatar" alt="Cirilo">
            <div class="bubble bubble-assistant" style="padding:0.4rem 0.7rem;">
                <div class="typing-dots"><span></span><span></span><span></span></div>
            </div>`;
        chatArea.appendChild(row);
        chatArea.scrollTop = chatArea.scrollHeight;
        return id;
    }

    function removeTypingIndicator(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    // ═════════════════════════════════════════
    //  TTS
    // ═════════════════════════════════════════

    function cleanForTTS(text) {
        return text
            .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
            .replace(/https?:\/\/\S+/g, '')
            .replace(/#{1,6}\s+/g, '')
            .replace(/\*{1,3}([^*]+)\*{1,3}/g, '$1')
            .replace(/`[^`]+`/g, '')
            .replace(/\s{2,}/g, ' ')
            .trim()
            .substring(0, 3000);
    }

    async function speakText(text, onEnded) {
        try {
            const response = await fetch('/text-to-speech', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ text })
            });
            const data = await response.json();
            if (response.ok && data.audioUrl) {
                playAudio(data.audioUrl, onEnded);
            } else {
                console.warn('TTS falló, omitiendo audio');
                if (onEnded) onEnded();
            }
        } catch (e) {
            console.error('Error en speakText:', e);
            if (onEnded) onEnded();
        }
    }

    function playAudio(audioUrl, onEnded) {
        if (!audioPlayer) { if (onEnded) onEnded(); return; }

        if (window.location.protocol === 'https:') {
            audioUrl = audioUrl.replace(/^http:\/\//i, 'https://');
        }

        // Guardia para que onEnded se dispare una sola vez
        let fired = false;
        function fireOnce() {
            if (!fired) {
                fired = true;
                if (onEnded) onEnded();
            }
        }

        audioPlayer.src = audioUrl;
        audioPlayer.load();

        // Timeout de seguridad: si canplay no llega en 8s, continuar igual
        const loadTimeout = setTimeout(() => {
            console.warn('playAudio: timeout esperando canplay');
            fireOnce();
        }, 8000);

        audioPlayer.addEventListener('canplay', function handler() {
            audioPlayer.removeEventListener('canplay', handler);
            clearTimeout(loadTimeout);
            audioPlayer.play().catch(e => {
                console.error('Error al reproducir audio:', e.message);
                fireOnce();
            });
        }, { once: false });

        audioPlayer.onerror = function () {
            clearTimeout(loadTimeout);
            console.error('playAudio: error al cargar el audio');
            fireOnce();
        };

        audioPlayer.addEventListener('ended', function handler() {
            audioPlayer.removeEventListener('ended', handler);
            clearTimeout(loadTimeout);
            fireOnce();
        }, { once: false });
    }

    // ═════════════════════════════════════════
    //  AUTO-GUARDAR CONVERSACIÓN
    // ═════════════════════════════════════════

    function autoSaveConversation() {
        if (historial.length < 2) return;

        const messages = [];
        let title = '';

        for (const item of historial) {
            if (item.pregunta) {
                if (!title) title = item.pregunta.substring(0, 50) + (item.pregunta.length > 50 ? '…' : '');
                messages.push({ role: 'user', content: item.pregunta, timestamp: new Date().toISOString() });
            } else if (item.respuesta) {
                messages.push({ role: 'assistant', content: item.respuesta, timestamp: new Date().toISOString() });
            }
        }

        const content = JSON.stringify({ messages });

        if (currentConversationId) {
            axios.put('/conversaciones/' + currentConversationId, { content })
                .catch(e => console.error('Error al actualizar conversación:', e));
        } else {
            axios.post('/conversaciones', { title, type: 'chat', content })
                .then(res => {
                    if (res.data.conversation_id) currentConversationId = res.data.conversation_id;
                })
                .catch(e => console.error('Error al guardar conversación:', e));
        }
    }

});
</script>
@endpush
