# LENTERA LMS

**Learning Technology for Education and Academic Resources**

LENTERA adalah aplikasi **Learning Management System (LMS) berbasis web** yang dikembangkan untuk mendukung penerapan **blended learning** pada SMP Negeri 1 Wewewa Timur.

Aplikasi ini dikembangkan sebagai bagian dari penelitian skripsi:

> **Penerapan Model Blended Learning Berbasis Digital untuk Meningkatkan Kualitas Pembelajaran pada SMP Negeri 1 Wewewa Timur Menggunakan Metode Kualitatif**

LENTERA dirancang untuk mempertemukan proses pembelajaran tatap muka dengan aktivitas pembelajaran digital melalui materi pembelajaran, tugas, penilaian, dan evaluasi.

---

## Teknologi

| Komponen | Teknologi |
|---|---|
| Framework | CodeIgniter 4.7.4 |
| Bahasa | PHP 8.2+ |
| Database | MySQL 8 |
| Frontend | Bootstrap 5 |
| Admin Template | AdminLTE 4 |
| Arsitektur | MVC |
| Authentication | Session-based Authentication |
| Web Server | Apache / Nginx |
| Development | Localhost / PHP Development Server |

---

## Role Pengguna

LENTERA memiliki tiga role utama:

### Admin

Admin bertanggung jawab terhadap pengelolaan data utama aplikasi, antara lain:

- Guru
- Siswa
- Tahun Akademik
- Kelas
- Mata Pelajaran
- Course
- Peserta Course

### Guru

Guru dapat:

- Melihat dashboard pembelajaran
- Mengelola course yang diajar
- Membuat dan mengelola materi pembelajaran
- Publish/unpublish materi
- Melihat aktivitas materi siswa
- Membuat tugas
- Publish/unpublish tugas
- Melihat pengumpulan tugas
- Memberikan nilai
- Memberikan feedback
- Membuat dan mengelola evaluasi/quiz

### Siswa

Siswa dapat:

- Melihat dashboard pembelajaran
- Melihat kelas/course yang diikuti
- Mengakses materi pembelajaran
- Melihat status materi sudah/belum dibaca
- Mengumpulkan tugas
- Melihat nilai dan feedback
- Mengikuti quiz/evaluasi
- Melihat perkembangan pembelajaran

---

# Fitur Aplikasi

## 1. Authentication & Authorization

LENTERA menyediakan autentikasi dan pembatasan akses berdasarkan role.

Fitur meliputi:

- Login
- Logout
- Session management
- Role-based access
- Proteksi halaman berdasarkan role
- CSRF protection
- Login throttling
- Account activation/deactivation
- Proteksi setelah logout
- Validasi akses server-side

---

## 2. Master Data Akademik

Admin dapat mengelola:

- Tahun Akademik
- Guru
- Siswa
- Kelas
- Mata Pelajaran
- Course

Termasuk validasi relasi antar data dan perlindungan terhadap penghapusan data yang masih digunakan.

---

# Phase 1 — Foundation & Master Data

Phase 1 telah selesai.

Fitur yang telah tersedia:

- Authentication
- Authorization
- Dashboard Admin
- Dashboard Guru
- Dashboard Siswa
- Master Guru
- Master Siswa
- Master Kelas
- Master Tahun Akademik
- Master Mata Pelajaran
- Course
- Peserta Course
- Validasi relasi data
- CSRF protection
- XSS protection
- Role access protection
- Security testing

---

# Phase 2 — Materi Pembelajaran

Phase 2 telah selesai.

Guru dapat:

- Membuat materi
- Mengedit materi
- Menghapus materi
- Publish materi
- Unpublish materi
- Menambahkan deskripsi
- Menambahkan konten pembelajaran
- Mengunggah file materi
- Menambahkan video YouTube

Siswa dapat:

- Melihat materi yang telah dipublish
- Membaca materi
- Mengakses video pembelajaran
- Mengunduh file yang diizinkan
- Melihat status materi

Sistem juga mencatat:

- Materi yang telah dibuka siswa
- Waktu terakhir materi dilihat
- Jumlah siswa yang telah melihat materi
- Persentase progress materi

File pembelajaran disimpan pada storage private dan tidak dapat diakses langsung melalui URL publik.

