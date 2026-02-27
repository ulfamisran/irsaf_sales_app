<x-app-layout>
    <x-slot name="title">{{ __('Kelola Landing Page') }}</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">
            {{ __('Kelola Landing Page') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-8">
        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white shadow-sm rounded-2xl border border-slate-200">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-800">Pengaturan Utama</h3>
                <p class="text-sm text-slate-500">Atur judul, deskripsi, dan kontak landing page.</p>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('landing-page.settings.update') }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="hero_title" :value="__('Judul Hero')" />
                            <x-text-input id="hero_title" name="hero_title" type="text" class="mt-1 block w-full" :value="old('hero_title', $settings->hero_title)" required />
                            <x-input-error :messages="$errors->get('hero_title')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="hero_cta_text" :value="__('Teks CTA')" />
                            <x-text-input id="hero_cta_text" name="hero_cta_text" type="text" class="mt-1 block w-full" :value="old('hero_cta_text', $settings->hero_cta_text)" />
                            <x-input-error :messages="$errors->get('hero_cta_text')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="hero_subtitle" :value="__('Subjudul Hero')" />
                            <textarea id="hero_subtitle" name="hero_subtitle" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('hero_subtitle', $settings->hero_subtitle) }}</textarea>
                            <x-input-error :messages="$errors->get('hero_subtitle')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="hero_cta_url" :value="__('Tautan CTA')" />
                            <x-text-input id="hero_cta_url" name="hero_cta_url" type="text" class="mt-1 block w-full" :value="old('hero_cta_url', $settings->hero_cta_url)" />
                            <x-input-error :messages="$errors->get('hero_cta_url')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="about_title" :value="__('Judul Tentang')" />
                            <x-text-input id="about_title" name="about_title" type="text" class="mt-1 block w-full" :value="old('about_title', $settings->about_title)" required />
                            <x-input-error :messages="$errors->get('about_title')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="features_title" :value="__('Judul Layanan')" />
                            <x-text-input id="features_title" name="features_title" type="text" class="mt-1 block w-full" :value="old('features_title', $settings->features_title)" required />
                            <x-input-error :messages="$errors->get('features_title')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="about_description" :value="__('Deskripsi Tentang')" />
                            <textarea id="about_description" name="about_description" rows="4" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>{{ old('about_description', $settings->about_description) }}</textarea>
                            <x-input-error :messages="$errors->get('about_description')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="features_subtitle" :value="__('Subjudul Layanan')" />
                            <textarea id="features_subtitle" name="features_subtitle" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('features_subtitle', $settings->features_subtitle) }}</textarea>
                            <x-input-error :messages="$errors->get('features_subtitle')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="instagram_title" :value="__('Judul Instagram')" />
                            <x-text-input id="instagram_title" name="instagram_title" type="text" class="mt-1 block w-full" :value="old('instagram_title', $settings->instagram_title)" required />
                            <x-input-error :messages="$errors->get('instagram_title')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="instagram_username" :value="__('Username Instagram')" />
                            <x-text-input id="instagram_username" name="instagram_username" type="text" class="mt-1 block w-full" :value="old('instagram_username', $settings->instagram_username)" />
                            <x-input-error :messages="$errors->get('instagram_username')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="contact_title" :value="__('Judul Kontak')" />
                            <x-text-input id="contact_title" name="contact_title" type="text" class="mt-1 block w-full" :value="old('contact_title', $settings->contact_title)" required />
                            <x-input-error :messages="$errors->get('contact_title')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="contact_phone" :value="__('Telepon')" />
                            <x-text-input id="contact_phone" name="contact_phone" type="text" class="mt-1 block w-full" :value="old('contact_phone', $settings->contact_phone)" />
                            <x-input-error :messages="$errors->get('contact_phone')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="contact_email" :value="__('Email')" />
                            <x-text-input id="contact_email" name="contact_email" type="text" class="mt-1 block w-full" :value="old('contact_email', $settings->contact_email)" />
                            <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="contact_whatsapp" :value="__('WhatsApp')" />
                            <x-text-input id="contact_whatsapp" name="contact_whatsapp" type="text" class="mt-1 block w-full" :value="old('contact_whatsapp', $settings->contact_whatsapp)" />
                            <x-input-error :messages="$errors->get('contact_whatsapp')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="contact_address" :value="__('Alamat')" />
                            <textarea id="contact_address" name="contact_address" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('contact_address', $settings->contact_address) }}</textarea>
                            <x-input-error :messages="$errors->get('contact_address')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="contact_description" :value="__('Deskripsi Kontak')" />
                            <textarea id="contact_description" name="contact_description" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('contact_description', $settings->contact_description) }}</textarea>
                            <x-input-error :messages="$errors->get('contact_description')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="contact_map_url" :value="__('Tautan Google Maps')" />
                            <x-text-input id="contact_map_url" name="contact_map_url" type="text" class="mt-1 block w-full" :value="old('contact_map_url', $settings->contact_map_url)" />
                            <x-input-error :messages="$errors->get('contact_map_url')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>{{ __('Simpan Pengaturan') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-2xl border border-slate-200">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-800">Slider Iklan</h3>
                <p class="text-sm text-slate-500">Maksimal 3 iklan utama. Gunakan urutan untuk menentukan posisi.</p>
            </div>
            <div class="p-6 space-y-6">
                <form method="POST" action="{{ route('landing-page.slides.store') }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <x-input-label for="new_slide_title" :value="__('Judul Slide')" />
                        <x-text-input id="new_slide_title" name="title" type="text" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="new_slide_link" :value="__('Tautan Slide')" />
                        <x-text-input id="new_slide_link" name="link_url" type="text" class="mt-1 block w-full" />
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="new_slide_caption" :value="__('Caption Slide')" />
                        <textarea id="new_slide_caption" name="caption" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>
                    <div>
                        <x-input-label for="new_slide_sort" :value="__('Urutan')" />
                        <x-text-input id="new_slide_sort" name="sort_order" type="number" class="mt-1 block w-full" value="0" />
                    </div>
                    <div class="flex items-center gap-2 mt-6">
                        <input id="new_slide_active" name="is_active" type="checkbox" class="rounded border-slate-300" checked>
                        <label for="new_slide_active" class="text-sm text-slate-700">Aktif</label>
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="new_slide_image" :value="__('Gambar Slide')" />
                        <input id="new_slide_image" name="image" type="file" class="mt-1 block w-full text-sm text-slate-600" required>
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <x-primary-button>{{ __('Tambah Slide') }}</x-primary-button>
                    </div>
                </form>

                <div class="space-y-4">
                    @forelse ($slides as $slide)
                        <div class="border border-slate-200 rounded-xl p-4">
                            <form method="POST" action="{{ route('landing-page.slides.update', $slide) }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
                                @csrf
                                @method('PUT')
                                <div>
                                    <x-input-label for="slide_title_{{ $slide->id }}" :value="__('Judul Slide')" />
                                    <x-text-input id="slide_title_{{ $slide->id }}" name="title" type="text" class="mt-1 block w-full" :value="old('title', $slide->title)" />
                                </div>
                                <div>
                                    <x-input-label for="slide_link_{{ $slide->id }}" :value="__('Tautan Slide')" />
                                    <x-text-input id="slide_link_{{ $slide->id }}" name="link_url" type="text" class="mt-1 block w-full" :value="old('link_url', $slide->link_url)" />
                                </div>
                                <div class="md:col-span-2">
                                    <x-input-label for="slide_caption_{{ $slide->id }}" :value="__('Caption Slide')" />
                                    <textarea id="slide_caption_{{ $slide->id }}" name="caption" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('caption', $slide->caption) }}</textarea>
                                </div>
                                <div>
                                    <x-input-label for="slide_sort_{{ $slide->id }}" :value="__('Urutan')" />
                                    <x-text-input id="slide_sort_{{ $slide->id }}" name="sort_order" type="number" class="mt-1 block w-full" :value="old('sort_order', $slide->sort_order)" />
                                </div>
                                <div class="flex items-center gap-2 mt-6">
                                    <input id="slide_active_{{ $slide->id }}" name="is_active" type="checkbox" class="rounded border-slate-300" {{ $slide->is_active ? 'checked' : '' }}>
                                    <label for="slide_active_{{ $slide->id }}" class="text-sm text-slate-700">Aktif</label>
                                </div>
                                <div class="md:col-span-2">
                                    <x-input-label for="slide_image_{{ $slide->id }}" :value="__('Ganti Gambar (opsional)')" />
                                    <input id="slide_image_{{ $slide->id }}" name="image" type="file" class="mt-1 block w-full text-sm text-slate-600">
                                </div>
                                <div class="md:col-span-2 flex flex-wrap items-center justify-between gap-3">
                                    <div class="text-sm text-slate-500">Gambar saat ini: {{ $slide->image_path }}</div>
                                    <div class="flex gap-2">
                                        <x-primary-button>{{ __('Simpan') }}</x-primary-button>
                                    </div>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('landing-page.slides.destroy', $slide) }}" class="mt-3">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-rose-600 hover:text-rose-700">Hapus Slide</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada slide. Tambahkan di atas.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-2xl border border-slate-200">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-800">Fitur Unggulan</h3>
                <p class="text-sm text-slate-500">Tampilkan layanan penting untuk pelanggan.</p>
            </div>
            <div class="p-6 space-y-6">
                <form method="POST" action="{{ route('landing-page.features.store') }}" class="grid gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <x-input-label for="new_feature_title" :value="__('Judul Fitur')" />
                        <x-text-input id="new_feature_title" name="title" type="text" class="mt-1 block w-full" required />
                    </div>
                    <div>
                        <x-input-label for="new_feature_icon" :value="__('Icon')" />
                        <select id="new_feature_icon" name="icon" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="shield">Shield</option>
                            <option value="wrench">Wrench</option>
                            <option value="truck">Truck</option>
                            <option value="star">Star</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="new_feature_description" :value="__('Deskripsi')" />
                        <textarea id="new_feature_description" name="description" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>
                    <div>
                        <x-input-label for="new_feature_sort" :value="__('Urutan')" />
                        <x-text-input id="new_feature_sort" name="sort_order" type="number" class="mt-1 block w-full" value="0" />
                    </div>
                    <div class="flex items-center gap-2 mt-6">
                        <input id="new_feature_active" name="is_active" type="checkbox" class="rounded border-slate-300" checked>
                        <label for="new_feature_active" class="text-sm text-slate-700">Aktif</label>
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <x-primary-button>{{ __('Tambah Fitur') }}</x-primary-button>
                    </div>
                </form>

                <div class="space-y-4">
                    @forelse ($features as $feature)
                        <div class="border border-slate-200 rounded-xl p-4">
                            <form method="POST" action="{{ route('landing-page.features.update', $feature) }}" class="grid gap-4 md:grid-cols-2">
                                @csrf
                                @method('PUT')
                                <div>
                                    <x-input-label for="feature_title_{{ $feature->id }}" :value="__('Judul Fitur')" />
                                    <x-text-input id="feature_title_{{ $feature->id }}" name="title" type="text" class="mt-1 block w-full" :value="old('title', $feature->title)" required />
                                </div>
                                <div>
                                    <x-input-label for="feature_icon_{{ $feature->id }}" :value="__('Icon')" />
                                    <select id="feature_icon_{{ $feature->id }}" name="icon" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach (['shield', 'wrench', 'truck', 'star'] as $icon)
                                            <option value="{{ $icon }}" {{ $feature->icon === $icon ? 'selected' : '' }}>{{ ucfirst($icon) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <x-input-label for="feature_description_{{ $feature->id }}" :value="__('Deskripsi')" />
                                    <textarea id="feature_description_{{ $feature->id }}" name="description" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $feature->description) }}</textarea>
                                </div>
                                <div>
                                    <x-input-label for="feature_sort_{{ $feature->id }}" :value="__('Urutan')" />
                                    <x-text-input id="feature_sort_{{ $feature->id }}" name="sort_order" type="number" class="mt-1 block w-full" :value="old('sort_order', $feature->sort_order)" />
                                </div>
                                <div class="flex items-center gap-2 mt-6">
                                    <input id="feature_active_{{ $feature->id }}" name="is_active" type="checkbox" class="rounded border-slate-300" {{ $feature->is_active ? 'checked' : '' }}>
                                    <label for="feature_active_{{ $feature->id }}" class="text-sm text-slate-700">Aktif</label>
                                </div>
                                <div class="md:col-span-2 flex justify-end">
                                    <x-primary-button>{{ __('Simpan') }}</x-primary-button>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('landing-page.features.destroy', $feature) }}" class="mt-3">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-rose-600 hover:text-rose-700">Hapus Fitur</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada fitur. Tambahkan di atas.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-2xl border border-slate-200">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-800">Postingan Instagram</h3>
                <p class="text-sm text-slate-500">Tambahkan tautan postingan (maksimal 12 ditampilkan).</p>
            </div>
            <div class="p-6 space-y-6">
                <form method="POST" action="{{ route('landing-page.instagram.store') }}" class="grid gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <x-input-label for="new_instagram_post_url" :value="__('URL Postingan')" />
                        <x-text-input id="new_instagram_post_url" name="post_url" type="text" class="mt-1 block w-full" required />
                    </div>
                    <div>
                        <x-input-label for="new_instagram_image_url" :value="__('URL Gambar')" />
                        <x-text-input id="new_instagram_image_url" name="image_url" type="text" class="mt-1 block w-full" required />
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="new_instagram_caption" :value="__('Caption')" />
                        <textarea id="new_instagram_caption" name="caption" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>
                    <div>
                        <x-input-label for="new_instagram_sort" :value="__('Urutan')" />
                        <x-text-input id="new_instagram_sort" name="sort_order" type="number" class="mt-1 block w-full" value="0" />
                    </div>
                    <div class="flex items-center gap-2 mt-6">
                        <input id="new_instagram_active" name="is_active" type="checkbox" class="rounded border-slate-300" checked>
                        <label for="new_instagram_active" class="text-sm text-slate-700">Aktif</label>
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <x-primary-button>{{ __('Tambah Postingan') }}</x-primary-button>
                    </div>
                </form>

                <div class="space-y-4">
                    @forelse ($instagramPosts as $post)
                        <div class="border border-slate-200 rounded-xl p-4">
                            <form method="POST" action="{{ route('landing-page.instagram.update', $post) }}" class="grid gap-4 md:grid-cols-2">
                                @csrf
                                @method('PUT')
                                <div>
                                    <x-input-label for="instagram_post_url_{{ $post->id }}" :value="__('URL Postingan')" />
                                    <x-text-input id="instagram_post_url_{{ $post->id }}" name="post_url" type="text" class="mt-1 block w-full" :value="old('post_url', $post->post_url)" required />
                                </div>
                                <div>
                                    <x-input-label for="instagram_image_url_{{ $post->id }}" :value="__('URL Gambar')" />
                                    <x-text-input id="instagram_image_url_{{ $post->id }}" name="image_url" type="text" class="mt-1 block w-full" :value="old('image_url', $post->image_url)" required />
                                </div>
                                <div class="md:col-span-2">
                                    <x-input-label for="instagram_caption_{{ $post->id }}" :value="__('Caption')" />
                                    <textarea id="instagram_caption_{{ $post->id }}" name="caption" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('caption', $post->caption) }}</textarea>
                                </div>
                                <div>
                                    <x-input-label for="instagram_sort_{{ $post->id }}" :value="__('Urutan')" />
                                    <x-text-input id="instagram_sort_{{ $post->id }}" name="sort_order" type="number" class="mt-1 block w-full" :value="old('sort_order', $post->sort_order)" />
                                </div>
                                <div class="flex items-center gap-2 mt-6">
                                    <input id="instagram_active_{{ $post->id }}" name="is_active" type="checkbox" class="rounded border-slate-300" {{ $post->is_active ? 'checked' : '' }}>
                                    <label for="instagram_active_{{ $post->id }}" class="text-sm text-slate-700">Aktif</label>
                                </div>
                                <div class="md:col-span-2 flex justify-end">
                                    <x-primary-button>{{ __('Simpan') }}</x-primary-button>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('landing-page.instagram.destroy', $post) }}" class="mt-3">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-rose-600 hover:text-rose-700">Hapus Postingan</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada postingan. Tambahkan di atas.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
