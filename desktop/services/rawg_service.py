# En services/rawg_service.py
import logging
import requests

class RAWGService:
    def __init__(self, api_key):
        self.base_url = "https://api.rawg.io/api/"
        self.api_key = api_key
        logging.info("Servicio RAWG inicializado")

    def get_game_info(self, game_name):
   
        logging.info(f"Consultando RAWG para el juego: {game_name}")
        params = {
            'key': self.api_key,
            'search': game_name
        }
        try:
            response = requests.get(f"{self.base_url}games", params=params)
            logging.info(f"Respuesta de RAWG recibida: {response.status_code}")
            if response.status_code == 200:
                data = response.json()
                
                # Log de primeros resultados para diagnóstico (solo claves principales)
                if 'results' in data and data['results']:
                    resultado_ejemplo = data['results'][0]
                    logging.info(f"Ejemplo de campos disponibles: {list(resultado_ejemplo.keys())}")
                
                logging.info(f"Datos de RAWG procesados, resultados: {len(data.get('results', []))}")
                return data
            else:
                logging.error(f"Error en la respuesta de RAWG: {response.status_code}")
                return None
        except Exception as e:
            logging.error(f"Error al consultar RAWG: {str(e)}")
            return None

