<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $church_name;
    
    public string $church_email;

    public string $church_abbreviation;
    
    public static function group(): string
    {
        return 'general';
    }
}