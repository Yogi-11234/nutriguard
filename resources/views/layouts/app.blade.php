<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'NutriGuard AI') — Clinical Nutrition Dashboard</title>

  {{-- Tailwind CSS via CDN (swap for Vite in production: @vite('resources/css/app.css')) --}}
  <script src="https://cdn.tailwindcss.com"></script>

  {{-- Chart.js --}}
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js" defer></script>

  {{-- Custom styles --}}
  <link rel="stylesheet" href="{{ asset('css/style.css') }}" />

  @stack('head')
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

  {{-- ═══════════════════════════════════════════ HEADER ═══════ --}}
  <header class="ng-header sticky top-0 z-50">
    <div class="max-w-screen-xl mx-auto px-4 py-3 flex items-center justify-between flex-wrap gap-y-2">
      <div class="flex items-center gap-3">
        <div class="ng-logo-icon">
          <svg width="32" height="32" viewBox="0 0 32 32" fill="none" aria-hidden="true">
            <rect width="32" height="32" rx="8" fill="#0D9488"/>
            <path d="M8 16 Q12 8 16 16 Q20 24 24 16" stroke="white" stroke-width="2.5" fill="none" stroke-linecap="round"/>
            <circle cx="16" cy="10" r="2.5" fill="#6EE7B7"/>
          </svg>
        </div>
        <div>
          <h1 class="text-white font-bold text-lg leading-tight">NutriGuard AI</h1>
          <p class="text-teal-200 text-xs">Clinical Nutrition Analytics</p>
        </div>
        <span class="ng-badge-demo ml-2">
          {{ app()->environment() === 'production' ? 'Production' : 'Demo Environment' }}
        </span>
      </div>

      <nav class="flex items-center gap-1 flex-wrap" aria-label="Main navigation">
        <a href="#dashboard-section"   class="ng-nav-link">Dashboard</a>
        <a href="#patient-section"     class="ng-nav-link">Patient Analysis</a>
        <a href="#meal-section"        class="ng-nav-link">Meal Recommendations</a>
        <a href="#insights-section"    class="ng-nav-link">Nutrition Insights</a>
        <a href="#batch-section"       class="ng-nav-link ng-nav-link-batch">Batch Clustering</a>
        <div class="ng-user-badge ml-2">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <circle cx="8" cy="6" r="3" fill="#94a3b8"/>
            <path d="M2 14c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="#94a3b8" stroke-width="1.5" fill="none"/>
          </svg>
          <span>Clinical Nutrition Team</span>
        </div>
      </nav>
    </div>
  </header>

  {{-- ═══════════════════════════════════════════ MAIN ═══════════ --}}
  <main class="max-w-screen-xl mx-auto px-4 py-6 space-y-8">
    @yield('content')
  </main>

  {{-- ═══════════════════════════════════════════ FOOTER ═══════ --}}
  <footer class="ng-footer mt-10">
    <p>NutriGuard AI &copy; {{ date('Y') }} — Prototype Demo &middot;
       Data pasien bersifat sintetis, hanya untuk keperluan demonstrasi klinis.</p>
  </footer>

  {{-- ═══════════════ MODALS (shared across views) ═══════════════ --}}
  @include('components.modal-dish')
  @include('components.modal-batch-patient')

  {{-- Global app config passed to JavaScript --}}
  <script>
    window.NutriGuard = {
      apiBase:   '{{ url('/api') }}',
      csrfToken: '{{ csrf_token() }}',
      env:       '{{ app()->environment() }}',
    };
  </script>

  {{-- Application JS (Fetch API + Chart.js logic) --}}
  <script src="{{ asset('js/nutriguard.js') }}" defer></script>

  @stack('scripts')
</body>
</html>
