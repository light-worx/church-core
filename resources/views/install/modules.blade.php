<x-layout>
    <div class="min-h-screen flex flex-col items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl w-full space-y-8">
            <div class="text-center">
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">Optional Module Installation</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Select the modules you want to install. Latest versions will be downloaded from GitHub.
                </p>
            </div>

            <form method="POST" action="{{ route('install.modules.save') }}" class="mt-8 space-y-6">
                @csrf
                <div class="bg-white shadow rounded-lg p-6 space-y-4">
                    @foreach($allModules as $module)
                        <div class="flex items-center justify-between border-b py-3">
                            <div class="flex items-center space-x-3">
                                <input type="checkbox" name="modules[]" value="{{ $module['slug'] }}" checked
                                    class="h-5 w-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                <div class="flex flex-col">
                                    <span class="font-medium text-gray-900">{{ $module['name'] }}</span>
                                    <span class="text-sm text-gray-500">{{ $module['description'] }}</span>
                                </div>
                            </div>
                            @if(!empty($module['download_url']))
                            <a href="{{ $module['download_url'] }}" target="_blank"
                            class="text-gray-400 hover:text-gray-700">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 .297c-6.63 0-12 5.373-12 12 ..."/> {{-- truncated GitHub SVG path --}}
                                </svg>
                            </a>
                            @endif
                            <input type="hidden" name="download_url_{{ $module['slug'] }}" value="{{ $module['download_url'] }}">
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end mt-4">
                    <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-6 rounded-lg shadow">
                        Install Selected Modules
                    </button>
                </div>
            </form>

            <div class="text-center mt-4 text-gray-500 text-sm">
                Installer will automatically configure your database and enable selected modules.
            </div>
        </div>
    </div>
</x-layout>