---

# Phase 3 — Tugas & Penilaian

Phase 3 telah selesai.

Guru dapat:

- Membuat tugas
- Mengedit tugas
- Menghapus tugas
- Publish/unpublish tugas
- Menentukan deadline
- Melihat pengumpulan siswa
- Memberikan nilai
- Memberikan feedback

Siswa dapat:

- Melihat tugas yang dipublish
- Membaca instruksi tugas
- Mengirim jawaban
- Melakukan resubmit sebelum deadline
- Melihat nilai
- Melihat feedback guru

Aturan pengumpulan:

- Jika tidak terdapat deadline, pengumpulan tetap terbuka.
- Jika deadline telah lewat, siswa tidak dapat mengirim atau mengubah jawaban.
- Jika tugas telah dinilai, submission tidak dapat diubah.
- Satu siswa memiliki satu submission untuk satu tugas.

File tugas dan file jawaban disimpan secara private dan diakses melalui controller dengan authorization.

---

# Phase 4 — Quiz / Evaluasi

Phase 4 merupakan pengembangan evaluasi pembelajaran berbasis quiz.

Rencana fitur:

- Quiz pada course
- Pertanyaan pilihan ganda
- Pilihan jawaban
- Kunci jawaban
- Bobot nilai
- Waktu mulai
- Waktu selesai
- Durasi pengerjaan
- Attempt siswa
- Penghitungan nilai otomatis
- Hasil evaluasi
- Rekap nilai siswa

Pada tahap awal, quiz menggunakan:

- Multiple choice
- Satu jawaban benar
- Tidak ada nilai negatif
- Nilai dihitung otomatis
- Satu siswa memiliki satu attempt
- Tidak ada retake

---

# Arsitektur Aplikasi

LENTERA menggunakan pola **Model-View-Controller (MVC)** CodeIgniter 4.

Struktur utama:

```text
app/
├── Controllers/
│   ├── Admin/
│   ├── Guru/
│   └── Siswa/
│
├── Models/
│
├── Views/
│   ├── admin/
│   ├── guru/
│   ├── siswa/
│   └── layouts/
│
├── Database/
│   └── Migrations/
│
├── Helpers/
│
└── Config/
```

---

# Struktur Database

Database utama terdiri dari beberapa kelompok data.

### Pengguna

- users
- teachers
- students

### Akademik

- academic_years
- classes
- subjects
- courses
- course_students

### Materi

- materials
- material_views

### Tugas

- assignments
- submissions

### Evaluasi

- quizzes
- questions
- quiz_options
- quiz_attempts
- quiz_answers

Relasi antar tabel menggunakan foreign key dan constraint untuk menjaga integritas data.

---

# Keamanan

LENTERA memperhatikan keamanan aplikasi pada level aplikasi dan database.

Implementasi meliputi:

- CSRF Protection
- Server-side authorization
- Role-based access control
- Input validation
- XSS protection
- Login throttling
- Session regeneration
- Private file storage
- Secure file naming
- File extension validation
- MIME validation
- Pembatasan ukuran file
- Larangan upload executable/PHP
- Protected file download
- Proteksi akses course
- Proteksi akses materi
- Proteksi akses tugas
- Proteksi akses submission
- Validasi ID
- HTTP method restriction

File pembelajaran, attachment tugas, dan file jawaban siswa tidak disimpan sebagai file publik yang dapat diakses langsung.

---

# Pengujian

Pengembangan LENTERA dilakukan secara bertahap dengan pengujian pada setiap phase.

Pengujian mencakup:

### Functional Testing

- Login
- Logout
- CRUD master data
- Course
- Materi
- Tugas
- Submission
- Penilaian
- Quiz

### Authorization Testing

Memastikan:

- Admin hanya mengakses area admin.
- Guru hanya mengelola course miliknya.
- Siswa hanya mengakses course yang diikutinya.
- Siswa tidak dapat mengakses materi draft.
- Siswa tidak dapat mengakses tugas yang tidak menjadi haknya.
- User tidak dapat mengakses data user lain melalui ID secara langsung.

### Security Testing

Meliputi:

- CSRF
- XSS
- ID manipulation
- Unauthorized access
- File upload security
- Private file access
- Session security
- HTTP method restriction

