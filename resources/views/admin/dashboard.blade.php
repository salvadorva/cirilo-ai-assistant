@extends('layout.app')

@section('title', 'Dashboard Administrativo')

@section('styles')
<style>
    * {
        box-sizing: border-box;
    }
    
    .admin-dashboard {
        padding: 2rem;
        background: #f8f9fa;
        min-height: 100vh;
        max-width: 1600px;
        margin: 0 auto;
        width: 100%;
    }
    
    .admin-dashboard > * {
        width: 100%;
    }

    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2.5rem;
        padding: 2rem;
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    }

    .dashboard-header h1 {
        font-size: 2rem;
        font-weight: 800;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .export-btn {
        padding: 0.875rem 1.75rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.938rem;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .export-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        color: white;
    }

    .export-btn:active {
        transform: translateY(0);
    }

    .metrics-grid {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 1.5rem !important;
        margin-bottom: 2rem;
        width: 100%;
    }
    
    @media (max-width: 1400px) {
        .metrics-grid {
            grid-template-columns: repeat(3, 1fr) !important;
        }
    }
    
    @media (max-width: 992px) {
        .metrics-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }
    }
    
    @media (max-width: 576px) {
        .metrics-grid {
            grid-template-columns: 1fr !important;
        }
    }

    .metric-card {
        background: white;
        padding: 1.75rem;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        display: flex !important;
        flex-direction: column !important;
        min-height: 160px;
        max-width: 100%;
        width: 100%;
        position: relative;
        overflow: hidden;
    }

    .metric-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--card-color, #3498db), transparent);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .metric-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }

    .metric-card:hover::before {
        opacity: 1;
    }

    .metric-card.blue { --card-color: #2196f3; }
    .metric-card.green { --card-color: #4caf50; }
    .metric-card.orange { --card-color: #ff9800; }
    .metric-card.purple { --card-color: #9c27b0; }
    .metric-card.red { --card-color: #f44336; }
    .metric-card.teal { --card-color: #009688; }

    .metric-card .icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        margin-bottom: 1.25rem;
        flex-shrink: 0;
    }

    .metric-card.blue .icon { background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); color: #1976d2; }
    .metric-card.green .icon { background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); color: #388e3c; }
    .metric-card.orange .icon { background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); color: #f57c00; }
    .metric-card.purple .icon { background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); color: #7b1fa2; }
    .metric-card.red .icon { background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%); color: #d32f2f; }
    .metric-card.teal .icon { background: linear-gradient(135deg, #e0f2f1 0%, #b2dfdb 100%); color: #00796b; }

    .metric-card .label {
        font-size: 0.813rem;
        color: #7f8c8d;
        margin-bottom: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .metric-card .value {
        font-size: 2.25rem;
        font-weight: 800;
        color: #2c3e50;
        margin-bottom: 0.5rem;
        line-height: 1;
    }

    .metric-card .subtext {
        font-size: 0.813rem;
        color: #95a5a6;
        margin-top: auto;
        font-weight: 500;
    }

    .charts-section {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .chart-card {
        background: white;
        padding: 2rem;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        transition: box-shadow 0.3s ease;
    }

    .chart-card:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }

    .chart-card.full-width {
        grid-column: 1 / -1;
    }

    .chart-card h3 {
        font-size: 1.125rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .chart-card canvas {
        max-height: 300px;
    }

    @media (max-width: 1200px) {
        .charts-section {
            grid-template-columns: 1fr;
        }
    }

    .top-users-section {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .top-users-card {
        background: white;
        padding: 2rem;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        transition: box-shadow 0.3s ease;
    }

    .top-users-card:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }

    .top-users-card h3 {
        font-size: 1.125rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f0f0f0;
    }

    @media (max-width: 1200px) {
        .top-users-section {
            grid-template-columns: 1fr;
        }
    }

    .user-item {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        border-bottom: 1px solid #ecf0f1;
        transition: background 0.2s ease;
    }

    .user-item:hover {
        background: #f8f9fa;
    }

    .user-item:last-child {
        border-bottom: none;
    }

    .user-rank {
        width: 30px;
        height: 30px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.875rem;
        margin-right: 1rem;
    }

    .user-rank.gold { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
    .user-rank.silver { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    .user-rank.bronze { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }

    .user-info {
        flex: 1;
    }

    .user-name {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }

    .user-email {
        font-size: 0.75rem;
        color: #95a5a6;
    }

    .user-stat {
        font-weight: 700;
        color: #3498db;
        font-size: 1.125rem;
    }

    .users-table-section {
        background: white;
        padding: 2rem;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        margin-bottom: 2rem;
    }

    .users-table-section h3 {
        font-size: 1.125rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f0f0f0;
    }

    .table-filters {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .table-filters input,
    .table-filters select {
        padding: 0.5rem 1rem;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        font-size: 0.875rem;
    }

    .users-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .users-table th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1rem 1.25rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: none;
    }

    .users-table th:first-child {
        border-radius: 8px 0 0 0;
    }

    .users-table th:last-child {
        border-radius: 0 8px 0 0;
    }

    .users-table td {
        padding: 1.25rem;
        border-bottom: 1px solid #ecf0f1;
        background: white;
        font-size: 0.938rem;
    }

    .users-table tr:hover td {
        background: #f8f9fa;
    }

    .users-table tr:last-child td:first-child {
        border-radius: 0 0 0 8px;
    }

    .users-table tr:last-child td:last-child {
        border-radius: 0 0 8px 0;
    }

    .level-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .xp-badge {
        color: #f39c12;
        font-weight: 700;
    }

    .streak-badge {
        color: #e74c3c;
        font-weight: 700;
    }

    .achievements-badge {
        color: #9b59b6;
        font-weight: 700;
    }

    @media (max-width: 768px) {
        .charts-section,
        .top-users-section {
            grid-template-columns: 1fr;
        }

        .dashboard-header {
            flex-direction: column;
            gap: 1rem;
        }
    }
</style>
@endsection

@section('content')
<div class="admin-dashboard">
    <div class="dashboard-header">
        <h1>📊 Dashboard Administrativo</h1>
        <a href="{{ route('admin.export-metrics') }}" class="export-btn">
            📥 Exportar Métricas
        </a>
    </div>

    <!-- Resumen Rápido -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 2rem; border-radius: 16px; margin-bottom: 2rem; color: white; box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; text-align: center;">
            <div>
                <div style="font-size: 3rem; font-weight: 800; margin-bottom: 0.5rem;">{{ $totalUsers }}</div>
                <div style="font-size: 0.875rem; opacity: 0.9; font-weight: 600;">USUARIOS TOTALES</div>
            </div>
            <div>
                <div style="font-size: 3rem; font-weight: 800; margin-bottom: 0.5rem;">{{ $activeUsersToday }}</div>
                <div style="font-size: 0.875rem; opacity: 0.9; font-weight: 600;">ACTIVOS HOY</div>
            </div>
            <div>
                <div style="font-size: 3rem; font-weight: 800; margin-bottom: 0.5rem;">{{ number_format($totalXP) }}</div>
                <div style="font-size: 0.875rem; opacity: 0.9; font-weight: 600;">XP TOTAL</div>
            </div>
            <div>
                <div style="font-size: 3rem; font-weight: 800; margin-bottom: 0.5rem;">{{ round($completionRate) }}%</div>
                <div style="font-size: 0.875rem; opacity: 0.9; font-weight: 600;">COMPLETACIÓN</div>
            </div>
        </div>
    </div>

    <!-- Métricas Principales -->
    <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="metric-card blue" style="background: white; padding: 1.75rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; flex-direction: column; min-height: 160px;">
            <div class="icon" style="width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); color: #1976d2;">👥</div>
            <div class="label" style="font-size: 0.813rem; color: #7f8c8d; margin-bottom: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Total Usuarios</div>
            <div class="value" style="font-size: 2.25rem; font-weight: 800; color: #2c3e50; margin-bottom: 0.5rem; line-height: 1;">{{ $totalUsers }}</div>
            <div class="subtext" style="font-size: 0.813rem; color: #95a5a6; margin-top: auto; font-weight: 500;">Registrados en la plataforma</div>
        </div>

        <div class="metric-card green" style="background: white; padding: 1.75rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; flex-direction: column; min-height: 160px;">
            <div class="icon" style="width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); color: #388e3c;">✅</div>
            <div class="label" style="font-size: 0.813rem; color: #7f8c8d; margin-bottom: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Activos Hoy</div>
            <div class="value" style="font-size: 2.25rem; font-weight: 800; color: #2c3e50; margin-bottom: 0.5rem; line-height: 1;">{{ $activeUsersToday }}</div>
            <div class="subtext" style="font-size: 0.813rem; color: #95a5a6; margin-top: auto; font-weight: 500;">{{ $totalUsers > 0 ? round(($activeUsersToday / $totalUsers) * 100, 1) : 0 }}% del total</div>
        </div>

        <div class="metric-card orange" style="background: white; padding: 1.75rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; flex-direction: column; min-height: 160px;">
            <div class="icon" style="width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem; background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); color: #f57c00;">📅</div>
            <div class="label" style="font-size: 0.813rem; color: #7f8c8d; margin-bottom: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Activos esta Semana</div>
            <div class="value" style="font-size: 2.25rem; font-weight: 800; color: #2c3e50; margin-bottom: 0.5rem; line-height: 1;">{{ $activeUsersWeek }}</div>
            <div class="subtext" style="font-size: 0.813rem; color: #95a5a6; margin-top: auto; font-weight: 500;">{{ $totalUsers > 0 ? round(($activeUsersWeek / $totalUsers) * 100, 1) : 0 }}% del total</div>
        </div>

        <div class="metric-card purple" style="background: white; padding: 1.75rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; flex-direction: column; min-height: 160px;">
            <div class="icon" style="width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem; background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); color: #7b1fa2;">⭐</div>
            <div class="label" style="font-size: 0.813rem; color: #7f8c8d; margin-bottom: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">XP Total</div>
            <div class="value" style="font-size: 2.25rem; font-weight: 800; color: #2c3e50; margin-bottom: 0.5rem; line-height: 1;">{{ number_format($totalXP) }}</div>
            <div class="subtext" style="font-size: 0.813rem; color: #95a5a6; margin-top: auto; font-weight: 500;">Promedio: {{ number_format($avgXP, 0) }} XP/usuario</div>
        </div>

        <div class="metric-card red" style="background: white; padding: 1.75rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; flex-direction: column; min-height: 160px;">
            <div class="icon" style="width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem; background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%); color: #d32f2f;">🔥</div>
            <div class="label" style="font-size: 0.813rem; color: #7f8c8d; margin-bottom: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Racha Promedio</div>
            <div class="value" style="font-size: 2.25rem; font-weight: 800; color: #2c3e50; margin-bottom: 0.5rem; line-height: 1;">{{ round($avgStreak, 1) }}</div>
            <div class="subtext" style="font-size: 0.813rem; color: #95a5a6; margin-top: auto; font-weight: 500;">Máxima: {{ $maxStreak }} días</div>
        </div>

        <div class="metric-card teal" style="background: white; padding: 1.75rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; flex-direction: column; min-height: 160px;">
            <div class="icon" style="width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem; background: linear-gradient(135deg, #e0f2f1 0%, #b2dfdb 100%); color: #00796b;">🏆</div>
            <div class="label" style="font-size: 0.813rem; color: #7f8c8d; margin-bottom: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Logros Desbloqueados</div>
            <div class="value" style="font-size: 2.25rem; font-weight: 800; color: #2c3e50; margin-bottom: 0.5rem; line-height: 1;">{{ $unlockedAchievements }}</div>
            <div class="subtext" style="font-size: 0.813rem; color: #95a5a6; margin-top: auto; font-weight: 500;">{{ round($avgAchievementsPerUser, 1) }} por usuario</div>
        </div>

        <div class="metric-card blue" style="background: white; padding: 1.75rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; flex-direction: column; min-height: 160px;">
            <div class="icon" style="width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); color: #1976d2;">📚</div>
            <div class="label" style="font-size: 0.813rem; color: #7f8c8d; margin-bottom: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Tasa de Completación</div>
            <div class="value" style="font-size: 2.25rem; font-weight: 800; color: #2c3e50; margin-bottom: 0.5rem; line-height: 1;">{{ round($completionRate, 1) }}%</div>
            <div class="subtext" style="font-size: 0.813rem; color: #95a5a6; margin-top: auto; font-weight: 500;">{{ $completedSessions }}/{{ $totalSessions }} sesiones</div>
        </div>

        <div class="metric-card green" style="background: white; padding: 1.75rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; flex-direction: column; min-height: 160px;">
            <div class="icon" style="width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); color: #388e3c;">🔔</div>
            <div class="label" style="font-size: 0.813rem; color: #7f8c8d; margin-bottom: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Notificaciones Leídas</div>
            <div class="value" style="font-size: 2.25rem; font-weight: 800; color: #2c3e50; margin-bottom: 0.5rem; line-height: 1;">{{ round($notificationReadRate, 1) }}%</div>
            <div class="subtext" style="font-size: 0.813rem; color: #95a5a6; margin-top: auto; font-weight: 500;">{{ $readNotifications }}/{{ $totalNotifications }}</div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="charts-section" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="chart-card full-width" style="grid-column: 1 / -1; background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06);">
            <h3 style="font-size: 1.125rem; font-weight: 700; color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0;">📈 Usuarios Activos (Últimos 30 días)</h3>
            <canvas id="activeUsersChart" style="max-height: 300px;"></canvas>
        </div>

        <div class="chart-card" style="background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06);">
            <h3 style="font-size: 1.125rem; font-weight: 700; color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0;">📊 Distribución de Niveles</h3>
            <canvas id="levelDistributionChart" style="max-height: 300px;"></canvas>
        </div>

        <div class="chart-card" style="background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06);">
            <h3 style="font-size: 1.125rem; font-weight: 700; color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0;">📅 Actividad por Día de la Semana</h3>
            <canvas id="activityByDayChart" style="max-height: 300px;"></canvas>
        </div>
    </div>

    <!-- Top Usuarios -->
    <div class="top-users-section" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="top-users-card" style="background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06);">
            <h3 style="font-size: 1.125rem; font-weight: 700; color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0;">🏆 Top 10 por XP</h3>
            @foreach($topUsersByXP as $index => $progress)
                <div class="user-item" style="display: flex; align-items: center; padding: 0.75rem; border-bottom: 1px solid #ecf0f1; transition: background 0.2s ease;">
                    <div class="user-rank" style="width: 30px; height: 30px; background: {{ $index === 0 ? 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' : ($index === 1 ? 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)' : ($index === 2 ? 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)' : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)')) }}; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem; margin-right: 1rem; flex-shrink: 0;">
                        {{ $index + 1 }}
                    </div>
                    <div class="user-info" style="flex: 1; min-width: 0;">
                        <div class="user-name" style="font-weight: 600; color: #2c3e50; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $progress->user->name }}</div>
                        <div class="user-email" style="font-size: 0.75rem; color: #95a5a6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $progress->user->email }}</div>
                    </div>
                    <div class="user-stat" style="font-weight: 700; color: #3498db; font-size: 1.125rem; margin-left: 1rem; flex-shrink: 0;">{{ number_format($progress->total_xp) }} XP</div>
                </div>
            @endforeach
        </div>

        <div class="top-users-card" style="background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06);">
            <h3 style="font-size: 1.125rem; font-weight: 700; color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0;">🔥 Top 10 por Racha</h3>
            @foreach($topUsersByStreak as $index => $progress)
                <div class="user-item" style="display: flex; align-items: center; padding: 0.75rem; border-bottom: 1px solid #ecf0f1; transition: background 0.2s ease;">
                    <div class="user-rank" style="width: 30px; height: 30px; background: {{ $index === 0 ? 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' : ($index === 1 ? 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)' : ($index === 2 ? 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)' : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)')) }}; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem; margin-right: 1rem; flex-shrink: 0;">
                        {{ $index + 1 }}
                    </div>
                    <div class="user-info" style="flex: 1; min-width: 0;">
                        <div class="user-name" style="font-weight: 600; color: #2c3e50; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $progress->user->name }}</div>
                        <div class="user-email" style="font-size: 0.75rem; color: #95a5a6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $progress->user->email }}</div>
                    </div>
                    <div class="user-stat" style="font-weight: 700; color: #e74c3c; font-size: 1.125rem; margin-left: 1rem; flex-shrink: 0;">{{ $progress->current_streak }} días</div>
                </div>
            @endforeach
        </div>

        <div class="top-users-card" style="background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06);">
            <h3 style="font-size: 1.125rem; font-weight: 700; color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0;">🎖️ Top 10 por Logros</h3>
            @foreach($topUsersByAchievements as $index => $user)
                <div class="user-item" style="display: flex; align-items: center; padding: 0.75rem; border-bottom: 1px solid #ecf0f1; transition: background 0.2s ease;">
                    <div class="user-rank" style="width: 30px; height: 30px; background: {{ $index === 0 ? 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' : ($index === 1 ? 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)' : ($index === 2 ? 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)' : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)')) }}; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem; margin-right: 1rem; flex-shrink: 0;">
                        {{ $index + 1 }}
                    </div>
                    <div class="user-info" style="flex: 1; min-width: 0;">
                        <div class="user-name" style="font-weight: 600; color: #2c3e50; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $user->name }}</div>
                        <div class="user-email" style="font-size: 0.75rem; color: #95a5a6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $user->email }}</div>
                    </div>
                    <div class="user-stat" style="font-weight: 700; color: #9b59b6; font-size: 1.125rem; margin-left: 1rem; flex-shrink: 0;">{{ $user->achievements_count }} 🏆</div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Tabla de Usuarios -->
    <div class="users-table-section" style="background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 2rem;">
        <h3 style="font-size: 1.125rem; font-weight: 700; color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0;">👥 Todos los Usuarios</h3>
        
        <table class="users-table" style="width: 100%; border-collapse: separate; border-spacing: 0; overflow: hidden; border-radius: 8px;"
            <thead>
                <tr>
                    <th style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 1.25rem; text-align: left; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px; border: none;">Nombre</th>
                    <th style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 1.25rem; text-align: left; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px; border: none;">Email</th>
                    <th style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 1.25rem; text-align: left; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px; border: none;">Nivel</th>
                    <th style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 1.25rem; text-align: left; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px; border: none;">XP Total</th>
                    <th style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 1.25rem; text-align: left; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px; border: none;">Racha</th>
                    <th style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 1.25rem; text-align: left; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px; border: none;">Logros</th>
                    <th style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 1.25rem; text-align: left; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px; border: none;">Última Actividad</th>
                    <th style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 1.25rem; text-align: center; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px; border: none;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr style="transition: background 0.2s ease;">
                        <td style="padding: 1.25rem; border-bottom: 1px solid #ecf0f1; background: white; font-size: 0.938rem; font-weight: 600; color: #2c3e50;">{{ $user->name }}</td>
                        <td style="padding: 1.25rem; border-bottom: 1px solid #ecf0f1; background: white; font-size: 0.938rem; color: #7f8c8d;">{{ $user->email }}</td>
                        <td style="padding: 1.25rem; border-bottom: 1px solid #ecf0f1; background: white; font-size: 0.938rem;">
                            <span class="level-badge" style="display: inline-block; padding: 0.35rem 0.85rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 20px; font-weight: 600; font-size: 0.813rem;">
                                Nivel {{ $user->gameProgress->level ?? 1 }}
                            </span>
                        </td>
                        <td class="xp-badge" style="padding: 1.25rem; border-bottom: 1px solid #ecf0f1; background: white; font-size: 0.938rem; color: #f39c12; font-weight: 700;">{{ number_format($user->gameProgress->total_xp ?? 0) }} XP</td>
                        <td class="streak-badge" style="padding: 1.25rem; border-bottom: 1px solid #ecf0f1; background: white; font-size: 0.938rem; color: #e74c3c; font-weight: 700;">🔥 {{ $user->gameProgress->current_streak ?? 0 }} días</td>
                        <td class="achievements-badge" style="padding: 1.25rem; border-bottom: 1px solid #ecf0f1; background: white; font-size: 0.938rem; color: #9b59b6; font-weight: 700;">🏆 {{ $user->achievements->count() }}</td>
                        <td style="padding: 1.25rem; border-bottom: 1px solid #ecf0f1; background: white; font-size: 0.938rem; color: #95a5a6;">{{ $user->gameProgress?->last_activity_at?->diffForHumans() ?? 'Nunca' }}</td>
                        <td style="padding: 1.25rem; border-bottom: 1px solid #ecf0f1; background: white; font-size: 0.938rem; text-align: center;">
                            <a href="{{ route('admin.users.analytics', $user->id) }}"
                               style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.875rem; transition: all 0.3s ease;"
                               onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(102, 126, 234, 0.4)';"
                               onmouseout="this.style.transform=''; this.style.boxShadow='';">
                                📊 Ver Analytics
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 1.5rem;">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de Usuarios Activos
    const activeUsersData = @json($activeUsersEvolution);
    const activeUsersCanvas = document.getElementById('activeUsersChart');
    
    if (activeUsersData && activeUsersData.length > 0 && activeUsersCanvas) {
        try {
            new Chart(activeUsersCanvas, {
        type: 'line',
        data: {
            labels: activeUsersData.map(d => d.date),
            datasets: [{
                label: 'Usuarios Activos',
                data: activeUsersData.map(d => d.count),
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
            });
        } catch (error) {
            console.error('Error al crear gráfico de usuarios activos:', error);
        }
    } else if (activeUsersCanvas) {
        activeUsersCanvas.parentElement.innerHTML = '<p style="text-align:center; padding:40px; color:#999;">Sin datos de actividad reciente</p>';
    }

    // Gráfico de Distribución de Niveles
    const levelData = @json($levelDistribution);
    const levelCanvas = document.getElementById('levelDistributionChart');
    
    if (levelData && levelData.length > 0 && levelCanvas) {
        try {
            new Chart(levelCanvas, {
        type: 'bar',
        data: {
            labels: levelData.map(d => `Nivel ${d.level}`),
            datasets: [{
                label: 'Usuarios',
                data: levelData.map(d => d.count),
                backgroundColor: 'rgba(155, 89, 182, 0.8)',
                borderColor: '#9b59b6',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
            });
        } catch (error) {
            console.error('Error al crear gráfico de distribución de niveles:', error);
        }
    } else if (levelCanvas) {
        levelCanvas.parentElement.innerHTML = '<p style="text-align:center; padding:40px; color:#999;">Sin datos de distribución de niveles</p>';
    }

    // Gráfico de Actividad por Día
    const activityData = @json($activityByDay);
    const activityCanvas = document.getElementById('activityByDayChart');
    
    if (activityData && activityData.length > 0 && activityCanvas) {
        try {
            new Chart(activityCanvas, {
        type: 'bar',
        data: {
            labels: activityData.map(d => d.day),
            datasets: [{
                label: 'Usuarios Activos',
                data: activityData.map(d => d.count),
                backgroundColor: [
                    'rgba(231, 76, 60, 0.8)',
                    'rgba(52, 152, 219, 0.8)',
                    'rgba(46, 204, 113, 0.8)',
                    'rgba(241, 196, 15, 0.8)',
                    'rgba(155, 89, 182, 0.8)',
                    'rgba(230, 126, 34, 0.8)',
                    'rgba(149, 165, 166, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
            });
        } catch (error) {
            console.error('Error al crear gráfico de actividad por día:', error);
        }
    } else if (activityCanvas) {
        activityCanvas.parentElement.innerHTML = '<p style="text-align:center; padding:40px; color:#999;">Sin datos de actividad por día</p>';
    }
});
</script>
@endpush
