<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Profil — EcoBank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="style.css?v=navbar-full-width-1" />
</head>

<body class="app-page user-tool-page" data-page="profile" data-account-role="{{ auth()->user()->role }}">
    <header class="app-header">
        <a class="brand"
            href="{{ auth()->user()->role === 'admin' ? 'dashboard_bank_sampah.html' : 'beranda_user.html' }}">EcoBank<span>.</span></a>
        <button class="menu-toggle" type="button" aria-label="Buka navigasi" aria-expanded="false">☰</button>
        <nav class="app-nav" aria-label="Navigasi utama">
            @if (auth()->user()->role === 'admin')
                <a href="dashboard_bank_sampah.html">Beranda</a>
                <a href="transaksi.html">Transaksi <span class="nav-count" data-pending-count>{{ $pendingTransactionCount }}</span></a>
                <a href="riwayat_transaksi.html">Riwayat</a>
                <a href="peringkat.html">Peringkat</a>
                <a href="listing_limbah_sampah.html">Layanan</a>
            @else
                <a href="beranda_user.html">Beranda</a>
                <a href="setor_sampah.html">Setor Sampah</a>
                <a href="riwayat.html">Riwayat</a>
                <a href="peringkat_user.html">Peringkat</a>
            @endif
        </nav>
        <div class="profile-control" data-profile-control>
            <button class="profile-button user-profile" type="button" aria-label="Buka menu akun" aria-haspopup="true"
                aria-expanded="false" aria-controls="profile-menu" data-profile-toggle>A</button>
            <div class="profile-dropdown" id="profile-menu" hidden>
                <strong data-profile-name>Memuat akun...</strong>
                @if (auth()->user()->role === 'admin')
                    <a href="profil.html" aria-current="page">Profil</a>
                    <a href="pengguna.html">Anggota</a>
                    <a href="penarikan_admin.html">Penarikan</a>
                @else
                    <a href="profil.html" aria-current="page">Profil</a>
                @endif
                <button type="button" data-profile-logout data-logout-target="auth.html">Keluar</button>
                <p data-profile-feedback role="status" hidden></p>
            </div>
        </div>
    </header>

    <main class="app-main profile-page-main" data-profile-page>
        <div class="page-intro profile-page-intro">
            <div>
                <p class="eyebrow eyebrow-green">Akun EcoBank</p>
                <h1>Profil</h1>
                @if (auth()->user()->role === 'admin')
                    <p>Kelola akun pengurus dan operasional bank sampah.</p>
                @else
                    <p>Kelola akun, setoran, dan aktivitas EcoBank kamu.</p>
                @endif
            </div>
            <a class="button button-secondary" href="{{ auth()->user()->role === 'admin' ? 'dashboard_bank_sampah.html' : 'beranda_user.html' }}">← Kembali ke beranda</a>
        </div>
        <p class="user-status profile-feedback" data-profile-status role="status" aria-live="polite" hidden></p>

        <div class="profile-dashboard-layout">
            <aside class="profile-side-card">
                <span class="profile-avatar-large" data-profile-avatar>A</span>
                <h2 data-profile-heading>Memuat profil...</h2>
                <p data-profile-email></p>
                <span class="profile-location"><span aria-hidden="true">⌖</span><span data-profile-district>Memuat
                        lokasi...</span></span>
                <span class="profile-role" data-profile-role></span>

                @if (auth()->user()->role === 'admin')
                    <dl class="profile-side-stats">
                        <div>
                            <dt>Total transaksi</dt>
                            <dd data-admin-total-transactions>—</dd>
                        </div>
                        <div>
                            <dt>Menunggu</dt>
                            <dd data-admin-pending>—</dd>
                        </div>
                        <div>
                            <dt>Berat disetujui</dt>
                            <dd><span data-admin-weight>—</span> kg</dd>
                        </div>
                    </dl>
                    <button class="button button-primary profile-edit-button" type="button" data-profile-edit-open>Edit
                        profil</button>
                    <nav class="profile-side-links" aria-label="Pintasan pengurus">
                        <a href="transaksi.html">Periksa setoran masuk <span aria-hidden="true">→</span></a>
                        <a href="pengguna.html">Kelola anggota <span aria-hidden="true">→</span></a>
                    </nav>
                @else
                    <dl class="profile-side-stats">
                        <div>
                            <dt>Saldo</dt>
                            <dd data-user-profile-balance>—</dd>
                        </div>
                        <div>
                            <dt>Total setoran</dt>
                            <dd><span data-user-profile-weight>—</span> kg</dd>
                        </div>
                        <div>
                            <dt>Peringkat bulan ini</dt>
                            <dd data-user-profile-rank>—</dd>
                        </div>
                    </dl>
                    <button class="button button-primary profile-edit-button" type="button" data-profile-edit-open>Edit
                        profil</button>
                    <div class="profile-side-logout" data-profile-control>
                        <button type="button" data-profile-logout data-logout-target="auth.html">Keluar</button>
                        <p data-profile-feedback role="status" hidden></p>
                    </div>
                @endif
            </aside>

            <div class="profile-sections">
                @if (auth()->user()->role === 'admin')
                    <section class="profile-section" aria-labelledby="profile-account-title">
                        <h2 class="profile-section-label" id="profile-account-title">AKUN PENGURUS</h2>
                        <div class="profile-row-list">
                            <button class="profile-row" type="button" data-profile-edit-open>
                                <span class="profile-row-icon">◎</span><span class="profile-row-copy"><strong>Informasi
                                        akun</strong><small><span data-row-name>Nama,</span> <span
                                            data-row-email>email,</span> dan nomor HP</small></span><span
                                    class="profile-row-arrow">›</span>
                            </button>
                            <a class="profile-row" href="listing_limbah_sampah.html">
                                <span class="profile-row-icon">▤</span><span class="profile-row-copy"><strong>Harga
                                        layanan</strong><small>Kelola harga dan kategori sampah</small></span><span
                                    class="profile-row-arrow">›</span>
                            </a>
                            <button class="profile-row" type="button" data-profile-edit-open data-focus-password>
                                <span class="profile-row-icon">▣</span><span class="profile-row-copy"><strong>Keamanan
                                        akun</strong><small>Perbarui password pengurus</small></span><span
                                    class="profile-row-arrow">›</span>
                            </button>
                        </div>
                    </section>
                    <section class="profile-section" aria-labelledby="profile-activity-title">
                        <h2 class="profile-section-label" id="profile-activity-title">AKTIVITAS BANK SAMPAH</h2>
                        <div class="profile-row-list">
                            <a class="profile-row" href="transaksi.html">
                                <span class="profile-row-icon">▣</span><span class="profile-row-copy"><strong>Setoran
                                        menunggu</strong><small>Transaksi yang perlu diperiksa</small></span><span
                                    class="profile-row-badge" data-admin-pending>—</span><span
                                    class="profile-row-arrow">›</span>
                            </a>
                            <a class="profile-row" href="pengguna.html">
                                <span class="profile-row-icon">♙</span><span class="profile-row-copy"><strong>Anggota
                                        EcoBank</strong><small>Kelola data dan aktivitas anggota</small></span><span
                                    class="profile-row-arrow">›</span>
                            </a>
                            <a class="profile-row" href="peringkat.html">
                                <span class="profile-row-icon">↗</span><span
                                    class="profile-row-copy"><strong>Peringkat kontribusi</strong><small>Pantau
                                        partisipasi bulan ini</small></span><span class="profile-row-arrow">›</span>
                            </a>
                        </div>
                    </section>
                    <section class="profile-section" aria-labelledby="profile-settings-title">
                        <h2 class="profile-section-label" id="profile-settings-title">PENGATURAN</h2>
                        <div class="profile-row-list">
                            <a class="profile-row" href="transaksi.html">
                                <span class="profile-row-icon">◷</span><span
                                    class="profile-row-copy"><strong>Verifikasi transaksi</strong><small>Nilai
                                        disetujui <span data-admin-value>—</span></small></span><span
                                    class="profile-row-arrow">›</span>
                            </a>
                            <a class="profile-row" href="profil.html">
                                <span class="profile-row-icon">◎</span><span
                                    class="profile-row-copy"><strong>Preferensi akun</strong><small>Bahasa tampilan:
                                        Indonesia</small></span><span class="profile-row-arrow">›</span>
                            </a>
                        </div>
                    </section>
                @else
                    <section class="profile-section" aria-labelledby="profile-account-title">
                        <h2 class="profile-section-label" id="profile-account-title">AKUN</h2>
                        <div class="profile-row-list">
                            <button class="profile-row" type="button" data-profile-edit-open>
                                <span class="profile-row-icon">◎</span><span
                                    class="profile-row-copy"><strong>Informasi pribadi</strong><small><span
                                            data-row-name>Nama,</span> <span data-row-email>email,</span> dan nomor
                                        HP</small></span><span class="profile-row-arrow">›</span>
                            </button>
                            <button class="profile-row" type="button" data-profile-edit-open>
                                <span class="profile-row-icon">⌖</span><span class="profile-row-copy"><strong>Alamat
                                        penjemputan</strong><small>Wilayah layanan <span
                                            data-row-district>—</span></small></span><span
                                    class="profile-row-badge profile-badge-green">Aktif</span><span
                                    class="profile-row-arrow">›</span>
                            </button>
                            <a class="profile-row" href="riwayat.html">
                                <span class="profile-row-icon">◉</span><span class="profile-row-copy"><strong>Saldo
                                        dan setoran</strong><small>Saldo tersedia <span
                                            data-user-profile-inline-balance>—</span></small></span><span
                                    class="profile-row-arrow">›</span>
                            </a>
                            <a class="profile-row" href="penarikan_saldo.html">
                                <span class="profile-row-icon">↗</span><span
                                    class="profile-row-copy"><strong>Tarik saldo</strong><small>Ajukan penarikan ke rekening
                                        bank atau e-wallet</small></span><span class="profile-row-arrow">›</span>
                            </a>
                            <button class="profile-row" type="button" data-profile-edit-open data-focus-password>
                                <span class="profile-row-icon">▣</span><span class="profile-row-copy"><strong>Ubah
                                        kata sandi</strong><small>Jaga keamanan akunmu</small></span><span
                                    class="profile-row-arrow">›</span>
                            </button>
                        </div>
                    </section>
                    <section class="profile-section" aria-labelledby="profile-activity-title">
                        <h2 class="profile-section-label" id="profile-activity-title">AKTIVITAS</h2>
                        <div class="profile-row-list">
                            <a class="profile-row" href="riwayat.html">
                                <span class="profile-row-icon">▤</span><span class="profile-row-copy"><strong>Riwayat
                                        transaksi</strong><small>Setoran sampah dan saldo masuk</small></span><span
                                    class="profile-row-badge" data-user-profile-pending>—</span><span
                                    class="profile-row-arrow">›</span>
                            </a>
                            <a class="profile-row" href="riwayat.html">
                                <span class="profile-row-icon">↓</span><span class="profile-row-copy"><strong>Saldo
                                        terkumpul</strong><small>Lihat setoran yang sudah
                                        disetujui</small></span><strong class="profile-row-value"
                                    data-user-profile-inline-balance>—</strong><span class="profile-row-arrow">›</span>
                            </a>
                        </div>
                    </section>
                    <section class="profile-section" aria-labelledby="profile-settings-title">
                        <h2 class="profile-section-label" id="profile-settings-title">PENGATURAN</h2>
                        <div class="profile-row-list">
                            <div class="profile-row profile-row-static">
                                <span class="profile-row-icon">◎</span><span
                                    class="profile-row-copy"><strong>Bahasa</strong><small>Bahasa tampilan
                                        aplikasi</small></span><span
                                    class="profile-row-badge profile-badge-green">Indonesia</span>
                            </div>
                            <button class="profile-row" type="button" data-profile-edit-open>
                                <span class="profile-row-icon">♧</span><span
                                    class="profile-row-copy"><strong>Informasi kontak</strong><small>Kelola email dan
                                        nomor telepon</small></span><span class="profile-row-arrow">›</span>
                            </button>
                        </div>
                    </section>
                @endif

                <section class="profile-section" aria-labelledby="profile-help-title">
                    <h2 class="profile-section-label" id="profile-help-title">BANTUAN</h2>
                    <div class="profile-row-list">
                        @if (auth()->user()->role === 'admin')
                            <a class="profile-row" href="transaksi.html">
                                <span class="profile-row-icon">?</span><span class="profile-row-copy"><strong>Panduan
                                        transaksi</strong><small>Alur pemeriksaan dan persetujuan
                                        setoran</small></span><span class="profile-row-arrow">›</span>
                            </a>
                        @else
                            <a class="profile-row" href="setor_sampah.html">
                                <span class="profile-row-icon">?</span><span class="profile-row-copy"><strong>Pusat
                                        bantuan (FAQ)</strong><small>Panduan setoran dan harga
                                        sampah</small></span><span class="profile-row-arrow">›</span>
                            </a>
                        @endif
                        <a class="profile-row" href="mailto:halo@ecobank.id">
                            <span class="profile-row-icon">▱</span><span class="profile-row-copy"><strong>Hubungi
                                    kami</strong><small>halo@ecobank.id</small></span><span
                                class="profile-row-arrow">›</span>
                        </a>
                        <a class="profile-row" href="mailto:halo@ecobank.id?subject=Kebijakan%20EcoBank">
                            <span class="profile-row-icon">▤</span><span class="profile-row-copy"><strong>Syarat dan
                                    kebijakan privasi</strong><small>Ketentuan akun dan data pribadi</small></span><span
                                class="profile-row-arrow">›</span>
                        </a>
                    </div>
                </section>
            </div>
        </div>

        <dialog class="edit-dialog profile-edit-dialog" data-profile-edit-dialog aria-labelledby="profile-edit-title">
            <form class="dialog-close-form" method="dialog"><button class="dialog-close"
                    aria-label="Tutup">×</button></form>
            <h2 id="profile-edit-title">Edit profil</h2>
            <form class="profile-form" data-profile-form>
                <fieldset>
                    <legend>{{ auth()->user()->role === 'admin' ? 'Informasi akun pengurus' : 'Informasi pribadi' }}
                    </legend>
                    <label for="profile-name">Nama lengkap
                        <input id="profile-name" name="name" type="text" maxlength="100" autocomplete="name"
                            required />
                    </label>
                    <label for="profile-email">Email
                        <input id="profile-email" name="email" type="email" maxlength="254"
                            autocomplete="email" required />
                    </label>
                    <label for="profile-phone">Nomor telepon
                        <input id="profile-phone" name="phone" type="tel" minlength="8" maxlength="20"
                            pattern="[0-9+() -]{8,20}" autocomplete="tel" required />
                    </label>
                    <label for="profile-district">Kecamatan
                        <select id="profile-district" name="district" required>
                            @foreach (['Batam Kota', 'Batu Aji', 'Batu Ampar', 'Belakang Padang', 'Bengkong', 'Bulang', 'Galang', 'Lubuk Baja', 'Nongsa', 'Sagulung', 'Sei Beduk', 'Sekupang'] as $district)
                                <option value="{{ $district }}">{{ $district }}</option>
                            @endforeach
                        </select>
                    </label>
                </fieldset>
                <fieldset>
                    <legend>Ubah password <small>Opsional, password saat ini wajib diisi.</small></legend>
                    <label for="profile-current-password">Password saat ini
                        <input id="profile-current-password" name="currentPassword" type="password"
                            autocomplete="current-password" />
                    </label>
                    <label for="profile-password">Password baru
                        <input id="profile-password" name="password" type="password" minlength="8" maxlength="72"
                            autocomplete="new-password" />
                    </label>
                </fieldset>
                <p class="profile-dialog-feedback" data-profile-dialog-feedback role="status" aria-live="polite">
                </p>
                <button class="button button-primary" type="submit">Simpan perubahan <span
                        aria-hidden="true">→</span></button>
            </form>
        </dialog>
    </main>

    <footer class="app-footer">
        <a class="brand"
            href="{{ auth()->user()->role === 'admin' ? 'dashboard_bank_sampah.html' : 'beranda_user.html' }}">EcoBank<span>.</span></a>
        <span>Lebih bijak mengelola sampah, lebih baik untuk bumi.</span>
    </footer>
    <script src="app.js?v=profile-menu-1"></script>
    <script src="profile.js?v=profile-wallet-2"></script>
</body>

</html>
