@extends('layout.app')

@section('title', 'Agenda Virtual')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-calendar-days me-2"></i>Agenda Virtual
                    </h5>
                    <div class="d-flex align-items-center gap-2">
                        <button id="btnAddEvent" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-plus me-1"></i>Nuevo Evento
                        </button>
                        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#voiceAssistantModal" title="Asistente de voz">
                            <i class="fas fa-microphone me-1"></i>Voz
                        </button>
                        <div class="btn-group btn-group-sm">
                            <button type="button" id="btnViewDay" class="btn btn-outline-secondary">Día</button>
                            <button type="button" id="btnViewWeek" class="btn btn-outline-secondary">Semana</button>
                            <button type="button" id="btnViewMonth" class="btn btn-outline-secondary active">Mes</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Modales del calendario -->
@include('agenda.partials.event-modal')

<!-- Modal del asistente de voz -->
@include('agenda.partials.voice-assistant-modal')

@endsection

@push('scripts')
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js'></script>

<!-- Scripts del calendario -->
@include('agenda.partials.calendar-scripts')

<!-- Asistente de voz -->
<script src="{{ asset('js/agenda-voice-assistant.js') }}"></script>
@endpush
