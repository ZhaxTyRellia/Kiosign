<div align="center">

<img src="https://cdn.gautavaris.web.id/Master/Logos/Kiosign.png" alt="Kiosign Logo" width="140" />

<h1>&#9876;&#65039; KIOSIGN &#9876;&#65039;</h1>
<p><em>Digital Information Display &bull; Dark Gothic Edition</em></p>

<p>
  <img src="https://img.shields.io/badge/PHP-8.0%2B-1a1625?style=for-the-badge&logo=php&logoColor=c084fc&labelColor=0b0910" alt="PHP 8.0+" />
  <img src="https://img.shields.io/badge/SQLite-Embedded-1a1625?style=for-the-badge&logo=sqlite&logoColor=e11d48&labelColor=0b0910" alt="SQLite" />
  <img src="https://img.shields.io/badge/Tailwind_CSS-UI-1a1625?style=for-the-badge&logo=tailwindcss&logoColor=38bdf8&labelColor=0b0910" alt="Tailwind CSS" />
  <img src="https://img.shields.io/badge/License-Free_%26_Anti--Resale-7f1d1d?style=for-the-badge&labelColor=0b0910" alt="Free & Anti-Resale License" />
</p>

<p>
  <strong>Kiosign</strong> adalah aplikasi <em>digital signage</em> berbasis web (PHP + SQLite + Tailwind CSS) yang ringan dan siap pakai untuk menampilkan pengumuman, informasi layanan, media gambar/video, hingga portal web interaktif secara <em>real-time</em> di layar TV, monitor, maupun kiosk.
</p>

</div>

<hr />

<h2>&#128367;&#65039; Fitur Utama</h2>

<table>
  <thead>
    <tr>
      <th align="left">Fitur</th>
      <th align="left">Deskripsi</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>Multi-Tipe Konten Slide</strong></td>
      <td>Mendukung slide <strong>Image</strong> (judul, subjudul, tag, badge, spesifikasi/poin, warna aksen kustom, label aksi), <strong>Video</strong> (<em>autoplay</em>, loop, muted), dan <strong>Offline / Embedded Web</strong> (<code>offline_web</code> via <em>iframe</em> + <em>Fullscreen Modal</em>).</td>
    </tr>
    <tr>
      <td><strong>Live Sync Otomatis</strong></td>
      <td>Layar display melakukan polling ke <code>api.php</code> setiap <strong>3 detik</strong> menggunakan hash versi MD5. Jika ada 1 slide atau auto-slide sedang pause, update langsung diterapkan; jika multi-slide aktif, update diantrekan dan diterapkan pada siklus berikutnya.</td>
    </tr>
    <tr>
      <td><strong>BGM Playlist &amp; Audio Engine</strong></td>
      <td>Mendukung pemutaran audio latar multi-track dari file lokal (<code>asset/audio/</code>) maupun URL eksternal, kontrol volume bawaan (<code>default_audio_volume</code>), auto-next track, serta <em>autoplay unlock</em> saat interaksi pengguna.</td>
    </tr>
    <tr>
      <td><strong>Jam &amp; Tanggal Multi-Bahasa</strong></td>
      <td>Tampilan jam digital real-time dan tanggal dengan 10 pilihan bahasa bawaan: <code>en</code>, <code>id</code>, <code>ms</code>, <code>ar</code>, <code>ja</code>, <code>ko</code>, <code>zh</code>, <code>fr</code>, <code>de</code>, <code>es</code>.</td>
    </tr>
    <tr>
      <td><strong>Identitas Display Kustom</strong></td>
      <td>Atur judul situs, badge status (<code>LIVE</code>), nama instansi/perusahaan, logo header kustom atau logo SVG bawaan Kiosign, serta ID perangkat via URL (<code>?id=NAMA-DEVICE</code>).</td>
    </tr>
    <tr>
      <td><strong>Admin Panel Terproteksi</strong></td>
      <td>Login <strong>khusus password</strong> (tanpa username) dengan proteksi CSRF, <em>session regeneration</em>, dan <em>rate limiting</em> (<code>db/guard.php</code>), manajemen slide, galeri media terpadu, pengaturan display, ganti password, dan reset ke default.</td>
    </tr>
    <tr>
      <td><strong>Database SQLite Otomatis</strong></td>
      <td>Tanpa perlu setup MySQL/MariaDB &mdash; <code>db/signage.db</code> dan <code>db/guard.db</code> dibuat serta di-<em>seed</em> otomatis saat pertama kali dijalankan.</td>
    </tr>
  </tbody>
