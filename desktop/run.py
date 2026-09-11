import sys
import os
import subprocess

# Importación segura de pkg_resources y otras dependencias
try:
    from importlib.metadata import distribution, PackageNotFoundError
    HAS_IMPORTLIB_METADATA = True
except ImportError:
    HAS_IMPORTLIB_METADATA = False
    try:
        import pkg_resources
    except ImportError:
        print("Instalando setuptools para pkg_resources...")
        subprocess.check_call([sys.executable, "-m", "pip", "install", "setuptools"])
        import pkg_resources


# Lista de paquetes requeridos
REQUIRED_PACKAGES = [
    'PyQt6',
    'gTTS',
    'pygame',
    'SpeechRecognition',
    'pyaudio',
    'pyttsx3',
    'openai'
]

def check_python_version():
    """Verifica la versión de Python"""
    if sys.version_info < (3, 8):
        print("Se requiere Python 3.8 o superior")
        print(f"Versión actual: {sys.version}")
        return False
    return True
def check_dependencies():
    """Verifica que todas las dependencias estén instaladas"""
    missing = []
    
    for package in REQUIRED_PACKAGES:
        try:
            # Usar importlib.metadata o pkg_resources según disponibilidad
            if HAS_IMPORTLIB_METADATA:
                try:
                    distribution(package)
                except PackageNotFoundError:
                    missing.append(package)
            else:
                try:
                    pkg_resources.get_distribution(package)
                except pkg_resources.DistributionNotFound:
                    missing.append(package)
        except Exception as e:
            print(f"Error al verificar {package}: {e}")
            missing.append(package)

    if missing:
        print("Faltan las siguientes dependencias:")
        for pkg in missing:
            print(f" - {pkg}")
        
        # Preguntar si desea instalarlas
        if input("¿Instalar dependencias faltantes? (s/n): ").lower() == 's':
            for pkg in missing:
                try:
                    subprocess.check_call([sys.executable, "-m", "pip", "install", pkg])
                    print(f"{pkg} instalado correctamente")
                except Exception as e:
                    print(f"Error al instalar {pkg}: {e}")
                    return False
            print("Todas las dependencias instaladas correctamente")
        else:
            print("La aplicación requiere estas dependencias para funcionar")
            return False
    
    return True

def check_structure():
    """Verifica que la estructura de carpetas sea correcta"""
    required_dirs = ['app', 'services', 'utils', 'threads', 'resources']
    missing_dirs = []
    
    for dir_name in required_dirs:
        if not os.path.isdir(dir_name):
            missing_dirs.append(dir_name)
    
    if missing_dirs:
        print("Faltan las siguientes carpetas en la estructura del proyecto:")
        for dir_name in missing_dirs:
            print(f" - {dir_name}")
        
        print("Por favor, asegúrate de que la estructura del proyecto sea correcta.")
        return False
    
    # También podemos verificar archivos críticos
    required_files = [
        'main.py',
        'app/__init__.py',
        'app/assistant.py',
        'services/__init__.py',
        'utils/__init__.py',
        'threads/__init__.py'
    ]
    
    missing_files = []
    for file_path in required_files:
        if not os.path.isfile(file_path):
            missing_files.append(file_path)
    
    if missing_files:
        print("Faltan los siguientes archivos importantes:")
        for file_path in missing_files:
            print(f" - {file_path}")
        
        print("Por favor, asegúrate de que todos los archivos necesarios estén presentes.")
        return False
    
    return True

def check_api_keys():
    """Verifica que las API keys necesarias estén configuradas"""
    # Verificar OpenAI API Key
    if not os.environ.get('OPENAI_API_KEY'):
        # Buscar en archivos de configuración
        config_files = [
            'config.json',  # En la raíz del proyecto
            '.env',         # Archivo .env en la raíz
            os.path.expanduser('~/.config/AsistenteMagico/config.json'),  # Ruta Linux estándar
            os.path.join(os.environ.get('APPDATA', ''), 'AsistenteMagico/config.json'),  # Ruta Windows
            os.path.expanduser('~/Library/Application Support/AsistenteMagico/config.json')  # Ruta macOS
        ]
        
        found = False
        for file in config_files:
            if os.path.exists(file):
                print(f"Encontrada configuración en: {file}")
                # Opcionalmente, podríamos cargar la API key desde aquí
                try:
                    import json
                    with open(file, 'r') as f:
                        if file.endswith('.json'):
                            config = json.load(f)
                            if 'api_key' in config or 'OPENAI_API_KEY' in config:
                                found = True
                                break
                        elif file.endswith('.env'):
                            for line in f:
                                if line.startswith('OPENAI_API_KEY='):
                                    found = True
                                    break
                except Exception as e:
                    print(f"Error al leer archivo de configuración {file}: {e}")
        
        if not found:
            print("No se encontró una API key de OpenAI configurada.")
            print("Por favor, configura tu API key antes de ejecutar la aplicación.")
            print("Opciones: variable de entorno OPENAI_API_KEY o archivo config.json")
            return False
    return True

