@props(['active' => false])

@php
$classes = $active
    ? 'flex items-center px-5 py-2 text-sm font-medium text-white bg-white/10 border-l-2 border-yellow-400'
    : 'flex items-center px-5 py-2 text-sm font-medium text-gray-300 border-l-2 border-transparent hover:bg-sidebar-hover hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} wire:navigate>
    {{ $slot }}
</a>
