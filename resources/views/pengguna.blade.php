<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="theme-color" content="#f3f5f2" />
    <meta name="description" content="Direktori anggota dan ringkasan setoran EcoBank." />
    <title>Anggota — EcoBank</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>

<body class="app-page" data-page="members">
    <header class="app-header">
        <a class="brand" href="index.html">EcoBank<span>.</span></a>
        <button class="menu-toggle" type="button" aria-label="Buka navigasi" aria-expanded="false">☰</button>
        <nav class="app-nav" aria-label="Navigasi utama">
            <a href="dashboard_bank_sampah.html">Beranda</a>
            <a href="transaksi.html">Transaksi <span class="nav-count" data-pending-count>0</span></a>
            <a href="pengguna.html" class="active" aria-current="page">Anggota</a>
            <a href="peringkat.html">Peringkat</a>
            <a href="listing_limbah_sampah.html">Layanan</a>
        </nav>
        <div class="profile-control" data-profile-control>
            <button class="profile-button" type="button" aria-label="Buka menu profil admin" aria-haspopup="true"
                aria-expanded="false" aria-controls="profile-menu" data-profile-toggle>A</button>
            <div class="profile-dropdown" id="profile-menu" hidden>
                <strong>Admin EcoBank</strong>
                <a href="dashboard_bank_sampah.html">Dashboard</a>
                <a href="transaksi.html">Transaksi</a>
                <a href="pengguna.html">Anggota</a>
                <button type="button" data-profile-logout data-logout-target="auth.html">Keluar</button>
                <p data-profile-feedback role="status" hidden></p>
            </div>
        </div>
    </header>

    <main class="app-main admin-directory-page">
        <div class="page-intro">
            <div>
                <p class="eyebrow eyebrow-green">Data anggota</p>
                <h1>Anggota bank sampah</h1>
                <p>{{ number_format($members->total(), 0, ',', '.') }} anggota terdaftar</p>
            </div>
            <a class="button button-secondary" href="{{ route('admin.transactions') }}">Lihat transaksi <span
                    aria-hidden="true">→</span></a>
        </div>

        <section class="panel directory-panel" aria-label="Direktori anggota">
            <form class="directory-toolbar" method="get" action="{{ route('admin.members') }}">
                <label class="search-field directory-search">
                    <span aria-hidden="true">⌕</span>
                    <input type="search" name="q" value="{{ $search }}"
                        placeholder="Cari nama, email, telepon, atau wilayah" aria-label="Cari anggota" />
                </label>
                <button class="button button-primary" type="submit">Cari anggota</button>
                @if ($search !== '')
                    <a class="button button-secondary" href="{{ route('admin.members') }}">Hapus filter</a>
                @endif
            </form>

            <div class="table-scroll">
                <table class="transaction-table member-table">
                    <thead>
                        <tr>
                            <th>Anggota</th>
                            <th>Telepon</th>
                            <th>Wilayah</th>
                            <th>Transaksi</th>
                            <th>Menunggu</th>
                            <th>Berat disetujui</th>
                            <th>Nilai disetujui</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($members as $member)
                            <tr>
                                <td><strong>{{ $member->name }}</strong><small
                                        class="member-email">{{ $member->email }}</small></td>
                                <td>{{ $member->phone }}</td>
                                <td>{{ $member->district ?: '—' }}</td>
                                <td>{{ number_format($member->transaction_count, 0, ',', '.') }}</td>
                                <td>{{ number_format($member->pending_count, 0, ',', '.') }}</td>
                                <td>{{ number_format((float) $member->approved_weight, 2, ',', '.') }} kg</td>
                                <td>Rp {{ number_format((int) $member->approved_value, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="directory-empty">
                                    {{ $search !== '' ? 'Anggota tidak ditemukan.' : 'Belum ada anggota terdaftar.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($members->hasPages())
                <div class="directory-pagination">
                    <span>Menampilkan {{ $members->firstItem() }}–{{ $members->lastItem() }} dari
                        {{ $members->total() }}</span>
                    <div>{{ $members->links() }}</div>
                </div>
            @endif
        </section>
    </main>

    <footer class="app-footer"><a class="brand" href="index.html">EcoBank<span>.</span></a><span>Mengubah sampah
            menjadi harapan.</span></footer>
    <script src="app.js?v=prices-api-1"></script>
</body>

</html>