def check_for_updates():
    """Verifica si hay actualizaciones disponibles"""
    try:
        # Verificar si es un repositorio git
        if not os.path.exists('.git'):
            return False
        
        # Obtener información del repositorio remoto
        subprocess.run(["git", "fetch"], check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        
        # Verificar si hay cambios
        result = subprocess.run(["git", "rev-list", "--count", "HEAD..origin/master"], 
                               check=True, capture_output=True, text=True)
        
        commits_behind = int(result.stdout.strip())
        
        if commits_behind > 0:
            print(f"Hay actualizaciones disponibles ({commits_behind} commits).")
            if input("¿Deseas actualizar ahora? (s/n): ").lower() == 's':
                try:
                    # Intentar pull normal primero
                    result = subprocess.run(["git", "pull", "origin", "master"], 
                                           capture_output=True, text=True)
                    
                    # Si hay error de historias no relacionadas, usar --allow-unrelated-histories
                    if "refusing to merge unrelated histories" in result.stderr:
                        print("Historias no relacionadas detectadas. Utilizando opción especial...")
                        subprocess.run(["git", "pull", "origin", "master"], 
                                      check=True)
                    
                    print("Actualización completada. Reiniciando aplicación...")
                    
                    # Reiniciar la aplicación
                    python = sys.executable
                    os.execl(python, python, *sys.argv)
                    
                except Exception as e:
                    print(f"Error durante la actualización: {e}")
                    print("Continuando con la versión actual...")
            else:
                print("Actualizaciones pospuestas.")
        
        return True
    except Exception as e:
        print(f"Error al verificar actualizaciones: {e}")
        return False


def create_shortcut_linux():
    """Crea un lanzador de aplicación en Linux"""
    try:
        if os.name == 'posix' and not sys.platform.startswith('darwin'):  # Linux
            # Obtener la ruta del escritorio usando xdg-user-dir o fallback a rutas típicas
            desktop_dir = subprocess.getoutput('xdg-user-dir DESKTOP').strip()
            if not os.path.isdir(desktop_dir):
                desktop_dir = os.path.expanduser('~/Escritorio')
                if not os.path.isdir(desktop_dir):
                    desktop_dir = os.path.expanduser('~/Desktop')
                    if not os.path.isdir(desktop_dir):
                        print("No se pudo encontrar el directorio del escritorio.")
                        return False
            
            desktop_file = os.path.join(desktop_dir, "AsistenteMagico.desktop")
            
            with open(desktop_file, 'w') as f:
                f.write(f"""[Desktop Entry]
Type=Application
Name=Asistente Cirilo
Exec={sys.executable} {os.path.abspath("main.py")}
Icon={os.path.abspath("resources/robot.png")}
Terminal=false
Categories=Education;
Comment=Asistente virtual educativo para niños
StartupNotify=true
Version=1.0
""")
            
            # Establecer permisos explícitamente
            os.chmod(desktop_file, 0o755)
            
            print(f"Lanzador creado en: {desktop_file}")
            return True
        else:
            return False
    except Exception as e:
        print(f"Error al crear lanzador en Linux: {e}")
        return False

def create_shortcut_linux_applications_menu():
    """Crea un lanzador en el menú de aplicaciones de Linux"""
    try:
        if os.name == 'posix' and not sys.platform.startswith('darwin'):
            # Directorio de aplicaciones del usuario
            app_dir = os.path.expanduser('~/.local/share/applications')
            
            # Crear el directorio si no existe
            os.makedirs(app_dir, exist_ok=True)
            
            desktop_file = os.path.join(app_dir, "AsistenteMagico.desktop")
            
            with open(desktop_file, 'w') as f:
                f.write(f"""[Desktop Entry]
Type=Application
Name=Asistente Cirilo
Exec={sys.executable} {os.path.abspath("main.py")}
Icon={os.path.abspath("resources/robot.png")}
Terminal=false
Categories=Education;Game;
Comment=Asistente virtual educativo para niños
StartupNotify=true
Version=1.0
""")
            
            os.chmod(desktop_file, 0o755)
            
            # Actualizar la base de datos de aplicaciones
            try:
                subprocess.run(['update-desktop-database', app_dir], check=False)
            except:
                pass
                
            print(f"Aplicación añadida al menú de aplicaciones: {desktop_file}")
            return True
        else:
            return False
    except Exception as e:
        print(f"Error al crear entrada en el menú de aplicaciones: {e}")
        return False

def create_linux_launcher():
    """Crea un script shell ejecutable en el escritorio"""
    try:
        # Determinar la ruta del escritorio
        desktop_dir = subprocess.getoutput('xdg-user-dir DESKTOP').strip()
        if not os.path.isdir(desktop_dir):
            desktop_dir = os.path.expanduser('~/Escritorio')
            if not os.path.isdir(desktop_dir):
                desktop_dir = os.path.expanduser('~/Desktop')
                if not os.path.isdir(desktop_dir):
                    print("No se pudo encontrar el directorio del escritorio.")
                    return False
        
        # Crear el script bash
        script_path = os.path.join(desktop_dir, "AsistenteMagico.sh")
        with open(script_path, 'w') as f:
            f.write(f"""#!/bin/bash
# Asistente Cirilo - Lanzador
cd "{os.path.abspath(os.path.dirname(__file__))}"
"{sys.executable}" "{os.path.abspath("main.py")}"
""")
        
        # Hacer el script ejecutable
        os.chmod(script_path, 0o755)
        
        print(f"Script de lanzamiento creado en: {script_path}")
        print("Para ejecutar, haz doble clic en el archivo o ejecútalo desde terminal.")
        return True
        
    except Exception as e:
        print(f"Error al crear script de lanzamiento: {e}")
        return False

def create_bash_alias():
    """Crea un alias en .bashrc o .zshrc para ejecutar la aplicación"""
    try:
        # Determinar qué shell usa el usuario
        shell = os.environ.get('SHELL', '')
        
        if 'zsh' in shell:
            rc_file = os.path.expanduser('~/.zshrc')
        else:
            rc_file = os.path.expanduser('~/.bashrc')
        
        # Verificar si ya existe el alias
        if os.path.exists(rc_file):
            with open(rc_file, 'r') as f:
                content = f.read()
                if 'alias asistentemagico=' in content:
                    print(f"El alias 'asistentemagico' ya existe en {rc_file}")
                    return True
        
        # Añadir el alias
        with open(rc_file, 'a') as f:
            f.write(f'\n# Alias para Asistente Cirilo\n')
            f.write(f'alias asistentemagico="{sys.executable} {os.path.abspath("main.py")}"\n')
        
        print(f"Alias 'asistentemagico' creado en {rc_file}")
        print("Para usarlo, abre una nueva terminal y escribe: asistentemagico")
        return True
        
    except Exception as e:
        print(f"Error al crear alias: {e}")
        return False

def create_shortcut_linux_all_methods():
    """Intenta crear accesos directos usando todos los métodos disponibles en Linux"""
    success = False
    
    print("\nCreando acceso directo en Linux usando múltiples métodos:")
    
    # Método 1: Archivo .desktop en el escritorio
    try:
        if create_shortcut_linux():
            success = True
    except Exception as e:
        print(f"  - Método .desktop (escritorio): Error - {e}")
    
    # Método 2: Archivo .desktop en el menú de aplicaciones
    try:
        if create_shortcut_linux_applications_menu():
            success = True
    except Exception as e:
        print(f"  - Método .desktop (menú): Error - {e}")
    
    # Método 3: Script bash ejecutable
    try:
        if create_linux_launcher():
            success = True
    except Exception as e:
        print(f"  - Método script shell: Error - {e}")
    
    # Método 4: Alias en .bashrc/.zshrc
    try:
        if create_bash_alias():
            success = True
    except Exception as e:
        print(f"  - Método alias: Error - {e}")
    
    if success:
        print("\n✅ Se crearon uno o más métodos de acceso a la aplicación.")
    else:
        print("\n❌ No se pudo crear ningún acceso directo.")
    
    return success

def create_shortcut():
    """Crea acceso directo según la plataforma"""
    if os.name == 'nt':  # Windows
        return create_shortcut_windows()
    elif sys.platform.startswith('darwin'):  # macOS
        return create_shortcut_macos()
    elif os.name == 'posix':  # Linux/Unix
        return create_shortcut_linux_all_methods()
    else:
        print("Plataforma no reconocida para crear acceso directo.")
        return False

def create_shortcut_windows():
    """Crea un acceso directo en el escritorio para Windows"""
    try:
        if os.name == 'nt':  # Verificar que estamos en Windows
            try:
                import winshell
                from win32com.client import Dispatch
            except ImportError:
                print("Instalando dependencias necesarias...")
                import subprocess
                subprocess.check_call([sys.executable, "-m", "pip", "install", "pywin32", "winshell"])
                import winshell
                from win32com.client import Dispatch
            
            desktop = winshell.desktop()
            path = os.path.join(desktop, "Asistente Cirilo.lnk")
            
            shell = Dispatch('WScript.Shell')
            shortcut = shell.CreateShortCut(path)
            shortcut.Targetpath = sys.executable
            
            # Determinar el archivo principal a ejecutar
            main_path = os.path.abspath("main.py")
            if not os.path.exists(main_path):
                main_path = os.path.abspath("run.py")  # Alternativa si main.py no existe
                
            shortcut.Arguments = f'"{main_path}"'  # Encerrar en comillas por si hay espacios
            shortcut.WorkingDirectory = os.path.abspath(os.path.dirname(__file__))
            
            # Usar el archivo icon.ico que ya existe
            icon_path = os.path.abspath("resources/icon.ico")
            if os.path.exists(icon_path):
                shortcut.IconLocation = icon_path
            else:
                print("Archivo de ícono no encontrado, intentando alternativa...")
                # Intentar usar robot.png como alternativa
                alt_icon = os.path.abspath("resources/robot.png")
                if os.path.exists(alt_icon):
                    shortcut.IconLocation = alt_icon
                    print(f"Usando icono alternativo: {alt_icon}")
                
            shortcut.save()
            
            print("Acceso directo creado en el escritorio.")
            return True
        else:
            print("No es un sistema Windows, omitiendo creación de acceso directo.")
            return False
    except Exception as e:
        print(f"Error al crear acceso directo en Windows: {e}")
        import traceback
        traceback.print_exc()  # Muestra el traceback completo para mejor diagnóstico
        return False

def create_shortcut_macos():
    """Crea un alias en macOS"""
    try:
        if sys.platform.startswith('darwin'):  # macOS
            script = f"""
            tell application "Finder"
                make new alias file to POSIX file "{os.path.abspath("main.py")}" at desktop
                set name of result to "Asistente Cirilo"
            end tell
            """
            subprocess.run(["osascript", "-e", script], check=True)
            print("Alias creado en el escritorio.")
            return True
        else:
            return False
    except Exception as e:
        print(f"Error al crear alias en macOS: {e}")
        return False
def create_shortcut():
    """Crea acceso directo según la plataforma"""
    if os.name == 'nt':  # Windows
        return create_shortcut_windows()
    elif sys.platform.startswith('darwin'):  # macOS
        return create_shortcut_macos()
    elif os.name == 'posix':  # Linux/Unix
        return create_shortcut_linux_all_methods()
    else:
        print("Plataforma no reconocida para crear acceso directo.")
        return False


def main():
    """Función principal"""
    print("=== Asistente Cirilo - Verificador de Instalación ===")
    
    # Verificar versión de Python
    if not check_python_version():
        print("La versión de Python no es compatible.")
        if input("¿Continuar de todos modos? (s/n): ").lower() != 's':
            sys.exit(1)
    
    # Verificar estructura
    if not check_structure():
        print("\nLa estructura del proyecto no es correcta. Por favor, verifica la instalación.")
        if input("¿Continuar de todos modos? (s/n): ").lower() != 's':
            sys.exit(1)
    
    # Verificar dependencias
    if not check_dependencies():
        sys.exit(1)
    
    # Verificar API keys
    if not check_api_keys():
        if input("¿Continuar sin API key configurada? (s/n): ").lower() != 's':
            sys.exit(1)
    
    # Verificar actualizaciones
    check_for_updates()
    
# Preguntar si desea crear acceso directo
if input("¿Crear acceso directo en el escritorio? (s/n): ").lower() == 's':
    # Instalar dependencias específicas para accesos directos según la plataforma
    try:
        if os.name == 'nt':  # Windows
            for pkg in ['winshell', 'pywin32']:
                try:
                    if HAS_IMPORTLIB_METADATA:
                        try:
                            distribution(pkg)
                        except PackageNotFoundError:
                            print(f"Instalando {pkg} para crear acceso directo...")
                            subprocess.check_call([sys.executable, "-m", "pip", "install", pkg])
                    else:
                        try:
                            pkg_resources.get_distribution(pkg)
                        except pkg_resources.DistributionNotFound:
                            print(f"Instalando {pkg} para crear acceso directo...")
                            subprocess.check_call([sys.executable, "-m", "pip", "install", pkg])
                except Exception as e:
                    print(f"Error al verificar/instalar {pkg}: {e}")
                    # Intentar instalarlo de todos modos
                    subprocess.check_call([sys.executable, "-m", "pip", "install", pkg])
        
        if create_shortcut():
            print("Acceso directo creado exitosamente.")
        else:
            print("No se pudo crear el acceso directo.")
    except Exception as e:
        print(f"Error al crear acceso directo: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)

if __name__ == "__main__":
    main()
