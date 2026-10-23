<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="theme-color" content="#f3f5f2" />
    <meta name="description" content="Ringkasan aktivitas dan dampak bank sampah EcoBank." />
    <title>Beranda — EcoBank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="style.css?v=navbar-full-width-1" />
</head>

<body class="app-page" data-page="dashboard">
    <header class="app-header">
        <a class="brand" href="index.html">EcoBank<span>.</span></a>
        <button class="menu-toggle" type="button" aria-label="Buka navigasi" aria-expanded="false">
            ☰
        </button>
        <nav class="app-nav" aria-label="Navigasi utama">
            <a class="active" href="dashboard_bank_sampah.html">Beranda</a>
            <a href="transaksi.html">Transaksi <span class="nav-count"
                    data-pending-count>{{ $pendingTransactionCount }}</span></a>
            <a href="riwayat_transaksi.html">Riwayat</a>
            <a href="peringkat.html">Peringkat</a>
            <a href="listing_limbah_sampah.html">Layanan</a>
        </nav>
        <div class="profile-control" data-profile-control>
            <button class="profile-button" type="button" aria-label="Buka menu profil admin" aria-haspopup="true"
                aria-expanded="false" aria-controls="profile-menu" data-profile-toggle>
                A
            </button>
            <div class="profile-dropdown" id="profile-menu" hidden>
                <strong>Admin EcoBank</strong>
                <a href="profil.html">Profil</a>
                <a href="pengguna.html">Anggota</a>
                <a href="penarikan_admin.html">Penarikan</a>
                <button type="button" data-profile-logout data-logout-target="auth.html">
                    Keluar
                </button>
                <p data-profile-feedback role="status" hidden></p>
            </div>
        </div>
    </header>

    <main class="app-main">
        <div class="page-intro">
            <div>
                <p class="eyebrow eyebrow-green">Ringkasan bank sampah</p>
                <h1>Halo, Admin EcoBank <span class="wave"></span></h1>
                <p>Berikut ringkasan aktivitas bank sampah hari ini.</p>
            </div>
            <a class="button button-primary" href="transaksi.html">Kelola transaksi <span
                    aria-hidden="true">→</span></a>
        </div>

        <section class="stats-grid" aria-label="Ringkasan EcoBank">
            <article class="stat-card stat-feature">
                <span class="stat-icon">◉</span>
                <p>Nilai setoran disetujui</p>
                <strong data-total-value>Rp 0</strong><small>Total sepanjang waktu</small>
            </article>
            <article class="stat-card">
                <span class="stat-icon stat-icon-green">♙</span>
                <p>Total anggota</p>
                <strong data-total-members>0</strong><small>Anggota terdaftar</small>
            </article>
            <article class="stat-card">
                <span class="stat-icon stat-icon-blue">↗</span>
                <p>Total transaksi</p>
                <strong data-total-transactions>0</strong><small>Semua transaksi tercatat</small>
            </article>
            <article class="stat-card">
                <span class="stat-icon stat-icon-yellow">☀</span>
                <p>Setoran bulan ini</p>
                <strong data-month-weight>0 kg</strong><small><span data-month-transactions>0</span> transaksi
                    disetujui</small>
            </article>
        </section>

        <section class="dashboard-grid">
            <article class="panel activity-panel">
                <div class="panel-heading">
                    <div>
                        <h2>Aktivitas setoran</h2>
                        <p data-chart-description>Berat setoran yang disetujui dalam 7 hari terakhir</p>
                    </div>
                    <label class="sr-only" for="dashboard-chart-period">Periode grafik setoran</label>
                    <select class="select-pill" id="dashboard-chart-period" data-chart-period>
                        <option value="7d">7 hari</option>
                        <option value="30d">30 hari</option>
                        <option value="12m">12 bulan</option>
                    </select>
                </div>
                <div class="chart" role="group" aria-label="Grafik setoran sampah">
                    <div class="chart-grid">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="chart-bars" data-weekly-chart aria-live="polite"></div>
                </div>
                <p class="chart-feedback" data-chart-feedback role="status" aria-live="polite" hidden></p>
            </article>
            <article class="impact-card">
                <p class="eyebrow">Dampak yang kamu buat</p>
                <h2>Dampak dari setoran disetujui.</h2>
                <p>Total berat sampah yang berhasil diproses oleh bank sampah.</p>
                <strong data-total-weight>0 <span>kg</span></strong><small>total sampah terkumpul</small>
                <small>Data dihitung dari setoran disetujui.</small>
            </article>
        </section>

        <section class="panel recent-panel">
            <div class="panel-heading">
                <div>
                    <h2>Transaksi terbaru</h2>
                    <p>Aktivitas yang baru saja tercatat</p>
                </div>
                <a class="text-link" href="riwayat_transaksi.html">Lihat semua riwayat <span
                        aria-hidden="true">→</span></a>
            </div>
            <div class="recent-list" data-recent-transactions>
                <p class="inline-notice">Memuat aktivitas...</p>
            </div>
        </section>
    </main>
    <footer class="app-footer">
        <a class="brand" href="index.html">EcoBank<span>.</span></a><span>Mengubah sampah menjadi harapan.</span>
    </footer>
    <script src="app.js?v=dashboard-members-1"></script>
</body>

</html>
