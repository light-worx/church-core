<?php

namespace App\Filament\Pages;

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
    protected static ?string $title = 'Connexion Module Manager';

    public array $modules = [];

    public function mount(): void
    {
        $this->loadModules();
    }

    protected function loadModules(): void
    {
        $githubUser = 'light-worx';
        $repos = ['church-people', 'church-worship'];

        $available = collect();

        foreach ($repos as $repo) {
            $response = Http::get("https://api.github.com/repos/{$githubUser}/{$repo}/releases/latest");

            if ($response->successful()) {
                $data = $response->json();

                $available->push([
                    'repo'         => $repo,
                    'slug'         => Str::slug($repo),
                    'name'         => Str::of($repo)->after('church-')->headline(),
                    'version'      => $data['tag_name'] ?? 'unknown',
                    'description'  => $data['name'] ?? ($data['body'] ?? 'No description provided'),
                    'download_url' => $data['zipball_url'] ?? null,
                ]);
            } else {
                // Fallback if repo has no releases yet
                $available->push([
                    'repo'         => $repo,
                    'slug'         => Str::slug($repo),
                    'name'         => Str::of($repo)->after('church-')->headline(),
                    'version'      => 'unknown',
                    'description'  => 'No release found',
                    'download_url' => null,
                ]);
            }
        }

        // --- Get locally installed modules ---
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
            ];
        })->keyBy('slug');

        // --- Merge available and installed data ---
        $this->modules = $available->map(function ($remote) use ($installed) {
            $slug = $remote['slug'];
            $local = $installed[$slug] ?? null;

            $remote['installed'] = $local !== null;
            $remote['installed_version'] = $local['version'] ?? null;
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
        })->toArray();
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
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'not_installed',
                        'success' => 'installed',
                        'warning' => 'update',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'not_installed' => 'Not Installed',
                        'installed' => 'Installed',
                        'update' => 'Update Available',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('version')
                    ->label('Available Version'),
                
                Tables\Columns\TextColumn::make('installed_version')
                    ->label('Installed Version')
                    ->placeholder('N/A'),
            ])
            ->recordActions([
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
                    ->visible(fn (array $record): bool => $record['status'] === 'installed'),
                Action::make('github')
                    ->label('')
                    ->icon(function(){
                        return new HtmlString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5 fill-current">
                            <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
                        </svg>');
                    })
                    ->url(fn (array $record) => "https://github.com/light-worx/{$record['slug']}/releases/latest")
                    ->openUrlInNewTab(),
            ])
            ->paginated(false);
    }

    public function install($slug, $downloadUrl)
    {
        $tmpPath = storage_path("app/tmp/{$slug}.zip");
        $modulePath = base_path("modules/{$slug}");

        File::ensureDirectoryExists(dirname($tmpPath));

        file_put_contents($tmpPath, file_get_contents($downloadUrl));

        $zip = new ZipArchive;
        if ($zip->open($tmpPath) === TRUE) {
            $zip->extractTo(base_path('modules'));
            $zip->close();
        }

        unlink($tmpPath);

        Artisan::call('module:discover');

        // Run migrations (optional)
        if (File::exists("{$modulePath}/Database/Migrations")) {
            Artisan::call('migrate', [
                '--path' => "modules/{$slug}/Database/Migrations",
                '--force' => true,
            ]);
        }

        Notification::make()
            ->title(File::exists($modulePath) ? "{$slug} updated successfully!" : "{$slug} installed successfully!")
            ->success()
            ->send();

        $this->loadModules();
    }

}