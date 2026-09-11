/**
 * Script para generar audio sintético para ejercicios de inglés
 * Utiliza la API Web Speech para generar audio a partir de texto
 */

// Textos de ejemplo para cada nivel
const audioTexts = {
    'A1': "Hello! My name is Sarah. I am from London. I like to read books and listen to music. What is your name?",
    'A2': "Last weekend, I went to the beach with my friends. We swam in the ocean and had a picnic. The weather was sunny and warm.",
    'B1': "I've been learning English for three years now. It's challenging sometimes, but I enjoy being able to communicate with people from different countries.",
    'B2': "Environmental protection has become a major concern worldwide. Many countries are implementing policies to reduce carbon emissions and promote sustainable development.",
    'C1': "The implications of artificial intelligence on society are profound and multifaceted. While it offers unprecedented opportunities, it also raises ethical questions that we must address.",
    'C2': "The intricate relationship between globalization and cultural identity presents a paradox whereby increased interconnectedness simultaneously homogenizes and diversifies cultural expressions across societies."
};

// Función para generar y descargar audio
function generateAudio(level) {
    if (!('speechSynthesis' in window)) {
        alert('Lo sentimos, tu navegador no soporta la síntesis de voz.');
        return;
    }
    
    const text = audioTexts[level] || "This is a sample English exercise. Listen carefully and practice your comprehension skills.";
    
    // Crear un objeto de síntesis de voz
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = 'en-US';
    utterance.rate = 0.9; // Velocidad ligeramente más lenta para mejor comprensión
    
    // Seleccionar una voz en inglés si está disponible
    window.speechSynthesis.onvoiceschanged = function() {
        const voices = window.speechSynthesis.getVoices();
        const englishVoices = voices.filter(voice => voice.lang.includes('en-'));
        
        if (englishVoices.length > 0) {
            utterance.voice = englishVoices[0];
        }
    };
    
    // Reproducir el audio
    window.speechSynthesis.speak(utterance);
}

// Exponer la función globalmente
window.generateEnglishAudio = generateAudio;
