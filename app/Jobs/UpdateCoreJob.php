<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class UpdateCoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        try {
            Log::info('Running core update...');
            // Safely update only your core package
            shell_exec('composer update light-worx/connexion --with-dependencies -q 2>&1');
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('optimize:clear');
            Log::info('Core update completed.');
        } catch (\Throwable $e) {
            Log::error('Core update failed: ' . $e->getMessage());
            Notification::make()
                ->title('Core update failed')
                ->danger()
                ->send();
            return;
        }

        Notification::make()
            ->title('Core updated successfully!')
            ->success()
            ->send();
    }
}
