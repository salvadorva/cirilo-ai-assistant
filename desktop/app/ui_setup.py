# app/ui_setup.py
from PyQt6.QtWidgets import (QVBoxLayout, QHBoxLayout, QLabel, QFrame, 
                            QPushButton, QTextBrowser, QWidget, 
                            QLineEdit)  # Añadir QLineEdit
from PyQt6.QtCore import Qt
from PyQt6.QtGui import QPixmap, QPalette, QBrush
import os

def setup_ui(assistant, central_widget):
    """Configura la interfaz de usuario del asistente"""
    # Cargar estilos desde archivo
    from utils.style_loader import load_stylesheet
    assistant.setStyleSheet(load_stylesheet())
    
    # Configuración básica de la ventana
    assistant.setWindowTitle("Asistente Cirilo")
    assistant.setMinimumSize(800, 600)  # Tamaño mínimo adaptado a pantallas más pequeñas
    
    # Configurar fondo según la configuración (siempre usar el fondo seleccionado)
    bg_file = assistant.config.get('background_file', 'background.png')
    bg_path = os.path.join(assistant.resource_path, bg_file)
    
    # Asegurarse de que exista el archivo de fondo, si no, usar el predeterminado
    if not os.path.exists(bg_path):
        bg_file = 'background.png'  # Usar el fondo predeterminado
        bg_path = os.path.join(assistant.resource_path, bg_file)
    
    # Aplicar el fondo como imagen de fondo
    if os.path.exists(bg_path):
        # Guardar una referencia al pixmap para evitar que sea eliminado por el recolector de basura
        assistant.bg_pixmap = QPixmap(bg_path)
        palette = QPalette()
        palette.setBrush(QPalette.ColorRole.Window, 
                        QBrush(assistant.bg_pixmap.scaled(
                            assistant.size(), 
                            Qt.AspectRatioMode.IgnoreAspectRatio, 
                            Qt.TransformationMode.SmoothTransformation)))
        assistant.setPalette(palette)
        
        # Configurar para que el fondo se redimensione con la ventana
        assistant.setAutoFillBackground(True)
    
    # Cargar íconos
    assistant.icon_listening = None
    assistant.icon_thinking = None
    
    listening_path = os.path.join(assistant.resource_path, 'listening.png')
    thinking_path = os.path.join(assistant.resource_path, 'thinking.png')
    
    if os.path.exists(listening_path):
        assistant.icon_listening = QPixmap(listening_path)
    
    if os.path.exists(thinking_path):
        assistant.icon_thinking = QPixmap(thinking_path)
    
    # Layout principal con márgenes adecuados
    main_layout = QVBoxLayout(central_widget)
    main_layout.setContentsMargins(10, 10, 10, 10)  # Márgenes reducidos para pantallas pequeñas
    main_layout.setSpacing(5)  # Espaciado reducido para ahorrar espacio vertical
    
    # Contenedor principal para dar un aspecto más refinado
    main_container = QFrame()
    main_container.setObjectName("main_container")
    main_container.setStyleSheet("background-color: rgba(245, 245, 245, 0.4); border-radius: 15px;")
    main_container_layout = QVBoxLayout(main_container)
    main_container_layout.setContentsMargins(10, 10, 10, 10)
    main_container_layout.setSpacing(8)
    
    # Layout para título y personaje - completamente transparente
    header_layout = QVBoxLayout()  # Cambiado a layout vertical
    header_layout.setContentsMargins(10, 0, 10, 0)  # Márgenes reducidos para el encabezado
    header_layout.setSpacing(0)  # Sin espacio entre elementos
    
    # Layout horizontal para título y personaje
    title_row = QHBoxLayout()
    title_row.setContentsMargins(0, 0, 0, 0)  # Sin márgenes
    
    # Título (completamente transparente)
    title_label = QLabel("Asistente Cirilo")
    title_label.setObjectName("title_label")
    title_label.setAlignment(Qt.AlignmentFlag.AlignCenter)
    title_label.setStyleSheet("background-color: transparent;")
    title_row.addWidget(title_label, 3)  # Proporción de 3
    
    # Personaje (robot o asistente) según la configuración - completamente transparente
    assistant.character_label = QLabel()
    assistant.character_label.setStyleSheet("background-color: transparent;")
    robot_file = assistant.config.get('robot_file', 'robot.png')
    robot_path = os.path.join(assistant.resource_path, robot_file)
    
    if os.path.exists(robot_path):
        assistant.character_pixmap = QPixmap(robot_path)
        assistant.character_label.setPixmap(assistant.character_pixmap.scaled(
            120, 120, Qt.AspectRatioMode.KeepAspectRatio, Qt.TransformationMode.SmoothTransformation))  # Aumentar tamaño
    assistant.character_label.setAlignment(Qt.AlignmentFlag.AlignRight | Qt.AlignmentFlag.AlignVCenter)
    title_row.addWidget(assistant.character_label, 1)  # Proporción de 1
    
    # Añadir la fila del título al layout del encabezado con margen inferior reducido
    header_layout.addLayout(title_row)
    
    # Añadir mensaje de bienvenida junto al título para ahorrar espacio vertical
    # Usamos un layout horizontal para posicionar el mensaje más a la izquierda
    welcome_layout = QHBoxLayout()
    welcome_layout.setContentsMargins(0, -5, 0, 0)  # Margen superior negativo para subir el texto
    welcome_layout.setSpacing(0)  # Sin espaciado
    
    # Añadir espacio a la izquierda (menos que antes para moverlo más a la izquierda)
    welcome_layout.addSpacing(20)
    
    welcome_label = QLabel("¡Hola! Soy tu asistente Cirilo. ¿En qué puedo ayudarte hoy?")
    welcome_label.setObjectName("welcome_label")
    welcome_label.setStyleSheet("background-color: transparent;")
    welcome_label.setAlignment(Qt.AlignmentFlag.AlignLeft)  # Alineación a la izquierda
    welcome_label.setWordWrap(True)
    welcome_label.setContentsMargins(0, 0, 0, 0)  # Sin márgenes
    welcome_layout.addWidget(welcome_label)
    
    # Añadir espacio flexible para empujar el texto hacia la izquierda
    welcome_layout.addStretch(1)
    
    # Añadir el layout de bienvenida al encabezado
    header_layout.addLayout(welcome_layout)
    
    main_container_layout.addLayout(header_layout)
    
    # Layout de contenido principal (dos columnas) - optimizado para aprovechar espacio vertical
    content_layout = QHBoxLayout()
    content_layout.setContentsMargins(0, 0, 0, 0)  # Sin márgenes
    content_layout.setSpacing(10)  # Espacio entre columnas
    
    # COLUMNA IZQUIERDA - Marco para respuestas con sombra y mejor aspecto
    response_frame = QFrame()
    response_frame.setObjectName("response_frame")
    response_frame.setMinimumHeight(400)  # Altura mínima para evitar que se comprima demasiado
    
    # Crear un único layout para el response_frame
    response_layout = QVBoxLayout()
    response_layout.setContentsMargins(15, 15, 15, 15)  # Márgenes internos altura para aprovechar el espacio
    response_frame.setLayout(response_layout)
    
    # Establecer estilo para el marco de respuestas
    response_frame.setStyleSheet("background-color: rgba(230, 240, 255, 0.5); border: 2px solid rgba(52, 152, 219, 0.7); border-radius: 10px;")
    
    
    # Etiqueta para mostrar la consulta del usuario
    assistant.user_query_label = QLabel()
    assistant.user_query_label.setObjectName("user_query_label")
    assistant.user_query_label.setWordWrap(True)
    assistant.user_query_label.setTextFormat(Qt.TextFormat.RichText)
    response_layout.addWidget(assistant.user_query_label)
    
    # Área de respuesta (texto del asistente) con scroll
    assistant.response_text = QTextBrowser()
    assistant.response_text.setObjectName("response_text")
    assistant.response_text.setMinimumHeight(300)  # Altura mínima para asegurar espacio suficiente
    assistant.response_text.setVerticalScrollBarPolicy(Qt.ScrollBarPolicy.ScrollBarAsNeeded)  # Mostrar scroll cuando sea necesario
    assistant.response_text.setHorizontalScrollBarPolicy(Qt.ScrollBarPolicy.ScrollBarAlwaysOff)  # Ocultar scroll horizontal
    response_layout.addWidget(assistant.response_text)
    
    # Añadir el marco de respuestas a la columna izquierda
    content_layout.addWidget(response_frame, 3)  # Proporción 3 para la columna izquierda
    
    # COLUMNA DERECHA - Campo de texto y botón de enviar
    input_column = QFrame()
    input_column.setObjectName("input_column")
    input_column.setStyleSheet("background-color: rgba(255, 240, 230, 0.5); border: 2px solid rgba(231, 76, 60, 0.7); border-radius: 10px;")
    input_column_layout = QVBoxLayout(input_column)
    input_column_layout.setContentsMargins(10, 15, 10, 15)
    input_column_layout.setSpacing(15)
    
    # Campo de texto para escribir preguntas (ahora como QTextEdit para múltiples líneas)
    from PyQt6.QtWidgets import QTextEdit
    assistant.text_input = QTextEdit()
    assistant.text_input.setObjectName("text_input")
    assistant.text_input.setPlaceholderText("Escribe tu pregunta aquí...")
    assistant.text_input.setMinimumHeight(100)  # Aumentar altura para múltiples líneas
    input_column_layout.addWidget(assistant.text_input)
    
    # Espacio para separar el campo de texto de los botones
    input_column_layout.addSpacing(70)  # Espacio aún mayor para separar completamente
    
    # Botón de enviar
    assistant.send_button = QPushButton("✉️ Enviar")
    assistant.send_button.setObjectName("send_button")
    assistant.send_button.setMinimumHeight(50)  # Altura ajustada
    assistant.send_button.setStyleSheet("background-color: #f39c12; color: white; font-weight: bold;")
    assistant.send_button.clicked.connect(assistant.send_text_query)
    # Agregar atajo de teclado Ctrl+Enter para enviar
    from PyQt6.QtGui import QShortcut, QKeySequence
    send_shortcut = QShortcut(QKeySequence("Ctrl+Return"), assistant.text_input)
    send_shortcut.activated.connect(assistant.send_text_query)
    input_column_layout.addWidget(assistant.send_button)
    
    # Espacio para separar los botones
    input_column_layout.addSpacing(5)  # Menor espacio entre botones para que queden más juntos
    
    # Botón para hablar (movido debajo del botón enviar)
    assistant.talk_button = QPushButton("🎤 Habla conmigo")
    assistant.talk_button.setObjectName("talk_button")
    assistant.talk_button.setMinimumHeight(50)  # Altura ajustada
    assistant.talk_button.setStyleSheet("background-color: #27ae60; color: white; font-weight: bold;")
    assistant.talk_button.clicked.connect(assistant.start_listening)
    input_column_layout.addWidget(assistant.talk_button)
    
    # Añadir espacio flexible para empujar los elementos hacia arriba
    input_column_layout.addStretch(1)
    
    # Añadir la columna derecha al layout de contenido
    content_layout.addWidget(input_column, 1)  # Proporción 1 para la columna derecha
    
    # Añadir el layout de dos columnas al contenedor principal
    main_container_layout.addLayout(content_layout)
    
    # Icono de estado (inicialmente vacío) - Lo movemos al área de respuesta
    assistant.status_icon = QLabel()
    assistant.status_icon.setFixedSize(32, 32)
    assistant.status_icon.setAlignment(Qt.AlignmentFlag.AlignCenter)
    
    # Etiqueta de estado - La mantenemos pero no la mostramos en la interfaz principal
    assistant.status_label = QLabel("")
    assistant.status_label.setObjectName("status_label")
    assistant.status_label.setAlignment(Qt.AlignmentFlag.AlignLeft | Qt.AlignmentFlag.AlignVCenter)
    assistant.status_label.hide()  # Ocultamos la etiqueta de estado para que no tape el texto
    
    # Contenedor para los botones de acción - con fondo claro y borde para que no parezca flotante
    buttons_container = QFrame()
    buttons_container.setObjectName("buttons_container")
    buttons_container.setStyleSheet("background-color: rgba(245, 245, 245, 0.5); border-top: 1px solid rgba(220, 221, 225, 0.7); padding: 5px;")
    buttons_layout = QVBoxLayout(buttons_container)
    buttons_layout.setContentsMargins(10, 8, 10, 8)  # Márgenes ajustados
    buttons_layout.setSpacing(5)  # Espaciado reducido
    
    # Todos los botones en una sola fila para ahorrar espacio
    all_buttons_layout = QHBoxLayout()
    all_buttons_layout.setSpacing(8)  # Espaciado reducido entre botones
    
    # Botón para nueva conversación
    assistant.new_chat_button = QPushButton("🔄 Nueva conversación")
    assistant.new_chat_button.setObjectName("new_chat_button")
    assistant.new_chat_button.setMinimumHeight(45)  # Altura reducida
    assistant.new_chat_button.setStyleSheet("background-color: #e74c3c; color: white; font-weight: bold;")
    assistant.new_chat_button.clicked.connect(assistant.start_new_conversation)
    all_buttons_layout.addWidget(assistant.new_chat_button)
    
    # Botón de configuración
    assistant.config_button = QPushButton("⚙️ Configuración")
    assistant.config_button.setObjectName("config_button")
    assistant.config_button.setMinimumHeight(45)  # Altura reducida
    assistant.config_button.setStyleSheet("background-color: #3498db; color: white; font-weight: bold;")
    assistant.config_button.clicked.connect(assistant.open_config)
    all_buttons_layout.addWidget(assistant.config_button)
    
    # Botón para generar imagen
    assistant.generate_image_button = QPushButton("🖼️ Generar imagen")
    assistant.generate_image_button.setObjectName("generate_image_button")
    assistant.generate_image_button.setMinimumHeight(45)  # Altura reducida
    assistant.generate_image_button.setStyleSheet("background-color: #9b59b6; color: white; font-weight: bold;")
    assistant.generate_image_button.clicked.connect(assistant.generate_image)
    all_buttons_layout.addWidget(assistant.generate_image_button)
    
    # Botón de descarga (inicialmente deshabilitado)
    assistant.download_button = QPushButton("⬇️ Descargar última imagen")
    assistant.download_button.setObjectName("download_button")
    assistant.download_button.setMinimumHeight(45)  # Misma altura que los demás botones
    assistant.download_button.setStyleSheet("background-color: #2980b9; color: white; font-weight: bold;")
    assistant.download_button.setEnabled(False)  # Deshabilitado inicialmente
    assistant.download_button.clicked.connect(lambda: assistant.download_last_image())
    all_buttons_layout.addWidget(assistant.download_button)
    
    buttons_layout.addLayout(all_buttons_layout)
    
    main_container_layout.addWidget(buttons_container)
    
    # Añadir el contenedor principal al layout
    main_layout.addWidget(main_container)
