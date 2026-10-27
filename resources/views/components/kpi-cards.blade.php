{{--
    components/kpi-cards.blade.php

    Renders the 4 Medical Dietary Guardrail KPI cards.

    Props (passed from index.blade.php or injected by JS):
      This component renders an empty shell on page load.
      Content is dynamically injected by nutriguard.js after API response.
--}}

<section id="dashboard-section" aria-labelledby="kpi-heading">
  <h2 id="kpi-heading" class="ng-section-title mb-4">
    <svg class="ng-section-icon" viewBox="0 0 20 20" fill="currentColor">
      <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5z
               M8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7z
               M14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
    </svg>
    Medical Dietary Guardrail KPIs
  </h2>

  {{-- JS injects card elements here after /api/analyze-patient responds --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" id="kpi-grid">
    <div class="ng-kpi-placeholder">Menunggu analisis pasien...</div>
  </div>
</section>
