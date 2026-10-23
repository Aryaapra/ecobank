<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="theme-color" content="#f3f5f2" />
    <meta name="description" content="Tinjau, setujui, atau batalkan transaksi setoran sampah." />
    <title>Transaksi — EcoBank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="style.css?v=navbar-full-width-1" />
</head>

<body class="app-page" data-page="transactions">
    <header class="app-header">
        <a class="brand" href="index.html">EcoBank<span>.</span></a>
        <button class="menu-toggle" type="button" aria-label="Buka navigasi" aria-expanded="false">
            ☰
        </button>
        <nav class="app-nav" aria-label="Navigasi utama">
            <a href="dashboard_bank_sampah.html">Beranda</a>
            <a class="active" href="transaksi.html" aria-current="page">Transaksi <span class="nav-count"
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
                <p class="eyebrow eyebrow-green">Aktivitas bank sampah</p>
                <h1>Transaksi</h1>
                <p>Periksa setoran masuk, lalu setujui atau batalkan transaksi.</p>
            </div>
            <a class="button button-secondary" href="listing_limbah_sampah.html">Atur harga sampah <span
                    aria-hidden="true">→</span></a>
        </div>
        <section class="panel transaction-report report-toolbar" aria-label="Periode rekap dan ekspor transaksi">
            <form method="get" action="{{ route('admin.transactions') }}">
                <label for="transaction-report-month">Rekap bulan</label>
                <input id="transaction-report-month" type="month" name="month" value="{{ $reportMonth }}"
                    data-report-month required />
                <button class="button button-secondary" type="submit">Tampilkan</button>
            </form>
            <a class="button button-primary" href="{{ route('admin.reports.export', ['month' => $reportMonth]) }}">Unduh
                CSV <span aria-hidden="true">↓</span></a>
        </section>
        <section class="stats-grid transaction-stats" aria-label="Rekap transaksi bulan {{ $reportMonth }}">
            <article class="stat-card stat-feature">
                <span class="stat-icon">↗</span>
                <p>Total transaksi</p>
                <strong
                    data-report-transaction-count>{{ number_format($monthSummary['transactionCount'], 0, ',', '.') }}</strong><small>Pada
                    bulan
                    terpilih</small>
            </article>
            <article class="stat-card">
                <span class="stat-icon stat-icon-green">♻</span>
                <p>Sampah terkumpul</p>
                <strong><span
                        data-report-approved-weight>{{ number_format($monthSummary['approvedWeight'], 2, ',', '.') }}</span>
                    kg</strong><small>Setoran
                    disetujui</small>
            </article>
            <article class="stat-card">
                <span class="stat-icon stat-icon-yellow">◷</span>
                <p>Menunggu verifikasi</p>
                <strong
                    data-report-pending-count>{{ number_format($monthSummary['pendingCount'], 0, ',', '.') }}</strong><small>Pada
                    bulan
                    terpilih</small>
            </article>
            <article class="stat-card">
                <span class="stat-icon stat-icon-blue">Rp</span>
                <p>Nilai setoran disetujui</p>
                <strong data-report-approved-value>Rp
                    {{ number_format($monthSummary['approvedValue'], 0, ',', '.') }}</strong><small>Pada bulan
                    terpilih</small>
            </article>
        </section>
        <section class="transaction-layout">
            <article class="panel transaction-panel">
                <div class="panel-heading transaction-heading">
                    <div>
                        <h2>Setoran masuk</h2>
                        <p>Kelola permintaan setoran dari pengguna</p>
                    </div>
                    <span class="pending-badge"><i></i><span data-pending-label>3 menunggu</span></span>
                </div>
                <div class="transaction-controls">
                    <label class="search-field transaction-search"><span aria-hidden="true">⌕</span><input
                            type="search" placeholder="Cari nama atau ID..." aria-label="Cari transaksi"
                            data-transaction-search /></label>
                    <label class="filter-select"><span class="sr-only">Filter status</span><select data-status-filter>
                            <option value="all">Semua setoran aktif</option>
                            <option value="pending">Menunggu proses</option>
                        </select></label>
                    <label class="filter-select"><span class="sr-only">Filter tahap proses</span><select
                            data-stage-filter>
                            <option value="all">Semua tahap</option>
                            <option value="requested">Permintaan baru</option>
                            <option value="pickup_confirmed">Jemput dikonfirmasi</option>
                            <option value="weighing">Menunggu penimbangan</option>
                            <option value="completed">Selesai ditimbang</option>
                        </select></label>
                    <label class="filter-select"><span class="sr-only">Filter jenis sampah</span><select
                            data-type-filter>
                            <option value="all">Semua sampah</option>
                            <option>Plastik</option>
                            <option>Kertas</option>
                            <option>Kardus</option>
                            <option>Kaca</option>
                            <option>Logam</option>
                        </select></label>
                </div>
                <p class="inline-notice transaction-feedback" data-transaction-feedback role="status"
                    aria-live="polite" hidden></p>
                <div class="table-scroll">
                    <table class="transaction-table">
                        <thead>
                            <tr>
                                <th>Pengguna</th>
                                <th>Jenis sampah</th>
                                <th>Metode</th>
                                <th>Berat</th>
                                <th>Nilai setoran</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-transaction-rows></tbody>
                    </table>
                </div>
                <div class="empty-state" data-transactions-empty hidden>
                    <span>♻</span>
                    <h3>Tidak ada setoran yang menunggu</h3>
                    <p>Setoran yang selesai atau dibatalkan bisa dilihat di Riwayat.</p>
                </div>
                <div class="table-footer">
                    <span data-transaction-count>Menampilkan transaksi</span><span class="pagination-label">EcoBank ·
                        Setoran sampah</span>
                </div>
            </article>
            <aside class="panel leaderboard-panel">
                <div class="panel-heading">
                    <div>
                        <h2>Peringkat sampah bulan ini</h2>
                        <p>Setoran terbanyak berdasarkan jenis</p>
                    </div>
                </div>
                <div class="leaderboard-list" data-monthly-waste-rankings>
                    <p class="inline-notice">Memuat peringkat material...</p>
                </div>
            </aside>
        </section>
        <section class="panel recent-panel">
            <div class="panel-heading">
                <div>
                    <h2>Aktivitas terbaru</h2>
                    <p>Riwayat setoran EcoBank</p>
                </div>
            </div>
            <div class="recent-list" data-recent-transactions>
                <p class="inline-notice">Memuat aktivitas...</p>
            </div>
        </section>
    </main>
    <dialog class="confirm-dialog" data-confirm-dialog>
        <span class="confirm-icon" data-confirm-icon>✓</span>
        <h2 data-confirm-title>Setujui setoran?</h2>
        <p data-confirm-copy>
            Setoran pengguna akan disetujui dan tercatat di transaksi EcoBank.
        </p>
        <div class="dialog-actions">
            <button class="button button-secondary" type="button" data-confirm-cancel>
                Kembali</button><button class="button button-primary" type="button" data-confirm-accept>
                Ya, setujui
            </button>
        </div>
    </dialog>
    <dialog class="confirm-dialog weigh-dialog" data-weigh-dialog aria-labelledby="weigh-dialog-title">
        <form data-weigh-form>
            <span class="confirm-icon">⚖</span>
            <h2 id="weigh-dialog-title">Konfirmasi hasil timbang</h2>
            <p data-weigh-summary>Masukkan berat aktual untuk setiap jenis sampah.</p>
            <div class="weigh-items" data-weigh-items></div>
            <p class="weigh-feedback" data-weigh-feedback role="status" aria-live="polite"></p>
            <div class="dialog-actions">
                <button class="button button-secondary" type="button" data-weigh-cancel>Kembali</button>
                <button class="button button-primary" type="submit">Simpan berat &amp; masukkan saldo</button>
            </div>
        </form>
    </dialog>
    <dialog class="confirm-dialog waste-types-dialog" data-waste-types-dialog aria-labelledby="waste-types-title">
        <span class="confirm-icon">♻</span>
        <h2 id="waste-types-title">Jenis sampah</h2>
        <p data-waste-types-summary></p>
        <ul data-waste-types-list></ul>
        <div class="dialog-actions">
            <button class="button button-secondary" type="button" data-waste-types-close>Tutup</button>
        </div>
    </dialog>
    <footer class="app-footer">
        <a class="brand" href="index.html">EcoBank<span>.</span></a><span>Mengubah sampah menjadi harapan.</span>
    </footer>
    <script src="app.js?v=pending-only-1"></script>
</body>

</html>
