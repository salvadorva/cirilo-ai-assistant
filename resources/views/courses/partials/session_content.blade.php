{{-- Contenido principal de la sesión --}}
<div class="session-content mb-4">
    @php
        // Procesar Markdown para mejorar la presentación del contenido
        $content = $session->content;
        
        // Filtrar secciones que ya tienen sus propios parciales
        $sectionsToFilter = [
            'Contenido Visual',
            'Guion de Audio',
            'Actividad de Práctica'
        ];
        
        // Dividir el contenido por líneas para filtrar secciones
        $lines = explode("\n", $content);
        $filteredLines = [];
        $skipSection = false;
        
        foreach ($lines as $line) {
            // Detectar encabezados de secciones a filtrar
            foreach ($sectionsToFilter as $section) {
                if (preg_match('/^#+\s*' . preg_quote($section, '/') . '/i', $line)) {
                    $skipSection = true;
                    break;
                }
            }
            
            // Detectar el siguiente encabezado para dejar de filtrar
            if ($skipSection && preg_match('/^#+\s+/', $line) && !in_array(trim(preg_replace('/^#+\s+/', '', $line)), $sectionsToFilter)) {
                $skipSection = false;
            }
            
            // Agregar línea solo si no estamos en una sección a filtrar
            if (!$skipSection) {
                $filteredLines[] = $line;
            }
        }
        
        // Reconstruir el contenido filtrado
        $content = implode("\n", $filteredLines);
        
        // Procesar encabezados (h1, h2, h3, h4, h5, h6)
        $content = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $content);
        $content = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^#### (.*?)$/m', '<h4>$1</h4>', $content);
        $content = preg_replace('/^##### (.*?)$/m', '<h5>$1</h5>', $content);
        $content = preg_replace('/^###### (.*?)$/m', '<h6>$1</h6>', $content);
        
        // Procesar negritas
        $content = preg_replace('/\*\*(.*?)\*\*/m', '<strong>$1</strong>', $content);
        
        // Procesar código en línea
        $content = preg_replace('/`(.*?)`/m', '<code>$1</code>', $content);
        
        // Procesar bloques de código
        $content = preg_replace('/```(.*?)```/s', '<pre><code>$1</code></pre>', $content);
        
        // Procesar listas no ordenadas
        $content = preg_replace('/^\* (.*?)$/m', '<li>$1</li>', $content);
        $content = preg_replace('/(<li>.*?<\/li>\n<li>.*?<\/li>)/s', '<ul>$1</ul>', $content);
        
        // Procesar listas ordenadas
        $content = preg_replace('/^\d+\. (.*?)$/m', '<li>$1</li>', $content);
        $content = preg_replace('/(<li>.*?<\/li>\n<li>.*?<\/li>)/s', '<ol>$1</ol>', $content);
        
        // Procesar párrafos (líneas que no son encabezados, listas, etc.)
        $content = preg_replace('/^([^<].*?)$/m', '<p>$1</p>', $content);
        
        // Limpiar párrafos vacíos o duplicados
        $content = preg_replace('/<p>\s*<\/p>/', '', $content);
        $content = preg_replace('/<p>(<h[1-6]>.*?<\/h[1-6]>)<\/p>/', '$1', $content);
        $content = preg_replace('/<p>(<ul>.*?<\/ul>)<\/p>/s', '$1', $content);
        $content = preg_replace('/<p>(<ol>.*?<\/ol>)<\/p>/s', '$1', $content);
        $content = preg_replace('/<p>(<pre>.*?<\/pre>)<\/p>/s', '$1', $content);
        
        // Agregar log para depuración
        \Illuminate\Support\Facades\Log::info('Contenido filtrado', [
            'original_length' => strlen($session->content),
            'filtered_length' => strlen($content),
            'sections_filtered' => $sectionsToFilter
        ]);
    @endphp
    
    {!! $content !!}
</div>
