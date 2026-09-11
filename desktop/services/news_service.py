import logging
import requests

class NewsService:
    def __init__(self, api_key=None):
        self.base_url = "https://newsapi.org/v2/"
        self.api_key = api_key
        logging.info("Servicio NewsAPI inicializado")

    def set_api_key(self, api_key):
        """Configura o actualiza la API key para NewsAPI"""
        self.api_key = api_key
        return self.api_key is not None

    def get_latest_news(self, query, max_results=5, language="en"):
        """
        Obtiene las últimas noticias relacionadas con la consulta.
        
        Args:
            query (str): Términos a buscar en las noticias
            max_results (int): Número máximo de resultados a devolver
            language (str): Código de idioma para las noticias (default: "en")
            
        Returns:
            dict: Diccionario con los resultados de la búsqueda, o None si hay un error
        """
        if not self.api_key:
            logging.error("API key de NewsAPI no configurada")
            return None
            
        logging.info(f"Consultando noticias para: {query}")
        
        # Construir los parámetros de búsqueda
        params = {
            'q': query,
            'apiKey': self.api_key,
            'language': language,
            'sortBy': 'publishedAt',
            'pageSize': max_results
        }
        
        try:
            # Realizar la consulta a la API
            response = requests.get(f"{self.base_url}everything", params=params)
            logging.info(f"Respuesta de NewsAPI recibida: {response.status_code}")
            
            if response.status_code == 200:
                data = response.json()
                num_articles = len(data.get('articles', []))
                logging.info(f"Noticias encontradas: {num_articles}")
                
                # Loguear ejemplo de estructura (para depuración)
                if num_articles > 0:
                    article_keys = list(data['articles'][0].keys())
                    logging.info(f"Campos de artículo disponibles: {article_keys}")
                
                return data
            else:
                logging.error(f"Error en la respuesta de NewsAPI: {response.status_code}")
                error_data = response.json() if response.headers.get('content-type') == 'application/json' else {}
                logging.error(f"Detalles del error: {error_data}")
                return None
                
        except Exception as e:
            logging.error(f"Error al consultar NewsAPI: {str(e)}")
            return None