</table>

<hr />

<h2>&#127984; Struktur Proyek</h2>

<pre><code>Kiosign/
├── index.php                  # Layar utama Digital Signage (display stage, header, slider, footer, modal)
├── api.php                    # Endpoint JSON Live-Sync (version, data, check) dengan validasi token
├── LICENSE                    # Lisensi Kiosign Free &amp; Anti-Resale (Share-Alike)
├── README.md                  # Dokumentasi proyek
├── .gitignore                 # Mengabaikan db/guard.db &amp; file sementara SQLite
├── panel/
│   └── index.php              # Admin Panel (Login, Content List, Media Gallery, Display Settings, System Info)
├── db/
│   ├── db.php                 # Koneksi PDO SQLite, CRUD slide/settings, version hash, ikon SVG &amp; locale map
│   ├── guard.php              # Rate limiter (IP hash), security headers, CSRF, API HMAC token, &amp; session auth
│   ├── seed.php               # Skema tabel &amp; data awal (settings, default slide, default password hash)
│   ├── signage.db             # Database utama konten &amp; pengaturan (auto-generated)
│   └── guard.db               # Database rate-limit runtime (auto-generated, diabaikan Git)
└── asset/
    ├── css/
    │   ├── style.css          # Animasi transisi slide, progress bar, dan equalizer
    │   └── tailwind.min.css   # Bundel CSS Tailwind lokal (offline-ready)
    ├── js/
    │   ├── app.js             # Engine slider, live polling, modal web, BGM player, jam &amp; kontrol input
    │   └── tailwind.js        # Runtime Tailwind lokal (offline-ready)
    └── audio/                 # Direktori penyimpanan audio latar (BGM)</code></pre>

<hr />

<h2>&#9889; Arsitektur Fungsi &amp; Endpoint</h2>

<details open>
  <summary><strong>1. Core Database &amp; Helper (<code>db/db.php</code> &amp; <code>db/seed.php</code>)</strong></summary>
  <table>
    <thead>
      <tr>
        <th align="left">Fungsi</th>
        <th align="left">Kegunaan</th>
      </tr>
    </thead>
    <tbody>
      <tr><td><code>get_db()</code></td><td>Membuka koneksi singleton <code>PDO</code> ke <code>db/signage.db</code> sekaligus menjalankan auto-seed dan migrasi kolom tabel <code>slides</code>.</td></tr>
      <tr><td><code>seed_database($pdo, $forceReset)</code></td><td>Membuat tabel <code>settings</code> &amp; <code>slides</code> serta mengisi konfigurasi awal, slide sambutan, dan hash password admin default.</td></tr>
      <tr><td><code>get_setting()</code> / <code>set_setting()</code></td><td>Membaca dan menyimpan pasangan kunci-nilai konfigurasi di tabel <code>settings</code> (<code>ON CONFLICT DO UPDATE</code>).</td></tr>
      <tr><td><code>get_all_settings()</code></td><td>Mengambil seluruh pengaturan publik untuk display/API (mengecualikan <code>admin_password_hash</code>).</td></tr>
      <tr><td><code>get_admin_password_hash()</code></td><td>Mengambil hash password admin dari database, atau menginisialisasi default jika belum tersedia.</td></tr>
      <tr><td><code>get_bgm_playlist()</code></td><td>Mengembalikan daftar URL/path audio latar dari <code>bgm_playlist</code> atau fallback ke <code>background_audio_url</code>.</td></tr>
      <tr><td><code>get_slides($activeOnly)</code> / <code>get_slide($id)</code></td><td>Mengambil daftar slide terurut (<code>sort_order ASC, id ASC</code>) atau satu slide berdasarkan ID beserta decode JSON <code>specs</code>.</td></tr>
      <tr><td><code>save_slide($data, $id)</code> / <code>delete_slide($id)</code></td><td>Menyimpan (insert/update) maupun menghapus slide di database.</td></tr>
      <tr><td><code>reset_to_seed()</code></td><td>Mereset ulang tabel <code>slides</code> dan <code>settings</code> ke kondisi awal pabrik.</td></tr>
      <tr><td><code>get_version_hash()</code></td><td>Menghasilkan hash MD5 dari seluruh slide aktif/nonaktif dan pengaturan publik untuk deteksi perubahan live-sync.</td></tr>
      <tr><td><code>get_kiosign_logo_svg()</code> / <code>get_icon_svg()</code></td><td>Menghasilkan logo SVG Kiosign, favicon data-URI (<code>get_kiosign_favicon_data_uri()</code>), dan ikon UI SVG inline.</td></tr>
      <tr><td><code>get_date_locale_maps()</code></td><td>Menyediakan peta nama hari &amp; bulan dalam 10 bahasa untuk jam/tanggal di display.</td></tr>
    </tbody>
  </table>
