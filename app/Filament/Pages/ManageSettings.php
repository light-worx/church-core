<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSettings extends SettingsPage
{
    protected static string $settings = GeneralSettings::class;

    protected static ?string $title="Settings";

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [
            Tab::make('Core')
                ->icon('heroicon-o-cog-6-tooth')
                ->schema([
                    TextInput::make('church_name')
                        ->label('Church Name')
                        ->required(),
                    TextInput::make('church_email')
                        ->label('Church Email')
                        ->email(),
                    TextInput::make('church_abbreviation')
                        ->label('Abbreviation')
                        ->required(),
                ]),
        ];

        // Dynamically add module tabs
        foreach ($this->getModuleSettingsTabs() as $tab) {
            $tabs[] = $tab;
        }

        return $schema->components([
            Tabs::make('Settings')
                ->tabs($tabs)
                ->columnSpanFull(),
        ]);
    }

    /**
     * Discover and return Tabs from enabled modules.
     *
     * @return Tab[]
     */
    protected function getModuleSettingsTabs(): array
    {
        $tabs = [];

        foreach (app('modules')->allEnabled() as $module) {
            $providerClass = "Modules\\{$module->getName()}\\Providers\\SettingsProvider";

            if (class_exists($providerClass)) {
                $provider = app($providerClass);

                if (method_exists($provider, 'getSettingsTab')) {
                    $tab = $provider->getSettingsTab();

                    if ($tab instanceof Tab) {
                        $tabs[] = $tab;
                    }
                }
            }
        }

        return $tabs;
    }
}
