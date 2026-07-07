@props(['value' => '', 'language' => 'shell', 'readOnly' => false, 'height' => '420px'])

<div
    x-data="codeEditor({ value: @js($value), language: @js($language), readOnly: @js($readOnly) })"
    x-init="init()"
    x-on:destroy="destroy()"
    wire:ignore
    {{ $attributes->merge(['class' => 'border border-gray-300 rounded-md overflow-hidden']) }}
    style="height: {{ $height }}"
></div>
