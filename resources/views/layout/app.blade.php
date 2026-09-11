<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Mi Aplicación')</title>
    <link rel="icon" type="image/x-icon" href="{{asset('resources/favicon.ico')}}"/>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#232946">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Asistente IA">
    <meta name="msapplication-TileColor" content="#232946">
    <meta name="msapplication-tap-highlight" content="no">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    
    <!-- PWA Icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="/pwa-icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/pwa-icons/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/pwa-icons/icon-16x16.png">
    <link rel="mask-icon" href="/pwa-icons/icon-base.svg" color="#232946">
    
    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" type="text/css" href="{{asset('src/assets/css/light/elements/alert.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('src/assets/css/dark/elements/alert.css')}}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Notifications CSS - Sprint 9 -->
    <link rel="stylesheet" href="{{ asset('css/notifications.css') }}">
    <style>
        body {
            min-height: 100vh;
            background: #f4f6fa;
            overflow-x: hidden;
        }
        .sidebar {
            width: 250px;
            background: #232946;
            color: white;
            flex-shrink: 0;
            border-radius: 0 18px 18px 0;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            margin: 0 0 18px 18px;
            padding: 18px 0 12px 0;
            position: fixed;
            left: 0;
            top: 0;
            height: calc(100vh - 0px);
            z-index: 1040;
            backdrop-filter: blur(8px);
            transition: all 0.3s ease;
            overflow-y: auto;
        }
        
        /* Estilos específicos para móviles */
        @media (max-width: 991.98px) {
            .sidebar {
                left: -280px; /* Oculto por defecto en móviles */
                margin: 0;
                border-radius: 0;
                width: 260px;
            }
            
            /* Clase para mostrar el sidebar en móviles */
            .sidebar-mobile-visible {
                left: 0 !important;
            }
        }
        
        /* Estilos para dispositivos móviles */
        @media (max-width: 1199.98px) {
            #main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding-left: 15px !important;
                padding-right: 15px !important;
            }
            #topNavbar {
                margin-left: 0 !important;
                width: 100% !important;
                border-radius: 0 !important;
            }
        }
        
        /* Estilos específicos para tablets */
        @media (min-width: 768px) and (max-width: 991.98px) {
            .sidebar {
                left: -260px;
            }
        }
        
        /* Estilos específicos para laptops pequeñas */
        @media (min-width: 992px) and (max-width: 1199.98px) {
            .sidebar {
                width: 220px;
            }
            #main-content {
                margin-left: 220px !important;
            }
        }
        
        /* Estilos específicos para móviles */
        @media (max-width: 767.98px) {
            .sidebar {
                left: -260px;
                width: 260px;
            }
            .navbar {
                padding: 0.5rem 1rem !important;
            }
            .container-fluid {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }
        }
        .sidebar.show {
            left: 0;
        }
        .sidebar.collapsed {
            width: 72px;
            padding-left: 0;
            padding-right: 0;
        }
        .sidebar .sidebar-header {
            padding: 16px 20px 10px 20px;
            border-bottom: 1px solid #49505744;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar .sidebar-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 2px 8px rgba(58,41,249,0.13);
            border: 2px solid #fff2;
            margin-right: 10px;
        }
        .sidebar .sidebar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            padding-left: 20px;
        }
        .sidebar .sidebar-user .sidebar-username {
            font-weight: bold;
            font-size: 1rem;
            color: #fff;
            letter-spacing: 0.02em;
        }
        .sidebar .nav {
            padding-left: 0;
            padding-right: 0;
        }
        .sidebar .nav-link {
            color: #b3c7f9;
            border-radius: 12px;
            margin: 4px 12px;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }
        .sidebar .nav-link .fa {
            font-size: 1.15rem;
        }
        .sidebar .nav-link[title]:hover::after {
            content: attr(title);
            position: absolute;
            left: 110%;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(58,41,249,0.9);
            color: #fff;
            padding: 3px 10px;
            border-radius: 6px;
            white-space: nowrap;
            font-size: 0.95em;
            z-index: 9999;
            box-shadow: 0 2px 8px rgba(58,41,249,0.08);
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(58,41,249,0.18);
            color: #fff;
            box-shadow: 0 2px 8px rgba(58,41,249,0.10);
        }
        .sidebar .nav-link.active {
            border-left: 5px solid #3a29f9;
            background: rgba(58,41,249,0.22);
        }
        .sidebar hr {
            border-top: 1.5px solid #49505744;
            margin: 16px 0 10px 0;
        }
        .sidebar .section-title {
            font-size: 0.9em;
            color: #b3c7f9cc;
            margin: 18px 0 4px 20px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .sidebar .nav-header {
            display: block;
            padding: 0;
            margin: 0;
        }
        .sidebar .small {
            color: #b3c7f9cc;
            margin-left: 12px;
            margin-right: 12px;
            font-size: 0.7rem;
            opacity: 0.8;
        }
        .sidebar.collapsed .nav-header {
            display: none;
        }
        .sidebar .nav-item {
            margin-bottom: 2px;
        }
        @media (max-width: 991.98px) {
            .sidebar {
                left: -270px;
                top: 0;
                margin: 0;
                border-radius: 0 18px 18px 0;
                height: 100vh;
            }
            .sidebar.show {
                left: 0;
                box-shadow: 0 0 0 9999px rgba(0,0,0,0.25);
            }
            #main-content {
                margin-left: 0 !important;
            }
        }
        #main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s ease;
            width: calc(100% - 250px);
        }
        .navbar {
            background: rgba(35,41,70,0.85) !important;
            color: #fff;
            box-shadow: 0 4px 18px 0 rgba(35,41,70,0.12);
            backdrop-filter: blur(8px);
            border-bottom: none !important;
        }
        .navbar .navbar-brand, .navbar .nav-link, .navbar .dropdown-toggle, .navbar .dropdown-item {
            color: #fff !important;
        }
        .navbar .dropdown-menu {
            background: #232946;
            color: #fff;
        }
        .navbar .dropdown-item:hover {
            background: rgba(58,41,249,0.18);
            color: #fff;
        }
        .navbar .dropdown-toggle {
            border-radius: 12px;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(58,41,249,0.10);
            background: #232946 !important;
            color: #fff !important;
        }
        .navbar .dropdown-toggle:hover, .navbar .dropdown-toggle:focus, .navbar .dropdown-toggle[aria-expanded="true"] {
            background: rgba(58,41,249,0.18) !important;
            color: #fff !important;
            box-shadow: 0 2px 8px rgba(58,41,249,0.10);
        }
        .menu-top:hover{
            background-color: #3a29f9;
            color: white;
        }
        
        /* Avatar en la topbar */
        .user-avatar-topbar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .user-avatar-topbar:hover {
            border-color: rgba(58, 41, 249, 0.8);
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(58, 41, 249, 0.4);
        }
        
        /* Eliminado para evitar conflictos */
        
        /* Overlay para el sidebar en móviles */
        #sidebarOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1035; /* Mayor que sidebar pero menor que el botón toggle */
            display: none;
            transition: opacity 0.3s ease;
            opacity: 0;
        }
        
        #sidebarOverlay.active {
            opacity: 1;
            display: block;
        }
        
        /* Ajustes para dispositivos móviles */
        @media (max-width: 767.98px) {
            .navbar .dropdown .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }
            
            #topNavbar {
                height: auto !important;
                min-height: 56px;
            }
            
            .container-fluid {
                padding: 8px !important;
            }
        }
        
        /* Estilos para el icono de toggle del submenu */
        .submenu-icon {
            transition: transform 0.3s ease;
        }
        
        .submenu-toggle[aria-expanded="true"] .submenu-icon {
            transform: rotate(180deg);
        }
    </style>
    
    @yield('css')