</details>

<details>
  <summary><strong>2. Security, Guard &amp; Auth (<code>db/guard.php</code>)</strong></summary>
  <table>
    <thead>
      <tr>
        <th align="left">Fungsi</th>
        <th align="left">Kegunaan</th>
      </tr>
    </thead>
    <tbody>
      <tr><td><code>ks_guard_request($zone, $maxHits, $windowSec, $blockSec)</code></td><td>Mengirim header keamanan HTTP, membatasi metode HTTP (<code>GET</code>/<code>POST</code>/<code>HEAD</code>) dan ukuran payload, serta menerapkan <em>rate limiting</em> berbasis SQLite (<code>guard.db</code>).</td></tr>
      <tr><td><code>ks_init_session()</code></td><td>Menginisialisasi sesi PHP (<code>HttpOnly</code>, <code>SameSite=Lax</code>) dan token CSRF <code>_ks_csrf</code>.</td></tr>
      <tr><td><code>ks_csrf_token()</code> / <code>ks_csrf_field()</code> / <code>ks_verify_csrf()</code></td><td>Membuat input hidden CSRF dan memvalidasinya dengan <code>hash_equals()</code> pada setiap request POST.</td></tr>
      <tr><td><code>ks_is_panel_authenticated()</code> / <code>ks_set_panel_authenticated()</code></td><td>Memeriksa dan mengatur status autentikasi panel admin disertai <code>session_regenerate_id(true)</code>.</td></tr>
      <tr><td><code>ks_api_token()</code> / <code>ks_verify_api_token()</code></td><td>Membuat dan memverifikasi token HMAC-SHA256 berbasis jendela waktu (1 jam) untuk endpoint <code>api.php</code>.</td></tr>
      <tr><td><code>ks_send_security_headers()</code></td><td>Mengirim <code>X-Content-Type-Options</code>, <code>X-Frame-Options</code>, <code>Referrer-Policy</code>, dan <code>X-XSS-Protection</code>.</td></tr>
    </tbody>
  </table>
</details>

<details>
  <summary><strong>3. Live Sync API (<code>api.php</code>)</strong></summary>
  <table>
    <thead>
      <tr>
        <th align="left">Parameter / Aksi</th>
        <th align="left">Respons JSON</th>
      </tr>
    </thead>
    <tbody>
      <tr><td><code>GET api.php?action=version</code></td><td>Mengembalikan <code>version</code> (hash MD5 saat ini) dan <code>token</code> HMAC berikutnya.</td></tr>
      <tr><td><code>GET api.php?action=data</code></td><td>Mengembalikan payload penuh: <code>version</code>, <code>token</code>, <code>settings</code>, <code>bgm_playlist</code>, dan <code>slides</code> aktif.</td></tr>
      <tr><td><code>GET api.php?action=check&amp;version=...</code></td><td>Membandingkan versi klien dengan server; mengembalikan <code>changed: false</code> jika sama, atau payload penuh dengan <code>changed: true</code> jika ada pembaruan.</td></tr>
    </tbody>
  </table>
  <p><em>Setiap permintaan non-CLI wajib menyertakan header <code>X-KS-Token</code> (atau query <code>_sig</code>) dan dibatasi 40 request per 10 detik.</em></p>
</details>

