/**
 * NutriGuard AI — Laravel Frontend Integration
 * resources/js/nutriguard.js
 *
 * Replaces the standalone script.js with a Fetch API–driven version
 * that delegates all business logic (guardrail calculation, scoring,
 * clustering) to the Laravel API endpoints.
 *
 * Architecture:
 *   1. User submits patient form
 *   2. JS POSTs to /api/analyze-patient  → receives full analysis JSON
 *   3. JS renders KPI cards, meal table, charts, justification from response
 *   4. Batch section POSTs to /api/batch-cluster or /api/demo-patients
 *   5. Chart.js instances are managed safely (destroy before re-create)
 *
 * All DOM mutation for user-sourced content uses textContent (never innerHTML).
 */

'use strict';

/* ══════════════════════════════════════════════════════════════
   1. GLOBAL CONFIG  (injected by Blade layout into window.NutriGuard)
   ══════════════════════════════════════════════════════════════ */
const API = window.NutriGuard?.apiBase ?? '/api';
const CSRF = () =>
    window.NutriGuard?.csrfToken ??
    document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/* ══════════════════════════════════════════════════════════════
   2. CLIENT-SIDE STATE  (display & chart state only — no business logic)
   ══════════════════════════════════════════════════════════════ */
const state = {
    // Last API response payload
    analysisResult: null,
    // Active meal schedule (portionGrams may be edited client-side)
    schedule: [],
    // Chart.js instances
    chartBar:      null,
    chartDoughnut: null,
    // Meal table filters
    filterSearch: '',
    filterTime:   '',
    filterSodium: '',
    sortKey:      'time',
    // Modal context
    modalContext:  null,
};

const batchState = {
    clustered:    [],
    filterSearch: '',
    filterPkg:    '',
    chartBatch:   null,
};

/* ══════════════════════════════════════════════════════════════
   3. CONSTANTS  (labels only — no business logic)
   ══════════════════════════════════════════════════════════════ */
const MEAL_TIME_ORDER = ['breakfast', 'snack_morning', 'lunch', 'snack_afternoon', 'dinner'];
const MEAL_TIME_LABELS = {
    breakfast: 'Sarapan', snack_morning: 'Snack Pagi',
    lunch: 'Makan Siang', snack_afternoon: 'Snack Sore', dinner: 'Makan Malam',
};
const ALLERGEN_LABELS = {
    seafood: 'Seafood/Udang', nuts: 'Kacang', egg: 'Telur', milk: 'Susu', soy: 'Kedelai',
};
const TEXTURE_LABELS = {
    normal: 'Biasa', soft: 'Lunak/Bubur', puree: 'Saring', liquid: 'Cair',
};

/* ══════════════════════════════════════════════════════════════
   4. UTILITIES
   ══════════════════════════════════════════════════════════════ */
function num(v, d = 1) {
    const n = parseFloat(v);
    return isNaN(n) ? '—' : n.toFixed(d);
}

function generatePatientId() {
    return `NG-${new Date().getFullYear()}-${Math.floor(1000 + Math.random() * 9000)}`;
}

function scaleNutrient(base, baseGrams, portionGrams) {
    if (baseGrams <= 0) return 0;
    return parseFloat((base * (portionGrams / baseGrams)).toFixed(2));
}

function getAllergyLabel(key) { return ALLERGEN_LABELS[key] ?? key; }
function getTextureLabel(key) { return TEXTURE_LABELS[key] ?? key; }

/* ══════════════════════════════════════════════════════════════
   5. API FETCH HELPERS
   ══════════════════════════════════════════════════════════════ */

/**
 * POST JSON to a Laravel API endpoint.
 * Returns the parsed response body or throws on non-2xx.
 */
async function apiPost(path, body) {
    const res = await fetch(`${API}${path}`, {
        method:  'POST',
        headers: {
            'Content-Type':  'application/json',
            'Accept':        'application/json',
            'X-CSRF-TOKEN':  CSRF(),
        },
        body: JSON.stringify(body),
    });
    const json = await res.json();
    if (!res.ok || !json.success) {
        throw new Error(json.message ?? `HTTP ${res.status}`);
    }
    return json.data;
}

/**
 * POST multipart form data (CSV file upload).
 */
async function apiPostForm(path, formData) {
    const res = await fetch(`${API}${path}`, {
        method:  'POST',
        headers: {
            'Accept':       'application/json',
            'X-CSRF-TOKEN': CSRF(),
        },
        body: formData,
    });
    const json = await res.json();
    if (!res.ok || !json.success) {
        throw new Error(json.message ?? `HTTP ${res.status}`);
    }
    return json.data;
}

/**
 * GET a Laravel API endpoint.
 */
async function apiGet(path) {
    const res = await fetch(`${API}${path}`, {
        headers: { 'Accept': 'application/json' },
    });
    const json = await res.json();
    if (!res.ok || !json.success) {
        throw new Error(json.message ?? `HTTP ${res.status}`);
    }
    return json.data;
}

/* ══════════════════════════════════════════════════════════════
   6. CHART MANAGEMENT
   ══════════════════════════════════════════════════════════════ */
function destroyChart(key) {
    if (state[key]) {
        try { state[key].destroy(); } catch (_) {}
        state[key] = null;
    }
}

function renderBarChart(totals, guardrail) {
    const canvas   = document.getElementById('chart-bar');
    const fallback = document.getElementById('chart1-fallback');
    if (!window.Chart) { fallback.classList.remove('hidden'); canvas.classList.add('hidden'); return; }

    destroyChart('chartBar');
    fallback.classList.add('hidden');
    canvas.classList.remove('hidden');

    const actuals = [
        parseFloat(totals.calories.toFixed(1)),
        parseFloat(totals.sodium.toFixed(1)),
        parseFloat(totals.sugar.toFixed(1)),
        parseFloat(totals.protein.toFixed(1)),
    ];
    const targets = [
        guardrail.energyKcalTarget,
        guardrail.sodiumMgMax,
        guardrail.sugarGMax,
        guardrail.proteinGMin,
    ];

    state.chartBar = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: ['Kalori (kcal)', 'Natrium (mg)', 'Gula (g)', 'Protein (g)'],
            datasets: [
                {
                    label: 'Aktual Harian',
                    data: actuals,
                    backgroundColor: ['rgba(13,148,136,.75)','rgba(220,38,38,.75)','rgba(124,58,237,.75)','rgba(5,150,105,.75)'],
                    borderColor:     ['#0D9488','#dc2626','#7c3aed','#059669'],
                    borderWidth: 2, borderRadius: 5,
                },
                {
                    label: 'Batas/Target Guardrail',
                    data: targets,
                    backgroundColor: 'rgba(148,163,184,.25)',
                    borderColor: '#94a3b8',
                    borderWidth: 2, borderRadius: 5,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12 } },
                tooltip: {
                    callbacks: {
                        afterLabel(ctx) {
                            if (ctx.datasetIndex !== 0) return '';
                            const diff = actuals[ctx.dataIndex] - targets[ctx.dataIndex];
                            return diff > 0
                                ? `⚠ Melebihi ${diff.toFixed(1)} dari target`
                                : `✓ Selisih ${Math.abs(diff).toFixed(1)} di bawah target`;
                        },
                    },
                },
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } } },
                x: { grid: { display: false }, ticks: { font: { size: 10 } } },
            },
        },
    });
}

