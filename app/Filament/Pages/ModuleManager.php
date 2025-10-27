<?php

namespace App\Filament\Pages;

use App\Jobs\UpdateCoreJob;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module;
use ZipArchive;
use Illuminate\Support\Str;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;

class ModuleManager extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;
    protected string $view = 'filament.pages.module-manager';
    protected static ?string $title = 'Module Manager';

    public array $modules = [];

    public function mount(): void
    {
        $this->loadModules();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false; 
    }

    protected function getCoreVersion(): string
    {
        // 1️⃣ Try composer.json from your cloned repo
        $path = base_path('modules/connexion/composer.json');
        if (file_exists($path)) {
            $composerData = json_decode(file_get_contents($path), true);
            if (!empty($composerData['version'])) {
                return $composerData['version'];
            }
        }

        // 2️⃣ Try reading a VERSION file (optional if you maintain one)
        $versionFile = base_path('modules/connexion/VERSION');
        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }

        // 3️⃣ Try Git tag (best for repos)
        $gitTag = @exec('cd ' . base_path('modules/connexion') . ' && git describe --tags --abbrev=0 2>/dev/null');
        if (!empty($gitTag)) {
            return $gitTag;
        }

        // 4️⃣ Default fallback
        return 'dev-local';
    }

    protected function loadModules(): void
    {
        $githubUser = 'light-worx';
        $repos = ['connexion-people', 'connexion-property', 'connexion-worship'];

        $available = collect();

        /**
         * 🧩 STEP 1: Add CORE module manually
         */
        $coreResponse = Http::get("https://api.github.com/repos/{$githubUser}/connexion/releases/latest");
        $coreData = $coreResponse->successful() ? $coreResponse->json() : [];

        // Add Core module first
        $available->push([
            'repo'               => 'connexion',
            'slug'               => 'connexion',
            'name'               => 'Core',
            'version'            => $coreData['tag_name'] ?? 'unknown',
            'description'        => $coreData['name'] ?? ($coreData['body'] ?? 'Connexion core application'),
            'download_url'       => $coreData['zipball_url'] ?? null,
            'installed_version'  => $this->getCoreVersion(),
            'installed'          => true,
            'enabled'            => true,
            'status'             => 'installed',
            'is_core'            => true,  // flag to identify core
        ]);

        /**
         * 🧩 STEP 2: Fetch available remote modules
         */
        foreach ($repos as $repo) {
            $response = Http::get("https://api.github.com/repos/{$githubUser}/{$repo}/releases/latest");

            if ($response->successful()) {
                $data = $response->json();
                $available->push([
                    'repo'         => $repo,
                    'slug'         => Str::slug($repo),
                    'name'         => Str::of($repo)->after('connexion-')->headline(),
                    'version'      => $data['tag_name'] ?? 'unknown',
                    'description'  => $data['name'] ?? ($data['body'] ?? 'No description provided'),
                    'download_url' => $data['zipball_url'] ?? null,
                    'is_core' => false,
                ]);
            } else {
                $available->push([
                    'repo'         => $repo,
                    'slug'         => Str::slug($repo),
                    'name'         => Str::of($repo)->after('connexion-')->headline(),
                    'version'      => 'unknown',
                    'description'  => 'No release found',
                    'download_url' => null,
                    'is_core' => false,
                ]);
            }
        }

        /**
         * 🧩 STEP 3: Get locally installed modules
         */
        $installed = collect(Module::all())->map(function ($m) {
            $alias = $m->get('alias') ?? $m->getLowerName();
            $slug = Str::slug($alias);
            $path = $m->getPath() . '/module.json';
            $manifest = File::exists($path)
                ? json_decode(File::get($path), true)
                : [];

            return [
                'slug'        => $slug,
                'alias'       => $alias,
                'name'        => $manifest['name'] ?? ucfirst($m->getName()),
                'version'     => $m->get('version') ?? 'dev',
                'description' => $manifest['description'] ?? '',
                'enabled'     => $m->isEnabled(),
            ];
        })->keyBy('slug');

        /**
         * 🧩 STEP 4: Merge remote & local data
         */
        $this->modules = $available->map(function ($remote) use ($installed) {
            $slug = $remote['slug'];
            $local = $installed[$slug] ?? null;

            $remote['installed'] = $remote['installed'] ?? ($local !== null);
            $remote['installed_version'] = $remote['installed_version'] ?? ($local['version'] ?? null);
            $remote['enabled'] = $remote['enabled'] ?? ($local['enabled'] ?? false);
            $remote['name'] = $local['name'] ?? $remote['name'];
            $remote['description'] = $local['description'] ?? $remote['description'];

            if ($local && $remote['version'] !== 'unknown' && version_compare($remote['version'], $local['version'], '>')) {
                $remote['status'] = 'update';
            } elseif ($local) {
                $remote['status'] = 'installed';
            } else {
                $remote['status'] = 'not_installed';
            }

            return $remote;
        })
        ->sortBy(function ($module) {
            // Ensure "Core" appears first
            return $module['slug'] === 'connexion' ? 0 : 1;
        })
        ->values()
        ->toArray();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => $this->modules)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Module')
                    ->description(fn (array $record): string => $record['description'] ?? '')
                    ->searchable(),
                
                Tables\Columns\ToggleColumn::make('enabled')
                    ->label('Enabled')
                    ->disabled(fn(array $record) => !$record['installed'] || $record['is_core'])
                    ->onColor('success')
                    ->offColor('gray')
                    ->updateStateUsing(function(array $record, bool $state) {
                        // Core module cannot be toggled via web
                        if ($record['is_core']) {
                            Notification::make()
                                ->title('The Core module cannot be disabled')
                                ->warning()
                                ->send();
                            return false;
                        }

                        $this->toggleModule($record['slug'], $state);
                        $this->loadModules();

                        // Refresh navigation
                        $this->js('window.location.reload()');

                        return false; // prevent Filament auto-save
                    }),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'not_installed',
                        'success' => 'installed',
                        'primary' => 'enabled',
                        'warning' => 'update',
                    ])
                    ->formatStateUsing(fn(array $record) => 
                        match(true) {
                            $record['status'] === 'not_installed' => 'Not Installed',
                            $record['status'] === 'update' => 'Update Available',
                            $record['installed'] && $record['enabled'] => 'Enabled',
                            $record['installed'] => 'Installed',
                            default => $record['status'],
                        }
                    ),
                
                Tables\Columns\TextColumn::make('version')
                    ->label('Available Version'),
                
                Tables\Columns\TextColumn::make('installed_version')
                    ->label('Installed Version')
                    ->placeholder('N/A'),
            ])
            ->recordActions([
                Action::make('update')
                    ->label('Update')
                    ->color('warning')
                    ->action(function(array $record) {
                        if ($record['is_core']) {
                            // Dispatch queued job to safely update core
                            UpdateCoreJob::dispatch();
                            Notification::make()
                                ->title('Core update queued')
                                ->success()
                                ->send();
                        } else {
                            $this->install($record['slug'], $record['download_url']);
                        }
                    })
                    ->visible(fn(array $record) => $record['status'] === 'update'),

                Action::make('install_module')
                    ->label('Install')
                    ->color('primary')
                    ->action(fn (array $record) => $this->install($record['slug'], $record['download_url']))
                    ->visible(fn (array $record): bool => $record['status'] === 'not_installed'),
                
                Action::make('update')
                    ->label('Update')
                    ->color('warning')
                    ->action(fn (array $record) => $this->install($record['slug'], $record['download_url']))
                    ->visible(fn (array $record): bool => $record['status'] === 'update'),
                
                Action::make('installed')
                    ->label('Installed')
                    ->color('gray')
                    ->disabled()
                    ->visible(fn (array $record): bool => $record['status'] === 'installed' && !$record['enabled']),
                
                Action::make('github')
                    ->label('')
                    ->icon(fn () => new HtmlString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5 fill-current"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>'))
                    ->url(fn (array $record) => "https://github.com/light-worx/{$record['repo']}")
                    ->openUrlInNewTab(),
            ])
            ->paginated(false);
    }

    public function toggleModule(string $slug, bool $enable): void
    {
        $modules = Module::all();
        $targetModule = null;

        foreach ($modules as $module) {
            $alias = strtolower($module->get('alias') ?? $module->getName());
            $moduleName = strtolower($module->getName());
            $slugWithoutPrefix = str_replace('connexion-', '', $slug);
            
            if ($alias === $slug || $moduleName === $slug || $moduleName === $slugWithoutPrefix) {
                $targetModule = $module;
                break;
            }
        }

        if (!$targetModule) {
            Notification::make()
                ->title("Module {$slug} not found")
                ->danger()
                ->send();
            return;
        }

        if ($enable) {
            $targetModule->enable();
            $title = "{$targetModule->getName()} enabled";
        } else {
            $targetModule->disable();
            $title = "{$targetModule->getName()} disabled";
        }

        Notification::make()
            ->title($title)
            ->success()
            ->send();
    }

    public function install($slug, $downloadUrl)
    {
        $tmpPath = storage_path("app/tmp/{$slug}.zip");
        $modulesDir = base_path("Modules");

        File::ensureDirectoryExists(dirname($tmpPath));
        File::ensureDirectoryExists($modulesDir);

        // Download ZIP
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => ['User-Agent: PHP']
            ]
        ];
        $context = stream_context_create($opts);
        file_put_contents($tmpPath, file_get_contents($downloadUrl, false, $context));

        // Extract to modules dir
        $zip = new ZipArchive;
        if ($zip->open($tmpPath) === TRUE) {
            $zip->extractTo($modulesDir);
            $zip->close();
        }
        unlink($tmpPath);

        // Find the most recent extracted folder (GitHub usually adds a hash)
        $extractedFolders = File::directories($modulesDir);
        $latestFolder = collect($extractedFolders)
            ->sortByDesc(fn($path) => File::lastModified($path))
            ->first();

        // Determine intended folder name from module.json
        $manifestPath = "{$latestFolder}/module.json";
        $targetName = $slug; // default
        if (File::exists($manifestPath)) {
            $manifest = json_decode(File::get($manifestPath), true);
            $targetName = $manifest['name'] ?? ucfirst($slug);
        }

        $targetPath = "{$modulesDir}/{$targetName}";

        // Remove any old copy
        if (File::exists($targetPath)) {
            File::deleteDirectory($targetPath);
        }

        // Move to correct name
        if ($latestFolder && basename($latestFolder) !== $targetName) {
            File::moveDirectory($latestFolder, $targetPath);
        }

        // Refresh autoload
        shell_exec('composer dump-autoload');
        Artisan::call('optimize:clear');

        // Run migrations if present
        if (File::exists("{$targetPath}/database/migrations")) {
            Artisan::call('migrate', [
                '--path' => "Modules/{$targetName}/database/migrations",
                '--force' => true,
            ]);
        }

        // Success notification
        Notification::make()
            ->title("{$targetName} module installed successfully!")
            ->success()
            ->send();

        $this->loadModules();
    }
}