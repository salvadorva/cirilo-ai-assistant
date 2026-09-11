# app/assistant.py
from PyQt6.QtCore import QThreadPool, Qt, QObject, pyqtSlot
from PyQt6.QtGui import QPalette, QBrush, QPixmap
import os
import sys
# Al principio del archivo, añade o asegúrate de tener:
from PyQt6.QtWidgets import (QMainWindow, QWidget, QPushButton, 
                            QHBoxLayout, QVBoxLayout, QFileDialog, QApplication)
from services.config_service import ConfigService
from services.openai_service import OpenAIService
from services.voice_system import VoiceSystem
from threads.workers import VoiceRecognizerWorker, OpenAIWorker, TextToSpeechWorker
from utils.code_formatter import format_markdown_code
from app.config_window import ConfigWindow


class AsistenteInfantil(QMainWindow):
    def __init__(self):
        super().__init__()
        
        # Variable para almacenar el fondo actual
        self.bg_pixmap = None
        
        # Configuración del pool de hilos
        self.threadpool = QThreadPool()
        print(f"Multihilo con máximo {self.threadpool.maxThreadCount()} hilos")
        
        # Rutas a recursos
        self.resource_path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'resources')
        
        # Inicializar servicios
        self.config_service = ConfigService()
        self.config = self.config_service.load_config()
        
        # Inicializar el servicio de OpenAI
        self.openai_service = OpenAIService()
        api_key = self.config.get('api_key', '')
        if api_key:
            self.openai_service.set_api_key(api_key)
        else:
            # Si no hay API key configurada, mostrar diálogo de configuración
            self.config_required = True
        
        # Historial de conversación para contexto
        self.conversation_history = []
        
        # Inicializar sistema de voz
        #self.voice_system = VoiceSystem()
        self.voice_system = VoiceSystem(openai_service=self.openai_service)
        
        # Configurar interfaz de usuario
        self.central_widget = QWidget()
        self.setCentralWidget(self.central_widget)
        
        # Configurar UI
        from app.ui_setup import setup_ui
        setup_ui(self, self.central_widget)
        
        # Aplicar la apariencia configurada (fondo y robot)
        self.update_appearance()
        
        # Si necesitamos configuración inicial, mostrar el diálogo
        if hasattr(self, 'config_required') and self.config_required:
            self.open_config()
        else:
            # Mensajes iniciales
            user_name = self.config.get('user_name', '')
            greeting = f"¡Hola{' ' + user_name if user_name else ''}! Presiona el botón para hablar conmigo"
            
            # Configurar mensaje de bienvenida en el área de respuesta - ahora más simple
            welcome_html = "<div style='text-align:center; margin-top:20px;'><p>¡Estoy listo para ayudarte!</p></div>"
            self.response_text.setHtml(welcome_html)
            
            self.update_status(greeting)
            self.voice_system.speak(greeting)
    
    def open_config(self):
        """Abre la ventana de configuración"""
        config_dialog = ConfigWindow(self)
        if config_dialog.exec():
            # Guardar configuración anterior para detectar cambios
            old_config = self.config.copy() if self.config else {}
            
            # Recargar configuración si se guardó
            self.config = self.config_service.load_config()
            
            # Actualizar servicio de OpenAI con nueva API key
            api_key = self.config.get('api_key', '')
            if api_key:
                self.openai_service.set_api_key(api_key)
                if hasattr(self, 'config_required'):
                    delattr(self, 'config_required')
            
            # Verificar si cambió la configuración de apariencia
            old_appearance = old_config.get('use_custom_appearance', False)
            old_bg = old_config.get('background_file', 'background.png')
            old_robot = old_config.get('robot_file', 'robot.png')
            
            new_appearance = self.config.get('use_custom_appearance', False)
            new_bg = self.config.get('background_file', 'background.png')
            new_robot = self.config.get('robot_file', 'robot.png')
            
            # Si cambió alguna configuración de apariencia, actualizar la interfaz
            if old_appearance != new_appearance or old_bg != new_bg or old_robot != new_robot:
                self.update_appearance()
            
            # Actualizar saludo si cambió el nombre
            user_name = self.config.get('user_name', '')
            if user_name:
                self.update_status(f"Configuración actualizada, {user_name}")
            else:
                self.update_status("Configuración actualizada")
    
    def start_new_conversation(self):
        """Inicia una nueva conversación, eliminando el contexto anterior"""
        # Deshabilitar temporalmente el botón para evitar clics múltiples
        self.new_chat_button.setEnabled(False)
        self.new_chat_button.setText("🔄 Iniciando...")
        
        # Limpiar la conversación y actualizar la interfaz
        self.conversation_history = []
        self.user_query_label.setText("")
        self.text_input.setText("")  # Limpiar el campo de texto de entrada
        self.response_text.setHtml("¡Estoy listo para una nueva conversación! ¿En qué puedo ayudarte?")
        
        # Actualizar el estado
        user_name = self.config.get('user_name', '')
        if user_name:
            self.update_status(f"Nueva conversación iniciada, {user_name}")
        else:
            self.update_status("Nueva conversación iniciada")
        
        # Usar un worker para la síntesis de voz
        try:
            from threads.workers import TextToSpeechWorker
            
            # Crear y configurar el worker
            speech_worker = TextToSpeechWorker(
                self.voice_system,
                "Nueva conversación iniciada. ¿En qué puedo ayudarte?"
            )
            
            # Conectar señales
            speech_worker.signals.finished.connect(self._on_new_conversation_speech_finished)
            speech_worker.signals.error.connect(self._on_new_conversation_speech_error)
            
            # Ejecutar en el pool de hilos
            self.threadpool.start(speech_worker)
            
        except Exception as e:
            print(f"Error al iniciar síntesis de voz: {str(e)}")
            # Reactivar el botón en caso de error
            self.new_chat_button.setEnabled(True)
            self.new_chat_button.setText("🔄 Nueva conversación")
    
    def _on_new_conversation_speech_finished(self, _):
        """Callback cuando la síntesis de voz para nueva conversación termina"""
        # Reactivar el botón
        self.new_chat_button.setEnabled(True)
        self.new_chat_button.setText("🔄 Nueva conversación")
    
    def _on_new_conversation_speech_error(self, error_message):
        """Callback cuando hay un error en la síntesis de voz para nueva conversación"""
        print(f"Error en síntesis de voz para nueva conversación: {error_message}")
        # Reactivar el botón
        self.new_chat_button.setEnabled(True)
        self.new_chat_button.setText("🔄 Nueva conversación")
    
    def start_listening(self):
        """Inicia el proceso de escucha"""
        # Verificar si tenemos configuración necesaria
        if hasattr(self, 'config_required') and self.config_required:
            self.update_status("Necesitas configurar la API de OpenAI primero")
            self.open_config()
            return
        
        # Deshabilitar botón mientras escucha
        self.talk_button.setEnabled(False)
        self.talk_button.setText("🔊 Escuchando...")
        
        # Actualizar estado e icono
        self.update_status("Escuchando... Por favor, habla ahora")
        if self.icon_listening:
            self.status_icon.setPixmap(self.icon_listening.scaled(
                48, 48, Qt.AspectRatioMode.KeepAspectRatio, Qt.TransformationMode.SmoothTransformation))
        
        # Reproducir sonido de inicio de escucha (opcional)
        self.voice_system.speak("Te escucho", language='es')
        
        # Iniciar hilo de reconocimiento de voz
        voice_worker = VoiceRecognizerWorker()
        voice_worker.signals.finished.connect(self.on_voice_recognized)
        voice_worker.signals.error.connect(self.on_voice_error)
        
        # Ejecutar en el pool de hilos
        self.threadpool.start(voice_worker)
    
    def on_voice_recognized(self, text):
        """Procesa el texto reconocido"""
        try:
            # Limpiar y mostrar lo que dijo el usuario
            self.user_query_label.clear()  # Limpiar el contenido anterior
            self.user_query_label.setText(f"Tú: {text}")
            self.user_query_label.setVisible(True)  # Asegurar que sea visible
            
            # También mostrar el texto en el campo de entrada para referencia
            self.text_input.setPlainText(text)  # Usar setPlainText para QTextEdit
            
            self.update_status("Pensando en una respuesta...")
            self.talk_button.setText("🔄 Procesando...")
            self.talk_button.setEnabled(False)  # Deshabilitar el botón mientras procesa
            
            # Cambiar icono a "pensando"
            if self.icon_thinking:
                self.status_icon.setPixmap(self.icon_thinking.scaled(
                    48, 48, Qt.AspectRatioMode.KeepAspectRatio, Qt.TransformationMode.SmoothTransformation))
            
            # Iniciar consulta a OpenAI con el contexto de la conversación
            system_prompt = self.config.get('system_prompt', "Eres un asistente amigable y útil para niños. Explica conceptos de manera sencilla y divertida. Mantén las respuestas breves y fáciles de entender.")
            
            openai_worker = OpenAIWorker(
                self.openai_service,
                text, 
                self.conversation_history, 
                system_prompt
            )
            openai_worker.signals.finished.connect(self.handle_openai_response)
            openai_worker.signals.error.connect(self.on_openai_error)
            
            # Ejecutar en el pool de hilos
            self.threadpool.start(openai_worker)
        except Exception as e:
            # Manejar cualquier error silenciosamente para evitar ventanas adicionales
            self.update_status("Error al procesar la voz")
            self.talk_button.setEnabled(True)
            self.talk_button.setText("🎤 Habla conmigo")
    
    def on_voice_error(self, error_message):
        """Maneja errores de reconocimiento de voz"""
        self.update_status(error_message)
        self.voice_system.speak("No pude entenderte. ¿Podrías intentarlo de nuevo?")
        self.status_icon.clear()
        self.talk_button.setEnabled(True)
        self.talk_button.setText("🎤 Habla conmigo")
    
    def handle_openai_response(self, response_text):
        """Maneja la respuesta de OpenAI"""
        try:
            # Actualizar el historial de conversación
            self.conversation_history.append({"role": "user", "content": self.user_query_label.text().replace("Tú: ", "")})
            self.conversation_history.append({"role": "assistant", "content": response_text})
            
            # Limitar el historial a las últimas 10 interacciones (5 turnos)
            if len(self.conversation_history) > 10:
                self.conversation_history = self.conversation_history[-10:]
            
            # Formatear texto para resaltar bloques de código
            formatted_response = format_markdown_code(response_text)
            
            # Asegurar que la etiqueta de consulta del usuario sea visible
            self.user_query_label.setVisible(True)
            
            # Obtener el HTML actual antes de modificarlo
            current_html = self.response_text.toHtml()
            
            # Asegurar que el texto de respuesta sea visible
            self.response_text.setVisible(True)
            
            # Traer la ventana principal al frente y darle foco
            self.activateWindow()
            self.raise_()
            
            # Crear un nuevo documento HTML para mostrar en el QTextBrowser
            if "¡Estoy listo para una nueva conversación!" in current_html or "¡Hola! Soy tu asistente Cirilo" in current_html:
                # Si es una nueva conversación, reemplazar todo el contenido
                self.response_text.clear()
                self.response_text.setHtml(formatted_response)
            else:
                # Agregar la nueva respuesta debajo de las anteriores con un separador
                self.response_text.setHtml(f"{current_html}<hr/>{formatted_response}")
            
            # Desplazar al final para mostrar la respuesta más reciente
            from PyQt6.QtGui import QTextCursor
            self.response_text.moveCursor(QTextCursor.MoveOperation.End)
            
            # Forzar la actualización de la interfaz para asegurar que el texto se muestre
            QApplication.processEvents()
            
            # Limpiar icono de estado
            self.status_icon.clear()
            
            # Actualizar estado
            self.update_status("Listo para otra pregunta")
            
            # Pronunciar respuesta en un hilo separado sin usar print
            tts_worker = TextToSpeechWorker(self.voice_system, response_text)
            self.threadpool.start(tts_worker)
            
            # Restaurar botones
            self.talk_button.setEnabled(True)
            self.talk_button.setText("🎤 Habla conmigo")
            self.send_button.setEnabled(True)
        except Exception as e:
            # Manejar cualquier error silenciosamente para evitar ventanas adicionales
            self.update_status("Error al mostrar respuesta")
            self.talk_button.setEnabled(True)
            self.talk_button.setText("🎤 Habla conmigo")
            self.send_button.setEnabled(True)
    
    def on_openai_error(self, error_message):
        """Maneja errores de OpenAI"""
        self.response_text.setHtml("Lo siento, tuve un problema para encontrar la respuesta.")
        self.update_status(error_message)
        
        # Limpiar icono de estado
        self.status_icon.clear()
        
        self.voice_system.speak("Lo siento, tuve un problema para encontrar la respuesta. Intenta de nuevo.")

        self.talk_button.setEnabled(True)
        self.talk_button.setText("🎤 Habla conmigo")
        self.send_button.setEnabled(True)
        
    def start_new_conversation(self):
        """Inicia una nueva conversación, eliminando el contexto anterior"""
        # Deshabilitar temporalmente el botón para evitar clics múltiples
        self.new_chat_button.setEnabled(False)
        self.new_chat_button.setText(" Iniciando...")

        # Limpiar la conversación y actualizar la interfaz
        self.conversation_history = []
        self.user_query_label.setText("")
        self.user_query_label.setVisible(False)  # Ocultar hasta que haya una consulta
        self.text_input.clear()  # Limpiar el campo de texto de entrada
        self.response_text.setHtml("¡Estoy listo para una nueva conversación! ¿En qué puedo ayudarte?")
        self.response_text.setVisible(True)  # Asegurar que el área de respuesta sea visible
        
        # Habilitar los botones
        self.send_button.setEnabled(True)
        self.talk_button.setEnabled(True)
        self.talk_button.setText("🎤 Habla conmigo")
        self.new_chat_button.setEnabled(True)
        self.new_chat_button.setText("🔄 Nueva conversación")
        
        # Actualizar estado
        self.update_status("Listo para una nueva conversación")

    
    def update_appearance(self):
        """Actualiza la apariencia de la aplicación según la configuración"""
        # Imprimir la configuración completa para depuración
        print("Configuración actual:", self.config)
        
        # Determinar qué archivos de recursos usar - siempre usar los seleccionados
        bg_file = self.config.get('background_file', 'background.png')
        robot_file = self.config.get('robot_file', 'robot.png')
        
        print(f"Actualizando apariencia: fondo={bg_file}, robot={robot_file}")
        
        # Actualizar fondo
        bg_path = os.path.join(self.resource_path, bg_file)
        print(f"Ruta del fondo: {bg_path}, existe: {os.path.exists(bg_path)}")
        
        if os.path.exists(bg_path):
            try:
                # Crear un nuevo pixmap desde el archivo
                bg_pixmap = QPixmap(bg_path)
                if bg_pixmap.isNull():
                    print(f"Error: No se pudo cargar la imagen de fondo desde {bg_path}")
                else:
                    # Guardar una referencia al pixmap para evitar que sea eliminado por el recolector de basura
                    self.bg_pixmap = bg_pixmap
                    
                    # Crear una nueva paleta y aplicarla
                    palette = QPalette()
                    palette.setBrush(QPalette.ColorRole.Window, 
                                QBrush(self.bg_pixmap.scaled(
                                    self.size(), 
                                    Qt.AspectRatioMode.IgnoreAspectRatio, 
                                    Qt.TransformationMode.SmoothTransformation)))
                    self.setPalette(palette)
                    
                    # Asegurarse de que la aplicación use la paleta personalizada
                    self.setAutoFillBackground(True)
                    
                    # Aplicar también como estilo CSS para mayor compatibilidad
                    self.setStyleSheet(f"QMainWindow {{ background-image: url({bg_path.replace('/', '/')}); }}")
                    
                    print(f"Fondo actualizado correctamente con {bg_file}")
            except Exception as e:
                print(f"Error al actualizar el fondo: {e}")
        
        # Actualizar personaje (robot)
        robot_path = os.path.join(self.resource_path, robot_file)
        if os.path.exists(robot_path) and hasattr(self, 'character_label'):
            try:
                self.character_pixmap = QPixmap(robot_path)
                if not self.character_pixmap.isNull():
                    self.character_label.setPixmap(self.character_pixmap.scaled(
                        120, 120, Qt.AspectRatioMode.KeepAspectRatio, Qt.TransformationMode.SmoothTransformation))
                    print(f"Robot actualizado correctamente con {robot_file}")
                else:
                    print(f"Error: No se pudo cargar la imagen del robot desde {robot_path}")
            except Exception as e:
                print(f"Error al actualizar el robot: {e}")
        
        # Actualizar la interfaz
        self.central_widget.setStyleSheet("")
        self.central_widget.setStyleSheet("background-color: transparent;")
        self.repaint()  # Forzar repintado completo
        
    def update_status(self, message):
        """Actualiza la etiqueta de estado"""
        self.status_label.setText(message)
    
    # Para asegurar que el fondo se redimensione con la ventana
    def resizeEvent(self, event):
        super().resizeEvent(event)
        bg_path = os.path.join(self.resource_path, 'background.png')
        if os.path.exists(bg_path):
            palette = QPalette()
            palette.setBrush(QPalette.ColorRole.Window, 
                            QBrush(QPixmap(bg_path).scaled(
                                self.size(), 
                                Qt.AspectRatioMode.IgnoreAspectRatio,
                                Qt.TransformationMode.SmoothTransformation)))
            self.setPalette(palette)

            # Asegurarse de que la aplicación use la paleta personalizada
            self.setAutoFillBackground(True)

    def send_text_query(self):
        """Envía la consulta de texto al asistente"""
        try:
            # Verificar si tenemos configuración necesaria
            if hasattr(self, 'config_required') and self.config_required:
                self.update_status("Necesitas configurar la API de OpenAI primero")
                self.open_config()
                return
            
            # Obtener el texto del QTextEdit
            text = self.text_input.toPlainText().strip()
            
            # Si no hay texto, no hacer nada
            if not text:
                self.update_status("Por favor escribe o di algo primero")
                return
            
            # Limpiar y mostrar lo que dijo el usuario
            self.user_query_label.clear()  # Limpiar el contenido anterior
            self.user_query_label.setText(f"Tú: {text}")
            self.user_query_label.setVisible(True)  # Asegurar que sea visible
            
            # Deshabilitar botones mientras se procesa
            self.send_button.setEnabled(False)
            self.talk_button.setEnabled(False)
            
            # Actualizar estado
            self.update_status("Pensando en una respuesta...")
            
            # Mostrar icono de pensando si está disponible
            if hasattr(self, 'icon_thinking') and self.icon_thinking is not None:
                self.status_icon.setPixmap(self.icon_thinking.scaled(32, 32, Qt.AspectRatioMode.KeepAspectRatio))
            
            # Preparar el prompt del sistema
            system_prompt = self.config.get('system_prompt', "Eres un asistente infantil amigable y educativo llamado Cirilo. "
                                          "Responde de manera sencilla, divertida y adecuada para niños. "
                                          "Usa ejemplos y explicaciones claras. Sé paciente y positivo.")
            
            # Crear y ejecutar el worker para OpenAI
            openai_worker = OpenAIWorker(
                self.openai_service,
                text, 
                self.conversation_history, 
                system_prompt
            )
            openai_worker.signals.finished.connect(self.handle_openai_response)
            openai_worker.signals.error.connect(self.on_openai_error)
            
            # Ejecutar en el pool de hilos
            self.threadpool.start(openai_worker)
            
            # Limpiar el campo de texto después de enviar
            self.text_input.clear()
        except Exception as e:
            # Manejar cualquier error silenciosamente para evitar ventanas adicionales
            self.update_status("Error al procesar la consulta")
            self.send_button.setEnabled(True)
            self.talk_button.setEnabled(True)
    
    def generate_image(self):
        """Genera una imagen basada en la entrada del usuario"""
        # Verificar si tenemos configuración necesaria
        if hasattr(self, 'config_required') and self.config_required:
            self.update_status("Necesitas configurar la API de OpenAI primero")
            self.open_config()
            return
        
        # Obtener el texto del campo de entrada o del reconocimiento de voz
        text = self.text_input.toPlainText().strip()
        
        # Si no hay texto en el input, intentar usar reconocimiento de voz
        if not text:
            self.start_listening_for_image()
            return
        
        # Procesar la solicitud de imagen
        self._process_image_request(text)

    def start_listening_for_image(self):
        """Inicia el proceso de escucha para generar una imagen"""
        # Deshabilitar botón mientras escucha
        self.generate_image_button.setEnabled(False)
        self.talk_button.setEnabled(False)
        self.generate_image_button.setText("🔊 Escuchando...")
        
        # Actualizar estado e icono
        self.update_status("Escuchando... Describe la imagen que deseas generar")
        if self.icon_listening:
            self.status_icon.setPixmap(self.icon_listening.scaled(
                48, 48, Qt.AspectRatioMode.KeepAspectRatio, Qt.TransformationMode.SmoothTransformation))
        
        # Reproducir sonido de inicio de escucha
        self.voice_system.speak("Describe la imagen que deseas crear", language='es')
        
        # Iniciar hilo de reconocimiento de voz
        voice_worker = VoiceRecognizerWorker()
        voice_worker.signals.finished.connect(self._on_voice_for_image)
        voice_worker.signals.error.connect(self.on_voice_error)
        
        # Ejecutar en el pool de hilos
        self.threadpool.start(voice_worker)

    def _on_voice_for_image(self, text):
        """Procesa el texto reconocido para generar una imagen"""
        # Limpiar la etiqueta de consulta del usuario
        self.user_query_label.clear()
        # Mostrar lo que dijo el usuario
        self.user_query_label.setText(f"Tú: {text} [Generando imagen...]")
        self._process_image_request(text)

    def _process_image_request(self, prompt):
        """Procesa la solicitud de generar una imagen"""
        # Actualizar estado
        self.update_status("Generando imagen... Esto puede tomar un momento")
        
        # Deshabilitar botones mientras se genera
        self.send_button.setEnabled(False)
        self.talk_button.setEnabled(False)
        self.generate_image_button.setEnabled(False)
        self.generate_image_button.setText("💼️ Generando...")
        
        # Cambiar icono a "pensando"
        if self.icon_thinking:
            self.status_icon.setPixmap(self.icon_thinking.scaled(
                48, 48, Qt.AspectRatioMode.KeepAspectRatio, Qt.TransformationMode.SmoothTransformation))
        
        # Crear directorio para imágenes si no existe
        images_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'generated_images')
        os.makedirs(images_dir, exist_ok=True)
        
        try:
            # Iniciar worker para generar imagen
            from threads.workers import ImageGeneratorWorker
            
            image_worker = ImageGeneratorWorker(
                self.openai_service,
                prompt,
                save_path=images_dir
            )
            image_worker.signals.finished.connect(self._on_image_generated)
            image_worker.signals.error.connect(self._on_image_error)
            
            # Ejecutar en el pool de hilos
            self.threadpool.start(image_worker)
            
            # Configurar un timer de seguridad para reactivar los botones en caso de error silencioso
            from PyQt6.QtCore import QTimer
            self.safety_timer = QTimer(self)
            self.safety_timer.setSingleShot(True)
            self.safety_timer.timeout.connect(self._on_image_timeout)
            self.safety_timer.start(30000)  # 30 segundos de timeout
            
        except Exception as e:
            # Si hay un error al iniciar el worker, reactivar los botones
            print(f"Error al iniciar el worker de imagen: {str(e)}")
            import traceback
            traceback.print_exc()
            self._on_image_error(f"Error al iniciar la generación: {str(e)}")


    def _on_image_generated(self, image_data):
        """Maneja la respuesta de generación de imagen exitosa"""
        # Obtener URL y ruta local
        image_url = image_data["url"]
        local_path = image_data.get("local_path")
        
        # Guardar la última imagen generada
        self.last_generated_image = local_path
        
        # Activar el botón de descarga
        self.download_button.setEnabled(True)
        
        # Formatear texto HTML para mostrar la imagen
        html_response = (
            f'<div style="text-align:center;">'
            f'<p>He generado una imagen según tu descripción:</p>'
            f'<p><img src="{local_path}" width="600" style="max-width:100%;"></p>'
            f'<p>La imagen se ha guardado temporalmente en: {local_path}</p>'
            f'<p>Usa el botón "Descargar última imagen" para guardarla permanentemente.</p>'
            f'</div>'
        )
        
        # Actualizar el área de respuesta
        current_html = self.response_text.toHtml()
        if "¡Estoy listo para una nueva conversación!" in current_html or "¡Hola! Soy tu asistente Cirilo" in current_html:
            self.response_text.setHtml(html_response)
        else:
            self.response_text.setHtml(f"{current_html}<hr/>{html_response}")
        
        # Actualizar estado
        self.update_status("Imagen generada con éxito! Usa el botón para descargarla.")
        
        # Limpiar icono de estado
        self.status_icon.clear()
        
        # Informar por voz que la imagen está lista
        self.voice_system.speak("¡He generado la imagen que solicitaste! Puedes verla en la pantalla y descargarla con el botón de descarga.")
        
        # Restaurar botones
        self.talk_button.setEnabled(True)
        self.talk_button.setText("🎤 Habla conmigo")
        self.send_button.setEnabled(True)
        self.generate_image_button.setEnabled(True)
        self.generate_image_button.setText("🖼️ Generar imagen")



    def _on_image_error(self, error_message):
        """Maneja errores en la generación de imágenes"""
        self.response_text.setHtml(f"{self.response_text.toHtml()}<hr/><p>Lo siento, tuve un problema al generar la imagen: {error_message}</p>")
        self.update_status(f"Error al generar imagen: {error_message}")
        
        # Limpiar icono de estado
        self.status_icon.clear()
        
        # Intentar hablar solo si no es un error de timeout (para evitar bloqueos adicionales)
        if "timeout" not in error_message.lower() and "demasiado tiempo" not in error_message.lower():
            try:
                self.voice_system.speak("Lo siento, tuve un problema al generar la imagen. Por favor intenta con otra descripción.")
            except Exception as e:
                print(f"Error al hablar: {str(e)}")
        
        # Restaurar botones
        self.talk_button.setEnabled(True)
        self.talk_button.setText("🎤 Habla conmigo")
        self.send_button.setEnabled(True)
        self.generate_image_button.setEnabled(True)
        self.generate_image_button.setText("🖼️ Generar imagen")
        
        # Detener el timer de seguridad si está activo
        if hasattr(self, 'safety_timer') and self.safety_timer.isActive():
            self.safety_timer.stop()
            
    def _on_image_timeout(self):
        """Maneja el timeout en la generación de imagen"""
        # Si llegamos aquí, significa que la generación de imagen ha tomado demasiado tiempo
        error_msg = "La generación de imagen está tomando demasiado tiempo. Inténtalo de nuevo con una descripción más simple."
        
        # Mostrar mensaje en la consola para depuración
        print("TIMEOUT: La generación de imagen ha excedido el tiempo límite de 30 segundos.")
        
        # Usar el manejador de errores existente
        self._on_image_error(error_msg)

    def download_image(self, image_path):
        """Permite al usuario descargar una imagen generada"""
        from PyQt6.QtWidgets import QFileDialog
        import shutil
        import os
        
        # Obtener solo el nombre del archivo de la ruta
        file_name = os.path.basename(image_path)
        
        # Abrir diálogo para seleccionar ubicación y nombre
        file_path, _ = QFileDialog.getSaveFileName(
            self,
            "Guardar imagen",
            file_name,
            "Imágenes (*.png *.jpg *.jpeg)"
        )
        
        # Si el usuario canceló la operación
        if not file_path:
            return
        
        try:
            # Copiar el archivo a la ubicación seleccionada
            shutil.copy2(image_path, file_path)
            self.update_status(f"Imagen guardada en: {file_path}")
            
            # Opcional: Informar por voz
            self.voice_system.speak("La imagen se ha guardado exitosamente")
        except Exception as e:
            self.update_status(f"Error al guardar la imagen: {str(e)}")


    def download_last_image(self):
        """Descarga la última imagen generada"""
        if hasattr(self, 'last_generated_image'):
            self.download_image(self.last_generated_image)
        else:
            self.update_status("No hay imagen para descargar")
    
    def resizeEvent(self, event):
        """Actualiza el fondo cuando cambia el tamaño de la ventana"""
        # Si tenemos un fondo configurado, actualizarlo al nuevo tamaño
        bg_file = self.config.get('background_file', 'background.png')
        bg_path = os.path.join(self.resource_path, bg_file)
        
        if os.path.exists(bg_path):
            try:
                # Crear un nuevo pixmap escalado al tamaño actual de la ventana
                pixmap = QPixmap(bg_path)
                palette = QPalette()
                palette.setBrush(QPalette.ColorRole.Window, 
                               QBrush(pixmap.scaled(
                                   self.size(), 
                                   Qt.AspectRatioMode.IgnoreAspectRatio, 
                                   Qt.TransformationMode.SmoothTransformation)))
                self.setPalette(palette)
            except Exception as e:
                print(f"Error al redimensionar el fondo: {e}")
        
        # Llamar al método original para manejar el evento normalmente
        super().resizeEvent(event)
