# main.py
import sys
import os
import io
from PyQt6.QtWidgets import QApplication
from app.assistant import AsistenteInfantil

# Configuración para evitar ventanas emergentes no deseadas
os.environ["QT_LOGGING_TO_CONSOLE"] = "1"
os.environ["QT_DEBUG_PLUGINS"] = "0"
os.environ["QT_NO_DEBUG_OUTPUT"] = "1"
os.environ["QT_NO_WARNING_OUTPUT"] = "1"

# Clase para redirigir la salida estándar y de error
class NullOutput(io.TextIOBase):
    def write(self, s):
        pass
    
    def flush(self):
        pass

# Variable global para mantener referencia a la ventana principal
main_window = None

if __name__ == "__main__":
    try:
        # Redirigir stdout y stderr a NullOutput para evitar ventanas emergentes
        sys.stdout = NullOutput()
        sys.stderr = NullOutput()
        
        # Crear la aplicación Qt
        app = QApplication(sys.argv)
        app.setQuitOnLastWindowClosed(True)
        
        # Crear la ventana principal y guardar referencia global
        main_window = AsistenteInfantil()
        
        # Asegurar que la ventana principal tenga el foco
        main_window.show()
        main_window.raise_()
        main_window.activateWindow()
        
        # Ejecutar el bucle principal de la aplicación
        sys.exit(app.exec())
    except Exception as e:
        # Restaurar stdout/stderr para reportar errores críticos
        sys.stdout = sys.__stdout__
        sys.stderr = sys.__stderr__
        print(f"Error iniciando la aplicación: {e}")
        import traceback
        traceback.print_exc()
