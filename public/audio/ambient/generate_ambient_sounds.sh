#!/bin/bash

# Script para generar archivos de audio ambiental básicos para Modo Zen
# Usando ffmpeg para crear tonos simples y relajantes

echo "Generando archivos de audio ambiental para Modo Zen..."

# Función para generar tonos
generate_tone() {
    local filename=$1
    local frequency=$2
    local duration=$3
    local volume=$4
    
    ffmpeg -f lavfi -i "sine=frequency=${frequency}:duration=${duration}" -af "volume=${volume}" "${filename}.mp3" -y
}

# Generar sonidos ambientales básicos
# Rain (lluvia simulada) - ruido blanco filtrado
ffmpeg -f lavfi -i "anoisesrc=duration=30:color=white:sample_rate=44100" -af "highpass=f=200,lowpass=f=2000,volume=0.3" rain.mp3 -y

# Ocean (océano) - onda sinusoidal baja con variaciones
ffmpeg -f lavfi -i "sine=frequency=60:duration=30" -af "tremolo=f=0.1:d=0.9,volume=0.4" ocean.mp3 -y

# Forest (bosque) - tonos naturales mezclados
ffmpeg -f lavfi -i "sine=frequency=80:duration=30" -af "tremolo=f=0.05:d=0.7,volume=0.35" forest.mp3 -y

# Wind (viento) - ruido rosa filtrado
ffmpeg -f lavfi -i "anoisesrc=duration=30:color=pink:sample_rate=44100" -af "highpass=f=100,lowpass=f=1000,volume=0.25" wind.mp3 -y

echo "Archivos de audio ambiental generados exitosamente:"
ls -la *.mp3