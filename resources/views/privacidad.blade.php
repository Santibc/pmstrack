<!DOCTYPE html>
<html lang="es">
    <head>
        @include('layouts.partials.head', ['title' => 'Tratamiento de datos personales'])
    </head>
    <body class="flex min-h-screen flex-col bg-ink-50">
    <header class="border-b border-ink-200 bg-white">
        <div class="mx-auto flex max-w-[900px] items-center justify-between gap-4 px-5 py-4 sm:px-8">
            <a href="{{ route('consulta') }}"><x-logo alto="h-12" /></a>
            <x-ui.button :href="route('consulta')" variant="outline" icon="chevron-left">Volver a la consulta</x-ui.button>
        </div>
    </header>

    <main class="mx-auto w-full max-w-[900px] flex-1 px-5 py-12 sm:px-8">
        <h1 class="text-[34px] leading-tight font-extrabold tracking-tight text-ink-900">Tratamiento de datos personales en PMS Track</h1>
        <p class="mt-4 text-lg leading-8 text-ink-600">
            Resumen de cómo se usan los datos personales en la entrega de rollos de datáfono que Precision Marketing Solutions Ltda. (PMS) realiza para Credibanco, conforme a la Ley 1581 de 2012 y el Decreto 1377 de 2013.
        </p>

        <div class="mt-10 space-y-5">
            @foreach ([
                ['Qué datos se registran', 'Al entregar los rollos, el repartidor registra el nombre, la cédula y la firma de quien recibe, y de forma opcional su teléfono. También queda la foto de la entrega, la ubicación y la hora del registro.'],
                ['Para qué se usan', 'Únicamente como evidencia de que la entrega se hizo: para que Credibanco y el comercio puedan verificarla y para medir el cumplimiento del tiempo de servicio.'],
                ['Autorización', 'Quien recibe autoriza el tratamiento de sus datos en la app del repartidor antes de firmar. Sin esa autorización la entrega no se registra con sus datos.'],
                ['Quién puede verlos', 'En la consulta pública el nombre y la cédula se muestran de forma parcial. La foto, la firma y el comprobante solo los ven los usuarios autorizados de PMS y de Credibanco, con su usuario y contraseña. Cada consulta y cada cambio quedan registrados.'],
                ['Ubicación', 'La ubicación se toma solo en el momento de registrar la visita. No se rastrea al repartidor durante su jornada.'],
                ['Tus derechos', 'Puedes conocer, actualizar y rectificar tus datos, y solicitar su supresión cuando proceda, escribiendo a PMS por sus canales de atención.'],
            ] as [$titulo, $texto])
                <section class="rounded-2xl border border-ink-200 bg-white p-6 shadow-card">
                    <h2 class="text-lg font-bold text-ink-900">{{ $titulo }}</h2>
                    <p class="mt-2 text-[16px] leading-7 text-ink-600">{{ $texto }}</p>
                </section>
            @endforeach
        </div>

        <p class="mt-8 text-sm leading-6 text-ink-500">
            Este resumen no reemplaza la política de tratamiento de datos personales de Precision Marketing Solutions Ltda., que es el documento oficial y se puede solicitar a PMS.
        </p>
    </main>
    </body>
</html>
