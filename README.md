# Kiosign — Digital Information Display

**Kiosign** adalah aplikasi *digital signage* berbasis web (PHP + SQLite + Tailwind CSS) yang ringan dan siap pakai untuk menampilkan pengumuman, informasi layanan, media gambar/video, hingga portal web interaktif secara *real-time* di layar TV, monitor, maupun kiosk.

---

## Fitur Utama

- **Multi-Tipe Konten Slide**:
  - **Image**: Gambar dengan teks judul, subjudul, tag, badge, spesifikasi/poin info, dan warna aksen kustom.
  - **Video**: Pemutaran video *autoplay* (loop & muted) layar penuh.
  - **Offline / Embedded Web (`offline_web`)**: Menampilkan halaman web/portal lokal di dalam *iframe* lengkap dengan mode *Fullscreen Modal*.
- **Live Sync Otomatis**: Layar display mengecek pembaruan ke `api.php` setiap **3 detik** dan menerapkan perubahan konten maupun pengaturan tanpa perlu *refresh* manual.
- **Background Music (BGM) Playlist**: Mendukung pemutaran audio latar multi-track dari file lokal (`asset/audio/`) maupun URL eksternal, lengkap dengan pengaturan volume bawaan.
- **Jam & Tanggal Real-Time**: Tampilan jam digital langsung dan tanggal dengan dukungan pilihan bahasa (`date_locale`).
- **Identitas Display Kustom**: Atur judul halaman, badge status (`LIVE`), nama instansi/perusahaan, logo header, serta ID perangkat (`?id=NAMA-DEVICE`).
- **Admin Panel Terproteksi Password**:
  - Login **khusus password** (tanpa username) dengan proteksi CSRF dan *rate limiting* (`db/guard.php`).
  - Manajemen konten slide (tambah, edit, urutkan, aktif/nonaktifkan, hapus).
  - Galeri media terpadu untuk unggah dan kelola gambar, video, serta audio.
  - Pengaturan tampilan, ganti password admin, dan reset ke pengaturan awal (*factory defaults*).
- **Database SQLite Otomatis**: Tanpa perlu setup MySQL/MariaDB—`db/signage.db` dan `db/guard.db` dibuat otomatis saat pertama kali dijalankan.

---

## Persyaratan Sistem

- **PHP 8.0+** dengan ekstensi `pdo_sqlite` dan `sqlite3` aktif
- Web server (Apache, Nginx, WampDock, XAMPP, Laragon, atau PHP built-in server)

---

## Cara Penggunaan

### 1. Instalasi
1. Clone atau salin folder proyek `Kiosign` ke direktori web server Anda (misalnya `htdocs/Kiosign` atau `host/Kiosign`).
2. Pastikan folder `db/` dan `asset/` memiliki izin tulis (*write permission*) agar SQLite dan unggahan media dapat disimpan.
3. Buka layar display melalui browser:
   ```text
   http://localhost/Kiosign/
   ```
   Anda juga dapat menentukan ID layar melalui parameter URL, misalnya:
   ```text
   http://localhost/Kiosign/?id=LOBBY-01
   ```

### 2. Mengakses Admin Panel
1. Buka panel admin melalui tombol **Panel** di pojok kanan bawah layar display, atau langsung akses:
   ```text
   http://localhost/Kiosign/panel/
   ```
2. Masukkan password default:
   ```text
   kiosign123
   ```
3. **Segera ubah password default**: masuk ke tab **System Information** → bagian **Admin Password**, lalu masukkan password baru Anda.

### 3. Mengelola Konten & Pengaturan
- **Content List**: Tambah slide baru (*Image*, *Video*, atau *Offline Web*), atur urutan (`sort_order`), serta aktifkan/sembunyikan slide.
- **Media Gallery**: Unggah file gambar (`jpg`, `jpeg`, `png`, `webp`, `gif`, `svg`), video (`mp4`, `webm`, `ogg`, `mov`, `mkv`, `avi`), dan audio (`mp3`, `wav`, `ogg`, `m4a`).
- **Display Settings**: Atur judul situs, nama perusahaan, logo header, durasi per slide (detik), *auto-slide*, bahasa tanggal, daftar putar BGM, volume audio, dan ID perangkat bawaan.

### 4. Kontrol Keyboard & Sentuh pada Layar Display
- `Panah Kanan` / `Panah Kiri` (atau *swipe* / *drag*): Pindah ke slide berikutnya / sebelumnya.
- `Spasi`: Pause / lanjutkan *auto-slide*.
- `M`: Mute / unmute audio latar (BGM).
- `F`: Toggle mode tampilan penuh (`force-full`).

---

## Hak Lisensi

Proyek ini dirilis di bawah **Kiosign Free & Anti-Resale License (Share-Alike)** — Copyright (c) 2026 **Gautavaris DigiSoft HUB**.

Ringkasan ketentuan lisensi (lihat teks lengkap di file [LICENSE](LICENSE)):
- **Gratis untuk Semua**: Siapa pun bebas menginstal, menjalankan, dan menggunakan Kiosign secara gratis.
- **Boleh Disebar & Dimodifikasi (Share-Alike)**: Boleh menyalin, memodifikasi, dan mendistribusikan ulang selama tetap menyertakan kredit **Gautavaris DigiSoft HUB**, mempertahankan file `LICENSE`, dan hasil modifikasi tetap gratis dengan lisensi yang sama.
- **Dilarang Keras Diperjualbelikan (Anti-Resale)**: Dilarang menjual, menyewakan, melisensikan berbayar, atau mengomersialkan ulang software/framework ini—baik dalam bentuk asli maupun setelah dimodifikasi atau di-*rebrand*.
- **Komponen Pihak Ketiga**: Library pihak ketiga bawaan (seperti Tailwind CSS) tetap tunduk pada lisensinya masing-masing (MIT).