function renderDoughnutChart(totals) {
    const canvas   = document.getElementById('chart-doughnut');
    const fallback = document.getElementById('chart2-fallback');
    if (!window.Chart) { fallback.classList.remove('hidden'); canvas.classList.add('hidden'); return; }

    destroyChart('chartDoughnut');

    const carbKcal    = totals.carbs   * 4;
    const proteinKcal = totals.protein * 4;
    const fatKcal     = totals.fat     * 9;
    const total       = carbKcal + proteinKcal + fatKcal;

    if (total <= 0) {
        fallback.textContent = 'Data makronutrient tidak tersedia.';
        fallback.classList.remove('hidden');
        canvas.classList.add('hidden');
        return;
    }

    fallback.classList.add('hidden');
    canvas.classList.remove('hidden');
    const pct = v => parseFloat(((v / total) * 100).toFixed(1));

    state.chartDoughnut = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Karbohidrat', 'Protein', 'Lemak'],
            datasets: [{
                data: [pct(carbKcal), pct(proteinKcal), pct(fatKcal)],
                backgroundColor: ['rgba(13,148,136,.8)','rgba(5,150,105,.8)','rgba(217,119,6,.8)'],
                borderColor:     ['#0D9488','#059669','#d97706'],
                borderWidth: 2, hoverOffset: 6,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: true, cutout: '60%',
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12 } },
                tooltip: {
                    callbacks: {
                        label(ctx) {
                            const kcalMap = { 'Karbohidrat': carbKcal, 'Protein': proteinKcal, 'Lemak': fatKcal };
                            return ` ${ctx.label}: ${ctx.raw}% (${kcalMap[ctx.label].toFixed(0)} kcal)`;
                        },
                    },
                },
            },
        },
    });
}

/* ══════════════════════════════════════════════════════════════
   7. DOM RENDERING — driven by API response
   ══════════════════════════════════════════════════════════════ */

// ── 7a. KPI Cards ────────────────────────────────────────────
function renderKPICards(result) {
    const { guardrail, totals, reviewStatus } = result;
    const grid = document.getElementById('kpi-grid');
    grid.innerHTML = '';
    grid.classList.add('ng-animate-in');

    const statusCls = { ok: 'ng-status-ok', warn: 'ng-status-warn', error: 'ng-status-error' };
    const statusBadge = `<span class="${statusCls[reviewStatus.key] ?? 'ng-status-na'}">${reviewStatus.label}</span>`;

    const accentMap = {
        ok:    'ng-kpi-accent-emerald',
        warn:  'ng-kpi-accent-amber',
        error: 'ng-kpi-accent-red',
    };

    grid.appendChild(createKPICard({
        accent:      accentMap[reviewStatus.key] ?? 'ng-kpi-accent-teal',
        label:       'Dietary Review Status',
        valueBadge:  statusBadge,
        tooltip:     `${guardrail.label} — ${guardrail.notes}`,
    }));
    grid.appendChild(createKPICard({
        accent:  totals.sodium <= guardrail.sodiumMgMax ? 'ng-kpi-accent-teal' : 'ng-kpi-accent-red',
        label:   'Batas Natrium Harian',
        value:   guardrail.sodiumMgMax.toLocaleString('id-ID'),
        unit:    'mg/hari',
        sub:     `Aktual: ${num(totals.sodium, 0)} mg`,
        tooltip: 'Batas maksimum natrium berdasarkan diagnosis utama pasien',
    }));
    grid.appendChild(createKPICard({
        accent:  totals.sugar <= guardrail.sugarGMax ? 'ng-kpi-accent-teal' : 'ng-kpi-accent-red',
        label:   'Batas Gula Harian',
        value:   guardrail.sugarGMax,
        unit:    'g/hari',
        sub:     `Aktual: ${num(totals.sugar, 1)} g`,
        tooltip: 'Batas maksimum gula harian berdasarkan diagnosis',
    }));
    grid.appendChild(createKPICard({
        accent:  'ng-kpi-accent-amber',
        label:   'Target Energi Harian',
        value:   guardrail.energyKcalTarget.toLocaleString('id-ID'),
        unit:    'kcal/hari',
        sub:     `Aktual: ${num(totals.calories, 0)} kcal`,
        tooltip: 'Target kalori harian yang direkomendasikan berdasarkan diagnosis',
    }));
}

function createKPICard({ accent, label, value, unit, valueBadge, sub, tooltip }) {
    const div = document.createElement('div');
    div.className = 'ng-kpi-card';

    const accentEl = document.createElement('div');
    accentEl.className = `ng-kpi-accent ${accent}`;
    div.appendChild(accentEl);

    const labelEl = document.createElement('div');
    labelEl.className = 'ng-kpi-label';
    labelEl.textContent = label;
    div.appendChild(labelEl);

    const valueWrap = document.createElement('div');
    valueWrap.className = 'ng-kpi-value';
    if (valueBadge) {
        valueWrap.innerHTML = valueBadge; // Only hardcoded status badge strings
    } else {
        valueWrap.textContent = value !== undefined ? String(value) : 'N/A';
        if (unit) {
            const unitEl = document.createElement('span');
            unitEl.className = 'ng-kpi-unit';
            unitEl.textContent = unit;
            valueWrap.appendChild(unitEl);
        }
    }
    div.appendChild(valueWrap);

    if (sub) {
        const subEl = document.createElement('div');
        subEl.className = 'ng-kpi-tooltip';
        subEl.textContent = sub;
        div.appendChild(subEl);
    }
    if (tooltip) {
        const tipEl = document.createElement('div');
        tipEl.className = 'ng-kpi-tooltip mt-1';
        tipEl.textContent = tooltip;
        div.appendChild(tipEl);
    }
    return div;
}

