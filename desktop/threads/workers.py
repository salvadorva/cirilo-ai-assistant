# threads/workers.py
from PyQt6.QtCore import QRunnable, pyqtSignal, QObject, pyqtSlot

class WorkerSignals(QObject):
    """Define las señales disponibles para los workers de hilos"""
    finished = pyqtSignal(object)
    error = pyqtSignal(str)

class VoiceRecognizerWorker(QRunnable):
    """Worker para reconocimiento de voz"""
    def __init__(self):
        super().__init__()
        self.signals = WorkerSignals()
    
    @pyqtSlot()
    def run(self):
        import speech_recognition as sr
        recognizer = sr.Recognizer()
        
        try:
            with sr.Microphone() as source:
                # Ajustar para ruido ambiental con duración mayor para mejor calibración
                recognizer.adjust_for_ambient_noise(source, duration=1)
                # Aumentar el timeout y el phrase_time_limit para ser más tolerante con pausas
                # Añadir pause_threshold para permitir pausas más largas entre palabras
                recognizer.pause_threshold = 1.0  # Tiempo en segundos para considerar una pausa como fin de frase
                audio = recognizer.listen(source, timeout=10, phrase_time_limit=15)
            
            # Intentar reconocer con Google
            text = recognizer.recognize_google(audio, language="es-ES")
            self.signals.finished.emit(text)
        except sr.UnknownValueError:
            self.signals.error.emit("No pude entender lo que dijiste")
        except sr.RequestError:
            self.signals.error.emit("No pude conectarme al servicio de reconocimiento")
        except Exception as e:
            self.signals.error.emit(f"Error en reconocimiento: {str(e)}")

class OpenAIWorker(QRunnable):
    """Worker para consultas a OpenAI"""
    def __init__(self, openai_service, query, conversation_history=None, system_prompt=None):
        super().__init__()
        self.signals = WorkerSignals()
        self.openai_service = openai_service
        self.query = query
        self.conversation_history = conversation_history or []
        self.system_prompt = system_prompt or "Eres un asistente amigable y útil para niños. Explica conceptos de manera sencilla y divertida. Mantén las respuestas breves y fáciles de entender."
    
    @pyqtSlot()
    def run(self):
        try:
            response = self.openai_service.get_response(
                query=self.query, 
                conversation_history=self.conversation_history,
                system_prompt=self.system_prompt
            )
            self.signals.finished.emit(response)
        except Exception as e:
            self.signals.error.emit(f"No pude obtener una respuesta: {str(e)}")

class TextToSpeechWorker(QRunnable):
    """Worker para síntesis de voz"""
    def __init__(self, voice_system, text):
        super().__init__()
        self.signals = WorkerSignals()
        self.voice_system = voice_system
        self.text = text
    
    @pyqtSlot()
    def run(self):
        try:
            # Intentar usar OpenAI primero si está disponible
            if hasattr(self.voice_system, 'openai_service') and self.voice_system.openai_service:
                if self.voice_system.openai_service.has_valid_client():
                    # Forzar el uso de OpenAI para esta llamada
                    old_mode = self.voice_system.voice_mode
                    self.voice_system.set_voice_mode('openai')
                    
                    # Usar OpenAI TTS sin imprimir mensajes de depuración
                    result = self.voice_system.speak(self.text)
                    
                    # Restaurar el modo anterior
                    self.voice_system.set_voice_mode(old_mode)
                    
                    if result:
                        self.signals.finished.emit(True)
                        return
                    # Si falla, continuar con el método normal (fallback) sin imprimir
            
            # Método normal (se ejecuta si OpenAI no está disponible o falla)
            self.voice_system.speak(self.text)
            self.signals.finished.emit(True)
        except Exception as e:
            # Emitir error sin imprimir ni mostrar trazas
            self.signals.error.emit(f"Error en síntesis de voz")



# Añadir a threads/workers.py

class ImageGeneratorWorker(QRunnable):
    """Worker para generar imágenes con DALL-E en segundo plano"""
    
    class Signals(QObject):
        finished = pyqtSignal(dict)  # Señal para indicar finalización con datos de la imagen
        error = pyqtSignal(str)      # Señal para indicar error
    
    def __init__(self, openai_service, prompt, save_path=None):
        super().__init__()
        self.openai_service = openai_service
        self.prompt = prompt
        self.save_path = save_path
        self.signals = self.Signals()
    
    def run(self):
        try:
            image_data = self.openai_service.generate_image(
                self.prompt, 
                self.save_path
            )
            self.signals.finished.emit(image_data)
        except Exception as e:
            self.signals.error.emit(str(e))


