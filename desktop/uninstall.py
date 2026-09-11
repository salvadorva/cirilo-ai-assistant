#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Uninstall script for Asistente Infantil
This script removes configuration files and shortcuts to allow a clean reinstall
"""

import os
import sys
import shutil
import ctypes
from pathlib import Path

def is_admin():
    """Check if the script is running with admin privileges (Windows only)"""
    try:
        return ctypes.windll.shell32.IsUserAnAdmin()
    except:
        return False

def remove_config_files():
    """Remove configuration files"""
    print("Eliminando archivos de configuración...")
    
    # Lista de posibles nombres de la aplicación
    app_names = ["AsistenteInfantil", "AsistenteMagico", "Asistente Infantil", "Asistente Magico", "AsistenteCircilo", "Asistente Cirilo"]
    configs_removed = False
    
    for app_name in app_names:
        # Determinar la ruta de configuración según el sistema operativo
        if os.name == 'nt':  # Windows
            config_dir = os.path.join(os.environ['APPDATA'], app_name)
        else:  # Linux/Mac
            config_dir = os.path.join(os.path.expanduser('~'), '.config', app_name)
        
        # Verificar si existe el directorio de configuración
        if os.path.exists(config_dir):
            try:
                shutil.rmtree(config_dir)
                print(f"✅ Configuración eliminada exitosamente: {config_dir}")
                configs_removed = True
            except Exception as e:
                print(f"❌ Error al eliminar la configuración {app_name}: {e}")
    
    if not configs_removed:
        print("ℹ️ No se encontró ninguna configuración para eliminar.")
    
    return True

def remove_shortcuts():
    """Remove desktop shortcuts"""
    print("Eliminando accesos directos...")
    shortcuts_removed = False
    
    if os.name == 'nt':  # Windows
        try:
            # Lista de posibles nombres de accesos directos
            shortcut_names = ["Asistente Cirilo.lnk", "Asistente Infantil.lnk", "AsistenteMagico.lnk"]
            
            # Obtener rutas de escritorio (personal y público)
            desktop_paths = []
            
            # Escritorio personal (inglés y español)
            user_desktop = os.path.join(os.path.expanduser('~'), 'Desktop')
            if not os.path.exists(user_desktop):
                user_desktop = os.path.join(os.path.expanduser('~'), 'Escritorio')
            if os.path.exists(user_desktop):
                desktop_paths.append(user_desktop)
            
            # Escritorio público (inglés y español)
            try:
                public_desktop = os.path.join(os.environ['PUBLIC'], 'Desktop')
                if not os.path.exists(public_desktop):
                    public_desktop = os.path.join(os.environ['PUBLIC'], 'Escritorio')
                if os.path.exists(public_desktop):
                    desktop_paths.append(public_desktop)
            except KeyError:
                # La variable PUBLIC puede no existir
                pass
            
            # Buscar y eliminar accesos directos
            for desktop in desktop_paths:
                for name in shortcut_names:
                    shortcut_path = os.path.join(desktop, name)
                    if os.path.exists(shortcut_path):
                        os.remove(shortcut_path)
                        print(f"✅ Acceso directo eliminado: {shortcut_path}")
                        shortcuts_removed = True
            
            if not shortcuts_removed:
                print("ℹ️ No se encontraron accesos directos en Windows para eliminar.")
                
        except Exception as e:
            print(f"❌ Error al eliminar accesos directos en Windows: {e}")
    
    elif sys.platform == 'darwin':  # macOS
        try:
            # Lista de posibles nombres de aplicaciones
            app_names = ["Asistente Cirilo.app", "Asistente Infantil.app", "AsistenteMagico.app"]
            
            for name in app_names:
                app_path = os.path.join('/Applications', name)
                if os.path.exists(app_path):
                    shutil.rmtree(app_path)
                    print(f"✅ Aplicación eliminada: {app_path}")
                    shortcuts_removed = True
            
            if not shortcuts_removed:
                print("ℹ️ No se encontraron aplicaciones en macOS para eliminar.")
                
        except Exception as e:
            print(f"❌ Error al eliminar aplicaciones en macOS: {e}")
    
    else:  # Linux
        try:
            # Lista de posibles nombres de archivos .desktop
            desktop_file_names = ["asistente-cirilo.desktop", "asistente-infantil.desktop", "AsistenteMagico.desktop"]
            
            # Rutas para buscar
            desktop_path = os.path.expanduser('~')
            for name in ["Desktop", "Escritorio"]:
                if os.path.exists(os.path.join(desktop_path, name)):
                    desktop_path = os.path.join(desktop_path, name)
                    break
            
            applications_path = os.path.join(os.path.expanduser('~'), '.local', 'share', 'applications')
            
            # Buscar y eliminar archivos .desktop en el escritorio
            for name in desktop_file_names:
                file_path = os.path.join(desktop_path, name)
                if os.path.exists(file_path):
                    os.remove(file_path)
                    print(f"✅ Acceso directo eliminado: {file_path}")
                    shortcuts_removed = True
            
            # Buscar y eliminar archivos .desktop en el menú de aplicaciones
            for name in desktop_file_names:
                file_path = os.path.join(applications_path, name)
                if os.path.exists(file_path):
                    os.remove(file_path)
                    print(f"✅ Acceso directo del menú de aplicaciones eliminado: {file_path}")
                    shortcuts_removed = True
            
            # Eliminar scripts de lanzamiento
            script_names = ["AsistenteMagico.sh", "AsistenteInfantil.sh", "AsistenteCircilo.sh"]
            for name in script_names:
                script_path = os.path.join(desktop_path, name)
                if os.path.exists(script_path):
                    os.remove(script_path)
                    print(f"✅ Script de lanzamiento eliminado: {script_path}")
                    shortcuts_removed = True
            
            # Eliminar alias del .bashrc o .zshrc
            shell_rc_files = []
            for rc in [".bashrc", ".zshrc"]:
                rc_path = os.path.join(os.path.expanduser('~'), rc)
                if os.path.exists(rc_path):
                    shell_rc_files.append(rc_path)
            
            alias_patterns = ["alias asistentemagico=", "alias asistentecirilo=", "alias asistenteinfantil="]
            for rc_file in shell_rc_files:
                try:
                    # Leer el archivo
                    with open(rc_file, 'r', encoding='utf-8') as f:
                        lines = f.readlines()
                    
                    # Filtrar líneas que contienen los alias
                    new_lines = []
                    aliases_removed = False
                    for line in lines:
                        if not any(pattern in line.lower() for pattern in alias_patterns):
                            new_lines.append(line)
                        else:
                            aliases_removed = True
                    
                    # Si se encontraron alias, escribir el archivo sin ellos
                    if aliases_removed:
                        with open(rc_file, 'w', encoding='utf-8') as f:
                            f.writelines(new_lines)
                        print(f"✅ Alias eliminados de {rc_file}")
                        shortcuts_removed = True
                except Exception as e:
                    print(f"❌ Error al procesar {rc_file}: {e}")
            
            if not shortcuts_removed:
                print("ℹ️ No se encontraron accesos directos en Linux para eliminar.")
                
        except Exception as e:
            print(f"❌ Error al eliminar accesos directos en Linux: {e}")
    
    return shortcuts_removed

def main():
    """Main function"""
    print("="*50)
    print("  DESINSTALADOR DE ASISTENTE INFANTIL CIRILO")
    print("="*50)
    print("\nEste programa eliminará la configuración y accesos directos")
    print("para permitir una reinstalación limpia de la aplicación.\n")
    
    # En Windows, verificar si se ejecuta como administrador
    if os.name == 'nt' and not is_admin():
        print("⚠️  ADVERTENCIA: No se está ejecutando como administrador.")
        print("    Algunos archivos podrían no eliminarse correctamente.")
        input("Presiona Enter para continuar de todos modos o Ctrl+C para cancelar...")
    
    # Eliminar configuración
    config_removed = remove_config_files()
    
    # Eliminar accesos directos
    shortcuts_removed = remove_shortcuts()
    
    print("\n" + "="*50)
    if config_removed or shortcuts_removed:
        print("✅ Desinstalación completada exitosamente.")
        print("   Ahora puedes reinstalar la aplicación.")
    else:
        print("⚠️ No se encontraron elementos para desinstalar.")
    
    print("="*50)
    
    # Mantener la ventana abierta en Windows
    if os.name == 'nt':
        input("\nPresiona Enter para salir...")

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n\nOperación cancelada por el usuario.")
        sys.exit(1)
    except Exception as e:
        print(f"\n\n❌ Error inesperado: {e}")
        if os.name == 'nt':
            input("\nPresiona Enter para salir...")
        sys.exit(1)
