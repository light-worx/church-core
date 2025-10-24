<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module;
use ZipArchive;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;

class InstallController extends Controller
{
    public function welcome() {
        return view('install.welcome');
    }

    public function environment() {
        $requirements = [
            'php_version' => version_compare(PHP_VERSION, '8.1', '>='),
            'extensions' => [
                'pdo_mysql' => extension_loaded('pdo_mysql'),
                'mbstring' => extension_loaded('mbstring'),
                'openssl' => extension_loaded('openssl'),
            ],
            'permissions' => [
                'storage' => is_writable(storage_path()),
                'bootstrap_cache' => is_writable(base_path('bootstrap/cache')),
                'modules' => is_writable(base_path('Modules')),
            ],
        ];

        return view('install.environment', compact('requirements'));
    }

    public function saveEnvironment(Request $request) {
        $envPath = base_path('.env');

        $content = "
APP_NAME='Connexion'
APP_ENV=production
APP_KEY=
DB_CONNECTION=mysql
DB_HOST={$request->DB_HOST}
DB_PORT={$request->DB_PORT}
DB_DATABASE={$request->DB_DATABASE}
DB_USERNAME={$request->DB_USERNAME}
DB_PASSWORD={$request->DB_PASSWORD}
";

        File::put($envPath, $content);

        Artisan::call('key:generate');
        Artisan::call('migrate', ['--force' => true]);

        return redirect()->route('install.modules');
    }

    public function modules() {
        $allModules = collect(Module::all())->map(fn($m) => [
            'slug' => $m->getLowerName(),
            'name' => $m->get('name') ?? ucfirst($m->getName()),
            'description' => $m->get('description') ?? '',
        ]);

        return view('install.modules', compact('allModules'));
    }

    public function installModuleFromGithub($slug, $downloadUrl)
    {
        $tmpPath = storage_path("app/tmp/{$slug}.zip");
        $modulePath = base_path("Modules/{$slug}");

        // Ensure directories exist
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($tmpPath));

        // Download zip from GitHub
        file_put_contents($tmpPath, file_get_contents($downloadUrl));

        $zip = new ZipArchive;
        if ($zip->open($tmpPath) === TRUE) {
            $zip->extractTo(base_path('Modules'));
            $zip->close();
        }
        unlink($tmpPath);

        // Discover and migrate module
        Artisan::call('module:discover');
        if (\Illuminate\Support\Facades\File::exists("{$modulePath}/Database/Migrations")) {
            Artisan::call('migrate', [
                '--path' => "Modules/{$slug}/Database/Migrations",
                '--force' => true,
            ]);
        }

        Notification::make()
            ->title("Module {$slug} installed successfully!")
            ->success()
            ->send();
    }

    public function installModules(Request $request)
    {
        $selected = $request->input('modules', []);

        foreach ($selected as $slug) {
            $downloadUrl = $request->input("download_url_{$slug}");
            $this->installModuleFromGithub($slug, $downloadUrl);
        }

        // Lock installer
        \Illuminate\Support\Facades\File::put(storage_path('installed.lock'), now());

        return redirect('/login')->with('success', 'Application installed successfully!');
    }

}