---

# Instalasi

## Persyaratan

Minimal:

- PHP 8.2 atau lebih tinggi
- MySQL 8
- Composer
- Extension PHP `intl`
- Extension PHP `mbstring`
- Extension PHP `json`
- Extension PHP `mysqlnd`
- Extension PHP `curl`

PHP 8.4 direkomendasikan untuk lingkungan pengembangan dan deployment saat ini.

---

## Clone Project

```bash
git clone <repository-url>
cd LENTERA
```

Install dependency:

```bash
composer install
```

---

## Konfigurasi Environment

Salin file environment:

```bash
cp env .env
```

Pada Windows dapat dilakukan dengan menyalin file `env` menjadi `.env`.

Kemudian sesuaikan:

```dotenv
CI_ENVIRONMENT = development

app.baseURL = 'http://localhost:8080'

database.default.hostname = localhost
database.default.database = lentera
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.DBPrefix =
database.default.port = 3306
```

Sesuaikan konfigurasi database dengan lingkungan masing-masing.

---

# Database Migration

Buat database MySQL:

```sql
CREATE DATABASE lentera;
```

Jalankan migration:

```bash
php spark migrate
```

Untuk melihat status migration:

```bash
php spark migrate:status
```

---

# Menjalankan Aplikasi

Gunakan development server CodeIgniter:

```bash
php spark serve
```

Kemudian buka:

```text
http://localhost:8080
```

Untuk deployment production, web server harus diarahkan ke:

```text
/public
```

dan **bukan ke root project**.

---

# Struktur Penyimpanan File

File pembelajaran dan file tugas menggunakan storage private.

Contoh:

```text
writable/
└── uploads/
    ├── materials/
    └── assignments/
```

File tidak boleh diakses langsung melalui URL publik.

Akses file dilakukan melalui controller setelah proses authorization.

---

# Prinsip Pengembangan

Pengembangan LENTERA menggunakan prinsip:

1. **Security first**
2. **Server-side authorization**
3. **Validasi data**
4. **Integritas database**
5. **Tidak mengubah fitur yang sudah stabil tanpa alasan**
6. **Pengembangan bertahap per phase**
7. **Testing setelah setiap phase**
8. **UI sederhana dan mudah digunakan**
9. **Fokus pada kebutuhan pembelajaran**
10. **Tidak membangun fitur akademik yang tidak diperlukan LMS**

---

# Tujuan Pengembangan

LENTERA dikembangkan sebagai media pendukung pembelajaran blended learning.

Sistem tidak dimaksudkan untuk menggantikan seluruh proses pembelajaran tatap muka, tetapi untuk melengkapi pembelajaran melalui aktivitas digital seperti:

```text
Guru
  │
  ├── Materi
  │
  ├── Tugas
  │
  └── Evaluasi
          │
          ▼
       Siswa
          │
          ├── Belajar
          ├── Mengerjakan
          ├── Mengumpulkan
          └── Mengikuti Evaluasi
                  │
                  ▼
              Feedback
                  │
                  ▼
            Perkembangan
```

Dengan demikian, LENTERA mendukung integrasi antara aktivitas pembelajaran **tatap muka dan pembelajaran berbasis digital**.

---

# Status Pengembangan

| Phase | Modul | Status |
|---|---|---|
| Phase 1 | Foundation & Master Data | ✅ Selesai |
| Phase 2 | Materi Pembelajaran | ✅ Selesai |
| Phase 3 | Tugas & Penilaian | ✅ Selesai |
| Phase 4 | Quiz / Evaluasi | 🔄 Berjalan |
| Phase 5 | Diskusi & Pengumuman | ⏳ Direncanakan |
| Phase 6 | Progress & Activity | ⏳ Direncanakan |
| Phase 7 | Final Testing | ⏳ Direncanakan |

---

# Lisensi

Project ini dikembangkan untuk kebutuhan penelitian dan implementasi pembelajaran di lingkungan:

**SMP Negeri 1 Wewewa Timur**  
**Kabupaten Sumba Barat Daya, Nusa Tenggara Timur**

Penggunaan dan distribusi project mengikuti ketentuan pengembang dan institusi terkait.