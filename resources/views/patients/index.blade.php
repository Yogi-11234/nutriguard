{{--
    patients/index.blade.php

    Main dashboard view for NutriGuard AI.
    Extends layouts/app.blade.php and composes all section components.

    All dynamic data (KPI cards, meal table, charts, batch results)
    is loaded asynchronously via Fetch API calls to Laravel API endpoints
    by resources/js/nutriguard.js.
--}}

@extends('layouts.app')

@section('title', 'NutriGuard AI — Dashboard')

@section('content')

  {{-- ──────────────────── PATIENT PROFILE FORM ──────────────── --}}
  <section id="patient-section" class="ng-card" aria-labelledby="patient-heading">
    <div class="ng-card-header">
      <h2 id="patient-heading" class="ng-section-title">
        <svg class="ng-section-icon" viewBox="0 0 20 20" fill="currentColor">
          <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
        </svg>
        Patient Profile &amp; Medical Record
      </h2>
      <span class="ng-badge-info">Interactive Form</span>
    </div>

    <div class="p-5">
      {{--
          novalidate: Laravel API handles server-side validation.
          JS intercepts submit, calls /api/analyze-patient via Fetch,
          then renders the response DOM-side without page reload.
      --}}
      <form id="patient-form" novalidate autocomplete="off">
        @csrf {{-- Token embedded for JS extraction if using POST form fallback --}}

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

          <div class="ng-field-group">
            <label for="f-name" class="ng-label">Nama Pasien <span class="ng-required">*</span></label>
            <input id="f-name" type="text" class="ng-input" placeholder="cth. Budi Santoso" maxlength="80" />
          </div>

          <div class="ng-field-group">
            <label for="f-pid" class="ng-label">ID Pasien Demo</label>
            <input id="f-pid" type="text" class="ng-input ng-input-readonly" readonly />
          </div>

          <div class="ng-field-group">
            <label for="f-age" class="ng-label">Usia (Tahun) <span class="ng-required">*</span></label>
            <input id="f-age" type="number" class="ng-input" placeholder="cth. 55" min="1" max="120" />
          </div>

          <div class="ng-field-group">
            <label for="f-diagnosis" class="ng-label">Diagnosis Utama <span class="ng-required">*</span></label>
            <select id="f-diagnosis" class="ng-input ng-select">
              <option value="">— Pilih Diagnosis —</option>
              <option value="dm2">Diabetes Melitus Tipe 2</option>
              <option value="htn">Hipertensi</option>
              <option value="ckd">Penyakit Ginjal Kronis</option>
              <option value="other">Lainnya</option>
            </select>
          </div>

          <div class="ng-field-group">
            <label for="f-texture" class="ng-label">Tekstur Makanan <span class="ng-required">*</span></label>
            <select id="f-texture" class="ng-input ng-select">
              <option value="">— Pilih Tekstur —</option>
              <option value="normal">Biasa</option>
              <option value="soft">Lunak / Bubur</option>
              <option value="puree">Saring</option>
              <option value="liquid">Cair</option>
            </select>
          </div>

          <div class="ng-field-group">
            <label class="ng-label">Alergi Makanan</label>
            <div class="ng-checkbox-grid" id="allergy-group">
              <label class="ng-check-label">
                <input type="checkbox" class="ng-check allergy-item" value="seafood" /> Seafood / Udang
              </label>
              <label class="ng-check-label">
                <input type="checkbox" class="ng-check allergy-item" value="nuts" /> Kacang
              </label>
              <label class="ng-check-label">
                <input type="checkbox" class="ng-check allergy-item" value="egg" /> Telur
              </label>
              <label class="ng-check-label">
                <input type="checkbox" class="ng-check allergy-item" value="milk" /> Susu
              </label>
              <label class="ng-check-label">
                <input type="checkbox" class="ng-check allergy-item" value="soy" /> Kedelai
              </label>
              <label class="ng-check-label ng-check-none">
                <input type="checkbox" class="ng-check" id="allergy-none" value="none" /> Tidak Ada Alergi
              </label>
            </div>
          </div>

          <div class="ng-field-group sm:col-span-2 lg:col-span-3">
            <label for="f-notes" class="ng-label">Catatan Tambahan</label>
            <textarea id="f-notes" class="ng-input ng-textarea" rows="2"
                      placeholder="Catatan medis atau diet khusus..." maxlength="300"></textarea>
          </div>
        </div>{{-- /grid --}}

        {{-- API validation errors rendered here by JS --}}
        <div id="form-error-banner" class="ng-error-banner hidden" role="alert" aria-live="polite"></div>

        <div class="flex flex-wrap gap-3 mt-5">
          <button type="submit" id="btn-analyze" class="ng-btn-primary">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586
                       7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                    clip-rule="evenodd"/>
            </svg>
            Jalankan Analisis Guardrail &amp; Rekomendasi Menu
          </button>
          <button type="button" id="btn-reset" class="ng-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd"
                    d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.902.606
                       A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1z
                       m.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5
                       a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566
                       1 1 0 01.61-1.276z"
                    clip-rule="evenodd"/>
            </svg>
            Reset
          </button>
        </div>
      </form>
    </div>
  </section>

  {{-- ──────────────────── KPI GUARDRAIL CARDS ──────────────── --}}
  @include('components.kpi-cards')

  {{-- ──────────────────── BLACKLIST + COOKING ──────────────── --}}
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <section class="ng-card" aria-labelledby="blacklist-heading">
      <div class="ng-card-header">
        <h2 id="blacklist-heading" class="ng-section-title">
          <svg class="ng-section-icon text-red-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
                  d="M13.477 14.89A6 6 0 015.11 6.524L13.477 14.89zm1.414-1.414L6.524 5.11
                     a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z"
                  clip-rule="evenodd"/>
          </svg>
          Restricted Ingredients &amp; Red Flags
        </h2>
      </div>
      <div id="blacklist-panel" class="p-5">
        <div class="ng-empty-state">Jalankan analisis untuk melihat daftar pembatasan.</div>
      </div>
    </section>

    <section class="ng-card" aria-labelledby="cooking-heading">
      <div class="ng-card-header">
        <h2 id="cooking-heading" class="ng-section-title">
          <svg class="ng-section-icon text-teal-600" viewBox="0 0 20 20" fill="currentColor">
            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0z
                     M12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07z
                     M6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
          </svg>
          Recommended Cooking Methods
        </h2>
      </div>
      <div id="cooking-panel" class="p-5">
        <div class="ng-empty-state">Jalankan analisis untuk saran metode memasak.</div>
      </div>
    </section>
  </div>

  {{-- ──────────────────── NUTRITION CHARTS ──────────────────── --}}
  <section id="insights-section" aria-labelledby="charts-heading">
    <h2 id="charts-heading" class="ng-section-title mb-4">
      <svg class="ng-section-icon" viewBox="0 0 20 20" fill="currentColor">
        <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"/>
        <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"/>
      </svg>
      Interactive Nutrition Charts
    </h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="ng-card p-5">
        <h3 class="font-semibold text-slate-700 mb-3 text-sm">Nutrisi Aktual vs Batas Target Guardrail</h3>
        <div id="chart1-container" class="ng-chart-container">
          <canvas id="chart-bar" aria-label="Bar chart: nutrisi aktual vs target" role="img"></canvas>
          <div id="chart1-fallback" class="ng-chart-fallback hidden">
            Data belum tersedia. Jalankan analisis terlebih dahulu.
          </div>
        </div>
      </div>
      <div class="ng-card p-5">
        <h3 class="font-semibold text-slate-700 mb-3 text-sm">Distribusi Makronutrient Harian (% Energi)</h3>
        <div id="chart2-container" class="ng-chart-container">
          <canvas id="chart-doughnut" aria-label="Doughnut chart: distribusi makronutrient" role="img"></canvas>
          <div id="chart2-fallback" class="ng-chart-fallback hidden">
            Data belum tersedia. Jalankan analisis terlebih dahulu.
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- ──────────────────── MEAL TABLE COMPONENT ──────────────── --}}
  @include('components.meal-table')

  {{-- ──────────────────── CLINICAL JUSTIFICATION ──────────────── --}}
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <section class="ng-card" aria-labelledby="justification-heading">
      <div class="ng-card-header">
        <h2 id="justification-heading" class="ng-section-title">
          <svg class="ng-section-icon text-teal-600" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
                  d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16
                     a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0
                     01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z"
                  clip-rule="evenodd"/>
          </svg>
          Clinical Justification
        </h2>
      </div>
      <div id="justification-panel" class="p-5">
        <div class="ng-empty-state">Jalankan analisis untuk melihat justifikasi klinis.</div>
      </div>
    </section>

    <section class="ng-card" aria-labelledby="action-heading">
      <div class="ng-card-header">
        <h2 id="action-heading" class="ng-section-title">
          <svg class="ng-section-icon text-amber-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
                  d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0z
                     M9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                  clip-rule="evenodd"/>
          </svg>
          Nurse / Dietitian Action Plan
        </h2>
      </div>
      <div id="action-panel" class="p-5">
        <div class="ng-empty-state">Jalankan analisis untuk melihat rencana tindakan.</div>
      </div>
    </section>
  </div>

  {{-- ──────────────────── BATCH CLUSTERING SECTION ──────────────── --}}
  <section id="batch-section" class="ng-card" aria-labelledby="batch-heading">
    <div class="ng-card-header">
      <h2 id="batch-heading" class="ng-section-title">
        <svg class="ng-section-icon text-purple-600" viewBox="0 0 20 20" fill="currentColor">
          <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0z
                   M14 15a4 4 0 00-8 0v1h8v-1zM6 8a2 2 0 11-4 0 2 2 0 014 0z
                   M16 18v-1a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v1h-3z
                   M4.75 14.094A5.973 5.973 0 004 17v1H1v-1a3 3 0 013.75-2.906z"/>
        </svg>
        Batch Patient Dietary Clustering
      </h2>
      <span class="ng-badge-batch">New Feature</span>
    </div>

    {{-- Upload / Demo Controls --}}
    <div class="p-5 border-b border-slate-100">
      <p class="text-xs text-slate-500 mb-4">
        Unggah file CSV data pasien atau gunakan dataset demo sintetis 60 pasien
        untuk melihat distribusi Paket Menu per kelompok klinis.
      </p>
      <div class="flex flex-wrap gap-3 items-center">
        <label for="batch-csv-upload" class="ng-btn-primary cursor-pointer">
          <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd"
                  d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z
                     M6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0
                     01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707
                     a1 1 0 01-1.414 0z"
                  clip-rule="evenodd"/>
          </svg>
          Upload CSV Patient Data
        </label>
        <input id="batch-csv-upload" type="file" accept=".csv" class="hidden" aria-label="Upload CSV" />

        <button type="button" id="btn-batch-demo" class="ng-btn-secondary">
          <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10
                     A7.969 7.969 0 015.5 14c1.69 0 3.254.545 4.5 1.464V4.804z
                     M10.5 15.464A7.967 7.967 0 0115 14c1.255 0 2.443.29 3.5.804v-10
                     A7.968 7.968 0 0015 4c-1.69 0-3.254.545-4.5 1.464v10z"/>
          </svg>
          Gunakan Data Demo (60 Pasien Sintetis)
        </button>

        <button type="button" id="btn-batch-reset" class="ng-btn-ghost hidden">
          Reset Batch
        </button>
        <span id="batch-csv-filename" class="text-xs text-slate-400 italic"></span>
      </div>
      <div id="batch-error-banner" class="ng-error-banner hidden mt-3" role="alert" aria-live="polite"></div>
    </div>

    {{-- Results (hidden until data loaded) --}}
    <div id="batch-content" class="hidden">

      <div class="p-5 border-b border-slate-100">
        <h3 class="text-sm font-semibold text-slate-700 mb-3">Ringkasan Distribusi Paket Menu</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" id="batch-package-cards"></div>
      </div>

      <div class="p-5 border-b border-slate-100">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
          <div>
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Patient Distribution per Menu Package</h3>
            <div id="batch-chart-container" class="ng-chart-container" style="min-height:200px;">
              <canvas id="chart-batch-dist" aria-label="Pie chart distribusi pasien per paket menu" role="img"></canvas>
            </div>
          </div>
          <div id="batch-stats-panel"></div>
        </div>
      </div>

      <div class="px-5 pt-4 pb-2 flex flex-wrap gap-3 items-end">
        <div class="ng-field-group mb-0">
          <label for="batch-search" class="ng-label">Cari Nama Pasien</label>
          <input id="batch-search" type="text" class="ng-input ng-input-sm" placeholder="Nama pasien..." />
        </div>
        <div class="ng-field-group mb-0">
          <label for="batch-filter-pkg" class="ng-label">Filter Paket Menu</label>
          <select id="batch-filter-pkg" class="ng-input ng-input-sm ng-select">
            <option value="">Semua Paket</option>
            <option value="1">Paket Menu 1 — Rendah Natrium</option>
            <option value="2">Paket Menu 2 — Rendah Gula</option>
            <option value="3">Paket Menu 3 — High Protein</option>
          </select>
        </div>
        <button type="button" id="btn-export-manifest" class="ng-btn-primary ml-auto">
          Export Kitchen Distribution Manifest
        </button>
      </div>

      <div class="px-5 pb-5 overflow-x-auto">
        <div id="batch-table-wrap"></div>
      </div>

    </div>{{-- /batch-content --}}

    <div id="batch-empty" class="p-10">
      <div class="ng-empty-state">
        Upload file CSV atau klik "Gunakan Data Demo" untuk memulai analisis batch.
      </div>
    </div>
  </section>

@endsection
