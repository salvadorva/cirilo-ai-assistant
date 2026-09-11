# Método para manejar la generación de imágenes con timeout
def _process_image_request(self, prompt):
    """Procesa la solicitud de generar una imagen"""
    # Actualizar estado
    self.update_status("Generando imagen... Esto puede tomar un momento")
    
    # Deshabilitar botones mientras se genera
    self.send_button.setEnabled(False)
    self.talk_button.setEnabled(False)
    self.generate_image_button.setEnabled(False)
    self.generate_image_button.setText("🖼️ Generando...")
    
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
    try:
        self.voice_system.speak("¡He generado la imagen que solicitaste! Puedes verla en la pantalla y descargarla con el botón de descarga.")
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
