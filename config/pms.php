<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contraseña de los usuarios iniciales
    |--------------------------------------------------------------------------
    |
    | La usa UsuariosInicialesSeeder al crear el superadministrador, la
    | administradora y el analista de Credibanco. Si está vacía se genera una
    | aleatoria y se muestra en la consola.
    |
    */

    'password_inicial' => env('PMS_PASSWORD_INICIAL'),

    /*
    |--------------------------------------------------------------------------
    | Crédito del desarrollador
    |--------------------------------------------------------------------------
    |
    | Se muestra en el pie de las pantallas públicas, en el menú lateral y en
    | los comprobantes.
    |
    */

    'desarrollador' => [
        'nombre' => 'Santiago Bellaizan',
        'telefono' => '3202230467',
    ],

    /*
    |--------------------------------------------------------------------------
    | Evidencias
    |--------------------------------------------------------------------------
    |
    | Disco privado donde se guardan fotos y firmas. Para pasar a un
    | almacenamiento compatible con S3 basta con cambiar el disco.
    |
    */

    'evidencias' => [
        'disco' => env('PMS_EVIDENCIAS_DISCO', 'local'),
        'max_kb' => (int) env('PMS_EVIDENCIAS_MAX_KB', 8192),
        'ancho_maximo' => 1600,
        'calidad_jpeg' => 82,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notificaciones a la app
    |--------------------------------------------------------------------------
    |
    | La app usa el servicio de notificaciones de Expo, que entrega por
    | Firebase Cloud Messaging en Android.
    |
    */

    'push' => [
        'activo' => (bool) env('PMS_PUSH_ACTIVO', true),
        'url' => env('PMS_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),
        'token_acceso' => env('EXPO_ACCESS_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Captcha de la consulta pública (Cloudflare Turnstile)
    |--------------------------------------------------------------------------
    |
    | Sin llaves, la consulta queda protegida solo por el límite de consultas
    | por IP y el campo trampa del formulario.
    |
    */

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | App Android
    |--------------------------------------------------------------------------
    |
    | Ruta (en el disco privado) del APK firmado que se descarga desde el
    | enlace privado de la plataforma.
    |
    */

    'apk' => [
        'disco' => 'local',
        'ruta' => env('PMS_APK_RUTA', 'app/pms-track.apk'),
    ],

];
