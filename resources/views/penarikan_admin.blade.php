<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="theme-color" content="#f3f5f2" />
    <title>Penarikan saldo — EcoBank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="style.css?v=navbar-full-width-1" />
</head>

<body class="app-page" data-page="withdrawal-admin">
    <header class="app-header">
        <a class="brand" href="dashboard_bank_sampah.html">EcoBank<span>.</span></a>
        <button class="menu-toggle" type="button" aria-label="Buka navigasi" aria-expanded="false">☰</button>
        <nav class="app-nav" aria-label="Navigasi utama">
            <a href="dashboard_bank_sampah.html">Beranda</a>
            <a href="transaksi.html">Transaksi</a>
            <a href="riwayat_transaksi.html">Riwayat</a>
            <a href="peringkat.html">Peringkat</a>
            <a href="listing_limbah_sampah.html">Layanan</a>
        </nav>
        <div class="profile-control" data-profile-control>
            <button class="profile-button" type="button" aria-label="Buka menu profil admin" aria-haspopup="true"
                aria-expanded="false" aria-controls="profile-menu" data-profile-toggle>A</button>
            <div class="profile-dropdown" id="profile-menu" hidden>
                <strong>Admin EcoBank</strong>
                <a href="profil.html">Profil</a>
                <a href="pengguna.html">Anggota</a>
                <a href="penarikan_admin.html" aria-current="page">Penarikan</a>
                <button type="button" data-profile-logout data-logout-target="auth.html">Keluar</button>
                <p data-profile-feedback role="status" hidden></p>
            </div>
        </div>
    </header>

    <main class="app-main withdrawal-admin-page">
        <div class="page-intro">
            <div>
                <p class="eyebrow eyebrow-green">Keuangan anggota</p>
                <h1>Permintaan penarikan</h1>
                <p>Periksa rekening, setujui permintaan, lalu tandai terkirim setelah transfer benar-benar dilakukan.</p>
            </div>
        </div>
        <p class="user-status" data-withdrawal-feedback role="status" aria-live="polite" hidden></p>
        <section class="panel withdrawal-admin-panel" aria-labelledby="withdrawal-admin-title">
            <div class="panel-heading">
                <div>
                    <h2 id="withdrawal-admin-title">Semua permintaan</h2>
                    <p data-withdrawal-admin-count>Memuat data...</p>
                </div>
                <label class="filter-select">
                    <span class="sr-only">Filter status penarikan</span>
                    <select data-withdrawal-admin-filter>
                        <option value="all">Semua status</option>
                        <option value="pending">Menunggu pemeriksaan</option>
                        <option value="approved">Disetujui, belum dikirim</option>
                        <option value="paid">Sudah dikirim</option>
                        <option value="rejected">Ditolak</option>
                        <option value="cancelled">Dibatalkan user</option>
                    </select>
                </label>
            </div>
            <div class="history-table-wrap">
                <table class="history-table withdrawal-admin-table">
                    <thead>
                        <tr>
                            <th>Permintaan</th>
                            <th>Anggota</th>
                            <th>Nominal</th>
                            <th>Rekening tujuan</th>
                            <th>Status</th>
                            <th>Tindakan</th>
                        </tr>
                    </thead>
                    <tbody data-withdrawal-admin-list>
                        <tr><td colspan="6">Memuat permintaan...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <footer class="app-footer">
        <a class="brand" href="dashboard_bank_sampah.html">EcoBank<span>.</span></a>
        <span>Mengubah sampah menjadi harapan.</span>
    </footer>
    <script src="app.js?v=withdrawals-1"></script>
    <script src="withdrawals.js?v=withdrawals-2"></script>
</body>

</html>
