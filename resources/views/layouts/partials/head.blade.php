<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#F07D2E">
<meta name="robots" content="noindex, nofollow">

<title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>

<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="64x64" href="{{ asset('img/favicon-64.png') }}">
<link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">

@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
