# Backend Agent Rules (Laravel / PHP)

Panduan berikut WAJIB diikuti dalam setiap pengembangan atau modifikasi fitur backend.

---

## 0. Project Baseline
- PHP: >= 8.3
- Laravel: >= 13.x
- Gunakan native PHP `enum` (bukan package tambahan) untuk status/kode tetap, kecuali proyek sudah punya konvensi lain.
- Tooling: **Laravel Pint** untuk code style checking. Pastikan lolos code style sebelum task dianggap selesai. (Catatan: Larastan saat ini tidak terinstall di proyek).

---

## 1. Definition of Done
Sebuah task backend dianggap selesai hanya jika:
1. Implementasi fitur selesai.
2. Request validation menggunakan `FormRequest` jika ada input dari user.
3. Authorization diterapkan jika aksi membutuhkan hak akses.
4. Test yang relevan sudah ditambahkan atau diperbarui.
5. Response API mengikuti format standar proyek / API Resource.
6. Migration dibuat jika ada perubahan database.
7. Tidak ada business logic berat di controller (lihat 3.1 untuk definisi konkret).
8. Lolos code style check (Pint).
9. Tidak ada data sensitif yang ter-log atau ter-expose ke response (lihat 7.1).

---

## 2. Testing Rules (Mandatory)
- Setiap fitur baru atau modifikasi fitur WAJIB disertai automated test.
- Setiap bug fix WAJIB disertai regression test jika memungkinkan.
- Test minimal harus mencakup:
  - **happy path**
  - **validation / sad path**
  - **authorization / forbidden case** jika relevan

### 2.1 Kapan membuat Feature Test
Feature Test WAJIB untuk:
- endpoint API baru
- perubahan request validation
- perubahan middleware/auth/authorization
- perubahan response contract API
- alur request -> controller -> service -> database

### 2.2 Kapan membuat Unit Test
Unit Test WAJIB untuk:
- service / action / helper / resolver / mapper
- business logic terisolasi
- kalkulasi, formatter, status mapping, dsb

### 2.3 Test Conventions
- Gunakan nama test deskriptif, contoh:
  - `test_user_can_create_invoice`
  - `test_create_invoice_fails_when_total_is_negative`
- Gunakan `RefreshDatabase` untuk test yang menyentuh database.
- Gunakan Factory/Faker untuk data test.
- Jangan bergantung pada data manual di database lokal.

---

## 3. Architecture & Design Patterns
### 3.1 Thin Controllers
- Controller hanya menangani HTTP request/response.
- Controller tidak boleh berisi business logic kompleks, query panjang, atau validasi inline.
- Controller sebaiknya hanya:
  1. menerima request
  2. memanggil action/service
  3. mengembalikan resource/response
- **Definisi "business logic berat"**: jika method controller membutuhkan lebih dari ~10-15 baris logic di luar pemanggilan action/service/resource, atau melibatkan query builder/kalkulasi/percabangan bisnis, maka logic tersebut WAJIB dipindah ke Action/Service.

### 3.2 Service / Action Layer
- Gunakan **Action** untuk satu use-case spesifik, misalnya:
  - `CreateTransactionAction`
  - `ApproveWithdrawalAction`
- Gunakan **Service** untuk business flow yang lebih besar atau reusable.
- Terapkan **Single Responsibility Principle** pada class dan method.

### 3.3 Repository Pattern
- Repository pattern **hanya wajib** digunakan jika salah satu kondisi berikut terpenuhi:
  - query yang sama dipakai di 2 modul/domain atau lebih, atau
  - query melibatkan join lebih dari 3 tabel atau logic filter/sort kompleks yang reusable.
- Di luar kondisi tersebut, cukup gunakan Eloquent langsung di dalam Service/Action. Jangan menambah abstraction yang tidak perlu.

---

## 4. Request Validation & Authorization
### 4.1 Form Requests
- Jangan melakukan validasi di controller.
- Selalu gunakan `FormRequest` untuk validasi input user.
- Controller tidak boleh memanggil `$request->validate([...])` secara inline.

### 4.2 Authorization
- Gunakan Laravel Policies atau Gates untuk memeriksa hak akses sebelum memproses aksi.
- Untuk endpoint sensitif, authorization harus diuji di test.

### 4.3 Mass Assignment
- Setiap model harus memiliki `$fillable` atau `$guarded` yang jelas dan aman.

### 4.4 Input & Query Safety
- Semua raw query (`DB::raw`, `whereRaw`, dsb) WAJIB menggunakan parameter binding, tidak boleh concatenate input user langsung ke string query.
- Semua output yang dirender ke HTML/Blade WAJIB di-escape (`{{ }}`), hindari `{!! !!}` untuk data yang berasal dari input user kecuali sudah disanitasi.
- Endpoint publik/berisiko tinggi (login, register, forgot password, webhook) WAJIB menggunakan rate limiting (`throttle` middleware).

---

## 5. Coding Standards & Best Practices
- Gunakan strict typing, type-hint parameter, dan return type bila memungkinkan.
- Ikuti PSR-12.
- Gunakan penamaan:
  - method / variable: `camelCase`
  - class: `StudlyCaps`
- Hindari magic string / magic number dalam business logic.
  Gunakan:
  - config
  - enum (native PHP enum, lihat section 0)
  - constant class
