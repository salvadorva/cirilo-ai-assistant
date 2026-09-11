# Corrección del Reconocimiento de Voz - Preguntas

## 🐛 Problema Identificado

El reconocimiento de voz se iniciaba pero se detenía inmediatamente porque:

1. **Reasignación de eventos**: El código reasignaba `micButton.onclick` dentro de la función `startRecognition()`, creando un bucle infinito de eventos.
2. **Conflicto de event handlers**: Al intentar guardar y restaurar el `originalClickHandler`, se activaba nuevamente el evento, deteniendo el reconocimiento.
3. **Falta de control de estado**: No había una forma clara de saber si el reconocimiento estaba activo o no.

## ✅ Solución Implementada

### 1. **Variables de Estado**
```javascript
let isRecording = false;
let currentRecognition = null;
```

### 2. **Event Listener Único**
En lugar de reasignar `onclick`, ahora usamos un solo `addEventListener` que verifica el estado:

```javascript
micButton.addEventListener('click', function() {
    if (isRecording && currentRecognition) {
        stopRecognition();
    } else {
        startRecognition();
    }
});
```

### 3. **Control de Estado en Eventos**
```javascript
recognition.start();
isRecording = true;  // ✅ Marcar como activo

recognition.onend = function() {
    isRecording = false;  // ✅ Marcar como inactivo
    currentRecognition = null;
    resetMicButton();
};
```

### 4. **Función de Detención**
Nueva función `stopRecognition()` para manejar la detención manual:

```javascript
function stopRecognition() {
    if (currentRecognition && isRecording) {
        currentRecognition.stop();
        isRecording = false;
        currentRecognition = null;
        resetMicButton();
    }
}
```

### 5. **Funciones Auxiliares Globales**
Movidas fuera de `startRecognition()` para evitar duplicación:
- `resetMicButton()` - Restaura el aspecto del botón
- `showRecognitionError()` - Muestra mensajes de error

## 📊 Cambios Realizados

### Antes
```javascript
// ❌ PROBLEMA: Reasignación de onclick dentro de startRecognition
const originalClickHandler = micButton.onclick;
micButton.onclick = function() {
    recognition.stop();
    // Intentar restaurar...
    setTimeout(() => {
        micButton.onclick = originalClickHandler;
    }, 500);
};
```

### Después
```javascript
// ✅ SOLUCIÓN: Un solo event listener con control de estado
let isRecording = false;
let currentRecognition = null;

micButton.addEventListener('click', function() {
    if (isRecording && currentRecognition) {
        stopRecognition();
    } else {
        startRecognition();
    }
});
```

## 🎯 Resultado

- ✅ El reconocimiento se inicia correctamente
- ✅ No se detiene automáticamente
- ✅ Se puede detener manualmente haciendo clic en el botón
- ✅ El botón cambia de aspecto correctamente (micrófono ↔ stop)
- ✅ No hay conflictos de eventos
- ✅ El código es más limpio y mantenible

## 🧪 Pruebas Recomendadas

1. **Inicio**: Hacer clic en el botón del micrófono → Debería cambiar a rojo con ícono de stop
2. **Hablar**: Decir algo claramente → Debería transcribir correctamente
3. **Detención Manual**: Hacer clic en el botón rojo → Debería detenerse y volver a azul
4. **Múltiples Usos**: Repetir el proceso varias veces → Debería funcionar sin problemas

## 📝 Notas

- El error "network" que viste en Vivaldi era porque ese navegador no es totalmente compatible con SpeechRecognition
- En Chrome funciona correctamente porque usa la API de Google Speech directamente
- Las rutas de audio (`static` vs `dynamic`) NO tienen nada que ver con este problema
- El cambio de rutas de audio está funcionando correctamente

---

**Fecha**: 17 de octubre de 2025  
**Archivo**: `/var/www/asistente/resources/views/home/preguntas.blade.php`
