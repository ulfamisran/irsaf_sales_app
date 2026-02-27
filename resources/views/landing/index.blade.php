<!doctype html>
<html lang="id" class="h-full">
 <head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Irsaf Komputer</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&amp;family=Outfit:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { height: 100%; }

    .font-display { font-family: 'Space Grotesk', sans-serif; }
    .font-body { font-family: 'Outfit', sans-serif; }

    @keyframes float {
      0%, 100% { transform: translateY(0px) rotate(-5deg); }
      50% { transform: translateY(-20px) rotate(-5deg); }
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes pulse-glow {
      0%, 100% { box-shadow: 0 0 20px rgba(99, 102, 241, 0.4); }
      50% { box-shadow: 0 0 40px rgba(99, 102, 241, 0.8); }
    }

    @keyframes gradient-shift {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

    .animate-float { animation: float 4s ease-in-out infinite; }
    .animate-fade-in { animation: fadeInUp 0.8s ease-out forwards; }
    .animate-pulse-glow { animation: pulse-glow 2s ease-in-out infinite; }

    .gradient-bg {
      background: linear-gradient(135deg, #ffffff 0%, #f0f8ff 50%, #e8f4f8 100%);
      background-size: 400% 400%;
      animation: gradient-shift 15s ease infinite;
    }

    .glass-card {
      background: rgba(255, 255, 255, 0.7);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(59, 130, 246, 0.2);
    }

    .content-stack > * + * {
      margin-top: 0.5rem;
    }

    .text-gradient {
      background: linear-gradient(135deg, #1e40af, #3b82f6, #ffd700);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .btn-primary {
      background: linear-gradient(135deg, #ffd700, #ffed4e);
      color: #1e40af;
      transition: all 0.3s ease;
    }

    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 40px rgba(255, 215, 0, 0.4);
    }

    .card-hover {
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .card-hover:hover {
      transform: translateY(-10px);
      border-color: rgba(99, 102, 241, 0.5);
    }

    .laptop-card {
      transition: all 0.3s ease;
    }

    .laptop-card:hover {
      transform: scale(1.02);
    }

    .nav-link {
      position: relative;
    }

    .nav-link::after {
      content: '';
      position: absolute;
      bottom: -4px;
      left: 0;
      width: 0;
      height: 2px;
      background: linear-gradient(90deg, #6366f1, #8b5cf6);
      transition: width 0.3s ease;
    }

    .nav-link:hover::after {
      width: 100%;
    }

    .stagger-1 { animation-delay: 0.1s; }
    .stagger-2 { animation-delay: 0.2s; }
    .stagger-3 { animation-delay: 0.3s; }
    .stagger-4 { animation-delay: 0.4s; }

    section {
      scroll-margin-top: 6rem;
    }

  </style>
 </head>
 <body class="h-full gradient-bg text-blue-900 font-body overflow-auto">
  <div class="w-full min-h-full pt-24">
   <nav class="fixed top-0 left-0 right-0 z-50 glass-card">
    <div class="max-w-7xl mx-auto px-6 py-4">
     <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
       <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-yellow-400 to-yellow-500 flex items-center justify-center">
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
       </div>
       <span id="brand-name" class="font-display font-bold text-xl">Irsaf Komputer</span>
      </div>
      <div class="hidden md:flex items-center gap-8">
       <a href="#home" class="nav-link text-blue-700 hover:text-blue-900 transition-colors">Beranda</a>
       <a href="#products" class="nav-link text-blue-700 hover:text-blue-900 transition-colors">Produk</a>
       <a href="#features" class="nav-link text-blue-700 hover:text-blue-900 transition-colors">Fitur</a>
       <a href="#contact" class="nav-link text-blue-700 hover:text-blue-900 transition-colors">Kontak</a>
      </div>
      <div class="flex items-center gap-3">
        @guest
            <a href="{{ route('login') }}" class="btn-primary px-6 py-2 rounded-full font-medium text-sm">Masuk</a>
        @endguest
      </div>
     </div>
    </div>
   </nav>
   <section id="home" class="pt-32 pb-20 px-6">
    <div class="max-w-7xl mx-auto">
     <div class="grid lg:grid-cols-2 gap-12 items-center">
      <div class="space-y-8">
       <div class="inline-flex items-center gap-2 glass-card px-4 py-2 rounded-full animate-fade-in">
        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
        <span class="text-sm text-blue-700">Promo Spesial Akhir Tahun</span>
       </div>
       <h1 id="hero-title" class="font-display text-5xl lg:text-7xl font-bold leading-tight animate-fade-in stagger-1">
        {!! $settings->hero_title ?: 'Temukan <span class="text-gradient">Laptop Impian</span> Anda' !!}
       </h1>
       <p id="hero-subtitle" class="text-xl text-blue-600 leading-relaxed max-w-lg animate-fade-in stagger-2">
        {{ $settings->hero_subtitle ?: 'Koleksi laptop terlengkap dengan harga terbaik. Dari gaming hingga bisnis, semua ada di sini dengan garansi resmi dan layanan premium.' }}
       </p>
       <div class="flex flex-wrap gap-4 animate-fade-in stagger-3">
        <a id="cta-button" href="{{ $settings->hero_cta_url ?: '#products' }}" class="btn-primary animate-pulse-glow px-8 py-4 rounded-full font-semibold text-lg inline-flex items-center gap-2">
         {{ $settings->hero_cta_text ?: 'Lihat Koleksi' }}
         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
        </a>
        <a href="#contact" class="glass-card px-8 py-4 rounded-full font-semibold text-lg hover:bg-white/10 transition-colors inline-flex items-center gap-2">Konsultasi Gratis</a>
       </div>
       <div class="flex items-center gap-8 pt-4 animate-fade-in stagger-4">
        <div class="text-center">
         <div class="font-display text-3xl font-bold text-gradient">500+</div>
         <div class="text-sm text-blue-600">Produk</div>
        </div>
        <div class="w-px h-12 bg-blue-200"></div>
        <div class="text-center">
         <div class="font-display text-3xl font-bold text-gradient">10K+</div>
         <div class="text-sm text-blue-600">Pelanggan</div>
        </div>
        <div class="w-px h-12 bg-blue-200"></div>
        <div class="text-center">
         <div class="font-display text-3xl font-bold text-gradient">99%</div>
         <div class="text-sm text-blue-600">Kepuasan</div>
        </div>
       </div>
      </div>
      <div class="relative">
       <div class="absolute inset-0 bg-gradient-to-r from-indigo-500/20 to-purple-500/20 blur-3xl rounded-full"></div>
       <div class="relative animate-float">
        <svg viewbox="0 0 400 300" class="w-full max-w-lg mx-auto drop-shadow-2xl">
         <defs>
          <lineargradient id="screenGrad" x1="0%" y1="0%" x2="100%" y2="100%">
           <stop offset="0%" style="stop-color:#6366f1" />
           <stop offset="100%" style="stop-color:#8b5cf6" />
          </lineargradient>
          <lineargradient id="bodyGrad" x1="0%" y1="0%" x2="0%" y2="100%">
           <stop offset="0%" style="stop-color:#374151" />
           <stop offset="100%" style="stop-color:#1f2937" />
          </lineargradient>
         </defs>
         <rect x="50" y="30" width="300" height="180" rx="10" fill="url(#bodyGrad)" stroke="#4b5563" stroke-width="2" />
         <rect x="60" y="40" width="280" height="160" rx="5" fill="url(#screenGrad)" />
         <rect x="75" y="60" width="100" height="8" rx="4" fill="rgba(255,255,255,0.3)" />
         <rect x="75" y="80" width="150" height="6" rx="3" fill="rgba(255,255,255,0.2)" />
         <rect x="75" y="95" width="120" height="6" rx="3" fill="rgba(255,255,255,0.2)" />
         <rect x="75" y="120" width="80" height="30" rx="5" fill="rgba(255,255,255,0.25)" />
         <circle cx="280" cy="140" r="40" fill="rgba(255,255,255,0.1)" />
         <path d="M265 140 L290 125 L290 155 Z" fill="rgba(255,255,255,0.3)" />
         <path d="M30 210 L50 210 L50 215 Q200 240 350 215 L350 210 L370 210 L380 230 Q200 260 20 230 Z" fill="url(#bodyGrad)" stroke="#4b5563" stroke-width="2" />
         <rect x="170" y="218" width="60" height="35" rx="3" fill="#1f2937" stroke="#4b5563" />
        </svg>
       </div>
       <div class="absolute top-10 right-0 glass-card p-3 rounded-xl animate-fade-in stagger-2">
        <div class="flex items-center gap-2">
         <div class="w-8 h-8 bg-green-500/20 rounded-lg flex items-center justify-center">
          <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
         </div>
         <span class="text-sm font-medium">Garansi Resmi</span>
        </div>
       </div>
       <div class="absolute bottom-20 left-0 glass-card p-3 rounded-xl animate-fade-in stagger-3">
        <div class="flex items-center gap-2">
         <div class="w-8 h-8 bg-yellow-500/20 rounded-lg flex items-center justify-center">
          <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>
         </div>
         <span class="text-sm font-medium">Rating 4.9/5</span>
        </div>
       </div>
      </div>
     </div>
    </div>
   </section>
   <section id="products" class="py-20 px-6">
    <div class="max-w-7xl mx-auto">
     <div class="text-center mb-16">
      <span class="inline-block glass-card px-4 py-2 rounded-full text-sm text-blue-700 mb-4">Produk Terlaris</span>
      <h2 class="font-display text-4xl lg:text-5xl font-bold mb-4">Laptop <span class="text-gradient">Pilihan Terbaik</span></h2>
      <p class="text-blue-600 max-w-2xl mx-auto">Pilihan laptop berkualitas tinggi untuk semua kebutuhan Anda</p>
     </div>
     <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
      @forelse ($products as $product)
       @php
        $productName = trim($product->brand . ' ' . ($product->series ?? ''));
        $productPrice = number_format((float) $product->selling_price, 0, ',', '.');
       @endphp
       <div class="glass-card rounded-2xl p-6 card-hover laptop-card">
        <div class="relative mb-6">
         <div class="absolute top-2 left-2 bg-emerald-500 text-white text-xs font-bold px-3 py-1 rounded-full">
            Ready {{ (int) $product->ready_units_count }} unit
         </div>
         <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-xl p-8 flex items-center justify-center">
          <svg viewbox="0 0 120 80" class="w-32 h-20">
           <rect x="10" y="5" width="100" height="60" rx="4" fill="#374151" stroke="#4b5563" />
           <rect x="15" y="10" width="90" height="50" rx="2" fill="#1f2937" />
           <rect x="20" y="15" width="40" height="4" rx="2" fill="#6366f1" />
           <rect x="20" y="23" width="60" height="3" rx="1" fill="#4b5563" />
           <rect x="20" y="30" width="50" height="3" rx="1" fill="#4b5563" />
           <path d="M5 65 L10 65 L10 67 Q60 75 110 67 L110 65 L115 65 L118 72 Q60 82 2 72 Z" fill="#374151" />
          </svg>
         </div>
        </div>
        <div class="space-y-3">
        <h3 class="font-display text-xl font-bold">{{ $productName ?: 'Produk Laptop' }}</h3>
         @if ($product->specs)
          <p class="text-gray-600 text-sm mt-2">{{ $product->specs }}</p>
         @endif
         <div class="flex items-center justify-between pt-2">
          <div>
           <span class="text-2xl font-bold text-blue-700">Rp {{ $productPrice }}</span>
          </div>
          <div class="text-xs text-gray-600">
            Ready {{ (int) $product->ready_units_count }} unit
          </div>
         </div>
        </div>
       </div>
      @empty
       <div class="col-span-full text-center text-blue-600">
        Belum ada produk laptop. Tambahkan melalui menu Produk.
       </div>
      @endforelse
     </div>
     <div class="text-center mt-12">
      <button class="glass-card px-8 py-4 rounded-full font-semibold text-blue-700 hover:bg-blue-50 transition-colors inline-flex items-center gap-2">
        Lihat Semua Produk
       <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
       </svg>
      </button>
     </div>
    </div>
   </section>
   <section id="features" class="py-20 px-6">
    <div class="max-w-7xl mx-auto">
     <div class="text-center mb-16">
      <span class="inline-block glass-card px-4 py-2 rounded-full text-sm text-purple-400 mb-4">Mengapa Memilih Kami</span>
      <h2 id="features-title" class="font-display text-4xl lg:text-5xl font-bold mb-4">{{ $settings->features_title }}</h2>
     </div>
     <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
      @forelse ($features as $feature)
       @php
        $icon = $feature->icon ?: 'shield';
       @endphp
       <div class="glass-card rounded-2xl p-6 card-hover text-center">
        <div class="w-16 h-16 bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
         @if ($icon === 'wrench')
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121a3 3 0 010-4.243l4.243-4.243a3 3 0 010 4.243l-4.243 4.243a3 3 0 01-4.243 0l-4.243 4.243a3 3 0 01-4.243 0l4.243-4.243a3 3 0 010-4.243l4.243-4.243a3 3 0 014.243 0l-4.243 4.243a3 3 0 000 4.243l4.243 4.243" />
          </svg>
         @elseif ($icon === 'truck')
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zm10 0a2 2 0 11-4 0 2 2 0 014 0zM3 17V6a1 1 0 011-1h11a1 1 0 011 1v11M16 8h4l2 3v6h-6" />
          </svg>
         @elseif ($icon === 'star')
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.518 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.364 1.118l1.518 4.674c.3.921-.755 1.688-1.54 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.784.57-1.838-.197-1.539-1.118l1.518-4.674a1 1 0 00-.364-1.118L2.98 10.101c-.783-.57-.38-1.81.588-1.81h4.915a1 1 0 00.95-.69l1.517-4.674z" />
          </svg>
         @else
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 011.8-.531c2.217 0 4.2 1.783 4.2 4s-1.983 4-4.2 4a4 4 0 01-1.8-.531V19l-4-2v-4l4-2z" />
          </svg>
         @endif
        </div>
        <h3 class="font-display text-lg font-bold mb-2">{{ $feature->title }}</h3>
        <p class="text-gray-600 text-sm mt-2">{{ $feature->description ?: 'Layanan terbaik untuk kebutuhan laptop Anda.' }}</p>
       </div>
      @empty
       <div class="col-span-full text-center text-blue-600">
        Belum ada fitur. Tambahkan melalui Kelola Landing Page.
       </div>
      @endforelse
     </div>
    </div>
   </section>
   <section class="py-20 px-6">
    <div class="max-w-4xl mx-auto">
     <div class="glass-card rounded-3xl p-12 text-center relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-r from-indigo-500/10 to-purple-500/10"></div>
      <div class="relative">
       <h2 class="font-display text-3xl lg:text-4xl font-bold mb-4">Siap Menemukan Laptop Terbaik?</h2>
       <p class="text-gray-600 mb-8 max-w-xl mx-auto">Konsultasikan kebutuhan Anda dengan tim ahli kami dan dapatkan rekomendasi laptop yang tepat untuk Anda.</p>
       <div class="flex flex-wrap justify-center gap-4">
        <a href="#contact" class="btn-primary px-8 py-4 rounded-full font-semibold inline-flex items-center gap-2">
         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
         </svg>
         Chat Sekarang
        </a>
        <a href="#contact" class="glass-card px-8 py-4 rounded-full font-semibold hover:bg-white/10 transition-colors inline-flex items-center gap-2">
         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
         </svg>
         Hubungi Kami
        </a>
       </div>
      </div>
     </div>
    </div>
   </section>
   <footer id="contact" class="py-12 px-6 border-t border-white/10">
    <div class="max-w-7xl mx-auto">
     <div class="grid md:grid-cols-4 gap-8 mb-8">
      <div>
       <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-yellow-400 to-yellow-500 flex items-center justify-center">
         <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
         </svg>
        </div>
        <span class="font-display font-bold text-xl">{{ config('app.name', 'Irsaf Komputer') }}</span>
       </div>
       <p class="text-gray-600 text-sm">{{ $settings->about_description }}</p>
      </div>
      <div>
       <h4 class="font-display font-bold mb-4">Produk</h4>
       <ul class="space-y-2 text-sm text-gray-600">
        <li>Gaming Laptop</li>
        <li>Business Laptop</li>
        <li>Ultrabook</li>
        <li>2-in-1 Laptop</li>
       </ul>
      </div>
      <div>
       <h4 class="font-display font-bold mb-4">Layanan</h4>
       <ul class="space-y-2 text-sm text-gray-600">
        <li>Konsultasi</li>
        <li>Trade-In</li>
        <li>Service Center</li>
        <li>Cicilan</li>
       </ul>
      </div>
      <div>
       <h4 class="font-display font-bold mb-4">Kontak</h4>
       <ul class="space-y-2 text-sm text-gray-600">
        <li class="flex items-center gap-2">
         <svg class="w-4 h-4" fill="none" stroke="currentColor" viewbox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
         </svg>
         {{ $settings->contact_email ?: 'info@irsafkomputer.id' }}
        </li>
        <li class="flex items-center gap-2">
         <svg class="w-4 h-4" fill="none" stroke="currentColor" viewbox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
         </svg>
         {{ $settings->contact_phone ?: '(021) 1234-5678' }}
        </li>
        <li class="flex items-center gap-2">
         <svg class="w-4 h-4" fill="none" stroke="currentColor" viewbox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
         </svg>
         {{ $settings->contact_address ?: 'Jakarta, Indonesia' }}
        </li>
       </ul>
      </div>
     </div>
     <div class="pt-8 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-4">
      <p id="footer-text" class="text-gray-600 text-sm">(c) {{ date('Y') }} {{ config('app.name', 'Irsaf Komputer') }}. Semua hak dilindungi.</p>
      <div class="flex items-center gap-4">
       <a href="#" class="w-10 h-10 glass-card rounded-full flex items-center justify-center hover:bg-white/10 transition-colors">
        <svg class="w-5 h-5" fill="currentColor" viewbox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z" /></svg>
       </a>
       <a href="{{ $settings->instagram_username ? 'https://www.instagram.com/' . ltrim($settings->instagram_username, '@') : '#' }}" class="w-10 h-10 glass-card rounded-full flex items-center justify-center hover:bg-white/10 transition-colors">
        <svg class="w-5 h-5" fill="currentColor" viewbox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" /></svg>
       </a>
       <a href="#" class="w-10 h-10 glass-card rounded-full flex items-center justify-center hover:bg-white/10 transition-colors">
        <svg class="w-5 h-5" fill="currentColor" viewbox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z" /></svg>
       </a>
      </div>
     </div>
    </div>
   </footer>
  </div>
  <script>
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  </script>
 </body>
</html>
