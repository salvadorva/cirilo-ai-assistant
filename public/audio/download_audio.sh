#!/bin/bash

# 🎵 Script de Descarga de Audio para TypeMaster AI Survival Mode
# Requiere: wget, curl o youtube-dl

echo "🎮 TypeMaster AI - Descarga de Audio para Modo Supervivencia"
echo "============================================================"

# Crear directorio si no existe
mkdir -p /var/www/asistente/public/audio

cd /var/www/asistente/public/audio

echo ""
echo "📁 Directorio actual: $(pwd)"
echo ""

# Función para descargar desde URLs directas (ejemplos con placeholder)
download_audio_pack() {
    echo "🔄 Descargando pack de audio básico..."
    
    # Nota: Estos son URLs de ejemplo - necesitarás reemplazar con URLs reales
    # desde Freesound.org o similar después de crear una cuenta
    
    echo "⚠️  IMPORTANTE: Este script requiere URLs reales de audio."
    echo "   Por favor, sigue estos pasos:"
    echo ""
    echo "1. 🌐 Visita https://freesound.org"
    echo "2. 📝 Crea una cuenta gratuita"
    echo "3. 🔍 Busca y descarga estos sonidos:"
    echo ""
    echo "   🚀 game_start.mp3  → 'epic battle start'"
    echo "   ⚔️  hit.mp3         → 'sword hit impact'"
    echo "   ❌ miss.mp3        → 'error buzz'"
    echo "   🏆 victory.mp3     → 'victory fanfare'"
    echo "   💀 defeat.mp3      → 'game over'"
    echo "   ⭐ level_up.mp3    → 'level up chime'"
    echo "   🎼 background_music.mp3 → 'epic battle music'"
    echo ""
    echo "4. 💾 Coloca los archivos en: /var/www/asistente/public/audio/"
    echo ""
}

# Función para crear archivos de audio silenciosos válidos (fallback)
create_silent_audio() {
    echo "🔇 Creando archivos de audio silenciosos como placeholder..."
    
    if command -v ffmpeg &> /dev/null; then
        echo "✅ FFmpeg encontrado - Creando archivos MP3 válidos..."
        
        # Crear archivos silenciosos de diferentes duraciones
        ffmpeg -f lavfi -i anullsrc=r=44100:cl=stereo -t 1 -q:a 9 -acodec libmp3lame game_start.mp3 -y 2>/dev/null
        ffmpeg -f lavfi -i anullsrc=r=44100:cl=stereo -t 0.5 -q:a 9 -acodec libmp3lame hit.mp3 -y 2>/dev/null
        ffmpeg -f lavfi -i anullsrc=r=44100:cl=stereo -t 0.5 -q:a 9 -acodec libmp3lame miss.mp3 -y 2>/dev/null
        ffmpeg -f lavfi -i anullsrc=r=44100:cl=stereo -t 1.5 -q:a 9 -acodec libmp3lame victory.mp3 -y 2>/dev/null
        ffmpeg -f lavfi -i anullsrc=r=44100:cl=stereo -t 1.5 -q:a 9 -acodec libmp3lame defeat.mp3 -y 2>/dev/null
        ffmpeg -f lavfi -i anullsrc=r=44100:cl=stereo -t 1 -q:a 9 -acodec libmp3lame level_up.mp3 -y 2>/dev/null
        ffmpeg -f lavfi -i anullsrc=r=44100:cl=stereo -t 30 -q:a 9 -acodec libmp3lame background_music.mp3 -y 2>/dev/null
        
        echo "✅ Archivos de audio silenciosos creados exitosamente!"
        echo "🔔 Nota: Estos son archivos silenciosos. Reemplázalos con audio real para mejor experiencia."
        
    else
        echo "❌ FFmpeg no encontrado. Instalando..."
        echo "🔄 Ejecuta: sudo apt update && sudo apt install ffmpeg"
        return 1
    fi
}

# Función para verificar archivos existentes
check_existing_files() {
    echo "🔍 Verificando archivos de audio existentes..."
    echo ""
    
    files=("game_start.mp3" "hit.mp3" "miss.mp3" "victory.mp3" "defeat.mp3" "level_up.mp3" "background_music.mp3")
    
    for file in "${files[@]}"; do
        if [ -f "$file" ]; then
            size=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file" 2>/dev/null)
            if [ "$size" -gt 1000 ]; then
                echo "✅ $file (${size} bytes) - OK"
            else
                echo "⚠️  $file (${size} bytes) - Archivo muy pequeño"
            fi
        else
            echo "❌ $file - Faltante"
        fi
    done
    echo ""
}

# Función principal
main() {
    check_existing_files
    
    echo "🎯 Opciones disponibles:"
    echo "1. 📖 Ver guía de descarga manual"
    echo "2. 🔇 Crear archivos silenciosos (placeholder)"
    echo "3. 🔍 Solo verificar archivos existentes"
    echo ""
    
    read -p "Selecciona una opción (1-3): " option
    
    case $option in
        1)
            download_audio_pack
            ;;
        2)
            create_silent_audio
            check_existing_files
            ;;
        3)
            echo "✅ Verificación completada."
            ;;
        *)
            echo "❌ Opción inválida. Saliendo..."
            exit 1
            ;;
    esac
    
    echo ""
    echo "🎮 ¡Listo! Ahora puedes probar el Modo Supervivencia."
    echo "🌐 Si los archivos son silenciosos, reemplázalos con audio real siguiendo la guía."
}

# Ejecutar función principal
main
