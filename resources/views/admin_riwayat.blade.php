<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="theme-color" content="#f3f5f2" />
    <title>Riwayat Transaksi — EcoBank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="style.css?v=navbar-full-width-1" />
</head>

<body class="app-page">
    <header class="app-header">
        <a class="brand" href="dashboard_bank_sampah.html">EcoBank<span>.</span></a>
        <button class="menu-toggle" type="button" aria-label="Buka navigasi" aria-expanded="false">☰</button>
        <nav class="app-nav" aria-label="Navigasi utama">
            <a href="dashboard_bank_sampah.html">Beranda</a>
            <a href="transaksi.html">Transaksi <span class="nav-count" data-pending-count>{{ $pendingTransactionCount }}</span></a>
            <a class="active" href="riwayat_transaksi.html" aria-current="page">Riwayat</a>
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
                <a href="penarikan_admin.html">Penarikan</a>
                <button type="button" data-profile-logout data-logout-target="auth.html">Keluar</button>
                <p data-profile-feedback role="status" hidden></p>
            </div>
        </div>
    </header>

    <main class="app-main">
        <div class="page-intro">
            <div>
                <p class="eyebrow eyebrow-green">Arsip bank sampah</p>
                <h1>Riwayat transaksi</h1>
                <p>Daftar transaksi setoran anggota pada bulan yang dipilih.</p>
            </div>
            <a class="button button-secondary" href="dashboard_bank_sampah.html">← Kembali ke beranda</a>
        </div>

        <section class="panel transaction-report report-toolbar" aria-label="Pilih periode riwayat transaksi">
            <form method="get" action="{{ route('admin.history') }}">
                <label for="history-month">Bulan riwayat</label>
                <input id="history-month" type="month" name="month" value="{{ $reportMonth }}" required />
                <button class="button button-secondary" type="submit">Tampilkan</button>
            </form>
            <a class="button button-primary" href="{{ route('admin.reports.export', ['month' => $reportMonth]) }}">
                Unduh CSV <span aria-hidden="true">↓</span>
            </a>
        </section>

        <section class="panel transaction-panel" aria-labelledby="history-title">
            <div class="panel-heading">
                <div>
                    <h2 id="history-title">Semua transaksi</h2>
                    <p>{{ number_format($transactions->count(), 0, ',', '.') }} transaksi pada {{ $reportMonth }}</p>
                </div>
            </div>
            <div class="table-scroll">
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th>Kode transaksi</th>
                            <th>Anggota</th>
                            <th>Jenis sampah</th>
                            <th>Berat</th>
                            <th>Nilai</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $transaction)
                            <tr>
                                <td>{{ $transaction->transaction_code }}</td>
                                <td>{{ $transaction->customer_name }}</td>
                                <td><button class="table-action view-waste-types" type="button"
                                        data-history-waste-types="{{ json_encode($transaction->items, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}">Lihat jenis sampah</button></td>
                                <td>{{ number_format($transaction->weight_kg, 2, ',', '.') }} kg</td>
                                <td>Rp {{ number_format((int) $transaction->total_value, 0, ',', '.') }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($transaction->created_at)->format('d/m/Y H:i') }}
                                </td>
                                <td>
                                    @php
                                        $historyStatus = $transaction->status === 'pending'
                                            ? ($transaction->process_stage ?: 'requested')
                                            : $transaction->status;
                                    @endphp
                                    <span class="history-status status-{{ $historyStatus === 'requested' ? 'pending' : $historyStatus }}">
                                    @switch($historyStatus)
                                        @case('approved')
                                            Selesai
                                        @break

                                        @case('cancelled')
                                            Dibatalkan
                                        @break

                                        @case('pickup_confirmed')
                                            Penjemputan dikonfirmasi
                                        @break

                                        @case('weighing')
                                            Menunggu penimbangan
                                        @break

                                        @default
                                            Menunggu
                                    @endswitch
                                    </span>
                                </td>
                            </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="directory-empty">Belum ada transaksi pada bulan ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <dialog class="confirm-dialog waste-types-dialog" data-history-waste-types-dialog
            aria-labelledby="history-waste-types-title">
            <span class="confirm-icon" aria-hidden="true">♻</span>
            <h2 id="history-waste-types-title">Jenis sampah</h2>
            <p data-history-waste-types-summary></p>
            <ul data-history-waste-types-list></ul>
            <div class="dialog-actions">
                <button class="button button-secondary" type="button" data-history-waste-types-close>Tutup</button>
            </div>
        </dialog>
        <footer class="app-footer">
            <a class="brand" href="dashboard_bank_sampah.html">EcoBank<span>.</span></a>
            <span>Mengubah sampah menjadi harapan.</span>
        </footer>
        <script src="app.js?v=grouped-history-details-1"></script>
</body>

</html>
