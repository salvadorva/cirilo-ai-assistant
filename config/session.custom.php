<?php

return [
    // Aumentar el tiempo de vida de la sesión a 8 horas (480 minutos)
    'lifetime' => 480,

    // Asegurar que la sesión no expire al cerrar el navegador
    'expire_on_close' => false,

    // Regenerar el ID de sesión periódicamente para mayor seguridad
    'lottery' => [2, 100],
];
