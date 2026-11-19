<!DOCTYPE html>
<html lang="es">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="flex min-h-screen flex-col bg-ink-50">
        {{ $slot }}

        <x-ui.toasts />
    </body>
</html>
