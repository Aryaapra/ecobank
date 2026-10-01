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
    <link rel="stylesheet" href="style.css" />
</head>

<body class="app-page" data-page="dashboard">
    <header class="app-header">
        <a class="brand" href="index.html">EcoBank<span>.</span></a>
        <button class="menu-toggle" type="button" aria-label="Buka navigasi" aria-expanded="false">
            ☰
        </button>
        <nav class="app-nav" aria-label="Navigasi utama">
            <a class="active" href="dashboard_bank_sampah.html">Beranda</a>
            <a href="transaksi.html">Transaksi <span class="nav-count" data-pending-count>3</span></a>
            <a href="pengguna.html">Anggota</a>
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
                <a href="dashboard_bank_sampah.html">Dashboard</a>
                <a href="transaksi.html">Transaksi</a>
                <a href="pengguna.html">Anggota</a>
                <a href="peringkat.html">Peringkat</a>
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
                <h1>Halo, Admin EcoBank <span class="wave">✦</span></h1>
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
                <span class="stat-decoration" aria-hidden="true">♻</span>
            </article>
            <article class="stat-card">
                <span class="stat-icon stat-icon-green">♻</span>
                <p>Sampah terkumpul</p>
                <strong data-total-weight>0 kg</strong><small>Total berat disetujui</small><span class="stat-art"
                    aria-hidden="true">🌱</span>
            </article>
            <article class="stat-card">
                <span class="stat-icon stat-icon-blue">↗</span>
                <p>Total transaksi</p>
                <strong data-total-transactions>0</strong><small>Semua transaksi tercatat</small><span class="stat-art"
                    aria-hidden="true">📦</span>
            </article>
            <article class="stat-card">
                <span class="stat-icon stat-icon-yellow">☀</span>
                <p>Setoran bulan ini</p>
                <strong data-month-weight>0 kg</strong><small><span data-month-transactions>0</span> transaksi
                    disetujui</small><span class="stat-art" aria-hidden="true">🌍</span>
            </article>
        </section>

        <section class="dashboard-grid">
            <article class="panel activity-panel">
                <div class="panel-heading">
                    <div>
                        <h2>Aktivitas setoran</h2>
                        <p>Berat setoran yang disetujui selama seminggu ini</p>
                    </div>
                    <button class="select-pill" type="button">7 hari⌄</button>
                </div>
                <div class="chart" role="img" aria-label="Grafik setoran sampah selama tujuh hari">
                    <div class="chart-grid">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="chart-bars" data-weekly-chart aria-label="Setoran mingguan"></div>
                </div>
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
                <a class="text-link" href="transaksi.html">Lihat semua <span aria-hidden="true">→</span></a>
            </div>
            <div class="recent-list" data-recent-transactions>
                <p class="inline-notice">Memuat aktivitas...</p>
            </div>
        </section>
    </main>
    <footer class="app-footer">
        <a class="brand" href="index.html">EcoBank<span>.</span></a><span>Mengubah sampah menjadi harapan.</span>
    </footer>
    <script src="app.js?v=prices-api-1"></script>
</body>

</html>
