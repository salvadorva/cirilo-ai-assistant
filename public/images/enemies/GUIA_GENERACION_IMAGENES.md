# 🎨 Guía para Generar Imágenes de Enemigos - Modo Supervivencia

## 📋 Imágenes Necesarias

El Modo Supervivencia necesita las siguientes imágenes de enemigos:

### 🎯 Lista de Enemigos:
1. **slime.png** - Enemigo básico (Nivel 1-5)
2. **goblin.png** - Enemigo básico (Nivel 6-10) 
3. **skeleton.png** - Enemigo intermedio (Nivel 11-15)
4. **orc.png** - Enemigo intermedio (Nivel 16-20)
5. **wizard.png** - Enemigo avanzado (Nivel 21-25)
6. **dragon.png** - Enemigo fuerte (Nivel 26-30)
7. **demon.png** - Enemigo élite (Nivel 31+)
8. **boss-tyrant.png** - Jefe final

## 🎨 Especificaciones Técnicas

### Formato y Tamaño:
- **Formato**: PNG con transparencia
- **Tamaño**: 256x256 píxeles (para máxima calidad)
- **Fondo**: Transparente
- **Estilo**: Pixel art o cartoon amigable

### Estilo Visual:
- **Paleta**: Colores vibrantes pero no agresivos
- **Diseño**: Apropiado para todas las edades
- **Perspectiva**: Vista frontal o 3/4
- **Expresión**: Amigable pero desafiante

## 🤖 Prompts para Gemini

### Prompt Base para Todos los Enemigos:
```
Genera una imagen en estilo pixel art de [ENEMIGO] para un juego de mecanografía familiar. 
Especificaciones:
- Tamaño: 256x256 píxeles
- Fondo transparente
- Estilo cartoon amigable
- Colores vibrantes
- Vista frontal
- Expresión amigable pero desafiante
- Apropiado para todas las edades
```

### 1. Slime (slime.png)
```
Genera una imagen en estilo pixel art de un SLIME VERDE para un juego de mecanografía familiar.
- Un slime gelatinoso verde brillante con ojos grandes y amigables
- Forma redondeada y bouncy
- Pequeñas gotas de slime cayendo
- Expresión curiosa y juguetona
- Fondo transparente, 256x256 píxeles
- Estilo cartoon amigable, colores vibrantes
```

### 2. Goblin (goblin.png)
```
Genera una imagen en estilo pixel art de un GOBLIN ESCRITOR para un juego de mecanografía familiar.
- Un pequeño goblin verde con orejas puntiagudas
- Vestido con ropa de escriba (túnica marrón)
- Sosteniendo una pluma o pergamino
- Gorro de estudiante o bibliotecario
- Expresión concentrada pero amigable
- Fondo transparente, 256x256 píxeles
- Estilo cartoon amigable, colores vibrantes
```

### 3. Skeleton (skeleton.png)
```
Genera una imagen en estilo pixel art de un ESQUELETO GUERRERO para un juego de mecanografía familiar.
- Esqueleto amigable con armadura ligera
- Huesos blancos brillantes
- Casco de guerrero medieval
- Espada pequeña de madera (no amenazante)
- Ojos con luces azules amigables
- Postura heroica pero no agresiva
- Fondo transparente, 256x256 píxeles
- Estilo cartoon amigable, colores vibrantes
```

### 4. Orc (orc.png)
```
Genera una imagen en estilo pixel art de un ORC FUERTE para un juego de mecanografía familiar.
- Orc grande de piel verde
- Músculos marcados pero no amenazante
- Armadura de cuero marrón
- Hacha pequeña de madera
- Expresión determinada pero amigable
- Colmillos pequeños y lindos
- Brazos cruzados en pose heroica
- Fondo transparente, 256x256 píxeles
- Estilo cartoon amigable, colores vibrantes
```

### 5. Wizard (wizard.png)
```
Genera una imagen en estilo pixel art de un MAGO MÁGICO para un juego de mecanografía familiar.
- Mago anciano con barba blanca larga
- Túnica azul con estrellas doradas
- Sombrero puntiagudo de mago
- Bastón mágico con cristal brillante
- Chispas mágicas flotando alrededor
- Expresión sabia y amigable
- Libro de hechizos en la otra mano
- Fondo transparente, 256x256 píxeles
- Estilo cartoon amigable, colores vibrantes
```

### 6. Dragon (dragon.png)
```
Genera una imagen en estilo pixel art de un DRAGÓN PEQUEÑO para un juego de mecanografía familiar.
- Dragón bebé de color rojo y dorado
- Alas pequeñas extendidas
- Expresión juguetona y curiosa
- Escamas brillantes pero suaves
- Pequeñas llamas inofensivas saliendo de la boca
- Cola con punta en forma de corazón
- Postura sentada como un cachorro
- Fondo transparente, 256x256 píxeles
- Estilo cartoon amigable, colores vibrantes
```

### 7. Demon (demon.png)
```
Genera una imagen en estilo pixel art de un DEMONIO ÉLITE para un juego de mecanografía familiar.
- Demonio de color púrpura oscuro
- Cuernos pequeños y redondeados
- Alas de murciélago estilizadas
- Túnica negra con detalles dorados
- Expresión misteriosa pero no aterradora
- Ojos brillantes amarillos amigables
- Postura elegante y digna
- Fondo transparente, 256x256 píxeles
- Estilo cartoon amigable, colores vibrantes
```

### 8. Boss Tyrant (boss-tyrant.png)
```
Genera una imagen en estilo pixel art de un REY TIRANO JEFE FINAL para un juego de mecanografía familiar.
- Rey imponente con corona dorada grande
- Túnica real púrpura con armiño
- Cetro real con gema brillante
- Barba majestuosa
- Expresión seria pero noble
- Capa flotante
- Armadura ceremonial dorada
- Postura majestuosa e imponente
- Aura de poder (brillos dorados)
- Fondo transparente, 256x256 píxeles
- Estilo cartoon amigable, colores vibrantes
```

## 🔧 Instrucciones de Uso

### Con Gemini:
1. Copia el prompt del enemigo que quieres generar
2. Pégalo en Gemini y solicita la imagen
3. Descarga la imagen generada
4. Renombra el archivo con el nombre exacto (ej: `slime.png`)
5. Coloca en `/var/www/asistente/public/images/enemies/`

### Verificación:
```bash
cd /var/www/asistente/public/images/enemies/
ls -la *.png
```

## 🎨 Alternativas de Generación

### Si Gemini no genera imágenes:
1. **DALL-E 3** (OpenAI) - Usa los mismos prompts
2. **Midjourney** - Adapta los prompts agregando "--style cartoon"
3. **Stable Diffusion** - Usa los prompts con "pixel art cartoon style"
4. **Bing Image Creator** - Usa los prompts directamente

### Recursos Gratuitos:
1. **OpenGameArt.org** - Busca "enemies pixel art"
2. **Itch.io** - Assets gratuitos de enemigos
3. **Kenney.nl** - Sprites gratuitos de alta calidad
4. **Freepik** - Con atribución

## 📝 Checklist de Verificación

- [ ] slime.png (256x256, transparente)
- [ ] goblin.png (256x256, transparente)  
- [ ] skeleton.png (256x256, transparente)
- [ ] orc.png (256x256, transparente)
- [ ] wizard.png (256x256, transparente)
- [ ] dragon.png (256x256, transparente)
- [ ] demon.png (256x256, transparente)
- [ ] boss-tyrant.png (256x256, transparente)

¡Una vez que tengas todas las imágenes, el Modo Supervivencia se verá épico! 🎮