// ── 7b. Blacklist Panel ──────────────────────────────────────
function renderBlacklist(redFlags) {
    const panel = document.getElementById('blacklist-panel');
    panel.innerHTML = '';

    if (!redFlags.length) {
        const ok = document.createElement('div');
        ok.className = 'ng-status-ok';
        ok.textContent = '✓ Tidak ada konflik pembatasan diet yang terdeteksi';
        panel.appendChild(ok);
        return;
    }

    const title = document.createElement('p');
    title.className = 'text-xs text-slate-500 mb-2 font-medium';
    title.textContent = `${redFlags.length} item pembatasan teridentifikasi:`;
    panel.appendChild(title);

    redFlags.forEach(flag => {
        const item = document.createElement('div');
        item.className = 'ng-red-flag-item';

        const icon = document.createElement('span');
        icon.textContent = '⚠';
        icon.className = 'ng-red-flag-icon';
        item.appendChild(icon);

        const text = document.createElement('div');
        const nameEl = document.createElement('div');
        nameEl.style.fontWeight = '600';
        nameEl.textContent = flag.dish;        // from API — already server-sanitized
        const reasonEl = document.createElement('div');
        reasonEl.style.opacity = '.8';
        reasonEl.textContent = flag.reason;
        text.appendChild(nameEl);
        text.appendChild(reasonEl);
        item.appendChild(text);
        panel.appendChild(item);
    });
}

// ── 7c. Cooking Methods ──────────────────────────────────────
function renderCookingMethods(methods, texture) {
    const panel = document.getElementById('cooking-panel');
    panel.innerHTML = '';

    const intro = document.createElement('p');
    intro.className = 'text-xs text-slate-500 mb-3';
    intro.textContent = `Metode aman untuk tekstur ${getTextureLabel(texture)}:`;
    panel.appendChild(intro);

    const wrap = document.createElement('div');
    wrap.style.display = 'flex';
    wrap.style.flexWrap = 'wrap';
    methods.forEach(m => {
        const tag = document.createElement('span');
        tag.className = 'ng-cooking-tag';
        tag.textContent = m;
        wrap.appendChild(tag);
    });
    panel.appendChild(wrap);

    const note = document.createElement('p');
    note.className = 'text-xs text-slate-400 mt-3';
    note.textContent = 'Hindari menggoreng dengan banyak minyak, bumbu instan tinggi sodium, dan MSG berlebih.';
    panel.appendChild(note);
}

