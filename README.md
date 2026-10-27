# NutriGuard AI 🥗🏥
> **Indonesian Hospital Patient Dietary & Clinical Nutrition System**

NutriGuard AI adalah aplikasi analitik gizi dan preskripsi menu diet harian pasien rawat inap berbasis web. Sistem ini dibuat untuk membantu tim gizi dan dapur rumah sakit di Indonesia menyusun menu makanan lokal yang aman, presisi, dan sesuai dengan batasan medis pasien (hipertensi, diabetes, ginjal, hingga alergi).

---

## 🚀 Fitur Utama

- **Medical Dietary Guardrail Check**: Otomatis menghitung batas aman Natrium, Gula, Kalori, dan memfilter pantangan bahan masakan lokal sesuai rekam medis pasien (Standar PGRS Kemenkes RI).
- **Personalized 1-Day Meal Plan**: Generasi rekomendasi 5 waktu makan (Sarapan, Snack Pagi, Makan Siang, Snack Sore, Makan Malam) menggunakan database kuliner nusantara.
- **Batch Patient Clustering (Operasional Dapur RS)**: Mengelompokkan hingga 60+ data pasien rawat inap secara otomatis ke dalam 3 Paket Menu Utama Dapur (Rendah Garam/Lunak, Rendah Gula, & High-Protein/Regular) untuk efisiensi masak massal.
- **Interactive Health Analytics**: Dashboard interaktif berbasis Chart.js untuk melihat pembanding nutrisi aktual vs batas aman medis serta komposisi makronutrisi.
- **Kitchen Manifest Export**: Ekspor daftar distribusi makanan per ruang rawat inap untuk tim operasional dapur.

---

## 🛠️ Tech Stack

- **Framework**: Laravel 12
- **Architecture**: Modular Monolith Architecture (`nwidart/laravel-modules`) + Service Layer Pattern
- **Frontend**: Blade Templating, Tailwind CSS, Vanilla JavaScript (Fetch API)
- **Visualization**: Chart.js
- **Database**: PostgreSQL 

---

## 📌 Modul & Alur Kerja (Backend Architecture)

Sistem ini memisahkan logika bisnis medis dari Controller utama menggunakan **Service Layer**:

- `DietaryGuardrailService.php`: Memproses aturan pantangan bahan masakan, tekstur (Biasa, Lunak/Bubur, Saring, Cair), dan limitasi nutrisi.
- `MealPlannerService.php`: Memilih hidangan lokal dari database yang lolos filter *blacklist* & *allergens*.
- `PatientClusteringService.php`: Algoritma pemetaan masal 60+ pasien ke dalam 3 jenis paket menu dapur rumah sakit.

---

## 💻 Cara Install & Jalankan di Lokal

### 1. Clone Repository
```bash
git clone [https://github.com/username-kamu/nutriguard-ai.git](https://github.com/username-kamu/nutriguard-ai.git)
cd nutriguard-ai
