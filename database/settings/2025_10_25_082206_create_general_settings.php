<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.church_name', 'Connexion');
        $this->migrator->add('general.church_email', '');
    }
};
