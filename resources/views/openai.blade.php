
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenAI Text Generator</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body, html {
            height: 100%;
        }
        #content-wrapper {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            height: 100%;
        }
        .response {
            margin-bottom: 15px;
        }
        .highlight {
            font-weight: bold;
            color: #007bff;
        }
        #mic-button {
            margin-left: 10px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div id="content-wrapper" class="container mt-5">
        <div id="responses-container" class="mb-3">
            <h2>Resultados</h2>
        </div>
        
        <form id="generate-text-form" method="POST" action="/generate-text">
            @csrf
            <div class="input-group mb-3">
                <input type="text" class="form-control" id="prompt" name="prompt" required>
                <button type="button" id="mic-button" class="btn btn-secondary">
                    <span class="bi bi-mic-fill"></span> <!-- Bootstrap icon for microphone -->
                </button>
            </div>
            <button type="submit" class="btn btn-primary">Generar Texto</button>
        </form>
    </div>

    <!-- Incluye Axios y Clipboard.js -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/clipboard/dist/clipboard.min.js"></script>
    <script>
        const form = document.getElementById('generate-text-form');
        const micButton = document.getElementById('mic-button');
        const promptInput = document.getElementById('prompt');
        const responsesContainer = document.getElementById('responses-container');

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            processPrompt(promptInput.value);
        });

        micButton.addEventListener('click', function() {
            startRecognition();
        });

        function processPrompt(prompt) {
            // Añade la pregunta con el prefijo "TU:"
            const userQuestion = document.createElement('div');
            userQuestion.classList.add('response', 'p-3', 'border', 'rounded', 'bg-light');
            userQuestion.innerHTML = `<span class="highlight">TU:</span> ${prompt}`;
            responsesContainer.appendChild(userQuestion);

            axios.post('/generate-text', { prompt: prompt })
                .then(response => {
                    const respuesta = response.data.choices[0].message.content;

                    const codeRegex = /```([^`]+)```/g;
                    let content = respuesta.split(codeRegex); // Divide el contenido por los bloques de código
                    let formattedContent = '';
                    let codeContent = '';

                    content.forEach((part, index) => {
                        if (index % 2 === 0) { // Texto normal
                            if (part.trim()) {
                                formattedContent += `<p>${part.trim()}</p>`;
                            }
                        } else { // Bloque de código
                            codeContent += `${part.trim()}\n`;
                            formattedContent += `<pre class="bg-dark text-white p-3">${part.trim()}</pre>`;
                        }
                    });

                    // Agregar la nueva respuesta al contenedor de respuestas
                    const newResponse = document.createElement('div');
                    newResponse.classList.add('response', 'p-3', 'border', 'rounded', 'bg-light');
                    newResponse.innerHTML = `<span class="highlight">ASISTENTE:</span> ${formattedContent}`;
                    responsesContainer.appendChild(newResponse);

                    // Generar síntesis de voz para la respuesta
                    speakText(formattedContent.replace(/<\/?[^>]+(>|$)/g, '').trim());

                    // Limpiar el input
                    promptInput.value = '';
                })
                .catch(error => {
                    console.error('Error:', error);
                    const errorResponse = document.createElement('div');
                    errorResponse.classList.add('response', 'p-3', 'border', 'rounded', 'bg-light');
                    errorResponse.innerHTML = `<span class="highlight">ASISTENTE:</span> ${error.response ? JSON.stringify(error.response.data, null, 2) : 'Hubo un error al generar el texto.'}`;
                    responsesContainer.appendChild(errorResponse);

                    // Generar síntesis de voz para el error
                    speakText('Hubo un error al generar el texto.');
                });
        }

        function speakText(text) {
            if ('speechSynthesis' in window) {
                const utterance = new SpeechSynthesisUtterance(text);
                window.speechSynthesis.speak(utterance);
            } else {
                console.error('Speech Synthesis not supported on this browser.');
            }
        }

        function startRecognition() {
            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                const recognition = new SpeechRecognition();
                recognition.lang = 'es-ES'; // Cambia este valor según el idioma deseado
                recognition.start();

                recognition.onresult = function(event) {
                    const transcript = event.results[0][0].transcript;
                    promptInput.value = transcript;
                    processPrompt(transcript);
                };

                recognition.onspeechend = function() {
                    recognition.stop();
                };

                recognition.onerror = function(event) {
                    if (event.error === 'not-allowed') {
                        alert('Permiso para usar el micrófono denegado. Por favor, permita el acceso al micrófono.');
                    }
                    console.error('Recognition error:', event.error);
                };
            } else {
                console.error('Speech Recognition not supported on this browser.');
                alert('Reconocimiento de voz no soportado en este navegador. Por favor, use un navegador compatible como Google Chrome.');
            }
        }

        // Inicializa Clipboard.js para los botones de copiar (si necesitas copiar código)
        document.addEventListener('click', function(e) {
            if (e.target && e.target.matches('button.copy-code')) {
                let codeElement = e.target.previousElementSibling;
                navigator.clipboard.writeText(codeElement.textContent).then(function() {
                    alert('Código copiado al portapapeles.');
                }, function(err) {
                    console.error('No se pudo copiar el contenido: ', err);
                });
            }
        });
    </script>
</body>
</html>