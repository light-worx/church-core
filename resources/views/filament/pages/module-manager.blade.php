<x-filament::page>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($modules as $module)
            <x-filament::card>
                <div class="flex justify-between items-center mb-2">
                    <h2 class="text-lg font-semibold">{{ $module['name'] }}</h2>
                    @if ($module['status'] === 'installed')
                        <x-filament::badge color="success">Installed</x-filament::badge>
                    @elseif ($module['status'] === 'update')
                        <x-filament::badge color="warning">Update</x-filament::badge>
                    @else
                        <x-filament::badge color="gray">Not installed</x-filament::badge>
                    @endif
                </div>

                <p class="text-sm text-gray-600 mb-3">{{ $module['description'] }}</p>

                <div class="text-sm text-gray-500 mb-4">
                    <strong>Version:</strong> {{ $module['version'] }}
                    @if ($module['installed_version'])
                        (Installed: {{ $module['installed_version'] }})
                    @endif
                </div>

                <div class="flex justify-end space-x-2">
                    @if ($module['status'] === 'not_installed')
                        <x-filament::button color="primary" wire:click="install('{{ $module['slug'] }}', '{{ $module['download_url'] }}')">
                            Install
                        </x-filament::button>
                    @elseif ($module['status'] === 'update')
                        <x-filament::button color="warning" wire:click="install('{{ $module['slug'] }}', '{{ $module['download_url'] }}')">
                            Update
                        </x-filament::button>
                    @else
                        <x-filament::button color="gray" disabled>
                            Installed
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::card>
        @endforeach
    </div>
</x-filament::page>
