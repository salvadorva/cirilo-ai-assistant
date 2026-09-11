# style_loader.py
import os

def load_stylesheet():
    """Carga los estilos desde el archivo CSS"""
    script_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))  # Subir dos niveles
    css_file = os.path.join(script_dir, 'resources', 'styles.css')
    
    if os.path.exists(css_file):
        with open(css_file, 'r', encoding='utf-8') as f:
            return f.read()
    else:
        print(f"No se pudo encontrar el archivo de estilos: {css_file}")
        return ""