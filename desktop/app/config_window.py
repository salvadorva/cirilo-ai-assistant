# config_window.py
import os
import json
from PyQt6.QtWidgets import (QDialog, QVBoxLayout, QHBoxLayout, QLabel, QLineEdit, 
                            QPushButton, QTextEdit, QGroupBox, QFormLayout, QComboBox,
                            QCheckBox, QTabWidget, QWidget, QScrollArea)
from PyQt6.QtCore import Qt
from PyQt6.QtGui import QPixmap

class ConfigWindow(QDialog):
    def __init__(self, parent=None):
        super().__init__(parent)
        self.setWindowTitle("Configuración del Asistente")
        self.setMinimumWidth(500)
        self.setup_ui()
        self.load_config()
    
    def setup_ui(self):
        """Configura la interfaz de usuario para la ventana de configuración"""
        self.setWindowTitle("Configuración del Asistente Cirilo")
        self.setMinimumWidth(600)
        self.setMinimumHeight(500)
        
        main_layout = QVBoxLayout(self)
        main_layout.setContentsMargins(15, 15, 15, 15)
        main_layout.setSpacing(10)
        
        # Estilo general
        self.setStyleSheet("""
            QDialog {
                background-color: #f5f9ff;
            }
            QTabWidget::pane {
                border: 1px solid #ccddff;
                border-radius: 6px;
                background-color: white;
            }
            QTabBar::tab {
                background-color: #e1e8f0;
                border: 1px solid #ccddff;
                border-bottom: none;
                border-top-left-radius: 4px;
                border-top-right-radius: 4px;
                padding: 8px 12px;
                margin-right: 2px;
            }
            QTabBar::tab:selected {
                background-color: white;
                border-bottom: 1px solid white;
            }
            QGroupBox {
                font-weight: bold;
                border: 1px solid #ccddff;
                border-radius: 6px;
                margin-top: 12px;
                padding-top: 10px;
            }
            QGroupBox::title {
                subcontrol-origin: margin;
                left: 10px;
                padding: 0 5px 0 5px;
            }
            QPushButton {
                background-color: #5b9bd5;
                color: white;
                border-radius: 4px;
                padding: 8px 12px;
                font-weight: bold;
                border: none;
            }
            QPushButton:hover {
                background-color: #4a8bc6;
            }
            QPushButton:pressed {
                background-color: #3a7ab6;
                padding-top: 9px;
                padding-bottom: 7px;
            }
            QLineEdit, QTextEdit, QComboBox {
                padding: 6px;
                border: 1px solid #ccddff;
                border-radius: 4px;
                background-color: white;
            }
            QComboBox::drop-down {
                border: none;
                width: 20px;
            }
            QComboBox:hover {
                background-color: #f0f4f8;
                color: #333333;
            }
            QComboBox QAbstractItemView {
                background-color: white;
                color: #333333;
                selection-background-color: #e1e8f0;
                selection-color: #333333;
            }
            QLabel[class="preview"] {
                border: 1px solid #ccddff;
                background-color: #f0f4f8;
                border-radius: 4px;
                padding: 5px;
            }
        """)
        
        # Crear pestañas para organizar mejor la configuración
        tab_widget = QTabWidget()
        
        # Pestaña de configuración general
        general_tab = QWidget()
        general_layout = QVBoxLayout(general_tab)
        
        # Grupo de configuración personal
        personal_group = QGroupBox("Configuración personal")
        personal_layout = QFormLayout(personal_group)
        
        self.name_input = QLineEdit()
        self.name_input.setPlaceholderText("Nombre del usuario (ej: Juan)")
        personal_layout.addRow("Nombre:", self.name_input)
        
        general_layout.addWidget(personal_group)
        
        # Grupo de configuración de OpenAI
        openai_group = QGroupBox("Configuración de OpenAI")
        openai_layout = QFormLayout(openai_group)
        
        self.api_key_input = QLineEdit()
        self.api_key_input.setPlaceholderText("sk-...")
        self.api_key_input.setEchoMode(QLineEdit.EchoMode.Password)  # Ocultar la API key
        openai_layout.addRow("API Key:", self.api_key_input)
        
        general_layout.addWidget(openai_group)
        
        # Grupo de configuración del prompt
        prompt_group = QGroupBox("Configuración del prompt")
        prompt_layout = QVBoxLayout(prompt_group)
        
        prompt_description = QLabel("Define cómo quieres que se comporte el asistente:")
        prompt_description.setWordWrap(True)
        prompt_layout.addWidget(prompt_description)
        
        self.prompt_input = QTextEdit()
        self.prompt_input.setPlaceholderText("Eres un asistente amigable y útil para niños. Explica conceptos de manera sencilla y divertida. Mantén las respuestas breves y fáciles de entender.")
        self.prompt_input.setMinimumHeight(100)
        prompt_layout.addWidget(self.prompt_input)
        
        general_layout.addWidget(prompt_group)
        
        # Agregar pestaña general
        tab_widget.addTab(general_tab, "General")
        
        # Pestaña de personalización de apariencia
        appearance_tab = QWidget()
        appearance_layout = QVBoxLayout(appearance_tab)
        
        # Grupo de configuración de apariencia
        appearance_group = QGroupBox("Personalización de apariencia")
        appearance_form = QFormLayout(appearance_group)
        
        # Título para la sección de personalización
        appearance_label = QLabel("Personalización de la apariencia")
        appearance_label.setStyleSheet("font-weight: bold; color: #4a6fa5;")
        appearance_form.addRow(appearance_label)
        
        # Selección de fondo
        self.background_combo = QComboBox()
        self.background_combo.addItem("Default", "background.png")
        self.background_combo.addItem("Alternativo", "background1.png")
        appearance_form.addRow("Fondo:", self.background_combo)
        
        # Vista previa del fondo
        self.background_preview = QLabel()
        self.background_preview.setProperty("class", "preview")
        self.background_preview.setFixedSize(200, 120)
        self.background_preview.setScaledContents(True)
        self.background_preview.setAlignment(Qt.AlignmentFlag.AlignCenter)
        appearance_form.addRow("Vista previa:", self.background_preview)
        
        # Selección de personaje (robot)
        self.robot_combo = QComboBox()
        self.robot_combo.addItem("Robot Default", "robot.png")
        self.robot_combo.addItem("Robot Alternativo", "robot1.png")
        appearance_form.addRow("Personaje:", self.robot_combo)
        
        # Vista previa del robot
        self.robot_preview = QLabel()
        self.robot_preview.setProperty("class", "preview")
        self.robot_preview.setFixedSize(100, 100)
        self.robot_preview.setScaledContents(True)
        self.robot_preview.setAlignment(Qt.AlignmentFlag.AlignCenter)
        appearance_form.addRow("Vista previa:", self.robot_preview)
        
        # Conectar eventos para actualizar vistas previas
        self.background_combo.currentIndexChanged.connect(self.update_previews)
        self.robot_combo.currentIndexChanged.connect(self.update_previews)
        
        appearance_layout.addWidget(appearance_group)
        
        # Agregar pestaña de apariencia
        tab_widget.addTab(appearance_tab, "Apariencia")
        
        # Agregar el widget de pestañas al layout principal
        main_layout.addWidget(tab_widget)
        
        # Botones de guardar y cancelar
        button_layout = QHBoxLayout()
        
        self.cancel_button = QPushButton("Cancelar")
        self.cancel_button.clicked.connect(self.reject)
        
        self.save_button = QPushButton("Guardar configuración")
        self.save_button.clicked.connect(self.save_config)
        
        button_layout.addWidget(self.cancel_button)
        button_layout.addWidget(self.save_button)
        
        main_layout.addLayout(button_layout)
    
    def update_previews(self):
        """Actualiza las vistas previas de fondo y robot"""
        # Obtener la ruta base de los recursos
        resource_path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'resources')
        
        # Actualizar vista previa de fondo
        bg_file = self.background_combo.currentData()
        bg_path = os.path.join(resource_path, bg_file)
        if os.path.exists(bg_path):
            self.background_preview.setPixmap(QPixmap(bg_path))
        else:
            self.background_preview.setText("Vista previa no disponible")
        
        # Actualizar vista previa de robot
        robot_file = self.robot_combo.currentData()
        robot_path = os.path.join(resource_path, robot_file)
        if os.path.exists(robot_path):
            self.robot_preview.setPixmap(QPixmap(robot_path))
        else:
            self.robot_preview.setText("Vista previa no disponible")
    
    # Ya no necesitamos este método porque eliminamos el checkbox
    # Lo mantenemos vacío por si alguna parte del código lo llama
    def toggle_appearance_options(self):
        """Método mantenido por compatibilidad"""
        pass
        
    def load_config(self):
        """Carga la configuración desde el archivo"""
        config_file = self.get_config_file()
        if os.path.exists(config_file):
            try:
                with open(config_file, 'r', encoding='utf-8') as f:
                    config = json.load(f)
                    
                    self.name_input.setText(config.get('user_name', ''))
                    self.api_key_input.setText(config.get('api_key', ''))
                    self.prompt_input.setText(config.get('system_prompt', ''))
                    
                    # Ya no necesitamos cargar use_custom_appearance porque lo eliminamos
                    
                    bg_file = config.get('background_file', 'background.png')
                    for i in range(self.background_combo.count()):
                        if self.background_combo.itemData(i) == bg_file:
                            self.background_combo.setCurrentIndex(i)
                            break
                    
                    robot_file = config.get('robot_file', 'robot.png')
                    for i in range(self.robot_combo.count()):
                        if self.robot_combo.itemData(i) == robot_file:
                            self.robot_combo.setCurrentIndex(i)
                            break
                    
                    # Actualizar vistas previas y estado de los controles
                    self.update_previews()
            except Exception as e:
                print(f"Error al cargar la configuración: {e}")
        else:
            # Configuración por defecto
            self.update_previews()
    
    def save_config(self):
        """Guarda la configuración en un archivo JSON"""
        config = {
            'user_name': self.name_input.text().strip(),
            'api_key': self.api_key_input.text().strip(),
            'system_prompt': self.prompt_input.toPlainText().strip(),
            
            # Guardar configuración de apariencia (sin depender de use_custom_appearance)
            'background_file': self.background_combo.currentData(),
            'robot_file': self.robot_combo.currentData()
        }
        
        config_file = self.get_config_file()
        os.makedirs(os.path.dirname(config_file), exist_ok=True)
        
        try:
            with open(config_file, 'w', encoding='utf-8') as f:
                json.dump(config, f, ensure_ascii=False, indent=2)
            self.accept()  # Cierra el diálogo con aceptación
        except Exception as e:
            print(f"Error al guardar la configuración: {e}")
    
    def get_config_file(self):
        """Devuelve la ruta al archivo de configuración"""
        # Usar un directorio apropiado según el sistema operativo
        if os.name == 'nt':  # Windows
            app_data = os.path.join(os.environ['APPDATA'], 'AsistenteInfantil')
        else:  # Linux/Mac
            app_data = os.path.join(os.path.expanduser('~'), '.config', 'AsistenteInfantil')
        
        return os.path.join(app_data, 'config.json')
