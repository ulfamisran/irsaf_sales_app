<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\LandingFeature;
use App\Models\LandingInstagramPost;
use App\Models\LandingPageSetting;
use App\Models\LandingSlide;
use App\Models\Product;

class LandingPageController extends Controller
{
    public function index()
    {
        $settings = LandingPageSetting::query()->firstOrCreate([], [
            'hero_title' => 'Irsaf Komputer',
            'hero_subtitle' => 'Pusat layanan komputer, laptop, dan aksesoris dengan pelayanan profesional.',
            'hero_cta_text' => 'Lihat Cabang',
            'hero_cta_url' => '#cabang',
            'about_title' => 'Tentang Irsaf Komputer',
            'about_description' => 'Irsaf Komputer menghadirkan produk dan layanan terbaik untuk kebutuhan teknologi Anda. Kami fokus pada kualitas, kecepatan, dan kepercayaan pelanggan.',
            'features_title' => 'Layanan Unggulan',
            'features_subtitle' => 'Solusi lengkap untuk pembelian, servis, dan konsultasi.',
            'instagram_title' => 'Instagram Terbaru',
            'instagram_username' => 'irsaf_computer',
            'contact_title' => 'Hubungi Kami',
            'contact_description' => 'Tim kami siap membantu kebutuhan perangkat Anda.',
        ]);

        $slides = LandingSlide::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $features = LandingFeature::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $instagramPosts = LandingInstagramPost::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->limit(12)
            ->get();

        $branches = Branch::query()
            ->orderBy('name')
            ->get();

        $products = Product::query()
            ->withCount(['units as ready_units_count' => function ($query) {
                $query->where('status', 'in_stock');
            }])
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return view('landing.index', compact('settings', 'slides', 'features', 'instagramPosts', 'branches', 'products'));
    }
}
