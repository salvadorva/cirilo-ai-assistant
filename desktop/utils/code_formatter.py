# utils.py
import re
import os

def clean_and_format_code_blocks(text):
    """
    Limpia y formatea los bloques de código, asegurándose de separar
    correctamente el texto explicativo que pueda estar dentro de los bloques.
    """
    # Patrón para identificar bloques de código con triple backtick
    code_block_pattern = r'```(?P<language>\w*)\n(?P<code>[\s\S]*?)```'
    
    # Función para procesar cada bloque encontrado
    def process_code_block(match):
        language = match.group('language').strip().lower() or 'python'  # Default a Python
        code_block = match.group('code')
        
        # Detectar y separar texto explicativo del código real
        lines = code_block.split('\n')
        code_lines = []
        text_lines = []
        
        # Estado actual: estamos en código o en texto explicativo
        in_code = True
        current_block = []
        
        for line in lines:
            line_stripped = line.strip()
            
            # Heurísticas para detectar líneas de texto explicativo:
            # 1. Líneas que comienzan con # y tienen más de 20 caracteres (probablemente explicaciones, no comentarios de código)
            # 2. Oraciones completas con puntuación que no parecen código
            # 3. Líneas que contienen patrones como "Ahora", "Luego", "Ejemplo:", etc.
            
            is_explanation = (
                (line_stripped.startswith('#') and len(line_stripped) > 25 and ('.' in line_stripped or ',' in line_stripped)) or
                (line_stripped.startswith('# ') and any(word in line_stripped.lower() for word in ['nota', 'ejemplo', 'explicación', 'ahora', 'luego', 'primero', 'después'])) or
                (line_stripped and not line_stripped.startswith('#') and not any(char in line_stripped for char in '=+-*/()[]{}:;,.<>?_"\'\\|') and len(line_stripped) > 30) or
                (line_stripped.endswith('.') and len(line_stripped) > 40)
            )
            
            # Cambiar de estado si detectamos cambio entre código y explicación
            if is_explanation and in_code:
                # Si veníamos escribiendo código, guardarlo e iniciar un bloque de texto
                if current_block:
                    code_lines.append('\n'.join(current_block))
                    current_block = []
                in_code = False
                current_block.append(line)
            elif not is_explanation and not in_code:
                # Si veníamos escribiendo texto, guardarlo e iniciar un bloque de código
                if current_block:
                    text_lines.append('\n'.join(current_block))
                    current_block = []
                in_code = True
                current_block.append(line)
            else:
                # Continuamos en el mismo tipo de bloque
                current_block.append(line)
        
        # Guardar el último bloque pendiente
        if current_block:
            if in_code:
                code_lines.append('\n'.join(current_block))
            else:
                text_lines.append('\n'.join(current_block))
        
        # Si no detectamos texto explicativo, mantener el bloque original
        if not text_lines:
            return f'```{language}\n{code_block}```'
        
        # Reconstruir con el texto fuera del bloque de código
        result = ""
        for text in text_lines:
            result += f"{text}\n\n"
        
        # Agregar bloques de código limpios
        for code in code_lines:
            if code.strip():  # Solo si hay código real
                result += f"```{language}\n{code}\n```\n\n"
        
        return result.strip()
    
    # Aplicar el procesamiento a todos los bloques de código
    processed_text = re.sub(code_block_pattern, process_code_block, text)
    return processed_text
def format_markdown_code(text):
    """
    Detecta y formatea bloques de código en texto markdown
    utilizando un enfoque más radical para separar código y texto.
    """
    # Estilos CSS
    code_block_style = 'background-color: #282c34; color: #abb2bf; padding: 10px; border-radius: 6px; font-family: "Courier New", monospace; white-space: pre-wrap; margin: 10px 0;'
    inline_code_style = 'background-color: #f0f0f0; padding: 2px 4px; border-radius: 4px; font-family: "Courier New", monospace;'
    normal_text_style = 'color: black; background-color: transparent; font-family: inherit;'
    
    # Encontrar todos los bloques de código con un enfoque diferente
    # Patrón para bloques de código con marca de lenguaje
    pattern = r'```(\w*)\n([\s\S]*?)```'
    
    # Función para reemplazar y forzar el formato correcto
    def format_code_block(match):
        language = match.group(1).strip()
        code = match.group(2).strip()
        
        # Verificar si hay texto de cierre que podría haber quedado dentro del bloque
        code_lines = code.split('\n')
        actual_code = []
        final_text = ""
        
        # Heurística más agresiva para detectar líneas no-código
        in_explanation = False
        
        for i, line in enumerate(code_lines):
            # Detectores de texto explicativo más agresivos
            is_explanation = (
                line.strip().startswith("Espero que") or
                line.strip().startswith("¿") or
                line.strip().startswith("Si tienes") or
                ("gracias" in line.lower()) or
                ("suerte" in line.lower()) or
                (len(line.strip()) > 30 and "." in line and not any(c in line for c in "{}[]()=+-*/"))
            )
            
            if is_explanation:
                in_explanation = True
                if not final_text:
                    final_text = line
                else:
                    final_text += "\n" + line
            elif not in_explanation:
                actual_code.append(line)
        
        # Construir HTML para el bloque de código y el texto explicativo
        lang_label = f'<div style="padding: 4px 10px; background-color: #23272e; color: #56b6c2; border-radius: 6px 6px 0 0; font-size: 0.9em;">{language.upper()}</div>' if language else ''
        
        html = f'{lang_label}<div style="{code_block_style}">'
        html += '\n'.join(actual_code).replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
        html += '</div>'
        
        # Agregar el texto explicativo fuera del bloque si se encontró
        if final_text:
            html += f'<div style="{normal_text_style}">{final_text}</div>'
        
        return html
    
    # Reemplazar todos los bloques de código
    formatted_text = re.sub(pattern, format_code_block, text, flags=re.DOTALL)
    
    # Formatear código en línea
    formatted_text = re.sub(
        r'`([^`\n]+)`', 
        lambda m: f'<code style="{inline_code_style}">{m.group(1).replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")}</code>',
        formatted_text
    )
    
    # Convertir saltos de línea normales a <br>
    formatted_text = formatted_text.replace('\n', '<br>')
    
    return formatted_text