</head>
<body>
<!-- Sidebar & Main Wrapper -->
<div id="wrapper">
    <!-- Sidebar -->
    <nav id="sidebarMenu" class="sidebar bg-dark text-white flex-shrink-0 p-3 position-fixed h-100" style="width: 230px; z-index:1040;">
        <div class="sidebar-header d-flex align-items-center justify-content-between mb-3">
            <span class="fs-4 fw-bold"><i class="fa-solid fa-robot me-2"></i>Cirilo</span>
            <button class="btn btn-sm btn-outline-light d-lg-none" id="sidebarToggle"><i class="fa fa-bars"></i></button>
        </div>
        <div class="sidebar-user">
            <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()?->name ?? 'Usuario') }}&background=3a29f9&color=fff" alt="Avatar" class="sidebar-avatar">
            <span class="sidebar-username">{{ Auth::user()?->name ?? 'Invitado' }}</span>
        </div>
        <ul class="nav nav-pills flex-column mb-auto">
            <!-- Inicio -->
            <li class="nav-item submenu-group">
                <a href="#" class="nav-link submenu-toggle text-white d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#inicioSubmenu" aria-expanded="false">
                    <span>
                        <i class="fa-solid fa-home me-2"></i>
                        <span class="submenu-title">Inicio</span>
                    </span>
                    <i class="fa-solid fa-chevron-down submenu-icon"></i>
                </a>
                <div class="collapse" id="inicioSubmenu">
                    <ul class="nav nav-pills flex-column submenu">
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white {{ Request::routeIs('idex_home') ? 'active bg-primary' : '' }}" href="{{ route('idex_home') }}">
                                <i class="fa-solid fa-house me-2"></i>Dashboard
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Asistente Virtual -->
            <li class="nav-item submenu-group">
                <a href="#" class="nav-link submenu-toggle text-white d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#asistenteSubmenu" aria-expanded="false">
                    <span>
                        <i class="fa-solid fa-robot me-2"></i>
                        <span class="submenu-title">Asistente Virtual</span>
                    </span>
                    <i class="fa-solid fa-chevron-down submenu-icon"></i>
                </a>
                <div class="collapse" id="asistenteSubmenu">
                    <ul class="nav nav-pills flex-column submenu">
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white {{ Request::routeIs('preguntas') ? 'active bg-primary' : '' }}" href="{{ route('preguntas') }}">
                                <i class="fa-solid fa-comments me-2"></i>Asistente
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white {{ Request::routeIs('creative_mode') ? 'active bg-primary' : '' }}" href="{{ route('creative_mode') }}">
                                <i class="fa-solid fa-lightbulb me-2"></i>Modo Creativo
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white {{ Request::routeIs('image_analysis') ? 'active bg-primary' : '' }}" href="{{ route('image_analysis') }}">
                                <i class="fa-solid fa-eye me-2"></i>Análisis de Imágenes
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white {{ Request::routeIs('create_image') ? 'active bg-primary' : '' }}" href="{{ route('create_image')}}">
                                <i class="fa-solid fa-image me-2"></i>Crear Imagen
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Agenda -->
            @if(config('features.agenda_enabled', false))
            <li class="nav-item">
                <a class="nav-link text-white {{ Request::routeIs('agenda.index') ? 'active bg-primary' : '' }}" href="{{ route('agenda.index') }}">
                    <i class="fa-solid fa-calendar-alt me-2"></i>
                    <span class="submenu-title">Agenda</span>
                </a>
            </li>
            @endif

            <!-- Tutor IA -->
            @if(config('features.tutor_enabled', false))
            <li class="nav-item submenu-group">
                <a href="#" class="nav-link submenu-toggle text-white d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#tutorSubmenu" aria-expanded="false">
                    <span>
                        <i class="fa-solid fa-graduation-cap me-2"></i>
                        <span class="submenu-title">Tutor IA</span>
                    </span>
                    <i class="fa-solid fa-chevron-down submenu-icon"></i>
                </a>
                <div class="collapse" id="tutorSubmenu">
                    <ul class="nav nav-pills flex-column submenu">
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white {{ Request::routeIs('tutor') ? 'active bg-primary' : '' }}" href="{{ route('tutor') }}">
                                <i class="fa-solid fa-graduation-cap me-2"></i>Tutor
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            @endif
            
            <!-- Juegos -->
            <li class="nav-item">
                <a class="nav-link text-white {{ Request::routeIs('games.*') || Request::routeIs('typing.*') ? 'active bg-primary' : '' }}" href="{{ route('games.index') }}">
                    <i class="fa-solid fa-gamepad me-2"></i>Juegos
                </a>
            </li>
            
            <!-- Progreso & Estadísticas -->
            <li class="nav-item submenu-group">
                <a href="#" class="nav-link submenu-toggle text-white d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#progresoSubmenu" aria-expanded="false">
                    <span>
                        <i class="fa-solid fa-chart-line me-2"></i>
                        <span class="submenu-title">Progreso</span>
                    </span>
                    <i class="fa-solid fa-chevron-down submenu-icon"></i>
                </a>
                <div class="collapse" id="progresoSubmenu">
                    <ul class="nav nav-pills flex-column submenu">
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white {{ Request::routeIs('engagement') ? 'active bg-primary' : '' }}" href="{{ route('engagement') }}">
                                <i class="fa-solid fa-chart-pie me-2"></i>Dashboard Engagement
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white {{ Request::routeIs('conversations_history') ? 'active bg-primary' : '' }}" href="{{ route('conversations_history') }}">
                                <i class="fa-solid fa-clock-rotate-left me-2"></i>Historial
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Administración -->
            @if(Auth::user() && Auth::user()->role && Auth::user()->role->name === 'admin')
            <li class="nav-item submenu-group">
                <a href="#" class="nav-link submenu-toggle text-white d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#adminSubmenu" aria-expanded="false">
                    <span>
                        <i class="fa-solid fa-user-shield me-2"></i>
                        <span class="submenu-title">Administración</span>
                    </span>
                    <i class="fa-solid fa-chevron-down submenu-icon"></i>
                </a>
                <div class="collapse" id="adminSubmenu">
                    <ul class="nav nav-pills flex-column submenu">
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white {{ Request::routeIs('admin.dashboard') ? 'active bg-primary' : '' }}" href="{{ route('admin.dashboard') }}">
                                <i class="fa-solid fa-chart-line me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white {{ Request::is('admin/users*') ? 'active bg-primary' : '' }}" href="{{ url('admin/users') }}">
                                <i class="fa-solid fa-users me-2"></i>Usuarios
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white {{ Request::routeIs('admin.audios.*') ? 'active bg-primary' : '' }}" href="{{ route('admin.audios.index') }}">
                                <i class="fa-solid fa-microphone me-2"></i>Audios Estáticos
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link text-white {{ Request::routeIs('admin.api-usage.*') ? 'active bg-primary' : '' }}" href="{{ route('admin.api-usage.index') }}">
                                <i class="fa-solid fa-clipboard-list me-2"></i>Bitácora
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            @endif
            <!-- Más enlaces -->
        </ul>
        <hr>
        <div class="d-none d-lg-block">
            <span class="small"><i class="fa fa-copyright"></i> 2025 Cirilo</span>
        </div>
    </nav>

    <!-- Overlay para sidebar en móviles -->
    <div id="sidebarOverlay"></div>
    
    <!-- Main Content -->
    <div class="wrapper d-flex flex-column min-vh-100">
        <!-- Topbar -->
        <nav class="navbar navbar-expand-lg sticky-top shadow-sm custom-navbar" style="height:auto; min-height:70px; z-index:1030; position:relative; background:rgba(35,41,70,0.95); color:#fff; backdrop-filter:blur(12px); transition: all 0.3s ease;" id="topNavbar">
            <div class="container-fluid">
                <div class="d-flex align-items-center w-100">
                    <!-- Botón sidebar móvil -->
                    <button class="btn btn-primary d-lg-none me-3" id="sidebarToggleMobile" style="z-index: 1050;">
                        <i class="fa fa-bars"></i>
                    </button>
                    
                    <!-- Logo/Brand -->
                    <span class="navbar-brand mb-0 h6 d-none d-md-block me-4">
                        <i class="fa-solid fa-robot text-primary me-2"></i>Cirilo
                    </span>
                    
                    <!-- Barra de progreso XP (solo si el usuario está autenticado) -->
                    @auth
                    <div class="flex-grow-1 d-none d-md-flex align-items-center me-4">
                        <div class="user-progress-container d-flex align-items-center">
                            <!-- Avatar y nivel -->
                            <div class="user-level-badge me-3">
                                <div class="d-flex align-items-center">
                                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=3a29f9&color=fff" 
                                         alt="Avatar" class="user-avatar me-2">
                                    <div class="user-level-info">
                                        <div class="user-level">Nivel {{ $gameProgress->level ?? 1 }}</div>
                                        <div class="user-xp-text">{{ number_format($gameProgress->total_xp ?? 0) }} XP</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Barra de progreso -->
                            <div class="xp-progress-container flex-grow-1 me-3">
                                <div class="xp-progress-bar">
                                    @php
                                        $currentXP = $gameProgress->current_xp ?? 0;
                                        $requiredXP = $gameProgress->required_xp ?? 100;
                                        $progressPercent = $requiredXP > 0 ? min(100, ($currentXP / $requiredXP) * 100) : 0;
                                    @endphp
                                    <div class="xp-progress-fill" style="width: {{ $progressPercent }}%"></div>
                                    <span class="xp-progress-text">{{ $currentXP }}/{{ $requiredXP }} XP</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endauth
                    
                    <!-- Elementos del lado derecho -->
                    <div class="d-flex align-items-center">
                        <!-- Campanita de notificaciones (solo si está autenticado) - Sprint 9 Mejorado -->
                        @auth
                        <div class="notification-dropdown me-3">
                            <button class="notification-bell" id="notificationBell" type="button">
                                <i class="fas fa-bell"></i>
                                @if(($unreadNotificationsCount ?? 0) > 0)
                                <span class="notification-badge">
                                    {{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}
                                </span>
                                @endif
                            </button>
                            
                            <!-- Overlay para cerrar dropdown -->
                            <div class="notification-overlay" id="notificationOverlay" onclick="closeNotifications()"></div>
                            
                            <!-- Dropdown de notificaciones mejorado -->
                            <div class="notification-dropdown-content" id="notificationDropdown">
                                <!-- Header -->
                                <div class="notification-dropdown-header">
                                    <div class="notification-header-content">
                                        <h3>
                                            <i class="fas fa-bell"></i>
                                            Notificaciones
                                        </h3>
                                        <button type="button" class="btn-mark-all-read" id="markAllReadBtn" style="display: none;">
                                            <i class="fas fa-check-double"></i>
                                            <span>Marcar leídas</span>
                                        </button>
                                    </div>
                                    <button class="notification-close" type="button" onclick="closeNotifications()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <!-- Body - Lista de notificaciones -->
                                <div class="notification-dropdown-body" id="notificationsList">
                                    <div class="notification-loading">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        <p>Cargando notificaciones...</p>
                                    </div>
                                </div>
                                
                                <!-- Footer con botón -->
                                <div class="notification-dropdown-footer">
                                    <a href="{{ route('notifications.index') }}" class="btn-view-all">
                                        <i class="fas fa-list"></i>
                                        Ver todas
                                        <span class="badge">{{ $totalNotifications ?? 0 }}</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endauth

                        <!-- Toggle audio de notificaciones -->
                        @auth
                        <button id="notif-audio-btn"
                                title="Audio de notificaciones (clic para activar/desactivar)"
                                style="background:none;border:none;padding:0 8px;font-size:1rem;color:rgba(255,255,255,0.65);cursor:pointer;line-height:1;">
                            <i id="notif-audio-icon" class="fas fa-volume-up"></i>
                        </button>
                        @endauth

                        <!-- Fecha/hora -->
                        <span class="me-3 text-light d-none d-lg-block small" id="datetime"></span>
                        
                        <!-- Dropdown de usuario -->
                        <div class="dropdown">
                            <a class="btn btn-light dropdown-toggle d-flex align-items-center" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                @auth
                                    @if(Auth::user()->avatar)
                                        <img src="{{ asset('storage/' . Auth::user()->avatar) }}" 
                                             alt="{{ Auth::user()->name }}" 
                                             class="user-avatar-topbar me-2">
                                    @else
                                        <i class="fas fa-user me-2"></i>
                                    @endif
                                @else
                                    <i class="fas fa-user me-2"></i>
                                @endauth
                                <span class="d-none d-sm-inline">
                                    @auth {{ Auth::user()->name }} @else Invitado @endauth
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                @auth
                                <li><a class="dropdown-item" href="{{ route('profile') }}"><i class="fa fa-user me-2"></i>Perfil</a></li>
                                <li><a class="dropdown-item" href="{{ route('engagement') }}"><i class="fa fa-chart-line me-2"></i>Mi Progreso</a></li>
                                <li><a class="dropdown-item" href="{{ route('settings') }}"><i class="fa fa-cog me-2"></i>Configuración</a></li>
                                <li><hr class="dropdown-divider"></li>
                                @endauth
                                <li>
                                    <a class="dropdown-item" href="#"
                                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        <i class="fa fa-sign-out-alt me-2"></i>Salir
                                    </a>
                                    <form id="logout-form" action="{{ route('logout_session') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Barra de progreso móvil (solo en pantallas pequeñas) -->
                @auth
                <div class="w-100 d-md-none mt-2">
                    <div class="d-flex align-items-center">
                        <div class="user-level-badge-mobile me-2">
                            <span class="badge bg-primary">Nivel {{ $gameProgress->level ?? 1 }}</span>
                        </div>
                        <div class="xp-progress-container-mobile flex-grow-1">
                            <div class="xp-progress-bar-mobile">
                                <div class="xp-progress-fill-mobile" style="width: {{ $progressPercent ?? 0 }}%"></div>
                                <span class="xp-progress-text-mobile">{{ number_format($gameProgress->total_xp ?? 0) }} XP</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endauth
            </div>
        </nav>
        
        <!-- Main Content Area -->
        <div id="main-content" class="flex-grow-1 py-4 px-3 px-md-4">




             <!-- INICIA MENSAJES FLASH -->

             @if(session('success'))
             <div class="alert alert-light-success alert-dismissible fade show border-0 mb-4" role="alert">
                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="#03ba06" d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c-9.4 9.4-9.4 24.6 0 33.9l47 47-47 47c-9.4 9.4-9.4 24.6 0 33.9s24.6 9.4 33.9 0l47-47 47 47c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9l-47-47 47-47c9.4-9.4 9.4-24.6 0-33.9s-24.6-9.4-33.9 0l-47 47-47-47c-9.4-9.4-24.6-9.4-33.9 0z"/></svg></button>
                 <strong>Éxito</strong> {{ session('success') }}</button>
             </div> 
             @endif
 
             @if(isset($errors) && $errors->any())
             <div class="alert alert-light-danger alert-dismissible fade show border-0 mb-4" role="alert">
                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                 <strong>Errores</strong>
                 <ul>
                     @foreach ($errors->all() as $error)
                         <li>{{ $error }}</li>
                     @endforeach
                 </ul>
             </div>
             @endif

             @if(session('error'))
             <div class="alert alert-light-danger alert-dismissible fade show border-0 mb-4" role="alert">
                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                     <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="#c70a1d" d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c-9.4 9.4-9.4 24.6 0 33.9l47 47-47 47c-9.4 9.4-9.4 24.6 0 33.9s24.6 9.4 33.9 0l47-47 47 47c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9l-47-47 47-47c9.4-9.4 9.4-24.6 0-33.9s-24.6-9.4-33.9 0l-47 47-47-47c-9.4-9.4-24.6-9.4-33.9 0z"/></svg></button>
                 <strong>Error</strong> {{ session('error') }}</button>
             </div> 
             @endif
             @if ($errors->any())
                 <div class="alert alert-light-danger alert-dismissible fade show border-0 mb-4" role="alert">
                     <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                     <strong>Errores</strong>
                     <ul>
                         @foreach ($errors->all() as $error)
                             <li>{{ $error }}</li>
                         @endforeach
                     </ul>
                 </div>
             @endif
            
                 <!-- FINALIZA MENSAJES FLASH -->
            @yield('content')
        </div>
    </div>

    @stack('scripts')
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <!-- Notifications System - Sprint 9 -->
    <script src="{{ asset('js/notifications.js') }}"></script>
    
    <!-- Script unificado para el sidebar (desktop y móvil) -->
    <script>
    (function() {
        document.addEventListener('DOMContentLoaded', function() {
            // Función para actualizar la fecha/hora
            function updateDateTime() {
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
                const now = new Date().toLocaleDateString('es-ES', options);
                const datetimeEl = document.getElementById('datetime');
                if (datetimeEl) {
                    datetimeEl.innerText = now;
                }
            }
            
            // Actualizar fecha/hora y configurar intervalo
            updateDateTime();
            setInterval(updateDateTime, 60000); // Actualiza cada minuto
            
            // Elementos del DOM
            var sidebarToggle = document.getElementById('sidebarToggle');
            var sidebarToggleMobile = document.getElementById('sidebarToggleMobile');
            var sidebar = document.querySelector('.sidebar');
            var content = document.querySelector('.content');
            var overlay = document.getElementById('sidebarOverlay');
            
            // Función para actualizar visibilidad de encabezados de sección
            function updateSectionHeaders() {
                var isCollapsed = sidebar.classList.contains('collapsed');
                var headers = document.querySelectorAll('.sidebar .nav-header');
                
                headers.forEach(function(header) {
                    header.style.display = isCollapsed ? 'none' : 'block';
                });
            }
            
            // Verificar si hay preferencia guardada para desktop
            if (sidebar) {
                var sidebarState = localStorage.getItem('sidebarState');
                if (sidebarState === 'collapsed') {
                    sidebar.classList.add('collapsed');
                    if (content) content.classList.add('expanded');
                    // Ocultar encabezados de sección inmediatamente
                    updateSectionHeaders();
                }
            }
            
            // Toggle del sidebar en desktop
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function(e) {
                    if (e) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    
                    // Toggle de clases
                    sidebar.classList.toggle('collapsed');
                    if (content) content.classList.toggle('expanded');
                    
                    // Actualizar visibilidad de encabezados de sección
                    updateSectionHeaders();
                    
                    // Guardar preferencia
                    var isCollapsed = sidebar.classList.contains('collapsed');
                    localStorage.setItem('sidebarState', isCollapsed ? 'collapsed' : 'expanded');
                });
            }
            
            // Toggle del sidebar en móvil
            if (sidebarToggleMobile && sidebar && overlay) {
                sidebarToggleMobile.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Toggle de clases
                    sidebar.classList.toggle('sidebar-mobile-visible');
                    overlay.classList.toggle('active');
                });
                
                // Cerrar al hacer clic en el overlay
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('sidebar-mobile-visible');
                    overlay.classList.remove('active');
                });
            }
            
            // Cerrar al hacer clic en enlaces del menú en móviles
            if (sidebar) {
                sidebar.addEventListener('click', function(e) {
                    if (e.target.tagName === 'A' && window.innerWidth < 992) {
                        sidebar.classList.remove('sidebar-mobile-visible');
                        if (overlay) overlay.classList.remove('active');
                    }
                });
            }
            
            // Observar cambios en la clase collapsed del sidebar
            if (sidebar) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'class') {
                            updateSectionHeaders();
                        }
                    });
                });
                
                observer.observe(sidebar, { attributes: true });
            }
        });
    })();
    </script>
    <script>
        function updateDateTime() {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
            const now = new Date().toLocaleDateString('es-ES', options);
            const datetimeEl = document.getElementById('datetime');
            if (datetimeEl) {
                datetimeEl.innerText = now;
            }
        }

        // Función para actualizar la fecha/hora
        document.addEventListener('DOMContentLoaded', function() {
            updateDateTime();
            setInterval(updateDateTime, 60000); // Actualiza cada minuto
        });
        
        // Función para el toggle del sidebar en pantallas grandes
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.getElementById('main-content');
            
            // Sidebar toggle para pantallas grandes
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                });
            }
        });        
    </script>
    @vite(['resources/js/app.js'])
    
    <!-- PWA JavaScript -->
    <script>
        // Registrar Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('✅ Service Worker registrado exitosamente:', registration.scope);
                        
                        // Verificar actualizaciones
                        registration.addEventListener('updatefound', function() {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', function() {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // Nueva versión disponible
                                    showUpdateNotification();
                                }
                            });
                        });
                    })
                    .catch(function(error) {
                        console.log('❌ Error al registrar Service Worker:', error);
                    });
            });
        }
        
        // Detectar instalación PWA
        let deferredPrompt;
        let installButton;
        
        window.addEventListener('beforeinstallprompt', function(e) {
            console.log('💡 PWA puede ser instalada');
            e.preventDefault();
            deferredPrompt = e;
            showInstallButton();
        });
        
        // Mostrar botón de instalación
        function showInstallButton() {
            // Crear botón de instalación si no existe
            if (!document.getElementById('pwa-install-btn')) {
                const installBtn = document.createElement('button');
                installBtn.id = 'pwa-install-btn';
                installBtn.innerHTML = '<i class="fas fa-download me-2"></i>Instalar App';
                installBtn.className = 'btn btn-primary btn-sm position-fixed';
                installBtn.style.cssText = 'bottom: 20px; right: 20px; z-index: 1050; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
                
                installBtn.addEventListener('click', function() {
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        deferredPrompt.userChoice.then(function(choiceResult) {
                            if (choiceResult.outcome === 'accepted') {
                                console.log('✅ Usuario aceptó instalar la PWA');
                                Swal.fire({
                                    title: '¡Instalación exitosa!',
                                    text: 'La aplicación se ha instalado correctamente',
                                    icon: 'success',
                                    timer: 3000,
                                    showConfirmButton: false
                                });
                            } else {
                                console.log('❌ Usuario rechazó instalar la PWA');
                            }
                            deferredPrompt = null;
                            installBtn.remove();
                        });
                    }
                });
                
                document.body.appendChild(installBtn);
                
                // Auto-ocultar después de 10 segundos
                setTimeout(() => {
                    if (installBtn && installBtn.parentNode) {
                        installBtn.style.opacity = '0.7';
                    }
                }, 10000);
            }
        }
        
        // Detectar cuando la app es instalada
        window.addEventListener('appinstalled', function(evt) {
            console.log('✅ PWA instalada exitosamente');
            const installBtn = document.getElementById('pwa-install-btn');
            if (installBtn) {
                installBtn.remove();
            }
        });
        
        // Mostrar notificación de actualización
        function showUpdateNotification() {
            Swal.fire({
                title: 'Nueva versión disponible',
                text: '¿Deseas actualizar la aplicación?',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Actualizar',
                cancelButtonText: 'Más tarde',
                confirmButtonColor: '#232946'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.reload();
                }
            });
        }
        
        // Detectar modo de visualización
        function detectDisplayMode() {
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            const isInStandaloneMode = ('standalone' in window.navigator) && (window.navigator.standalone);
            
            if (isStandalone || (isIOS && isInStandaloneMode)) {
                document.body.classList.add('pwa-mode');
                console.log('📱 Ejecutándose como PWA');
            } else {
                console.log('🌐 Ejecutándose en navegador');
            }
        }
        
        // Ejecutar detección al cargar
        document.addEventListener('DOMContentLoaded', detectDisplayMode);
        
        // Manejo de conexión offline/online
        window.addEventListener('online', function() {
            console.log('🌐 Conexión restaurada');
            const offlineAlert = document.getElementById('offline-alert');
            if (offlineAlert) {
                offlineAlert.remove();
            }
        });
        
        window.addEventListener('offline', function() {
            console.log('📵 Sin conexión a internet');
            showOfflineAlert();
        });
        
        // ── Notificaciones locales de agenda (sin VAPID) ──────────────────────
        function initAgendaNotifications() {
            if (!('Notification' in window) || !('serviceWorker' in navigator)) return;

            // IDs ya mostrados, para no repetir en la misma sesión
            const shownKey = 'pwa_shown_notifications';
            function getShown() {
                try { return JSON.parse(localStorage.getItem(shownKey) || '[]'); } catch { return []; }
            }
            function markShown(key) {
                const list = getShown();
                list.push(key);
                localStorage.setItem(shownKey, JSON.stringify(list.slice(-100)));
            }

            async function checkUpcoming() {
                try {
                    const res = await fetch('/agenda/upcoming-notifications');
                    if (!res.ok) return;
                    const { events } = await res.json();
                    if (!events || !events.length) return;

                    const sw = await navigator.serviceWorker.ready;
                    const shown = getShown();

                    events.forEach(ev => {
                        const tag = `agenda-ev-${ev.id}`;
                        if (shown.includes(tag)) return;

                        markShown(tag);
                        sw.active?.postMessage({
                            type: 'SHOW_NOTIFICATION',
                            title: `📅 ${ev.title}`,
                            body: `Empieza en ${ev.minutesBefore} min (${ev.start})`,
                            url: '/agenda',
                            tag,
                        });
                    });
                } catch {
                    // silencioso cuando offline
                }
            }

            function startPolling() {
                checkUpcoming();
                setInterval(checkUpcoming, 5 * 60 * 1000); // cada 5 min
            }

            @auth
            if (Notification.permission === 'granted') {
                startPolling();
            } else if (Notification.permission !== 'denied') {
                // Pedir permiso la primera vez que el usuario interactúa con la página
                document.addEventListener('click', function askOnce() {
                    Notification.requestPermission().then(perm => {
                        if (perm === 'granted') startPolling();
                    });
                    document.removeEventListener('click', askOnce);
                }, { once: true });
            }
            @endauth
        }

        // Iniciar notificaciones una vez que el SW esté listo
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.ready.then(initAgendaNotifications);
        }

        function showOfflineAlert() {
            if (!document.getElementById('offline-alert')) {
                const alert = document.createElement('div');
                alert.id = 'offline-alert';
                alert.className = 'alert alert-warning position-fixed';
                alert.style.cssText = 'top: 20px; right: 20px; z-index: 1060; max-width: 300px;';
                alert.innerHTML = `
                    <i class="fas fa-wifi-slash me-2"></i>
                    <strong>Sin conexión</strong><br>
                    <small>Algunas funciones pueden no estar disponibles</small>
                `;
                document.body.appendChild(alert);
            }
        }
    </script>
    
    <!-- JavaScript para notificaciones -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const notificationBell = document.getElementById('notificationBell');
        const notificationsList = document.getElementById('notificationsList');
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        
        if (!notificationBell) return; // Si no hay campanita (usuario no autenticado), salir
        
        let notificationsLoaded = false;
        
        // Cargar notificaciones cuando se abre el dropdown
        notificationBell.addEventListener('click', function() {
            if (!notificationsLoaded) {
                loadNotifications();
                notificationsLoaded = true;
            }
        });
        
        // Marcar todas como leídas
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function() {
                markAllNotificationsAsRead();
            });
        }
        
        // Función para cargar notificaciones
        function loadNotifications() {
            fetch('/notifications/unread', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                displayNotifications(data.notifications);
                updateNotificationBadge(data.unread_count);
            })
            .catch(error => {
                console.error('Error cargando notificaciones:', error);
                notificationsList.innerHTML = '<div class="text-center py-3 text-muted">Error al cargar notificaciones</div>';
            });
        }
        
        // Función para mostrar notificaciones
        function displayNotifications(notifications) {
            const markAllBtn = document.getElementById('markAllReadBtn');
            
            if (notifications.length === 0) {
                notificationsList.innerHTML = `
                    <div class="notification-empty">
                        <i class="fas fa-bell-slash"></i>
                        <p>No tienes notificaciones no leídas</p>
                    </div>
                `;
                if (markAllBtn) markAllBtn.style.display = 'none';
                return;
            }
            
            // Mostrar botón de marcar todas como leídas si hay notificaciones
            if (markAllBtn) markAllBtn.style.display = 'inline-block';
            
            let html = '';
            notifications.forEach(notification => {
                const timeAgo = getTimeAgo(notification.created_at);
                const isUnread = !notification.is_read;
                
                html += `
                    <div class="notification-item ${isUnread ? 'unread' : ''}" data-id="${notification.id}">
                        <div class="notification-icon" style="background: ${notification.color || 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'};">
                            <i class="${notification.icon || 'fas fa-bell'}"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">${notification.title}</div>
                            <div class="notification-message">${notification.message}</div>
                            <div class="notification-time">
                                <i class="far fa-clock"></i>
                                ${timeAgo}
                            </div>
                            <div class="notification-actions">
                                <button class="mark-read-btn" onclick="markAsRead(${notification.id})">
                                    <i class="fas fa-check"></i> Marcar leída
                                </button>
                                <button class="delete-btn" onclick="deleteNotification(${notification.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            notificationsList.innerHTML = html;
        }
        
        // Función para marcar todas como leídas
        function markAllNotificationsAsRead() {
            fetch('/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Limpiar lista de notificaciones
                    const notificationsList = document.getElementById('notificationsList');
                    notificationsList.innerHTML = `
                        <div class="notification-empty">
                            <i class="fas fa-bell-slash"></i>
                            <p>No tienes notificaciones no leídas</p>
                        </div>
                    `;
                    
                    // Ocultar botón de marcar todas
                    const markAllBtn = document.getElementById('markAllReadBtn');
                    if (markAllBtn) markAllBtn.style.display = 'none';
                    
                    // Actualizar badge
                    updateNotificationBadge(0);
                    
                    // Mostrar mensaje
                    showToast('Todas las notificaciones marcadas como leídas', 'success');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error al marcar notificaciones', 'error');
            });
        }
        
        // Actualizar el badge de notificaciones
        function updateNotificationBadge(count) {
            const badge = document.querySelector('.notification-bell .badge');
            if (count > 0) {
                if (badge) {
                    badge.textContent = count > 99 ? '99+' : count;
                } else {
                    // Crear badge si no existe
                    const newBadge = document.createElement('span');
                    newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                    newBadge.innerHTML = `${count > 99 ? '99+' : count}<span class="visually-hidden">notificaciones no leídas</span>`;
                    notificationBell.appendChild(newBadge);
                }
            } else {
                if (badge) {
                    badge.remove();
                }
            }
        }
        
        // Función para calcular tiempo transcurrido
        function getTimeAgo(dateString) {
            const now = new Date();
            const date = new Date(dateString);
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) return 'Ahora';
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h`;
            if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d`;
            return date.toLocaleDateString();
        }
        
        // Función para mostrar toast
        function showToast(message, type = 'info') {
            // Usar SweetAlert2 si está disponible
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: message,
                    icon: type === 'error' ? 'error' : 'success',
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            } else {
                // Fallback simple
                console.log(`${type.toUpperCase()}: ${message}`);
            }
        }
        
        // Recargar notificaciones cada 5 minutos
        setInterval(() => {
            if (notificationsLoaded) {
                loadNotifications();
            }
        }, 300000); // 5 minutos
    });
    
    // Funciones globales para los botones
    function markAsRead(notificationId) {
        fetch(`/notifications/${notificationId}/mark-read`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const item = document.querySelector(`[data-id="${notificationId}"]`);
                if (item) {
                    item.classList.remove('unread');
                    const markBtn = item.querySelector('.mark-read-btn');
                    if (markBtn) markBtn.remove();
                }
                
                // Actualizar badge
                const badge = document.querySelector('.notification-bell .badge');
                if (badge) {
                    const currentCount = parseInt(badge.textContent);
                    const newCount = Math.max(0, currentCount - 1);
                    if (newCount === 0) {
                        badge.remove();
                    } else {
                        badge.textContent = newCount > 99 ? '99+' : newCount;
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    
    function deleteNotification(notificationId) {
        if (confirm('¿Estás seguro de que quieres eliminar esta notificación?')) {
            fetch(`/notifications/${notificationId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`[data-id="${notificationId}"]`);
                    if (item) {
                        item.remove();
                    }
                    
                    // Actualizar badge si era no leída
                    const wasUnread = item && item.classList.contains('unread');
                    if (wasUnread) {
                        const badge = document.querySelector('.notification-bell .badge');
                        if (badge) {
                            const currentCount = parseInt(badge.textContent);
                            const newCount = Math.max(0, currentCount - 1);
                            if (newCount === 0) {
                                badge.remove();
                            } else {
                                badge.textContent = newCount > 99 ? '99+' : newCount;
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    }
    </script>
    
    <!-- Estilos PWA -->
    <style>
        .pwa-mode {
            /* Estilos específicos para modo PWA */
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
        }
        
        #pwa-install-btn {
            transition: all 0.3s ease;
        }
        
        #pwa-install-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.4) !important;
        }
        
        #offline-alert {
            animation: slideInRight 0.3s ease;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Mejoras para PWA en iOS */
        @supports (-webkit-touch-callout: none) {
            .pwa-mode {
                -webkit-user-select: none;
                -webkit-touch-callout: none;
                -webkit-tap-highlight-color: transparent;
            }
        }
        
        /* Estilos para la barra de progreso XP */
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .user-level-info {
            line-height: 1.2;
        }
        
        .user-level {
            font-size: 0.875rem;
            font-weight: bold;
            color: #fff;
        }
        
        .user-xp-text {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.8);
        }
        
        .xp-progress-container {
            min-width: 200px;
        }
        
        .xp-progress-bar {
            height: 20px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(4px);
        }
        
        .xp-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3a29f9, #6f42c1);
            border-radius: 10px;
            transition: width 0.5s ease;
            position: relative;
        }
        
        .xp-progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .xp-progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 0.75rem;
            font-weight: bold;
            color: #fff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.5);
            z-index: 2;
        }
        
        /* Estilos para móvil */
        .xp-progress-container-mobile {
            min-width: 150px;
        }
        
        .xp-progress-bar-mobile {
            height: 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }
        
        .xp-progress-fill-mobile {
            height: 100%;
            background: linear-gradient(90deg, #3a29f9, #6f42c1);
            border-radius: 8px;
            transition: width 0.5s ease;
        }
        
        .xp-progress-text-mobile {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 0.7rem;
            font-weight: bold;
            color: #fff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.5);
        }
        
        /* Estilos para las notificaciones */
        .notification-bell .btn {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .notification-bell .btn:hover {
            background-color: rgba(255,255,255,0.1);
            transform: scale(1.1);
        }
        
        .notifications-dropdown {
            border: none;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            border-radius: 12px;
            background: #fff;
            min-width: 320px;
            max-width: 400px;
        }
        
        .notifications-dropdown .dropdown-header {
            background: #f8f9fa;
            border-radius: 12px 12px 0 0;
            padding: 12px 16px;
            border-bottom: 1px solid #e9ecef;
            flex-wrap: nowrap;
        }
        
        .notifications-dropdown .dropdown-header h6 {
            flex: 1;
            min-width: 0;
            margin-right: 8px;
        }
        
        .notifications-dropdown .dropdown-header button {
            flex-shrink: 0;
            white-space: nowrap;
            font-size: 0.75rem;
        }
        
        .notification-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f8f9fa;
            transition: background-color 0.2s ease;
            cursor: pointer;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        
        .notification-item.unread {
            background-color: #e3f2fd;
            border-left: 4px solid #3a29f9;
        }
        
        .notification-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 0.875rem;
        }
        
        .notification-content {
            flex-grow: 1;
            min-width: 0;
            overflow: hidden;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 0.875rem;
            line-height: 1.3;
            margin-bottom: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .notification-message {
            font-size: 0.8rem;
            color: #6c757d;
            line-height: 1.3;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
            margin-bottom: 2px;
            color: #333;
        }
        
        .notification-message {
            font-size: 0.8rem;
            color: #666;
            line-height: 1.3;
        }
        
        .notification-time {
            font-size: 0.75rem;
            color: #999;
            margin-top: 4px;
        }
        
        .notification-actions {
            display: flex;
            gap: 4px;
        }
        
        .notification-actions .btn {
            padding: 2px 6px;
            font-size: 0.7rem;
        }
        
        /* Animación para el badge de notificaciones */
        .notification-bell .badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 767.98px) {
            .user-progress-container {
                display: none !important;
            }
            
            .notification-bell .btn {
                width: 36px;
                height: 36px;
            }
            
            .notifications-dropdown {
                width: 300px !important;
                max-width: calc(100vw - 40px) !important;
                right: 20px !important;
            }
            
            .notifications-dropdown .dropdown-header {
                padding: 10px 12px;
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
            
            .notifications-dropdown .dropdown-header h6 {
                text-align: center;
                margin-right: 0;
            }
            
            .notifications-dropdown .dropdown-header button {
                align-self: stretch;
                font-size: 0.7rem;
            }
            
            .notification-item {
                padding: 10px 12px;
            }
        }
        
        @media (max-width: 480px) {
            .notifications-dropdown {
                width: 280px !important;
                max-width: calc(100vw - 20px) !important;
                right: 10px !important;
            }
        }
        
        @media (max-width: 991.98px) {
            .xp-progress-container {
                min-width: 150px;
            }
            
            .user-level, .user-xp-text {
                font-size: 0.8rem;
            }
        }
    </style>

    <!-- Modal de Configuración de Edad -->
    @auth
    @if(!Auth::user()->age)
    <div class="modal fade" id="ageConfigModal" tabindex="-1" aria-labelledby="ageConfigModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 text-center">
                    <div class="w-100">
                        <i class="fas fa-user-cog fa-3x text-primary mb-3"></i>
                        <h4 class="modal-title" id="ageConfigModalLabel">¡Personaliza tu experiencia!</h4>
                    </div>
                </div>
                <div class="modal-body text-center">
                    <p class="mb-4">Para ofrecerte la mejor experiencia de aprendizaje, necesitamos conocer tu edad. Esto nos ayudará a adaptar los ejercicios y objetivos a tu nivel.</p>
                    
                    <form id="ageConfigForm">
                        @csrf
                        <div class="mb-4">
                            <label for="user_age" class="form-label fw-bold">¿Cuál es tu edad?</label>
                            <div class="row justify-content-center">
                                <div class="col-6">
                                    <select class="form-select form-select-lg" id="user_age" name="age" required>
                                        <option value="">Selecciona tu edad</option>
                                        @for ($i = 6; $i <= 80; $i++)
                                            <option value="{{ $i }}">{{ $i }} años</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info border-0">
                            <small>
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>¿Por qué necesitamos tu edad?</strong><br>
                                • <strong>6-10 años</strong>: Ejercicios más simples y velocidad reducida<br>
                                • <strong>11-15 años</strong>: Dificultad intermedia adaptada<br>
                                • <strong>16+ años</strong>: Desafíos completos y velocidad estándar
                            </small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-primary btn-lg px-5" onclick="saveAge()">
                        <i class="fas fa-save me-2"></i>Guardar y Continuar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mostrar modal de edad al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const ageModal = new bootstrap.Modal(document.getElementById('ageConfigModal'));
            ageModal.show();
        });

        function saveAge() {
            const age = document.getElementById('user_age').value;
            
            if (!age) {
                alert('Por favor selecciona tu edad.');
                return;
            }

            fetch('/save-age', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ age: parseInt(age) })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const ageModal = bootstrap.Modal.getInstance(document.getElementById('ageConfigModal'));
                    ageModal.hide();
                    
                    // Mostrar mensaje de éxito
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success alert-dismissible fade show position-fixed';
                    successAlert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                    successAlert.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>
                        ¡Perfil configurado correctamente!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(successAlert);
                    
                    setTimeout(() => {
                        if (successAlert.parentNode) {
                            successAlert.parentNode.removeChild(successAlert);
                        }
                    }, 5000);
                } else {
                    alert('Error al guardar la edad. Por favor intenta de nuevo.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al guardar la edad. Por favor intenta de nuevo.');
            });
        }
    </script>
    @endif
    @endauth

    {{-- ── Audio global para notificaciones in-app ── --}}
    <audio id="global-notif-audio" class="d-none"></audio>

    @auth
    <script>
    (function () {
        // ── Toggle audio (opt-out: activado por defecto) ──────────────
        let audioEnabled = localStorage.getItem('notif_audio_enabled') !== '0';
        const audioBtn   = document.getElementById('notif-audio-btn');
        const audioIcon  = document.getElementById('notif-audio-icon');
        const audioEl    = document.getElementById('global-notif-audio');

        function syncBtn() {
            if (!audioIcon) return;
            audioIcon.className = audioEnabled ? 'fas fa-volume-up' : 'fas fa-volume-mute';
            if (audioBtn) audioBtn.style.opacity = audioEnabled ? '0.65' : '0.3';
        }
        syncBtn();
        if (audioBtn) {
            audioBtn.addEventListener('click', function () {
                audioEnabled = !audioEnabled;
                localStorage.setItem('notif_audio_enabled', audioEnabled ? '1' : '0');
                syncBtn();
            });
        }

        // ── TTS (mismo endpoint que /conversar) ───────────────────────
        async function speakNotification(text) {
            if (!audioEnabled || !audioEl) return;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res   = await fetch('/text-to-speech', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body:    JSON.stringify({ text: text.substring(0, 250) })
                });
                const data = await res.json();
                if (!data.audioUrl) return;
                audioEl.src = data.audioUrl.replace(/^http:\/\//i, 'https://');
                audioEl.load();
                audioEl.addEventListener('canplay', function h() {
                    audioEl.removeEventListener('canplay', h);
                    audioEl.play().catch(() => {});
                });
            } catch (e) { console.warn('speakNotification:', e); }
        }

        // ── Set de IDs ya anunciados en esta sesión ───────────────────
        const announcedIds = new Set(
            JSON.parse(sessionStorage.getItem('announced_notif_ids') || '[]')
        );
        function saveIds() {
            sessionStorage.setItem('announced_notif_ids', JSON.stringify([...announcedIds]));
        }

        let initialized = false;

        async function checkNewNotifications() {
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res   = await fetch('/notifications/unread', {
                    headers: { 'X-CSRF-TOKEN': token }
                });
                // Sesión expirada o redirigido a login → silenciar, la cookie remember-me
                // volverá a autenticar en la próxima carga de página.
                if (!res.ok || res.redirected) return;
                const { notifications = [] } = await res.json();

                if (!initialized) {
                    // Primera carga: registrar existentes sin audio (no queremos repetir lo anterior)
                    notifications.forEach(n => announcedIds.add(n.id));
                    saveIds();
                    initialized = true;
                    return;
                }

                // Polls siguientes: anunciar solo las nuevas (la más reciente primero)
                for (const notif of notifications) {
                    if (!announcedIds.has(notif.id)) {
                        announcedIds.add(notif.id);
                        saveIds();
                        const text = notif.title + (notif.message ? '. ' + notif.message : '');
                        await speakNotification(text);
                        break; // un audio a la vez por ciclo
                    }
                }
            } catch (e) { console.warn('checkNewNotifications:', e); }
        }

        // Esperar 2s para no competir con la carga inicial de la página
        setTimeout(function () {
            checkNewNotifications();
            setInterval(checkNewNotifications, 60 * 1000);
        }, 2000);
    })();
    </script>
    @endauth

    {{-- ── Modal "¿Personal o de trabajo?" para sincronizar con Nextcloud ── --}}
    @auth
    @if(Auth::user()->hasNextcloud())
    <div class="modal fade" id="nextcloudSyncModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="ncSyncModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0" id="ncSyncModalLabel">
                        <i class="fas fa-calendar-plus me-2 text-primary"></i>Evento creado
                    </h6>
                </div>
                <div class="modal-body text-center">
                    <p id="ncSyncEventTitle" class="fw-bold mb-2 text-truncate"></p>
                    <p class="text-muted small mb-3">¿Es un evento personal o de trabajo?</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary btn-sm" id="ncSyncWorkBtn">
                            <i class="fas fa-briefcase me-2"></i>Trabajo — sincronizar con Nextcloud
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" id="ncSyncPersonalBtn">
                            <i class="fas fa-user me-2"></i>Personal — solo guardar aquí
                        </button>
                    </div>
                    <div id="ncSyncSpinner" class="mt-3" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                        <span class="text-muted small">Sincronizando…</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        const modal        = new bootstrap.Modal(document.getElementById('nextcloudSyncModal'));
        const titleEl      = document.getElementById('ncSyncEventTitle');
        const workBtn      = document.getElementById('ncSyncWorkBtn');
        const personalBtn  = document.getElementById('ncSyncPersonalBtn');
        const spinner      = document.getElementById('ncSyncSpinner');
        const csrf         = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        let pending        = null;

        window.addEventListener('eventCreated', function (e) {
            pending = e.detail; // {id, series_id, title, count}
            if (titleEl) titleEl.textContent = pending.title || 'Nuevo evento';
            modal.show();
        });

        if (workBtn) {
            workBtn.addEventListener('click', async function () {
                if (!pending) return;
                workBtn.disabled = true;
                personalBtn.disabled = true;
                spinner.style.display = 'block';

                const url = pending.series_id
                    ? `/agenda/series/${pending.series_id}/sync-nextcloud`
                    : `/agenda/events/${pending.id}/sync-nextcloud`;

                try {
                    const res  = await fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' }
                    });
                    const data = await res.json();

                    modal.hide();
                    if (typeof Swal !== 'undefined') {
                        const count   = pending.count > 1 ? ` (${pending.count} eventos)` : '';
                        const success = data.success || data.nextcloud_synced;
                        Swal.fire({
                            toast: true, position: 'top-end', timer: 4000,
                            showConfirmButton: false,
                            icon:  success ? 'success' : 'warning',
                            title: success
                                ? `Sincronizado con Nextcloud${count}`
                                : `Guardado localmente. ${data.message || ''}`
                        });
                    }
                } catch (e) {
                    modal.hide();
                    console.warn('nextcloud sync error', e);
                } finally {
                    pending = null;
                    workBtn.disabled = false;
                    personalBtn.disabled = false;
                    spinner.style.display = 'none';
                }
            });
        }

        if (personalBtn) {
            personalBtn.addEventListener('click', function () {
                modal.hide();
                pending = null;
            });
        }
    })();
    </script>
    @endif
    @endauth

</body>
</html>