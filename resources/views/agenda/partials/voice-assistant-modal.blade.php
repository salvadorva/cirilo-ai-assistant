<!-- Modal para el asistente de voz simple -->
<div class="modal fade" id="voiceAssistantModal" tabindex="-1" aria-labelledby="voiceAssistantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="voiceAssistantModalLabel">Asistente de Voz</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div id="voiceStatus" class="mb-3">
                        <p>Presiona el botón para hablar con tu asistente</p>
                    </div>
                    <button id="startRecordingBtn" class="btn btn-success btn-lg rounded-circle">
                        <i class="fas fa-microphone"></i>
                    </button>
                </div>
                <div id="voiceResponse" class="mt-3 p-3 border rounded bg-light" style="display: none;">
                    <p id="responseText"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
