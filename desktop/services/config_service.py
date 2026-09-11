# services/config_service.py
import os
import json

class ConfigService:
    def __init__(self, app_name="AsistenteInfantil"):
        self.app_name = app_name
        self.config = self.load_config()
    
    def get_config_file(self):
        """Devuelve la ruta al archivo de configuración"""
        if os.name == 'nt':  # Windows
            app_data = os.path.join(os.environ['APPDATA'], self.app_name)
        else:  # Linux/Mac
            app_data = os.path.join(os.path.expanduser('~'), '.config', self.app_name)
        
        # Asegurar que el directorio existe
        os.makedirs(app_data, exist_ok=True)
        
        return os.path.join(app_data, 'config.json')
    
    def load_config(self):
        """Carga la configuración desde el archivo"""
        config_file = self.get_config_file()
        if os.path.exists(config_file):
            try:
                with open(config_file, 'r', encoding='utf-8') as f:
                    return json.load(f)
            except Exception as e:
                print(f"Error al cargar la configuración: {e}")
        return {}
    
    def save_config(self, config_data):
        """Guarda la configuración en el archivo"""
        config_file = self.get_config_file()
        try:
            # Asegurar que el directorio existe
            os.makedirs(os.path.dirname(config_file), exist_ok=True)
            
            with open(config_file, 'w', encoding='utf-8') as f:
                json.dump(config_data, f, indent=4)
            return True
        except Exception as e:
            print(f"Error al guardar la configuración: {e}")
            return False
    
    def get(self, key, default=None):
        """Obtiene un valor de configuración"""
        return self.config.get(key, default)
    
    def set(self, key, value):
        """Establece un valor de configuración y lo guarda"""
        self.config[key] = value
        return self.save_config(self.config)
    
    def update(self, config_dict):
        """Actualiza múltiples valores de configuración y los guarda"""
        self.config.update(config_dict)
        return self.save_config(self.config)
