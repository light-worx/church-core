@php
    $enabled = $getRecord() ? $this->isModuleEnabled($getRecord()['slug']) : false;
@endphp

<div 
    class="flex items-center justify-center cursor-pointer"
    wire:click.prevent="toggleModule('{{ $getRecord()['slug'] }}', {{ $enabled ? 'false' : 'true' }})"
>
    @if ($enabled)
        <span class="px-2 py-1 rounded bg-green-100 text-green-700">✅ On</span>
    @else
        <span class="px-2 py-1 rounded bg-gray-200 text-gray-600">❌ Off</span>
    @endif
</div>
