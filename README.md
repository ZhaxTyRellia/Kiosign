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
      <td>Mendukung slide <strong>Image</strong> (judul, subjudul, tag, badge, spesifikasi, warna aksen kustom), <strong>Video</strong> (<em>autoplay</em>, loop, muted), dan <strong>Offline / Embedded Web</strong> (<code>offline_web</code> via <em>iframe</em> + <em>Fullscreen Modal</em>).</td>
    </tr>
    <tr>
      <td><strong>Live Sync Otomatis</strong></td>
      <td>Layar display melakukan polling ke <code>api.php</code> setiap <strong>3 detik</strong> dan menerapkan pembaruan konten maupun pengaturan tanpa perlu <em>refresh</em> manual.</td>
    </tr>
    <tr>
      <td><strong>BGM Playlist</strong></td>
      <td>Mendukung pemutaran audio latar multi-track dari file lokal (<code>asset/audio/</code>) maupun URL eksternal, lengkap dengan pengaturan volume bawaan.</td>
    </tr>
    <tr>
      <td><strong>Jam &amp; Tanggal Real-Time</strong></td>
      <td>Tampilan jam digital langsung dan tanggal otomatis dengan dukungan pilihan bahasa (<code>date_locale</code>).</td>
    </tr>
    <tr>
      <td><strong>Identitas Display Kustom</strong></td>
      <td>Atur judul halaman, badge status (<code>LIVE</code>), nama instansi/perusahaan, logo header, serta ID perangkat via URL (<code>?id=NAMA-DEVICE</code>).</td>
    </tr>
    <tr>
      <td><strong>Admin Panel Terproteksi</strong></td>
      <td>Login <strong>khusus password</strong> (tanpa username) dengan proteksi CSRF dan <em>rate limiting</em> (<code>db/guard.php</code>), galeri media terpadu, serta manajemen slide dan pengaturan.</td>
    </tr>
    <tr>
      <td><strong>Database SQLite Otomatis</strong></td>
      <td>Tanpa perlu setup MySQL/MariaDB &mdash; <code>db/signage.db</code> dan <code>db/guard.db</code> dibuat otomatis saat pertama kali dijalankan.</td>
    </tr>
  </tbody>
</table>

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
  <li><strong>Display Settings</strong>: Atur judul situs, nama perusahaan, logo header, durasi per slide (detik), <em>auto-slide</em>, bahasa tanggal, daftar putar BGM, volume audio, dan ID perangkat bawaan.</li>
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
