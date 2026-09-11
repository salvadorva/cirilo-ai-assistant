# services/openai_service.py
import openai
import base64
import os

class OpenAIService:
    def __init__(self, api_key=None):
        self.client = None
        if api_key:
            self.set_api_key(api_key)

    def set_api_key(self, api_key):
        """Configura o actualiza la API key para OpenAI"""
        self.client = openai.OpenAI(api_key=api_key)
        return self.client is not None

    def has_valid_client(self):
        """Verifica si hay un cliente configurado"""
        return self.client is not None

    def get_response(self, query, conversation_history=None, system_prompt=None):
        """
        Obtiene una respuesta de OpenAI usando el historial de conversación
        """
        if not self.has_valid_client():
            raise ValueError("Cliente OpenAI no configurado. Se requiere una API key válida.")

        # Usar valores predeterminados si no se proporcionan
        conversation_history = conversation_history or []
        system_prompt = system_prompt or "Eres un asistente amigable y útil para niños. Explica conceptos de manera sencilla y divertida. Mantén las respuestas breves y fáciles de entender."

        # Preparar mensajes con el contexto
        messages = [{"role": "system", "content": system_prompt}]
        messages.extend(conversation_history)
        messages.append({"role": "user", "content": query})

        try:
            # Realizar la consulta a la API
            response = self.client.chat.completions.create(
                model="gpt-3.5-turbo",
                messages=messages,
                max_tokens=250
            )
            return response.choices[0].message.content
        except Exception as e:
            raise Exception(f"Error al obtener respuesta de OpenAI: {str(e)}")
    
    def generate_image(self, prompt, save_path=None, size="1024x1024"):
        """
        Genera una imagen usando DALL-E basada en el prompt proporcionado
        
        Args:
            prompt (str): Descripción de la imagen a generar
            save_path (str, optional): Ruta donde guardar la imagen. Si es None, solo retorna la URL
            size (str, optional): Tamaño de la imagen. Por defecto "1024x1024"
            
        Returns:
            dict: Diccionario con la URL de la imagen y la ruta local si se guardó
        """
        if not self.has_valid_client():
            raise ValueError("Cliente OpenAI no configurado. Se requiere una API key válida.")
        
        try:
            # Intentar usar DALL-E 2 que es más rápido y estable
            try:
                # Generar imagen con DALL-E 2
                response = self.client.images.generate(
                    model="dall-e-2",  # Usar DALL-E 2 que es más rápido
                    prompt=prompt,
                    size=size,
                    n=1  # Generar una sola imagen
                )
            except Exception as e:
                # Si falla DALL-E 2, intentar con DALL-E 3 pero con un prompt más corto
                # Acortar el prompt si es muy largo
                short_prompt = prompt[:500] if len(prompt) > 500 else prompt
                response = self.client.images.generate(
                    model="dall-e-3",
                    prompt=short_prompt,
                    size=size,
                    quality="standard",
                    n=1
                )
            
            # Obtener URL de la imagen
            image_url = response.data[0].url
            
            # Si se proporcionó una ruta, descargar y guardar la imagen
            local_path = None
            if save_path:
                try:
                    import requests
                    from datetime import datetime
                    
                    # Crear directorio si no existe
                    os.makedirs(save_path, exist_ok=True)
                    
                    # Nombre de archivo con timestamp para evitar duplicados
                    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                    file_path = os.path.join(save_path, f"imagen_{timestamp}.png")
                    
                    # Descargar imagen con timeout para evitar bloqueos
                    response = requests.get(image_url, timeout=10)
                    with open(file_path, "wb") as f:
                        f.write(response.content)
                    
                    local_path = file_path
                except ImportError:
                    # Error silencioso si no está instalada la biblioteca requests
                    local_path = None
                except Exception as e:
                    # Error silencioso al guardar la imagen localmente
                    local_path = None
            
            return {
                "url": image_url,
                "local_path": local_path
            }
            
        except Exception as e:
            # Capturar el error sin imprimir
            raise Exception(f"Error al generar imagen: {str(e)}")
            
