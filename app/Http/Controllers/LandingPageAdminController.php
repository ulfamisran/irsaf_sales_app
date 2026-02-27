<?php

namespace App\Http\Controllers;

use App\Models\LandingFeature;
use App\Models\LandingInstagramPost;
use App\Models\LandingPageSetting;
use App\Models\LandingSlide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LandingPageAdminController extends Controller
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

        $slides = LandingSlide::query()->orderBy('sort_order')->get();
        $features = LandingFeature::query()->orderBy('sort_order')->get();
        $instagramPosts = LandingInstagramPost::query()->orderBy('sort_order')->get();

        return view('landing.manage', compact('settings', 'slides', 'features', 'instagramPosts'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'hero_title' => ['required', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string'],
            'hero_cta_text' => ['nullable', 'string', 'max:255'],
            'hero_cta_url' => ['nullable', 'string', 'max:255'],
            'about_title' => ['required', 'string', 'max:255'],
            'about_description' => ['required', 'string'],
            'features_title' => ['required', 'string', 'max:255'],
            'features_subtitle' => ['nullable', 'string'],
            'instagram_title' => ['required', 'string', 'max:255'],
            'instagram_username' => ['nullable', 'string', 'max:255'],
            'contact_title' => ['required', 'string', 'max:255'],
            'contact_description' => ['nullable', 'string'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'max:255'],
            'contact_whatsapp' => ['nullable', 'string', 'max:255'],
            'contact_address' => ['nullable', 'string'],
            'contact_map_url' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = LandingPageSetting::query()->first();
        if (! $settings) {
            $settings = LandingPageSetting::query()->create($data);
        } else {
            $settings->update($data);
        }

        return back()->with('success', 'Pengaturan landing page berhasil diperbarui.');
    }

    public function storeSlide(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => ['required', 'image', 'max:2048'],
            'is_active' => ['nullable'],
        ]);

        $data['image_path'] = $request->file('image')->store('landing/slides', 'public');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        LandingSlide::query()->create($data);

        return back()->with('success', 'Slide berhasil ditambahkan.');
    }

    public function updateSlide(Request $request, LandingSlide $slide)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['nullable'],
        ]);

        if ($request->hasFile('image')) {
            if ($slide->image_path && Storage::disk('public')->exists($slide->image_path)) {
                Storage::disk('public')->delete($slide->image_path);
            }
            $data['image_path'] = $request->file('image')->store('landing/slides', 'public');
        }

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        $slide->update($data);

        return back()->with('success', 'Slide berhasil diperbarui.');
    }

    public function destroySlide(LandingSlide $slide)
    {
        if ($slide->image_path && Storage::disk('public')->exists($slide->image_path)) {
            Storage::disk('public')->delete($slide->image_path);
        }

        $slide->delete();

        return back()->with('success', 'Slide berhasil dihapus.');
    }

    public function storeFeature(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        LandingFeature::query()->create($data);

        return back()->with('success', 'Fitur berhasil ditambahkan.');
    }

    public function updateFeature(Request $request, LandingFeature $feature)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        $feature->update($data);

        return back()->with('success', 'Fitur berhasil diperbarui.');
    }

    public function destroyFeature(LandingFeature $feature)
    {
        $feature->delete();

        return back()->with('success', 'Fitur berhasil dihapus.');
    }

    public function storeInstagramPost(Request $request)
    {
        $data = $request->validate([
            'post_url' => ['required', 'url', 'max:255'],
            'image_url' => ['required', 'url', 'max:255'],
            'caption' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        LandingInstagramPost::query()->create($data);

        return back()->with('success', 'Postingan Instagram berhasil ditambahkan.');
    }

    public function updateInstagramPost(Request $request, LandingInstagramPost $post)
    {
        $data = $request->validate([
            'post_url' => ['required', 'url', 'max:255'],
            'image_url' => ['required', 'url', 'max:255'],
            'caption' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        $post->update($data);

        return back()->with('success', 'Postingan Instagram berhasil diperbarui.');
    }

    public function destroyInstagramPost(LandingInstagramPost $post)
    {
        $post->delete();

        return back()->with('success', 'Postingan Instagram berhasil dihapus.');
    }
}
