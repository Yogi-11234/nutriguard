{{--
    components/meal-table.blade.php

    Renders the Daily Nusantara Meal Plan section including:
    — Filter controls (search, time, sodium, sort)
    — Interactive meal table (JS-rendered)
    — Daily totals bar
--}}

<section id="meal-section" class="ng-card" aria-labelledby="meal-heading">
  <div class="ng-card-header">
    <h2 id="meal-heading" class="ng-section-title">
      <svg class="ng-section-icon" viewBox="0 0 20 20" fill="currentColor">
        <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892
                 C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553
                 l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3z
                 M16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z
                 M6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/>
      </svg>
      Daily Nusantara Meal Plan
    </h2>
    <span class="ng-badge-info">1-Day Recommendation</span>
  </div>

  {{-- Filter controls --}}
  <div class="px-5 pt-4 pb-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3" id="meal-controls">
    <div class="ng-field-group mb-0">
      <label for="meal-search" class="ng-label">Cari Hidangan</label>
      <input id="meal-search" type="text" class="ng-input ng-input-sm" placeholder="Nama hidangan..." />
    </div>
    <div class="ng-field-group mb-0">
      <label for="meal-filter-time" class="ng-label">Filter Waktu Makan</label>
      <select id="meal-filter-time" class="ng-input ng-input-sm ng-select">
        <option value="">Semua Waktu</option>
        <option value="breakfast">Sarapan</option>
        <option value="snack_morning">Snack Pagi</option>
        <option value="lunch">Makan Siang</option>
        <option value="snack_afternoon">Snack Sore</option>
        <option value="dinner">Makan Malam</option>
      </select>
    </div>
    <div class="ng-field-group mb-0">
      <label for="meal-filter-sodium" class="ng-label">Filter Natrium Maks (mg)</label>
      <input id="meal-filter-sodium" type="number" class="ng-input ng-input-sm" placeholder="cth. 500" min="0" />
    </div>
    <div class="ng-field-group mb-0">
      <label for="meal-sort" class="ng-label">Urutkan</label>
      <select id="meal-sort" class="ng-input ng-input-sm ng-select">
        <option value="time">Waktu Makan</option>
        <option value="cal-asc">Kalori ↑</option>
        <option value="cal-desc">Kalori ↓</option>
        <option value="sodium-asc">Natrium ↑</option>
        <option value="sodium-desc">Natrium ↓</option>
      </select>
    </div>
  </div>

  {{-- Table (JS renders rows here) --}}
  <div class="px-5 pb-5 overflow-x-auto">
    <div id="meal-table-wrap">
      <div class="ng-empty-state mt-4">Jalankan analisis untuk melihat rekomendasi menu harian.</div>
    </div>
  </div>

  {{-- Daily Totals Bar (shown after analysis) --}}
  <div id="daily-totals" class="hidden px-5 pb-5">
    <div class="ng-totals-bar">
      <span class="ng-totals-label">Total Harian Aktif:</span>
      <span id="total-cal"     class="ng-total-chip ng-total-cal">— kcal</span>
      <span id="total-protein" class="ng-total-chip ng-total-protein">— g Protein</span>
      <span id="total-sodium"  class="ng-total-chip ng-total-sodium">— mg Sodium</span>
      <span id="total-sugar"   class="ng-total-chip ng-total-sugar">— g Gula</span>
    </div>
  </div>
</section>
