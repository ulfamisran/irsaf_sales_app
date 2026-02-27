<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingPageSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'hero_title',
        'hero_subtitle',
        'hero_cta_text',
        'hero_cta_url',
        'about_title',
        'about_description',
        'features_title',
        'features_subtitle',
        'instagram_title',
        'instagram_username',
        'contact_title',
        'contact_description',
        'contact_phone',
        'contact_email',
        'contact_whatsapp',
        'contact_address',
        'contact_map_url',
    ];
}