<details>
  <summary><strong>4. Frontend Display Engine (<code>asset/js/app.js</code>)</strong></summary>
  <table>
    <thead>
      <tr>
        <th align="left">Fungsi</th>
        <th align="left">Kegunaan</th>
      </tr>
    </thead>
    <tbody>
      <tr><td><code>checkLiveUpdates()</code> &amp; <code>applyLiveUpdate()</code></td><td>Polling <code>api.php</code> setiap 3 detik, menerapkan perubahan tema/header/BGM/slide secara dinamis, dan menampilkan notifikasi <code>showSyncToast()</code>.</td></tr>
      <tr><td><code>renderSlides()</code> &amp; <code>showSlide()</code></td><td>Merender ulang DOM slide &amp; indikator titik serta mengatur transisi aktif dan <code>resetProgress()</code>.</td></tr>
      <tr><td><code>nextSlide()</code>, <code>prevSlide()</code>, <code>goToSlide()</code>, <code>toggleAutoSlide()</code></td><td>Navigasi slide otomatis maupun manual serta kontrol jeda <em>auto-slide</em>.</td></tr>
      <tr><td><code>openOfflineWebModal()</code> &amp; <code>closeOfflineWebModal()</code></td><td>Membuka halaman web lokal/embedded dalam modal layar penuh dan melanjutkan slider saat ditutup.</td></tr>
      <tr><td><code>playTrack()</code>, <code>playNextTrack()</code>, <code>toggleAudio()</code>, <code>initAutoplay()</code></td><td>Pemutar BGM playlist berurutan, loop otomatis untuk 1 track, dan buka kunci <em>autoplay</em> browser.</td></tr>
      <tr><td><code>updateClock()</code></td><td>Memperbarui jam (<code>HH:MM:SS</code>) dan tanggal terlokalisasi setiap detik.</td></tr>
    </tbody>
  </table>
</details>

<details>
  <summary><strong>5. Aksi Admin Panel (<code>panel/index.php</code>)</strong></summary>
  <table>
    <thead>
      <tr>
        <th align="left">Aksi POST</th>
        <th align="left">Fungsi</th>
      </tr>
    </thead>
    <tbody>
      <tr><td><code>login</code> / <code>logout</code></td><td>Verifikasi password admin (dilindungi rate limiter <code>panel_login</code>) dan mengakhiri sesi.</td></tr>
      <tr><td><code>save_slide</code> / <code>delete_slide</code> / <code>toggle_slide</code></td><td>Tambah/edit konten slide, hapus slide, dan toggle status aktif/sembunyi.</td></tr>
      <tr><td><code>upload_media</code> / <code>unlink_media</code> / <code>add_to_playlist</code></td><td>Unggah gambar/video/audio dengan penomoran sekuensial otomatis, hapus file media, dan tambahkan audio ke playlist BGM.</td></tr>
      <tr><td><code>save_settings</code></td><td>Simpan pengaturan display, mode BGM (lokal/URL), durasi slide, bahasa tanggal, volume, dan ID perangkat.</td></tr>
      <tr><td><code>change_password</code> / <code>reset_db</code></td><td>Perbarui password admin (minimal 6 karakter) dan reset seluruh sistem ke pengaturan pabrik.</td></tr>
    </tbody>
  </table>
</details>

<hr />

<h2>&#9881;&#65039; Persyaratan Sistem</h2>

<ul>
  <li><strong>PHP 8.0+</strong> dengan ekstensi <code>pdo_sqlite</code> dan <code>sqlite3</code> aktif.</li>
  <li><strong>Web Server</strong>: Apache, Nginx, WampDock, XAMPP, Laragon, atau PHP built-in server.</li>
</ul>

<hr />

<h2>&#128220; Cara Penggunaan</h2>

<h3>1. Instalasi</h3>
<ol>
  <li>Clone atau salin folder proyek <code>Kiosign</code> ke direktori web server Anda (misalnya <code>htdocs/Kiosign</code> atau <code>host/Kiosign</code>).</li>
  <li>Pastikan folder <code>db/</code> dan <code>asset/</code> memiliki izin tulis (<em>write permission</em>) agar SQLite dan unggahan media dapat disimpan.</li>
  <li>Buka layar display melalui browser:
    <pre><code>http://localhost/Kiosign/</code></pre>
    Anda juga dapat menentukan ID layar melalui parameter URL:
    <pre><code>http://localhost/Kiosign/?id=LOBBY-01</code></pre>
  </li>
</ol>

