# voice_system.py
import os
import tempfile
import pygame
import pyttsx3
from gtts import gTTS

class VoiceSystem:
    def __init__(self, openai_service=None):
        """
        Inicializa el sistema de voz con OpenAI TTS, gTTS y fallback local
        
        Args:
            openai_service: Instancia de OpenAIService ya configurada
        """
        # Inicializar pygame para reproducción de audio
        pygame.mixer.init()
        
        # Referencia al servicio de OpenAI
        self.openai_service = openai_service
        
        # Configurar motor de voz de respaldo (offline)
        self.offline_engine = pyttsx3.init()
        self._configure_offline_voice()
        
        # Indicador para saber si tenemos conexión a internet
        self.online = True
        
        # Modo de voz: 'openai' (preferido), 'gtts', o 'offline'
        self.voice_mode = 'openai' if (openai_service and openai_service.has_valid_client()) else 'gtts'
    
    def set_openai_service(self, openai_service):
        """Establece o actualiza el servicio de OpenAI"""
        self.openai_service = openai_service
        if openai_service and openai_service.has_valid_client():
            self.voice_mode = 'openai'
            return True
        return False
    
    def _configure_offline_voice(self):
        """Configura la mejor voz local disponible"""
        voices = self.offline_engine.getProperty('voices')
        
        # Buscar voces en español
        spanish_voice = None
        for voice in voices:
            if ("spanish" in str(voice.languages).lower() or 
                "es" in str(voice.id).lower()):
                spanish_voice = voice.id
                break
        
        # Aplicar voz en español si se encontró
        if spanish_voice:
            self.offline_engine.setProperty('voice', spanish_voice)
        
        # Ajustar velocidad para mejor comprensión
        self.offline_engine.setProperty('rate', 200)
    
    def _speak_with_openai(self, text, language='es'):
        """Utiliza la API de OpenAI para convertir texto a voz"""
        if not (self.openai_service and self.openai_service.has_valid_client()):
            return False
        
        try:
            # Elegir la voz según el idioma
            voice = "nova" if language == 'en' else "echo"  # echo para español
            
            # Realizar la solicitud a la API de OpenAI
            response = self.openai_service.client.audio.speech.create(
                model="tts-1",
                voice=voice,
                input=text
            )
            
            # Crear archivo temporal para el audio
            with tempfile.NamedTemporaryFile(delete=False, suffix='.mp3') as temp_file:
                temp_filename = temp_file.name
                # Obtener los datos binarios y guardarlos
                response_data = response.content
                temp_file.write(response_data)
            
            # Reproducir audio
            pygame.mixer.music.load(temp_filename)
            pygame.mixer.music.play()
            
            # Esperar a que termine la reproducción
            while pygame.mixer.music.get_busy():
                pygame.time.Clock().tick(10)
            
            # Limpiar
            pygame.mixer.music.unload()
            try:
                os.unlink(temp_filename)
            except:
                pass
            
            return True
                
        except Exception as e:
            # Error silencioso con OpenAI TTS
            return False
    
    def speak(self, text, language='es'):
        """
        Pronuncia el texto usando OpenAI TTS, gTTS o fallback a pyttsx3
        
        Args:
            text (str): Texto a pronunciar
            language (str): Código de idioma (es, en, etc.)
        """
        # Intentar con OpenAI si está configurado
        if self.voice_mode == 'openai' and self.online:
            if self._speak_with_openai(text, language):
                return True
            else:
                # Si falla OpenAI, intentar con gTTS
                self.voice_mode = 'gtts'
        
        # Intentar con gTTS
        if self.voice_mode == 'gtts' and self.online:
            try:
                # Crear archivo temporal para el audio
                with tempfile.NamedTemporaryFile(delete=False, suffix='.mp3') as temp_file:
                    temp_filename = temp_file.name
                
                # Generar audio con Google TTS
                tts = gTTS(text=text, lang=language, slow=False)
                tts.save(temp_filename)
                
                # Reproducir audio
                pygame.mixer.music.load(temp_filename)
                pygame.mixer.music.play()
                
                # Esperar a que termine la reproducción
                while pygame.mixer.music.get_busy():
                    pygame.time.Clock().tick(10)
                
                # Limpiar
                pygame.mixer.music.unload()
                try:
                    os.unlink(temp_filename)
                except:
                    pass
                
                return True
                
            except Exception as e:
                # Error silencioso con gTTS
                self.online = False
                # Continuar al fallback
        
        # Fallback: usar motor local (pyttsx3)
        try:
            self.offline_engine.say(text)
            self.offline_engine.runAndWait()
            return True
        except Exception as e:
            # Error silencioso con motor de voz local
            return False
    
    def is_speaking(self):
        """Verifica si está reproduciendo audio actualmente"""
        return pygame.mixer.music.get_busy() if self.online else False
    
    def set_voice_mode(self, mode):
        """Establece el modo de voz preferido: 'openai', 'gtts' o 'offline'"""
        if mode in ['openai', 'gtts', 'offline']:
            # Solo permitir openai si hay servicio configurado
            if mode == 'openai' and not (self.openai_service and self.openai_service.has_valid_client()):
                # No se puede usar OpenAI TTS sin un servicio configurado
                return False
            
            self.voice_mode = mode
            return True
        return False
    
    def set_online_mode(self, is_online=True):
        """Cambia manualmente entre modo online y offline"""
        self.online = is_online
        if not is_online:
            self.voice_mode = 'offline'
    
    def test_connection(self):
        """Intenta verificar conexión a internet"""
        try:
            import requests
            response = requests.get("https://api.openai.com", timeout=5)
            self.online = True
            return True
        except:
            self.online = False
            return False
