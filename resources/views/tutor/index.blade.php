@extends('layout.app')

@section('title', 'Centro de Aprendizaje')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- Header Section -->
                <div class="card mb-5 bg-primary">
                    <div class="card-body text-center text-white py-8">
                        <h1 class="display-4 fw-bold mb-3">
                            <i class="fas fa-graduation-cap me-3"></i>
                            Centro de Aprendizaje
                        </h1>
                        <p class="fs-4 mb-4">¿Qué deseas aprender hoy?</p>
                        <p class="fs-6 opacity-75">Explora nuestros cursos disponibles o crea uno personalizado con IA</p>
                    </div>
                </div>

                <!-- Cirilo - Tutor IA Notification Area -->
                <div class="card border-0 shadow-sm mb-5 text-white"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start">
                            <div class="me-3">
                                <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center shadow"
                                    style="width: 60px; height: 60px;">
                                    <i class="fas fa-robot fa-2x"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="fw-bold mb-2">Cirilo (Tu Tutor IA) dice:</h5>
                                <div id="tutor-content">
                                    @if(isset($tutorMessage))
                                        <p class="lead mb-3" id="tutor-message-text">{{ $tutorMessage['message'] }}</p>

                                        <div class="d-flex align-items-center gap-3">
                                            @if(isset($tutorMessage['audioUrl']))
                                                <audio id="tutor-audio" controls autoplay class="d-none">
                                                    <source src="{{ $tutorMessage['audioUrl'] }}" type="audio/mpeg">
                                                </audio>
                                                <button class="btn btn-light btn-sm rounded-pill px-3 shadow-sm"
                                                    onclick="playTutorAudio()">
                                                    <i class="fas fa-volume-up me-1"></i> Escuchar de nuevo
                                                </button>
                                            @endif
                                        </div>
                                    @else
                                        <p class="lead mb-3">¡Hola! Estoy analizando tu progreso para darte recomendaciones
                                            personalizadas...</p>
                                    @endif
                                </div>

                                <div class="mt-3">
                                    <button class="btn btn-outline-light btn-sm rounded-pill px-3" id="btn-refresh-tutor"
                                        onclick="refreshTutorMessage()">
                                        <i class="fas fa-sync-alt me-1"></i> Actualizar mensaje
                                    </button>
                                    <span class="ms-2 small opacity-75" id="tutor-last-updated">
                                        @if(isset($tutorMessage['generated_at']))
                                            Actualizado
                                            {{ \Carbon\Carbon::parse($tutorMessage['generated_at'])->diffForHumans() }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Course Selection Section -->
                <div class="row g-4 mb-8">
                    <!-- English Course Card -->
                    <div class="col-lg-6">
                        <div class="card h-100 shadow-sm hover-elevate-up">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="symbol symbol-50px me-3">
                                        <span class="symbol-label bg-light-primary">
                                            <i class="fas fa-flag-usa text-primary fs-2"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <h3 class="card-title mb-1">Curso de Inglés</h3>
                                        <p class="text-muted mb-0">Curso completo con evaluación IA</p>
                                    </div>
                                </div>

                                <div class="mb-4 flex-grow-1">
                                    <p class="text-gray-700">
                                        Mejora tu inglés con nuestro sistema inteligente que incluye:
                                    </p>
                                    <ul class="list-unstyled">
                                        <li class="d-flex align-items-center mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Evaluación personalizada de nivel
                                        </li>
                                        <li class="d-flex align-items-center mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Ejercicios de speaking con IA
                                        </li>
                                        <li class="d-flex align-items-center mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Gramática, vocabulario y listening
                                        </li>
                                        <li class="d-flex align-items-center mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Seguimiento de progreso
                                        </li>
                                    </ul>

                                    @if(isset($englishProgress))
                                        <div class="bg-light-info p-3 rounded mt-3">
                                            <small class="text-info fw-bold">Tu progreso actual:</small>
                                            <div class="row mt-2">
                                                <div class="col-6">
                                                    <small>Nivel: <span
                                                            class="badge badge-light-primary">{{ $englishProgress['level'] }}</span></small>
                                                </div>
                                                <div class="col-6">
                                                    <small>Speaking: {{ $englishProgress['speaking_score'] }}/100</small>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <a href="{{ route('tutor.english') }}" class="btn btn-primary btn-lg">
                                    <i class="fas fa-play me-2"></i>Continuar Curso de Inglés
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Course Card -->
                    <div class="col-lg-6">
                        <div class="card h-100 shadow-sm hover-elevate-up">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="symbol symbol-50px me-3">
                                        <span class="symbol-label bg-light-success">
                                            <i class="fas fa-robot text-success fs-2"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <h3 class="card-title mb-1">Curso Personalizado</h3>
                                        <p class="text-muted mb-0">Creado con Inteligencia Artificial</p>
                                    </div>
                                </div>

                                <div class="mb-4 flex-grow-1">
                                    <p class="text-gray-700">
                                        Crea un curso sobre cualquier tema que te interese:
                                    </p>
                                    <ul class="list-unstyled">
                                        <li class="d-flex align-items-center mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Cualquier tema (JavaScript, Marketing, etc.)
                                        </li>
                                        <li class="d-flex align-items-center mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Contenido generado por IA
                                        </li>
                                        <li class="d-flex align-items-center mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Ejercicios prácticos incluidos
                                        </li>
                                        <li class="d-flex align-items-center mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Audio y material visual
                                        </li>
                                    </ul>
                                </div>

                                <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal"
                                    data-bs-target="#createCourseModal">
                                    <i class="fas fa-plus me-2"></i>Crear Curso Personalizado
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User's Recent Courses -->
                @if($userCourses && $userCourses->count() > 0)
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-history me-2"></i>Tus Cursos Recientes
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                @foreach($userCourses as $course)
                                    <div class="col-md-4">
                                        <div class="card border border-gray-300">
                                            <div class="card-body">
                                                <h5 class="card-title">{{ $course->title }}</h5>
                                                <p class="card-text text-muted small">{{ Str::limit($course->description, 100) }}
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="badge badge-light-info">{{ ucfirst($course->level) }}</span>
                                                    <span class="text-muted small">{{ $course->sessions_count }} sesiones</span>
                                                </div>
                                                <div class="mt-3 d-flex gap-2">
                                                    <a href="{{ route('courses.show', $course->id) }}"
                                                        class="btn btn-sm btn-outline-primary">
                                                        Ver Curso
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteCourse({{ $course->id }}, '{{ addslashes($course->title) }}')"
                                                        title="Eliminar curso">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="text-center mt-4">
                                <a href="{{ route('courses.index') }}" class="btn btn-light-primary">
                                    Ver Todos Mis Cursos
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Create Course Modal -->
    <div class="modal fade" id="createCourseModal" tabindex="-1" aria-labelledby="createCourseModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createCourseModalLabel">
                        <i class="fas fa-robot me-2"></i>Crear Curso Personalizado
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="createCourseForm">
                    <div class="modal-body">
                        <div class="mb-4">
                            <label for="course_topic" class="form-label fw-bold">¿Qué quieres aprender?</label>
                            <input type="text" class="form-control form-control-lg" id="course_topic" name="course_topic"
                                placeholder="Ej: JavaScript, Marketing Digital, Fotografía..." required>
                            <div class="form-text">Describe el tema que te interesa aprender</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <label for="level" class="form-label fw-bold">Nivel</label>
                                <select class="form-select" id="level" name="level" required>
                                    <option value="beginner">Principiante</option>
                                    <option value="intermediate">Intermedio</option>
                                    <option value="advanced">Avanzado</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="sessions_count" class="form-label fw-bold">Número de Sesiones</label>
                                <select class="form-select" id="sessions_count" name="sessions_count" required>
                                    <option value="1">1 sesiones</option>
                                    <option value="2" selected>2 sesiones</option>
                                    <option value="3">3 sesiones</option>
                                </select>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>¿Cómo funciona?</strong><br>
                            Nuestra IA creará un curso estructurado con contenido educativo, ejercicios prácticos,
                            audio y material visual personalizado para tu nivel y tema de interés.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success" id="createCourseBtn">
                            <i class="fas fa-magic me-2"></i>Crear Curso con IA
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            console.log('DOM loaded, initializing course creation form');

            const createCourseForm = document.getElementById('createCourseForm');
            const createCourseBtn = document.getElementById('createCourseBtn');
            const modal = new bootstrap.Modal(document.getElementById('createCourseModal'));

            console.log('Form elements found:', {
                form: !!createCourseForm,
                button: !!createCourseBtn,
                modal: !!modal
            });

            createCourseForm.addEventListener('submit', async function (e) {
                console.log('Form submit event triggered');
                e.preventDefault();

                // Mostrar loading
                const originalBtnText = createCourseBtn.innerHTML;
                createCourseBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creando curso...';
                createCourseBtn.disabled = true;

                // Crear un AbortController para manejar el timeout
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 300000); // 5 minutos de timeout

                try {
                    const formData = new FormData(createCourseForm);
                    const courseTopic = formData.get('course_topic');

                    console.log('Form data prepared:', {
                        topic: courseTopic,
                        level: formData.get('level'),
                        sessions: formData.get('sessions_count')
                    });

                    // Mostrar mensaje de que la operación puede tardar
                    Swal.fire({
                        title: 'Creando curso',
                        html: 'Estamos generando tu curso. Este proceso puede tardar unos minutos.<br><br><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const response = await fetch('{{ route("courses.create") }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        signal: controller.signal
                    });

                    clearTimeout(timeoutId);

                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error('Error response:', errorText);

                        // Intentar parsear como JSON, si falla usar el texto plano
                        let errorData;
                        try {
                            errorData = JSON.parse(errorText);
                        } catch (e) {
                            errorData = { message: errorText };
                        }

                        throw new Error(errorData.message || `Error ${response.status}: ${response.statusText}`);
                    }

                    const result = await response.json();
                    console.log('Response data:', result);

                    if (result.success) {
                        // Cerrar el modal
                        modal.hide();

                        // Mostrar éxito y redirigir
                        Swal.fire({
                            icon: 'success',
                            title: '¡Curso creado!',
                            text: result.message || 'Redirigiendo a tu nuevo curso...',
                            showConfirmButton: false,
                            timer: 3000,
                            didClose: () => {
                                if (result.redirect_url) {
                                    window.location.href = result.redirect_url;
                                }
                            }
                        });

                        // Redirigir por si acaso el usuario cierra el mensaje
                        if (result.redirect_url) {
                            setTimeout(() => {
                                window.location.href = result.redirect_url;
                            }, 3000);
                        }
                    } else {
                        throw new Error(result.message || 'Error desconocido al crear el curso');
                    }

                } catch (error) {
                    console.error('Error:', error);

                    // Verificar si el error es por timeout
                    if (error.name === 'AbortError') {
                        // Mostrar mensaje de que estamos verificando el estado
                        Swal.fire({
                            icon: 'info',
                            title: 'Verificando estado del curso...',
                            text: 'La creación está tomando más tiempo del esperado. Verificando si el curso se creó correctamente.',
                            allowOutsideClick: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        // Verificar si el curso se creó en segundo plano
                        setTimeout(() => {
                            checkCourseCreationStatus(formData);
                        }, 2000);

                        return; // No mostrar el error de timeout inmediatamente
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error al crear curso',
                            html: `<p>${error.message}</p><br><small class="text-muted">Si el problema persiste, intenta con menos sesiones o un tema más específico.</small>`,
                            confirmButtonText: 'Entendido'
                        });
                    }
                } finally {
                    // Restaurar el botón
                    createCourseBtn.disabled = false;
                    createCourseBtn.innerHTML = originalBtnText;
                    clearTimeout(timeoutId);
                }
            }); // Cerrar addEventListener

            // Función para verificar si el curso se creó después de un timeout
            async function checkCourseCreationStatus(formData) {
                try {
                    // Buscar cursos recientes del usuario
                    const response = await fetch('/cursos', {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();

                        // Buscar un curso reciente que coincida con el tema
                        const recentCourse = data.courses?.find(course => {
                            const courseTopicLower = course.topic?.toLowerCase() || '';
                            const formTopicLower = formData.get('course_topic')?.toLowerCase() || '';
                            const timeDiff = new Date() - new Date(course.created_at);
                            return courseTopicLower.includes(formTopicLower) && timeDiff < 300000; // 5 minutos
                        });

                        if (recentCourse) {
                            // El curso se creó exitosamente
                            Swal.fire({
                                icon: 'success',
                                title: '¡Curso creado exitosamente!',
                                text: 'El curso se creó correctamente en segundo plano.',
                                confirmButtonText: 'Ver curso'
                            }).then(() => {
                                window.location.href = `/cursos/${recentCourse.id}`;
                            });

                            // Cerrar el modal
                            modal.hide();
                            return;
                        }
                    }

                    // Si no se encontró el curso, mostrar mensaje de error
                    Swal.fire({
                        icon: 'error',
                        title: 'Timeout en la creación',
                        html: `
                            <p>La creación del curso está tomando más tiempo del esperado.</p>
                            <br>
                            <p><strong>Opciones:</strong></p>
                            <ul style="text-align: left; margin: 10px 0;">
                                <li>Refrescar la página para ver si el curso se creó</li>
                                <li>Intentar con menos sesiones (1-2 sesiones)</li>
                                <li>Usar un tema más específico</li>
                            </ul>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Refrescar página',
                        cancelButtonText: 'Cerrar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            location.reload();
                        }
                    });

                } catch (error) {
                    console.error('Error verificando estado del curso:', error);

                    Swal.fire({
                        icon: 'warning',
                        title: 'No se pudo verificar el estado',
                        text: 'Por favor, refresca la página para ver si el curso se creó correctamente.',
                        confirmButtonText: 'Refrescar página'
                    }).then(() => {
                        location.reload();
                    });
                }
            }
        });

        // Función para eliminar curso
        function deleteCourse(courseId, courseTitle) {
            Swal.fire({
                title: '¿Eliminar curso?',
                html: `¿Estás seguro de que deseas eliminar el curso <strong>"${courseTitle}"</strong>?<br><br><small class="text-danger">Esta acción eliminará todas las sesiones, progreso y recursos asociados. No se puede deshacer.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    Swal.fire({
                        title: 'Eliminando curso...',
                        text: 'Por favor espera mientras eliminamos el curso y sus recursos.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Realizar petición DELETE
                    fetch(`/cursos/${courseId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(async response => {
                            const contentType = response.headers.get('content-type');
                            if (contentType && contentType.includes('application/json')) {
                                return response.json().then(data => ({
                                    data: data,
                                    ok: response.ok
                                }));
                            } else {
                                const text = await response.text();
                                return Promise.reject(new Error('La respuesta del servidor no es un JSON válido'));
                            }
                        })
                        .then(({ data, ok }) => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Curso eliminado!',
                                    text: data.message,
                                    confirmButtonText: 'Entendido'
                                }).then(() => {
                                    // Recargar la página para actualizar la lista
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error al eliminar',
                                    text: data.message || 'No se pudo eliminar el curso'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error al eliminar curso:', error);
                            let errorMessage = 'No se pudo conectar con el servidor para eliminar el curso';

                            if (error.message.includes('JSON')) {
                                errorMessage = 'Error procesando la respuesta del servidor. Por favor, recarga la página e intenta de nuevo.';
                            } else if (error.response) {
                                // Si hay una respuesta del servidor con código de error
                                errorMessage = `Error del servidor (${error.response.status}): ${error.statusText || 'Error desconocido'}`;
                            } else if (error.request) {
                                // La petición fue hecha pero no hubo respuesta
                                errorMessage = 'No se recibió respuesta del servidor. Verifica tu conexión a internet.';
                            }

                            Swal.fire({
                                icon: 'error',
                                title: 'Error al eliminar',
                                html: `${errorMessage}<br><br><small class="text-muted">Si el problema persiste, contacta al soporte.</small>`,
                                confirmButtonText: 'Entendido'
                            });
                        });
                }
            });
        }

        // Funciones del Tutor IA Cirilo
        function playTutorAudio() {
            const audio = document.getElementById('tutor-audio');
            if (audio) {
                audio.currentTime = 0;
                audio.play();
            }
        }

        async function refreshTutorMessage() {
            const btn = document.getElementById('btn-refresh-tutor');
            const originalContent = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Analizando...';

            try {
                const response = await fetch('/tutor/refresh-message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                const data = await response.json();

                if (data.success) {
                    const summary = data.summary;
                    const contentDiv = document.getElementById('tutor-content');

                    let html = `<p class="lead mb-3" id="tutor-message-text">${summary.message}</p>`;

                    if (summary.audioUrl) {
                        html += `
                                <div class="d-flex align-items-center gap-3">
                                    <audio id="tutor-audio" controls autoplay class="d-none">
                                        <source src="${summary.audioUrl}" type="audio/mpeg">
                                    </audio>
                                    <button class="btn btn-light btn-sm rounded-pill px-3 shadow-sm" onclick="playTutorAudio()">
                                        <i class="fas fa-volume-up me-1"></i> Escuchar de nuevo
                                    </button>
                                </div>
                            `;
                    }

                    contentDiv.innerHTML = html;
                    document.getElementById('tutor-last-updated').innerText = 'Actualizado hace un momento';

                    // Reproducir audio automáticamente
                    setTimeout(() => playTutorAudio(), 500);

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Mensaje actualizado',
                        showConfirmButton: false,
                        timer: 3000
                    });
                } else {
                    throw new Error(data.error || 'Error desconocido');
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo actualizar el mensaje. Intenta de nuevo.'
                });
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }
    </script>
@endpush