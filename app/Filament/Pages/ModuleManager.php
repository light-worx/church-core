<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module;
use Filament\Support\Icons\Heroicon;
use ZipArchive;

class ModuleManager extends Page
{
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

    public function install($slug, $downloadUrl)
    {
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

        $this->notify('success', "{$slug} installed successfully!");
        $this->loadModules();
    }
}
