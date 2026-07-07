@props(['type', 'data', 'options' => []])

<div
    x-data="chartWidget({ type: @js($type), data: @js($data), options: @js($options) })"
    x-init="init()"
    x-on:destroy="destroy()"
    wire:ignore
    {{ $attributes }}
>
    <canvas x-ref="canvas"></canvas>
</div>