<h3>2. Mengakses Admin Panel</h3>
<ol>
  <li>Buka panel admin melalui tombol <strong>Panel</strong> di pojok kanan bawah layar display, atau langsung akses:
    <pre><code>http://localhost/Kiosign/panel/</code></pre>
  </li>
  <li>Masukkan password default:
    <pre><code>kiosign123</code></pre>
  </li>
  <li><strong>Segera ubah password default</strong>: masuk ke tab <strong>System Information</strong> &rarr; bagian <strong>Admin Password</strong>, lalu simpan password baru Anda.</li>
</ol>

<h3>3. Mengelola Konten &amp; Pengaturan</h3>
<ul>
  <li><strong>Content List</strong>: Tambah slide baru (<em>Image</em>, <em>Video</em>, atau <em>Offline Web</em>), atur urutan (<code>sort_order</code>), serta aktifkan atau sembunyikan slide.</li>
  <li><strong>Media Gallery</strong>: Unggah file gambar (<code>jpg</code>, <code>jpeg</code>, <code>png</code>, <code>webp</code>, <code>gif</code>, <code>svg</code>), video (<code>mp4</code>, <code>webm</code>, <code>ogg</code>, <code>mov</code>, <code>mkv</code>, <code>avi</code>), dan audio (<code>mp3</code>, <code>wav</code>, <code>ogg</code>, <code>m4a</code>).</li>
  <li><strong>Display Settings</strong>: Atur judul situs, badge header, nama perusahaan, logo header, durasi per slide (detik), <em>auto-slide</em>, bahasa tanggal, daftar putar BGM, volume audio, dan ID perangkat bawaan.</li>
</ul>

<h3>4. Kontrol Keyboard &amp; Sentuh pada Layar Display</h3>

<table>
  <thead>
    <tr>
      <th align="left">Tombol / Gestur</th>
      <th align="left">Fungsi</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><kbd>&rarr;</kbd> / <kbd>&larr;</kbd> atau <em>Swipe / Drag</em></td>
      <td>Pindah ke slide berikutnya / sebelumnya</td>
    </tr>
    <tr>
      <td><kbd>Space</kbd></td>
      <td>Pause / lanjutkan <em>auto-slide</em></td>
    </tr>
    <tr>
      <td><kbd>M</kbd></td>
      <td>Mute / unmute audio latar (BGM)</td>
    </tr>
    <tr>
      <td><kbd>F</kbd></td>
      <td>Toggle mode tampilan penuh (<code>force-full</code>)</td>
    </tr>
  </tbody>
</table>

<hr />

<h2>&#9878;&#65039; Hak Lisensi</h2>

<p>
  Proyek ini dirilis di bawah <strong>Kiosign Free &amp; Anti-Resale License (Share-Alike)</strong> &mdash; Copyright &copy; 2026 <strong>Gautavaris DigiSoft HUB</strong>.
</p>

<blockquote>
  Lihat teks lisensi lengkap pada file <a href="LICENSE"><code>LICENSE</code></a>.
</blockquote>

<ul>
  <li><strong>Gratis untuk Semua</strong>: Siapa pun bebas menginstal, menjalankan, dan menggunakan Kiosign secara gratis.</li>
  <li><strong>Boleh Disebar &amp; Dimodifikasi (Share-Alike)</strong>: Boleh menyalin, memodifikasi, dan mendistribusikan ulang selama tetap menyertakan kredit <strong>Gautavaris DigiSoft HUB</strong>, mempertahankan file <code>LICENSE</code>, dan hasil modifikasi tetap gratis dengan lisensi yang sama.</li>
  <li><strong>Dilarang Keras Diperjualbelikan (Anti-Resale)</strong>: Dilarang menjual, menyewakan, melisensikan berbayar, atau mengomersialkan ulang software/framework ini &mdash; baik dalam bentuk asli maupun setelah dimodifikasi atau di-<em>rebrand</em>.</li>
  <li><strong>Komponen Pihak Ketiga</strong>: Library pihak ketiga bawaan (seperti Tailwind CSS) tetap tunduk pada lisensinya masing-masing (MIT).</li>
</ul>

<div align="center">
  <hr />
  <p><sub>&#9876;&#65039; Crafted by <strong>Gautavaris DigiSoft HUB</strong> &bull; Free Forever, Anti-Resale &#9876;&#65039;</sub></p>
</div>
