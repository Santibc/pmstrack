@props(['alto' => 'h-14'])

<img
    src="{{ asset('img/logo-pms.png') }}"
    alt="PMS · Precision Marketing Solutions"
    width="788"
    height="373"
    {{ $attributes->class([$alto, 'w-auto select-none']) }}
    draggable="false"
/>