- Gunakan early return dan clean code pattern untuk mengurangi nested code berlebihan.

---

## 6. Database & Migrations
### 6.1 Migration Rules
- Jika migration sudah pernah dipakai di production, jangan edit file migration lama.
- Buat migration baru untuk alter table / perubahan schema.
- Setiap migration WAJIB memiliki method `down()` yang benar-benar reversible (bukan kosong), kecuali secara eksplisit tidak memungkinkan (sebutkan alasannya di komentar).

### 6.2 Relational Integrity
- Gunakan foreign key jika relasi memang ada.
- Tambahkan index untuk:
  - foreign key
  - kolom pencarian / filter / sort yang sering dipakai
- Tentukan `cascadeOnDelete` / `cascadeOnUpdate` secara sadar sesuai kebutuhan domain.

### 6.3 Transactional Integrity
- Gunakan `DB::transaction()` untuk proses yang mengubah beberapa data penting dalam satu flow.
- Contoh:
  - payment settlement
  - invoice + invoice items
  - wallet topup / ledger update
  - proses checkout yang menyentuh beberapa tabel

---

## 7. Error Handling & Logging
- Gunakan try-catch untuk proses kritikal seperti:
  - transaksi database yang kompleks
  - integrasi 3rd party API
  - webhook / callback / payment flow
- Setiap error penting harus dicatat menggunakan logging yang jelas.
- Jangan swallow exception tanpa logging atau handling yang jelas.
- Hindari expose stack trace / internal error detail ke response production.

### 7.1 Sensitive Data in Logs
- Dilarang menulis data sensitif ke log dalam bentuk plain text, termasuk namun tidak terbatas pada: password, token/API key, nomor kartu pembayaran, OTP, dan data pribadi pelanggan (NIK, nomor HP, email) kecuali benar-benar diperlukan untuk debugging dan sudah dimasking sebagian (mis. `****1234`).
- Payload request/response yang dilog untuk keperluan audit integrasi pihak ketiga (section 10.2) harus melalui proses redaksi field sensitif terlebih dahulu.

### 7.2 File Upload Handling
- Setiap fitur upload file (foto, dokumen, dsb) WAJIB memvalidasi:
  - mime type yang diizinkan
  - ukuran maksimal file
  - nama file (hindari path traversal, gunakan nama ter-generate/hash)
- Simpan file di disk/storage sesuai konfigurasi proyek (`public`, `s3`, dsb), jangan hardcode path absolut.

---

## 8. API Response Rules
- Gunakan Laravel API Resources untuk response sukses.
- Jangan return raw model langsung dari controller.
- Gunakan format response yang konsisten untuk:
  - success
  - validation error
  - unauthorized / forbidden
  - not found
  - server error
- Untuk API publik/eksternal, gunakan versioning pada route (`/api/v1/...`). Perubahan yang breaking terhadap contract yang sudah dipakai wajib masuk versi baru, bukan mengubah versi yang sudah live.

---

## 9. Performance & Optimization
### 9.1 Query Performance
- Cegah N+1 query dengan eager loading (`with()`).
- Untuk endpoint list, gunakan pagination bila data bisa membesar.
- Hindari query di dalam loop jika bisa dihindari.

### 9.2 Background Jobs
- Tugas berat / lambat wajib dipindah ke queue/job, contohnya:
  - kirim email
  - generate report / PDF / Excel
  - sinkronisasi data eksternal
  - proses notifikasi massal
- Setiap job WAJIB mendefinisikan strategi retry (`$tries`, `backoff()`) yang masuk akal sesuai jenis proses.
- Job yang gagal permanen (masuk `failed_jobs`) untuk proses kritikal (mis. payment, notifikasi penting) harus punya mekanisme notifikasi/alert ke tim, bukan dibiarkan diam-diam.

---

## 10. Critical Flow Rules
### 10.1 Idempotency
- Endpoint webhook, callback payment, dan proses finansial harus dirancang idempotent.
- Jangan sampai callback / event yang dikirim ulang memicu efek bisnis ganda.

### 10.2 External Integrations
- Untuk integrasi pihak ketiga:
  - simpan log request/response penting jika relevan (dengan redaksi data sensitif, lihat 7.1)
  - validasi payload masuk
  - tangani retry/failure dengan aman jika flow bersifat kritikal

---

## 11. Output Expectation for AI
Saat mengerjakan task backend, AI harus:
1. Mengidentifikasi file yang perlu dibuat/diubah.
2. Memilih apakah perlu `Feature Test`, `Unit Test`, atau keduanya.
3. Menggunakan `FormRequest`, `Resource`, `Policy`, `Service/Action` sesuai kebutuhan.
4. Menjaga controller tetap tipis (lihat definisi di 3.1).
5. Menyebutkan test apa yang ditambahkan.
6. Memastikan tidak ada data sensitif yang ter-log/ter-expose (lihat 7.1).
7. Menjalankan/menyebutkan hasil code style check (Pint) karena ini adalah tool standar yang ada di proyek.
8. Menjelaskan jika ada bagian yang belum bisa diuji dan alasannya.
9. Setiap kali melakukan perubahan fitur, perbaikan bug, atau pembaruan sistem lainnya, WAJIB mendokumentasikan perubahan tersebut ke dalam file `CHANGELOG.md` yang relevan.