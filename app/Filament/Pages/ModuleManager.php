<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module;
use ZipArchive;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

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

    protected function loadModules(): void
    {
        $githubUser = 'bishopm';
        $repos = ['church'];

        $available = collect();

        foreach ($repos as $repo) {
            $response = Http::get("https://api.github.com/repos/{$githubUser}/{$repo}/releases/latest");

            if ($response->successful()) {
                $data = $response->json();

                $available->push([
                    'name' => ucfirst(str_replace('modules-', '', $repo)),
                    'slug' => str_replace('modules-', '', $repo),
                    'version' => $data['tag_name'] ?? 'unknown',
                    'description' => $data['name'] ?? 'No description',
                    'download_url' => $data['zipball_url'] ?? null,
                ]);
            }
        }

        $installed = collect(Module::all())->map(fn($m) => [
            'slug' => $m->getLowerName(),
            'version' => $m->get('version'),
        ])->keyBy('slug');

        $this->modules = $available->map(function ($remote) use ($installed) {
            $local = $installed[$remote['slug']] ?? null;

            $remote['installed'] = $local !== null;
            $remote['installed_version'] = $local['version'] ?? null;

            if ($local && version_compare($remote['version'], $local['version'], '>')) {
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
                
                Tables\Columns\BadgeColumn::make('status')
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
            // ... rest of your configuration ...
            ->actions([
                // ... actions ...
            ])
            ->paginated(false);
    }

    /**
     * This method is also identical for v3 and v4
     */
    public function install($slug, $downloadUrl)
    {
        // ... (Your existing install logic is perfectly fine) ...
        // (I'm omitting it here for brevity, but keep yours as-is)
        $tmpPath = storage_path("app/tmp/{$slug}.zip");
        $modulePath = base_path("modules/{$slug}");

        File::ensureDirectoryExists(dirname($tmpPath));
        File::ensureDirectoryExists($modulePath);

        file_put_contents($tmpPath, file_get_contents($downloadUrl));

        $zip = new ZipArchive;
        if ($zip->open($tmpPath) === TRUE) {
            $zip->extractTo($modulePath);
            $zip->close();
        }

        unlink($tmpPath);

        Artisan::call('module:discover');

        if (File::exists("{$modulePath}/Database/Migrations")) {
            Artisan::call('migrate', [
                '--path' => "modules/{$slug}/Database/Migrations",
                '--force' => true,
            ]);
        }
        
        // This notification syntax works in v3 and v4
        Notification::make()
            ->title("{$slug} installed successfully!")
            ->success()
            ->send();

        $this->loadModules();
    }
}