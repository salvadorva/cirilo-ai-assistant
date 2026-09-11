#!/bin/bash

# Script para generar prompts individuales de enemigos
# Uso: ./generate_enemy_prompts.sh

echo "🎨 GENERADOR DE PROMPTS PARA ENEMIGOS - MODO SUPERVIVENCIA"
echo "=========================================================="
echo ""

# Crear directorio de prompts
mkdir -p prompts_individuales

# Función para crear prompt individual
create_prompt() {
    local enemy_name="$1"
    local filename="$2"
    local description="$3"
    
    cat > "prompts_individuales/${filename}.txt" << EOF
PROMPT PARA GENERAR: ${enemy_name}
Archivo necesario: ${filename}.png
Ubicación: /var/www/asistente/public/images/enemies/

=== PROMPT PARA GEMINI/DALL-E ===

${description}

=== ESPECIFICACIONES TÉCNICAS ===
- Formato: PNG con fondo transparente
- Tamaño: 256x256 píxeles
- Calidad: Alta resolución
- Estilo: Pixel art cartoon amigable
- Público: Todas las edades
- Uso: Juego de mecanografía educativo

=== INSTRUCCIONES POST-GENERACIÓN ===
1. Descargar la imagen generada
2. Renombrar a: ${filename}.png
3. Verificar que tenga fondo transparente
4. Redimensionar a 256x256 si es necesario
5. Colocar en: /var/www/asistente/public/images/enemies/

EOF
    
    echo "✅ Creado: prompts_individuales/${filename}.txt"
}

# Generar prompts para cada enemigo
echo "Generando prompts individuales..."
echo ""

create_prompt "SLIME VERDE" "slime" "Create a pixel art image of a FRIENDLY GREEN SLIME for a family typing game. The slime should be:
- Bright green gelatinous blob with large friendly eyes
- Rounded bouncy shape with small drips
- Curious and playful expression
- Slightly translucent with highlights
- Sitting pose like a happy blob
- Cartoon style, vibrant colors, transparent background
- 256x256 pixels, appropriate for all ages"

create_prompt "GOBLIN ESCRITOR" "goblin" "Create a pixel art image of a GOBLIN SCRIBE for a family typing game. The goblin should be:
- Small green goblin with pointed ears
- Wearing brown scholar robes
- Holding a quill pen or scroll
- Student cap or librarian hat
- Concentrated but friendly expression
- Glasses optional for scholarly look
- Standing in a studious pose
- Cartoon style, vibrant colors, transparent background
- 256x256 pixels, appropriate for all ages"

create_prompt "ESQUELETO GUERRERO" "skeleton" "Create a pixel art image of a FRIENDLY SKELETON WARRIOR for a family typing game. The skeleton should be:
- Friendly skeleton with light armor
- Bright white bones
- Medieval warrior helmet
- Small wooden sword (non-threatening)
- Glowing blue friendly eyes
- Heroic but non-aggressive pose
- Maybe a small shield with book symbol
- Cartoon style, vibrant colors, transparent background
- 256x256 pixels, appropriate for all ages"

create_prompt "ORC FUERTE" "orc" "Create a pixel art image of a STRONG ORC for a family typing game. The orc should be:
- Large green-skinned orc
- Well-muscled but not threatening
- Brown leather armor
- Small wooden axe
- Determined but friendly expression
- Small cute tusks
- Arms crossed in heroic pose
- Maybe holding a book or scroll
- Cartoon style, vibrant colors, transparent background
- 256x256 pixels, appropriate for all ages"

create_prompt "MAGO MÁGICO" "wizard" "Create a pixel art image of a MAGICAL WIZARD for a family typing game. The wizard should be:
- Elderly wizard with long white beard
- Blue robe with golden stars
- Pointed wizard hat
- Magic staff with glowing crystal
- Sparkles floating around
- Wise and friendly expression
- Spellbook in other hand
- Maybe floating letters or words around him
- Cartoon style, vibrant colors, transparent background
- 256x256 pixels, appropriate for all ages"

create_prompt "DRAGÓN PEQUEÑO" "dragon" "Create a pixel art image of a BABY DRAGON for a family typing game. The dragon should be:
- Small red and gold dragon
- Small wings spread out
- Playful and curious expression
- Soft shiny scales
- Small harmless flames from mouth
- Heart-shaped tail tip
- Sitting pose like a puppy
- Maybe holding a scroll in claws
- Cartoon style, vibrant colors, transparent background
- 256x256 pixels, appropriate for all ages"

create_prompt "DEMONIO ÉLITE" "demon" "Create a pixel art image of an ELITE DEMON for a family typing game. The demon should be:
- Dark purple colored demon
- Small rounded horns
- Stylized bat wings
- Black robe with golden details
- Mysterious but not scary expression
- Bright yellow friendly eyes
- Elegant and dignified posture
- Maybe holding an ancient tome
- Cartoon style, vibrant colors, transparent background
- 256x256 pixels, appropriate for all ages"

create_prompt "REY TIRANO JEFE FINAL" "boss-tyrant" "Create a pixel art image of a TYRANT KING FINAL BOSS for a family typing game. The king should be:
- Imposing king with large golden crown
- Royal purple robe with ermine
- Royal scepter with glowing gem
- Majestic beard
- Serious but noble expression
- Flowing cape
- Golden ceremonial armor
- Majestic and imposing posture
- Golden aura of power (glowing effects)
- Maybe surrounded by floating words or letters
- Cartoon style, vibrant colors, transparent background
- 256x256 pixels, appropriate for all ages"

echo ""
echo "🎉 ¡PROMPTS GENERADOS EXITOSAMENTE!"
echo ""
echo "📁 Archivos creados en: prompts_individuales/"
echo ""
echo "🔄 PRÓXIMOS PASOS:"
echo "1. Abre cada archivo .txt en prompts_individuales/"
echo "2. Copia el prompt y úsalo en Gemini/DALL-E"
echo "3. Descarga las imágenes generadas"
echo "4. Renómbralas según las especificaciones"
echo "5. Colócalas en /var/www/asistente/public/images/enemies/"
echo ""
echo "📋 LISTA DE ARCHIVOS NECESARIOS:"
echo "✅ slime.png"
echo "✅ goblin.png" 
echo "✅ skeleton.png"
echo "✅ orc.png"
echo "✅ wizard.png"
echo "✅ dragon.png"
echo "✅ demon.png"
echo "✅ boss-tyrant.png"
echo ""
echo "🎮 ¡Tu Modo Supervivencia será épico con estas imágenes!"