// ── 7d. Meal Table ───────────────────────────────────────────
function renderMealTable() {
    const wrap     = document.getElementById('meal-table-wrap');
    const guardrail = state.analysisResult?.guardrail;
    wrap.innerHTML = '';

    let rows = state.schedule.map((slot, idx) => ({ ...slot, idx }));

    // Apply display filters (totals always come from full state.schedule)
    const searchTerm  = state.filterSearch.toLowerCase().trim();
    const filterTime  = state.filterTime;
    const filterSodiumVal = parseFloat(state.filterSodium);

    const filtered = rows.filter(r => {
        if (searchTerm && !r.dish.name.toLowerCase().includes(searchTerm)) return false;
        if (filterTime && r.mealTime !== filterTime) return false;
        if (!isNaN(filterSodiumVal)) {
            const actualSod = scaleNutrient(r.dish.sodiumMg, r.dish.servingGrams, r.portionGrams);
            if (actualSod > filterSodiumVal) return false;
        }
        return true;
    });

    // Sort
    const sorted = [...filtered];
    if (state.sortKey === 'time') {
        sorted.sort((a, b) => MEAL_TIME_ORDER.indexOf(a.mealTime) - MEAL_TIME_ORDER.indexOf(b.mealTime));
    } else if (state.sortKey === 'cal-asc') {
        sorted.sort((a, b) => scaleNutrient(a.dish.caloriesKcal, a.dish.servingGrams, a.portionGrams)
                             - scaleNutrient(b.dish.caloriesKcal, b.dish.servingGrams, b.portionGrams));
    } else if (state.sortKey === 'cal-desc') {
        sorted.sort((a, b) => scaleNutrient(b.dish.caloriesKcal, b.dish.servingGrams, b.portionGrams)
                             - scaleNutrient(a.dish.caloriesKcal, a.dish.servingGrams, a.portionGrams));
    } else if (state.sortKey === 'sodium-asc') {
        sorted.sort((a, b) => scaleNutrient(a.dish.sodiumMg, a.dish.servingGrams, a.portionGrams)
                             - scaleNutrient(b.dish.sodiumMg, b.dish.servingGrams, b.portionGrams));
    } else if (state.sortKey === 'sodium-desc') {
        sorted.sort((a, b) => scaleNutrient(b.dish.sodiumMg, b.dish.servingGrams, b.portionGrams)
                             - scaleNutrient(a.dish.sodiumMg, a.dish.servingGrams, a.portionGrams));
    }

    if (!sorted.length) {
        const empty = document.createElement('div');
        empty.className = 'ng-empty-state mt-4';
        empty.textContent = 'Tidak ada hidangan yang cocok dengan filter saat ini.';
        wrap.appendChild(empty);
        return;
    }

    const table = document.createElement('table');
    table.className = 'ng-meal-table';

    const thead = document.createElement('thead');
    thead.innerHTML = `<tr>
      <th>Waktu Makan</th><th>Nama Hidangan</th><th>Porsi (g)</th>
      <th>Energi (kcal)</th><th>Protein (g)</th><th>Natrium (mg)</th>
      <th>Status</th><th>Aksi</th>
    </tr>`;
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    sorted.forEach(slot => {
        const ratio       = slot.portionGrams / slot.dish.servingGrams;
        const actualCal   = parseFloat((slot.dish.caloriesKcal * ratio).toFixed(1));
        const actualProt  = parseFloat((slot.dish.proteinGrams  * ratio).toFixed(1));
        const actualSod   = parseFloat((slot.dish.sodiumMg      * ratio).toFixed(1));
        // Use compliance from API response; fall back to slot.compliance
        const compliance  = slot.compliance ?? 'ok';
        const compMap     = { ok: ['ng-compliance-ok','✓ Sesuai'], warn: ['ng-compliance-warn','⚠ Perhatian'], bad: ['ng-compliance-bad','✗ Konflik'] };

        const tr = document.createElement('tr');
        tr.dataset.idx = slot.idx;

        // Waktu
        const tdTime = document.createElement('td');
        const chip = document.createElement('span');
        chip.className = `ng-time-chip ng-time-${slot.mealTime}`;
        chip.textContent = MEAL_TIME_LABELS[slot.mealTime] ?? slot.mealTime;
        tdTime.appendChild(chip);
        tr.appendChild(tdTime);

        // Nama
        const tdName = document.createElement('td');
        tdName.style.fontWeight = '500';
        tdName.textContent = slot.dish.name;
        tr.appendChild(tdName);

        // Porsi (editable)
        const tdPortion = document.createElement('td');
        const portionInput = document.createElement('input');
        portionInput.type      = 'number';
        portionInput.className = 'ng-portion-input';
        portionInput.value     = slot.portionGrams;
        portionInput.min = 10; portionInput.max = 999;
        portionInput.dataset.idx = slot.idx;
        portionInput.addEventListener('change', onPortionChange);
        tdPortion.appendChild(portionInput);
        tr.appendChild(tdPortion);

        [actualCal, actualProt, actualSod].forEach(val => {
            const td = document.createElement('td');
            td.textContent = num(val, 1);
            tr.appendChild(td);
        });

        // Status
        const tdStatus = document.createElement('td');
        const statusEl = document.createElement('span');
        statusEl.className = compMap[compliance][0];
        statusEl.textContent = compMap[compliance][1];
        tdStatus.appendChild(statusEl);
        tr.appendChild(tdStatus);

        // Action
        const tdAct = document.createElement('td');
        const btn = document.createElement('button');
        btn.className = 'ng-btn-sm';
        btn.textContent = 'Detail / Ganti';
        btn.dataset.idx = slot.idx;
        btn.dataset.mealtime = slot.mealTime;
        btn.addEventListener('click', openDishModal);
        tdAct.appendChild(btn);
        tr.appendChild(tdAct);

        tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    wrap.appendChild(table);
}

function renderDailyTotals() {
    // Recompute from the current state.schedule client-side
    // (portion changes happen client-side; only re-analysis hits the API)
    const totals = state.schedule.reduce((acc, slot) => {
        const r = slot.portionGrams / slot.dish.servingGrams;
        acc.calories += slot.dish.caloriesKcal * r;
        acc.protein  += slot.dish.proteinGrams  * r;
        acc.sodium   += slot.dish.sodiumMg      * r;
        acc.sugar    += slot.dish.sugarGrams    * r;
        acc.carbs    += slot.dish.carbsGrams    * r;
        acc.fat      += slot.dish.fatGrams      * r;
        return acc;
    }, { calories: 0, protein: 0, sodium: 0, sugar: 0, carbs: 0, fat: 0 });

    document.getElementById('daily-totals').classList.remove('hidden');
    document.getElementById('total-cal').textContent     = `${num(totals.calories, 0)} kcal`;
    document.getElementById('total-protein').textContent = `${num(totals.protein, 1)} g Protein`;
    document.getElementById('total-sodium').textContent  = `${num(totals.sodium, 0)} mg Sodium`;
    document.getElementById('total-sugar').textContent   = `${num(totals.sugar, 1)} g Gula`;

    return totals;
}

// ── 7e. Clinical Justification ───────────────────────────────
function renderJustification(result) {
    const { patient, guardrail, totals } = result;
    const panel = document.getElementById('justification-panel');
    panel.innerHTML = '';

    const diagLabels = { dm2:'DM Tipe 2', htn:'Hipertensi', ckd:'CKD', other:'Lainnya' };
    const items = [
        `Pasien dengan diagnosis utama ${diagLabels[patient.diagnosis] ?? patient.diagnosis}, usia ${patient.age} tahun.`,
        `Tekstur makanan: ${getTextureLabel(patient.texture)}. Semua hidangan telah diverifikasi mendukung tekstur tersebut.`,
        patient.allergies.length
            ? `Alergi: ${patient.allergies.map(getAllergyLabel).join(', ')}. Semua hidangan bebas dari alergen tersebut.`
            : 'Tidak ada alergi yang dilaporkan.',
        `Total natrium: ${num(totals.sodium, 0)} mg dari batas ${guardrail.sodiumMgMax} mg (${totals.sodium <= guardrail.sodiumMgMax ? '✓ Dalam batas' : '✗ Melebihi batas'}).`,
        `Total gula: ${num(totals.sugar, 1)} g dari batas ${guardrail.sugarGMax} g (${totals.sugar <= guardrail.sugarGMax ? '✓ Dalam batas' : '✗ Melebihi batas'}).`,
        `Target energi: ${guardrail.energyKcalTarget} kcal. Menu harian: ${num(totals.calories, 0)} kcal.`,
        guardrail.notes,
    ];

    items.forEach(text => {
        const el = document.createElement('div');
        el.className = 'ng-justification-item';
        el.textContent = text;
        panel.appendChild(el);
    });

    if (patient.notes) {
        const el = document.createElement('div');
        el.className = 'ng-justification-item';
        const b = document.createElement('strong');
        b.textContent = 'Catatan: ';
        el.appendChild(b);
        el.appendChild(document.createTextNode(patient.notes));
        panel.appendChild(el);
    }
}

// ── 7f. Action Plan ──────────────────────────────────────────
function renderActionPlan(result) {
    const { patient, guardrail, totals, redFlags } = result;
    const panel = document.getElementById('action-panel');
    panel.innerHTML = '';

    const actions = [];
    const diagLabels = { dm2:'DM Tipe 2', htn:'Hipertensi', ckd:'CKD', other:'Lainnya' };

    if (patient.allergies.length) {
        actions.push({ type:'verify', text:`Verifikasi alergi: ${patient.allergies.map(getAllergyLabel).join(', ')} — konfirmasi sebelum penyajian.` });
    }
    actions.push({ type:'ok', text:`Pastikan semua hidangan disajikan tekstur ${getTextureLabel(patient.texture)}.` });

    if (totals.sodium > guardrail.sodiumMgMax) {
        actions.push({ type:'escalate', text:`ESKALASI: Natrium ${num(totals.sodium,0)}mg melebihi batas ${guardrail.sodiumMgMax}mg. Laporkan ke DPJP.` });
    }
    if (totals.sugar > guardrail.sugarGMax) {
        actions.push({ type:'escalate', text:`ESKALASI: Gula ${num(totals.sugar,1)}g melebihi batas ${guardrail.sugarGMax}g.` });
    }
    if (redFlags.length) {
        actions.push({ type:'verify', text:`${redFlags.length} konflik bahan teridentifikasi — tinjau panel Restricted Ingredients.` });
    }
    if (patient.diagnosis === 'dm2') {
        actions.push({ type:'verify', text:'Monitor gula darah 2 jam post-prandial. Jadwal makan teratur setiap 3–4 jam.' });
    }
    if (patient.diagnosis === 'ckd') {
        actions.push({ type:'verify', text:'Pantau kadar kalium dan fosfor. Hindari buah/sayur tinggi kalium berlebih.' });
    }
    actions.push({ type:'ok', text:'Dokumentasikan asupan aktual di rekam medis setelah setiap waktu makan.' });
    actions.push({ type:'ok', text:'Evaluasi rencana makan setiap 3 hari atau sesuai perkembangan klinis.' });

    const clsMap = {
        escalate: 'ng-action-item ng-action-item-escalate',
        verify:   'ng-action-item ng-action-item-verify',
        ok:       'ng-action-item ng-action-item-ok',
    };

    actions.forEach((action, i) => {
        const el  = document.createElement('div');
        el.className = clsMap[action.type] ?? 'ng-action-item';
        const num = document.createElement('div');
        num.className = 'ng-action-num';
        num.textContent = String(i + 1);
        const txt = document.createElement('div');
        txt.textContent = action.text;
        el.appendChild(num);
        el.appendChild(txt);
        panel.appendChild(el);
    });
}

/* ══════════════════════════════════════════════════════════════
   8. SINGLE-PATIENT ANALYSIS FLOW
   ══════════════════════════════════════════════════════════════ */
async function onFormSubmit(e) {
    e.preventDefault();
    hideFormErrors();

    // Client-side pre-validation before hitting the API
    const name  = document.getElementById('f-name').value.trim();
    const age   = parseInt(document.getElementById('f-age').value, 10);
    const diag  = document.getElementById('f-diagnosis').value;
    const tex   = document.getElementById('f-texture').value;
    const errors = [];
    if (!name)                errors.push('Nama pasien wajib diisi.');
    if (!age || age < 1)      errors.push('Usia harus antara 1–120 tahun.');
    if (!diag)                errors.push('Pilih diagnosis utama.');
    if (!tex)                 errors.push('Pilih tekstur makanan.');
    if (errors.length) { showFormErrors(errors); return; }

    const noneChecked = document.getElementById('allergy-none').checked;
    const allergies   = noneChecked ? [] : Array.from(
        document.querySelectorAll('.allergy-item:checked')
    ).map(c => c.value);

    const btn = document.getElementById('btn-analyze');
    btn.disabled = true;
    btn.textContent = 'Menganalisis...';

    try {
        const result = await apiPost('/analyze-patient', {
            name,
            age,
            diagnosis: diag,
            texture:   tex,
            allergies,
            notes: document.getElementById('f-notes').value.trim(),
        });

        // Store result in state
        state.analysisResult = result;
        // Build schedule from API response — portionGrams is editable client-side
        state.schedule = result.schedule.map(slot => ({ ...slot }));

        // Render all panels
        renderKPICards(result);
        renderBlacklist(result.redFlags);
        renderCookingMethods(result.cookingMethods, result.patient.texture);
        renderMealTable();
        renderDailyTotals();
        renderBarChart(result.totals, result.guardrail);
        renderDoughnutChart(result.totals);
        renderJustification(result);
        renderActionPlan(result);

        setTimeout(() => {
            document.getElementById('dashboard-section').scrollIntoView({ behavior:'smooth', block:'start' });
        }, 100);

    } catch (err) {
        showFormErrors([`Gagal menghubungi server: ${err.message}`]);
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Jalankan Analisis Guardrail &amp; Rekomendasi Menu`;
    }
}

function onPortionChange(e) {
    const idx = parseInt(e.target.dataset.idx, 10);
    let raw   = parseInt(e.target.value, 10);
    if (isNaN(raw) || raw < 10) { raw = 10; e.target.value = 10; }
    if (raw > 999)              { raw = 999; e.target.value = 999; }
    state.schedule[idx].portionGrams = raw;

    // Recompute totals client-side after portion edit
    const totals = renderDailyTotals();

    // Re-render bar chart with updated totals
    if (state.analysisResult) {
        renderBarChart(totals, state.analysisResult.guardrail);
        renderDoughnutChart(totals);
    }
}

/* ══════════════════════════════════════════════════════════════
   9. DISH SWAP MODAL
   ══════════════════════════════════════════════════════════════ */
function openDishModal(e) {
    const idx      = parseInt(e.currentTarget.dataset.idx, 10);
    const mealTime = e.currentTarget.dataset.mealtime;
    state.modalContext = { idx, mealTime };

    const slot    = state.schedule[idx];
    const patient = state.analysisResult.patient;
    const modal   = document.getElementById('dish-modal');
    const body    = document.getElementById('modal-body');
    body.innerHTML = '';

    // Current dish detail
    const h4 = document.createElement('h4');
    h4.className = 'text-sm font-semibold text-slate-700 mb-2';
    h4.textContent = 'Detail Hidangan Aktif:';
    body.appendChild(h4);

    const detail = document.createElement('div');
    detail.className = 'ng-justification-item text-xs';
    const lines = [
        `Hidangan: ${slot.dish.name}`,
        `Porsi: ${slot.portionGrams}g (base: ${slot.dish.servingGrams}g)`,
        `Energi: ${num(scaleNutrient(slot.dish.caloriesKcal, slot.dish.servingGrams, slot.portionGrams), 1)} kcal`,
        `Natrium: ${num(scaleNutrient(slot.dish.sodiumMg, slot.dish.servingGrams, slot.portionGrams), 1)} mg`,
        `Alergen: ${slot.dish.allergens.length ? slot.dish.allergens.map(getAllergyLabel).join(', ') : 'Tidak ada'}`,
        `Catatan: ${slot.dish.sourceNote}`,
    ];
    lines.forEach(l => {
        const p = document.createElement('p');
        p.textContent = l;
        detail.appendChild(p);
    });
    body.appendChild(detail);

    // Alternatives from the full food catalogue (fetched async)
    const swapTitle = document.createElement('h4');
    swapTitle.className = 'text-sm font-semibold text-slate-700 mt-4 mb-2';
    swapTitle.textContent = 'Ganti dengan Hidangan Lain:';
    body.appendChild(swapTitle);

    const loadingEl = document.createElement('p');
    loadingEl.className = 'text-xs text-slate-400';
    loadingEl.textContent = 'Memuat alternatif...';
    body.appendChild(loadingEl);

    // Fetch food catalogue from API
    apiGet('/food-items').then(dishes => {
        loadingEl.remove();
        const allergies = patient.allergies ?? [];
        const alternatives = dishes.filter(d =>
            d.id !== slot.dish.id &&
            d.mealTypes.includes(mealTime) &&
            d.suitableTextures.includes(patient.texture) &&
            !d.allergens.some(a => allergies.includes(a))
        );

        if (!alternatives.length) {
            const noAlt = document.createElement('p');
            noAlt.className = 'text-xs text-slate-400';
            noAlt.textContent = 'Tidak ada alternatif hidangan yang sesuai.';
            body.appendChild(noAlt);
            return;
        }

        alternatives.forEach(alt => {
            const opt = document.createElement('div');
            opt.className = 'ng-swap-option';
            opt.tabIndex = 0;

            const nameEl = document.createElement('div');
            nameEl.className = 'text-sm font-semibold text-slate-800';
            nameEl.textContent = alt.name;

            const meta = document.createElement('div');
            meta.className = 'text-xs text-slate-500 mt-0.5';
            meta.textContent = `${alt.caloriesKcal} kcal · Protein ${alt.proteinGrams}g · Sodium ${alt.sodiumMg}mg`;

            const note = document.createElement('div');
            note.className = 'text-xs text-teal-700 mt-0.5';
            note.textContent = alt.sourceNote;

            opt.appendChild(nameEl);
            opt.appendChild(meta);
            opt.appendChild(note);
            opt.addEventListener('click', () => swapDish(alt, idx));
            opt.addEventListener('keydown', ev => { if (ev.key === 'Enter' || ev.key === ' ') swapDish(alt, idx); });
            body.appendChild(opt);
        });
    }).catch(() => {
        loadingEl.textContent = 'Gagal memuat daftar hidangan.';
    });

    document.getElementById('modal-title').textContent = `Detail & Ganti: ${MEAL_TIME_LABELS[mealTime]}`;
    modal.classList.remove('hidden');
}

function swapDish(newDish, idx) {
    state.schedule[idx] = { ...state.schedule[idx], dish: newDish, portionGrams: newDish.servingGrams };
    closeDishModal();
    renderMealTable();
    const totals = renderDailyTotals();
    if (state.analysisResult) {
        renderBarChart(totals, state.analysisResult.guardrail);
        renderDoughnutChart(totals);
    }
}

function closeDishModal() {
    document.getElementById('dish-modal').classList.add('hidden');
    state.modalContext = null;
}

/* ══════════════════════════════════════════════════════════════
   10. BATCH CLUSTERING
   ══════════════════════════════════════════════════════════════ */

// ── Load demo patients from API ──────────────────────────────
async function onBatchDemo() {
    document.getElementById('batch-error-banner').classList.add('hidden');
    document.getElementById('batch-csv-filename').textContent = '(Data demo 60 pasien sintetis)';
    try {
        const patients = await apiGet('/demo-patients');
        await runBatchCluster({ patients });
    } catch (err) {
        showBatchError(`Gagal memuat data demo: ${err.message}`);
    }
}

// ── Upload CSV → POST to /api/batch-cluster ──────────────────
async function onBatchCsvUpload(e) {
    const file = e.target.files[0];
    if (!file) return;
    if (!file.name.endsWith('.csv')) { showBatchError('Hanya file .csv yang didukung.'); return; }

    document.getElementById('batch-csv-filename').textContent = file.name;
    document.getElementById('batch-error-banner').classList.add('hidden');

    const formData = new FormData();
    formData.append('csv_file', file);
    // Add CSRF for multipart
    formData.append('_token', CSRF());

    try {
        const result = await apiPostForm('/batch-cluster', formData);
        applyBatchResult(result);
    } catch (err) {
        showBatchError(`Gagal membaca CSV: ${err.message}`);
    }
}

// ── POST patient array to /api/batch-cluster ─────────────────
async function runBatchCluster(body) {
    try {
        const result = await apiPost('/batch-cluster', body);
        applyBatchResult(result);
        setTimeout(() => {
            document.getElementById('batch-section').scrollIntoView({ behavior:'smooth', block:'start' });
        }, 100);
    } catch (err) {
        showBatchError(`Gagal melakukan clustering: ${err.message}`);
    }
}

function applyBatchResult(result) {
    batchState.clustered = result.clustered;
    document.getElementById('batch-content').classList.remove('hidden');
    document.getElementById('batch-empty').classList.add('hidden');
    document.getElementById('btn-batch-reset').classList.remove('hidden');

    renderBatchPackageCards(result.packages, result.total);
    renderBatchStats(result);
    renderBatchDistChart(result.packages);
    renderBatchTable();
}

// ── Package summary cards ────────────────────────────────────
function renderBatchPackageCards(packages, total) {
    const container = document.getElementById('batch-package-cards');
    container.innerHTML = '';

    [1, 2, 3].forEach(pkgId => {
        const pkg = packages[pkgId];
        const card = document.createElement('div');
        card.className = `ng-pkg-card ng-pkg-card-${pkgId}`;

        const accent = document.createElement('div');
        accent.className = `ng-pkg-accent ng-pkg-accent-${pkgId}`;
        card.appendChild(accent);

        const titleEl = document.createElement('div');
        titleEl.className = `ng-pkg-title ng-pkg-title-${pkgId}`;
        titleEl.textContent = `${pkg.name} — ${pkg.label}`;
        card.appendChild(titleEl);

        const sub = document.createElement('div');
        sub.className = 'ng-pkg-subtitle';
        sub.textContent = pkg.target;
        card.appendChild(sub);

        const countEl = document.createElement('div');
        countEl.className = `ng-pkg-count ng-pkg-count-${pkgId}`;
        countEl.textContent = pkg.count;
        card.appendChild(countEl);

        const countLabel = document.createElement('div');
        countLabel.className = 'ng-pkg-count-label';
        countLabel.textContent = `Pasien cocok · ${pkg.percentage}% dari total · ${pkg.fitFull} 100% Fit`;
        card.appendChild(countLabel);

        if (Array.isArray(pkg.dishNames)) {
            const dishList = document.createElement('div');
            dishList.className = 'ng-pkg-dish-list';
            pkg.dishNames.forEach(line => {
                const p = document.createElement('div');
                p.textContent = '• ' + line;
                dishList.appendChild(p);
            });
            card.appendChild(dishList);
        }

        container.appendChild(card);
    });
}

function renderBatchStats(result) {
    const panel = document.getElementById('batch-stats-panel');
    panel.innerHTML = '';

    const h3 = document.createElement('h3');
    h3.className = 'text-sm font-semibold text-slate-700 mb-3';
    h3.textContent = 'Ringkasan Statistik Batch';
    panel.appendChild(h3);

    const rows = [
        ['Total Pasien Dianalisis', result.total],
        ['Paket Menu 1 (Rendah Natrium)', result.packages[1].count],
        ['Paket Menu 2 (Rendah Gula)',    result.packages[2].count],
        ['Paket Menu 3 (High Protein)',   result.packages[3].count],
        ['Status 100% Fit',               result.stats.totalFit],
        ['Butuh Penyesuaian Porsi',       result.stats.totalAdjust],
    ];

    rows.forEach(([label, value]) => {
        const row = document.createElement('div');
        row.className = 'ng-batch-stat-row';
        const lbl = document.createElement('span'); lbl.className = 'ng-batch-stat-label'; lbl.textContent = label;
        const val = document.createElement('span'); val.className = 'ng-batch-stat-value'; val.textContent = value;
        row.appendChild(lbl); row.appendChild(val);
        panel.appendChild(row);
    });
}

function renderBatchDistChart(packages) {
    if (batchState.chartBatch) {
        try { batchState.chartBatch.destroy(); } catch (_) {}
        batchState.chartBatch = null;
    }
    if (!window.Chart) return;

    batchState.chartBatch = new Chart(
        document.getElementById('chart-batch-dist'), {
        type: 'pie',
        data: {
            labels: ['Paket 1 — Rendah Natrium', 'Paket 2 — Rendah Gula', 'Paket 3 — High Protein'],
            datasets: [{
                data: [packages[1].count, packages[2].count, packages[3].count],
                backgroundColor: ['rgba(5,150,105,.8)','rgba(37,99,235,.8)','rgba(124,58,237,.8)'],
                borderColor:     ['#059669','#2563eb','#7c3aed'],
                borderWidth: 2, hoverOffset: 8,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            plugins: {
                legend: { position:'bottom', labels:{ font:{size:11}, boxWidth:12, padding:10 } },
                tooltip: {
                    callbacks: {
                        label(ctx) {
                            const t = ctx.dataset.data.reduce((a,b)=>a+b,0);
                            return ` ${ctx.label}: ${ctx.raw} pasien (${t > 0 ? ((ctx.raw/t)*100).toFixed(1) : 0}%)`;
                        },
                    },
                },
            },
        },
    });
}

function renderBatchTable() {
    const wrap       = document.getElementById('batch-table-wrap');
    wrap.innerHTML   = '';
    const searchTerm = batchState.filterSearch.toLowerCase().trim();
    const filterPkg  = batchState.filterPkg ? parseInt(batchState.filterPkg, 10) : 0;

    const rows = batchState.clustered.filter(c => {
        if (searchTerm && !c.patient.name.toLowerCase().includes(searchTerm)) return false;
        if (filterPkg && c.packageId !== filterPkg) return false;
        return true;
    });

    if (!rows.length) {
        const empty = document.createElement('div');
        empty.className = 'ng-empty-state mt-4';
        empty.textContent = 'Tidak ada pasien yang cocok dengan filter.';
        wrap.appendChild(empty);
        return;
    }

    const table  = document.createElement('table');
    table.className = 'ng-batch-table';
    const thead  = document.createElement('thead');
    thead.innerHTML = `<tr>
      <th>#</th><th>ID Pasien</th><th>Nama Pasien</th><th>Diagnosis</th>
      <th>Alergi</th><th>Tekstur</th><th>Bangsal / Bed</th>
      <th>Paket Menu</th><th>Status</th><th>Aksi</th>
    </tr>`;
    table.appendChild(thead);

    const diagLabels = { dm2:'DM Tipe 2', htn:'Hipertensi', ckd:'CKD', other:'Lainnya' };
    const tbody = document.createElement('tbody');
    const pkgNames = { 1:'Paket Menu 1', 2:'Paket Menu 2', 3:'Paket Menu 3' };

    rows.forEach((c, i) => {
        const p  = c.patient;
        const tr = document.createElement('tr');

        const addTd = txt => { const td = document.createElement('td'); td.textContent = txt; tr.appendChild(td); return td; };

        addTd(String(i + 1));
        addTd(p.pid ?? '');

        const nameTd = addTd(p.name);
        nameTd.style.fontWeight = '600';

        addTd(diagLabels[p.diagnosis] ?? p.diagnosis);
        addTd(p.allergies?.length ? p.allergies.map(getAllergyLabel).join(', ') : 'Tidak ada');
        addTd(getTextureLabel(p.texture));
        addTd(p.bed ?? p.ward ?? '—');

        const pkgTd = document.createElement('td');
        const badge = document.createElement('span');
        badge.className = `ng-pkg-badge ng-pkg-badge-${c.packageId}`;
        badge.textContent = pkgNames[c.packageId] ?? c.packageId;
        pkgTd.appendChild(badge);
        tr.appendChild(pkgTd);

        const fitTd = document.createElement('td');
        const fitBadge = document.createElement('span');
        fitBadge.className = c.fitStatus === 'full' ? 'ng-fit-badge ng-fit-full' : 'ng-fit-badge ng-fit-adjust';
        fitBadge.textContent = c.fitStatus === 'full' ? '✓ 100% Fit' : '⚠ Butuh Penyesuaian';
        fitTd.appendChild(fitBadge);
        tr.appendChild(fitTd);

        const actTd = document.createElement('td');
        const btn = document.createElement('button');
        btn.className = 'ng-btn-sm';
        btn.textContent = 'Lihat Detail';
        btn.addEventListener('click', () => openBatchPatientModal(c));
        actTd.appendChild(btn);
        tr.appendChild(actTd);

        tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    wrap.appendChild(table);
}

function openBatchPatientModal(c) {
    const modal = document.getElementById('batch-patient-modal');
    const body  = document.getElementById('bpm-body');
    const title = document.getElementById('bpm-title');
    body.innerHTML = '';
    title.textContent = `Detail Pasien — ${c.patient.name}`;

    const p   = c.patient;
    const diagLabels = { dm2:'DM Tipe 2', htn:'Hipertensi', ckd:'CKD', other:'Lainnya' };
    const pkgLabels  = { 1:'Paket Menu 1 — Rendah Natrium', 2:'Paket Menu 2 — Rendah Gula', 3:'Paket Menu 3 — High Protein' };

    const infoBlock = document.createElement('div');
    infoBlock.className = 'ng-justification-item';
    [
        ['ID Pasien', p.pid],
        ['Nama',      p.name],
        ['Usia',      `${p.age} tahun`],
        ['Diagnosis', diagLabels[p.diagnosis] ?? p.diagnosis],
        ['Tekstur',   getTextureLabel(p.texture)],
        ['Alergi',    p.allergies?.length ? p.allergies.map(getAllergyLabel).join(', ') : 'Tidak ada'],
        ['Bangsal',   p.bed ?? p.ward ?? '—'],
        ['Paket Menu Rekomendasi', pkgLabels[c.packageId]],
    ].forEach(([k, v]) => {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex;gap:.5rem;margin-bottom:.2rem;font-size:.8rem;';
        const kEl = document.createElement('span');
        kEl.style.cssText = 'min-width:120px;font-weight:600;color:#475569;';
        kEl.textContent = k + ':';
        const vEl = document.createElement('span');
        vEl.textContent = v;
        row.appendChild(kEl); row.appendChild(vEl);
        infoBlock.appendChild(row);
    });
    body.appendChild(infoBlock);

    if (c.fitStatus === 'adjust') {
        const warn = document.createElement('div');
        warn.className = 'ng-action-item ng-action-item-verify mt-2';
        warn.style.fontSize = '.78rem';
        warn.textContent = '⚠ Beberapa hidangan memerlukan penyesuaian karena alergi atau tekstur. Verifikasi dengan ahli gizi.';
        body.appendChild(warn);
    }

    modal.classList.remove('hidden');
}

function closeBatchPatientModal() {
    document.getElementById('batch-patient-modal').classList.add('hidden');
}

/* ══════════════════════════════════════════════════════════════
   11. EXPORT MANIFEST
   ══════════════════════════════════════════════════════════════ */
async function exportManifest() {
    if (!batchState.clustered.length) {
        showBatchError('Tidak ada data batch untuk diekspor. Muat data terlebih dahulu.');
        return;
    }
    try {
        // POST to web route (returns CSV file download)
        const res = await fetch('/download-manifest', {
            method:  'POST',
            headers: {
                'Content-Type':  'application/json',
                'Accept':        'text/csv',
                'X-CSRF-TOKEN':  CSRF(),
            },
            body: JSON.stringify({ clustered: batchState.clustered }),
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const blob = await res.blob();
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = `NutriGuard_KitchenManifest_${new Date().toISOString().slice(0,10)}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(() => URL.revokeObjectURL(url), 5000);
        // Also trigger print
        setTimeout(() => window.print(), 400);
    } catch (err) {
        showBatchError(`Gagal mengekspor manifest: ${err.message}`);
    }
}

/* ══════════════════════════════════════════════════════════════
   12. FORM HELPERS
   ══════════════════════════════════════════════════════════════ */
function showFormErrors(errors) {
    const banner = document.getElementById('form-error-banner');
    banner.innerHTML = '';
    errors.forEach(msg => {
        const p = document.createElement('p');
        p.textContent = msg;
        banner.appendChild(p);
    });
    banner.classList.remove('hidden');
}

function hideFormErrors() {
    const banner = document.getElementById('form-error-banner');
    banner.innerHTML = '';
    banner.classList.add('hidden');
}

function showBatchError(msg) {
    const banner = document.getElementById('batch-error-banner');
    banner.textContent = msg;
    banner.classList.remove('hidden');
}

function resetDashboard() {
    document.getElementById('patient-form').reset();
    document.getElementById('f-pid').value = generatePatientId();
    hideFormErrors();

    state.analysisResult = null;
    state.schedule       = [];
    destroyChart('chartBar');
    destroyChart('chartDoughnut');

    document.getElementById('kpi-grid').innerHTML        = '<div class="ng-kpi-placeholder">Menunggu analisis pasien...</div>';
    document.getElementById('blacklist-panel').innerHTML = '<div class="ng-empty-state">Jalankan analisis untuk melihat daftar pembatasan.</div>';
    document.getElementById('cooking-panel').innerHTML   = '<div class="ng-empty-state">Jalankan analisis untuk saran metode memasak.</div>';
    document.getElementById('meal-table-wrap').innerHTML = '<div class="ng-empty-state mt-4">Jalankan analisis untuk melihat rekomendasi menu harian.</div>';
    document.getElementById('daily-totals').classList.add('hidden');
    document.getElementById('justification-panel').innerHTML = '<div class="ng-empty-state">Jalankan analisis untuk melihat justifikasi klinis.</div>';
    document.getElementById('action-panel').innerHTML        = '<div class="ng-empty-state">Jalankan analisis untuk melihat rencana tindakan.</div>';

    ['chart1-fallback','chart2-fallback'].forEach(id => {
        const el = document.getElementById(id);
        el.textContent = 'Data belum tersedia. Jalankan analisis terlebih dahulu.';
        el.classList.remove('hidden');
    });
    document.getElementById('chart-bar').classList.add('hidden');
    document.getElementById('chart-doughnut').classList.add('hidden');

    state.filterSearch = ''; state.filterTime = ''; state.filterSodium = ''; state.sortKey = 'time';
    document.getElementById('meal-search').value       = '';
    document.getElementById('meal-filter-time').value  = '';
    document.getElementById('meal-filter-sodium').value= '';
    document.getElementById('meal-sort').value         = 'time';
}

function resetBatch() {
    batchState.clustered    = [];
    batchState.filterSearch = '';
    batchState.filterPkg    = '';
    if (batchState.chartBatch) {
        try { batchState.chartBatch.destroy(); } catch(_) {}
        batchState.chartBatch = null;
    }
    document.getElementById('batch-content').classList.add('hidden');
    document.getElementById('batch-empty').classList.remove('hidden');
    document.getElementById('btn-batch-reset').classList.add('hidden');
    document.getElementById('batch-csv-filename').textContent = '';
    document.getElementById('batch-csv-upload').value         = '';
    document.getElementById('batch-search').value             = '';
    document.getElementById('batch-filter-pkg').value         = '';
    document.getElementById('batch-error-banner').classList.add('hidden');
}

/* ══════════════════════════════════════════════════════════════
   13. INIT
   ══════════════════════════════════════════════════════════════ */
function init() {
    document.getElementById('f-pid').value = generatePatientId();
    // Pre-fill demo values
    document.getElementById('f-name').value      = 'Budi Santoso';
    document.getElementById('f-age').value       = '62';
    document.getElementById('f-diagnosis').value = 'htn';
    document.getElementById('f-texture').value   = 'normal';

    // Chart canvas hidden until analysis
    document.getElementById('chart-bar').classList.add('hidden');
    document.getElementById('chart-doughnut').classList.add('hidden');

    // ── Form events ──────────────────────────────────────────
    document.getElementById('patient-form').addEventListener('submit', onFormSubmit);
    document.getElementById('btn-reset').addEventListener('click', resetDashboard);

    // Allergy "none" toggle
    document.getElementById('allergy-none').addEventListener('change', function () {
        document.querySelectorAll('.allergy-item').forEach(c => {
            c.checked  = false;
            c.disabled = this.checked;
        });
    });
    document.querySelectorAll('.allergy-item').forEach(c => {
        c.addEventListener('change', () => {
            if (c.checked) document.getElementById('allergy-none').checked = false;
        });
    });

    // ── Meal table filters ────────────────────────────────────
    document.getElementById('meal-search').addEventListener('input', function () {
        state.filterSearch = this.value;
        if (state.schedule.length) renderMealTable();
    });
    document.getElementById('meal-filter-time').addEventListener('change', function () {
        state.filterTime = this.value;
        if (state.schedule.length) renderMealTable();
    });
    document.getElementById('meal-filter-sodium').addEventListener('input', function () {
        state.filterSodium = this.value;
        if (state.schedule.length) renderMealTable();
    });
    document.getElementById('meal-sort').addEventListener('change', function () {
        state.sortKey = this.value;
        if (state.schedule.length) renderMealTable();
    });

    // ── Modals ────────────────────────────────────────────────
    document.getElementById('modal-close').addEventListener('click', closeDishModal);
    document.getElementById('dish-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closeDishModal(); });
    document.getElementById('bpm-close').addEventListener('click', closeBatchPatientModal);
    document.getElementById('batch-patient-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closeBatchPatientModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeDishModal(); closeBatchPatientModal(); } });

    // ── Batch events ──────────────────────────────────────────
    document.getElementById('btn-batch-demo').addEventListener('click', onBatchDemo);
    document.getElementById('batch-csv-upload').addEventListener('change', onBatchCsvUpload);
    document.getElementById('btn-batch-reset').addEventListener('click', resetBatch);
    document.getElementById('btn-export-manifest').addEventListener('click', exportManifest);

    document.getElementById('batch-search').addEventListener('input', function () {
        batchState.filterSearch = this.value;
        if (batchState.clustered.length) renderBatchTable();
    });
    document.getElementById('batch-filter-pkg').addEventListener('change', function () {
        batchState.filterPkg = this.value;
        if (batchState.clustered.length) renderBatchTable();
    });

    // Chart.js availability fallback
    if (!window.Chart) {
        ['chart1-fallback','chart2-fallback'].forEach(id => {
            const el = document.getElementById(id);
            el.textContent = 'Chart.js gagal dimuat. Pastikan koneksi internet tersedia.';
            el.classList.remove('hidden');
        });
    }
}

document.addEventListener('DOMContentLoaded', init);
