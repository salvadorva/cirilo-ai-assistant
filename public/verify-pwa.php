<?php

header('Content-Type: application/json');

// Script para verificar archivos PWA
$results = [
    'status' => 'ok',
    'checks' => [],
    'errors' => [],
    'warnings' => [],
];

// Verificar manifest
if (file_exists('manifest.json')) {
    $manifest = json_decode(file_get_contents('manifest.json'), true);
    if ($manifest) {
        $results['checks']['manifest'] = '✅ Manifest válido';

        // Verificar iconos en manifest
        if (isset($manifest['icons'])) {
            $iconErrors = [];
            foreach ($manifest['icons'] as $icon) {
                // Convertir ruta de pwa-icons a ruta real del archivo
                $iconPath = str_replace('/pwa-icons/', 'icons/', $icon['src']);
                $iconPath = ltrim($iconPath, '/');
                if (! file_exists($iconPath)) {
                    $iconErrors[] = $icon['src'];
                }
            }

            if (empty($iconErrors)) {
                $results['checks']['icons'] = '✅ Todos los iconos disponibles';
            } else {
                $results['errors']['icons'] = '❌ Iconos faltantes: '.implode(', ', $iconErrors);
                $results['status'] = 'error';
            }
        }

        // Verificar shortcuts
        if (isset($manifest['shortcuts'])) {
            $results['checks']['shortcuts'] = '✅ Shortcuts configurados: '.count($manifest['shortcuts']);
        }

    } else {
        $results['errors']['manifest'] = '❌ Manifest JSON inválido';
        $results['status'] = 'error';
    }
} else {
    $results['errors']['manifest'] = '❌ Manifest no encontrado';
    $results['status'] = 'error';
}

// Verificar Service Worker
if (file_exists('sw.js')) {
    $results['checks']['service_worker'] = '✅ Service Worker disponible';
} else {
    $results['errors']['service_worker'] = '❌ Service Worker no encontrado';
    $results['status'] = 'error';
}

// Verificar iconos individuales
$requiredIcons = ['72x72', '96x96', '128x128', '144x144', '152x152', '192x192', '384x384', '512x512'];
$missingIcons = [];

foreach ($requiredIcons as $size) {
    if (! file_exists("icons/icon-{$size}.png")) {
        $missingIcons[] = $size;
    }
}

// Verificar que los archivos de iconos existan físicamente
$iconFilesExist = true;
$missingIconFiles = [];

foreach ($manifest['icons'] as $icon) {
    // Convertir ruta de pwa-icons a ruta real del archivo
    $iconPath = str_replace('/pwa-icons/', 'icons/', $icon['src']);
    $iconPath = ltrim($iconPath, '/');
    if (! file_exists($iconPath)) {
        $iconFilesExist = false;
        $missingIconFiles[] = basename($iconPath);
    }
}

if ($iconFilesExist) {
    $results['checks']['icon_files_exist'] = '✅ Todos los archivos de iconos existen';
} else {
    $results['errors']['icon_files_exist'] = '❌ Archivos de iconos faltantes: '.implode(', ', $missingIconFiles);
    $results['status'] = 'error';
}

if (empty($missingIcons)) {
    $results['checks']['icon_files'] = '✅ Todos los iconos PNG disponibles';
} else {
    $results['warnings']['icon_files'] = '⚠️ Iconos faltantes: '.implode(', ', $missingIcons);
}

// Verificar screenshots
if (isset($manifest['screenshots'])) {
    $screenshotErrors = [];
    foreach ($manifest['screenshots'] as $screenshot) {
        $screenshotPath = ltrim($screenshot['src'], '/');
        if (! file_exists($screenshotPath)) {
            $screenshotErrors[] = $screenshot['src'];
        }
    }

    if (empty($screenshotErrors)) {
        $results['checks']['screenshots'] = '✅ Todos los screenshots disponibles';
    } else {
        $results['warnings']['screenshots'] = '⚠️ Screenshots faltantes: '.implode(', ', $screenshotErrors);
    }
} else {
    $results['warnings']['screenshots'] = '⚠️ No se encontraron screenshots en el manifest';
}

// Verificar HTTPS
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
$isLocalhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) ||
               strpos($_SERVER['HTTP_HOST'], '.local') !== false ||
               strpos($_SERVER['HTTP_HOST'], '.its') !== false;

if ($isHttps || $isLocalhost) {
    $results['checks']['https'] = '✅ HTTPS o localhost detectado';
} else {
    $results['warnings']['https'] = '⚠️ HTTPS requerido para PWA completa';
}

// Información adicional
$results['info'] = [
    'host' => $_SERVER['HTTP_HOST'],
    'protocol' => isset($_SERVER['HTTPS']) ? 'https' : 'http',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
];

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
