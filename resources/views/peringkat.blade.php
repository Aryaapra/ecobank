<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="theme-color" content="#f3f5f2" />
    <meta name="description" content="Papan peringkat kontributor dan jenis sampah EcoBank bulan ini." />
    <title>Peringkat — EcoBank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>

<body class="app-page" data-page="ranking">
    <header class="app-header">
        <a class="brand" href="index.html">EcoBank<span>.</span></a>
        <button class="menu-toggle" type="button" aria-label="Buka navigasi" aria-expanded="false">
            ☰
        </button>
        <nav class="app-nav" aria-label="Navigasi utama">
            <a href="dashboard_bank_sampah.html">Beranda</a>
            <a href="transaksi.html">Transaksi <span class="nav-count" data-pending-count>3</span></a>
            <a href="pengguna.html">Anggota</a>
            <a class="active" href="peringkat.html" aria-current="page">Peringkat</a>
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

    <main class="app-main ranking-page">
        <div class="page-intro ranking-intro">
            <div>
                <h1>Peringkat bulan ini</h1>
                <p>Batam Centre</p>
                <p class="ranking-period-note">Setoran menunggu verifikasi dihitung sementara.</p>
            </div>
        </div>

        <section class="ranking-overview" aria-label="Peringkat kontribusi bulan ini">
            <article class="ranking-board" aria-label="Papan peringkat kontributor">
                <div class="ranking-mini-podium" aria-label="Tiga kontributor teratas">
                    @if ($rankings->isEmpty())
                        <p class="ranking-empty-podium">Belum ada setoran masuk bulan ini.</p>
                    @else
                        <div class="ranking-podium">
                            @foreach ([2 => 'second', 1 => 'first', 3 => 'third'] as $rank => $place)
                                @if (isset($topRankings[$rank]))
                                    <div class="mini-podium-place mini-podium-{{ $place }}"
                                        title="{{ $topRankings[$rank]['has_pending'] ? 'Termasuk setoran menunggu verifikasi' : 'Setoran disetujui' }}">
                                        <span class="mini-avatar">{{ $topRankings[$rank]['initials'] }}</span>
                                        <span class="ranking-podium-step">{{ $rank }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="ranking-table">
                    <div class="ranking-table-heading">
                        <span>Peringkat</span>
                        <span>{{ $rankings->count() }} dari 100</span>
                    </div>
                    <div class="ranking-list-scroll" tabindex="0"
                        aria-label="Daftar hingga 100 peringkat kontributor">
                        <ol class="ranking-list">
                            @forelse ($rankings as $entry)
                                <li class="{{ $entry['rank'] <= 3 ? 'top-rank-row' : 'rank-row' }}">
                                    <span class="list-rank">#{{ $entry['rank'] }}</span>
                                    <span class="list-avatar">{{ $entry['initials'] }}</span>
                                    <span class="list-name">
                                        <strong>{{ $entry['name'] }}</strong>
                                        @if ($entry['has_pending'])
                                            <small class="ranking-pending-note">{{ $entry['pending_weight_label'] }} kg
                                                menunggu verifikasi</small>
                                        @endif
                                    </span>
                                    <strong class="list-score">{{ $entry['weight_label'] }} kg</strong>
                                </li>
                            @empty
                                <li class="ranking-empty">Belum ada kontributor bulan ini.</li>
                            @endforelse
                        </ol>
                    </div>
                </div>
            </article>

            <article class="ranking-waste-panel" aria-labelledby="waste-ranking-title">
                <div class="waste-ranking-copy">
                    <h2 id="waste-ranking-title">Jenis sampah<br />bulan ini</h2>
                    <p>Termasuk setoran yang masih menunggu verifikasi.</p>
                    <a class="button button-light" href="{{ route('admin.prices') }}">Lihat semua <span
                            aria-hidden="true">→</span></a>
                </div>
                <ol class="waste-ranking-list" aria-label="Berat berdasarkan jenis sampah">
                    @forelse ($wasteRankings as $waste)
                        <li>
                            <div class="waste-rank-label">
                                <strong>{{ $waste['name'] }}</strong><b>{{ $waste['weight_label'] }} kg</b>
                            </div>
                            <div class="ranking-meter">
                                <span
                                    style="--rank-width: {{ $wasteRankings->first()['weight'] > 0 ? round(($waste['weight'] / $wasteRankings->first()['weight']) * 100) : 0 }}%"></span>
                            </div>
                        </li>
                    @empty
                        <li class="waste-ranking-empty">Belum ada setoran material bulan ini.</li>
                    @endforelse
                </ol>
            </article>
        </section>

        <section class="ranking-impact" aria-label="Dampak EcoBank bulan ini">
            <div class="ranking-impact-copy">
                <p class="eyebrow eyebrow-green">Setoran bulan ini</p>
                <h2>Kontribusi bersama.</h2>
                <p>Jumlah sementara, termasuk setoran menunggu verifikasi.</p>
            </div>
            <div class="ranking-impact-stats">
                <div class="ranking-impact-stat"><strong>{{ $totalWeightLabel }} <span>kg</span></strong><span>Total
                        masuk</span></div>
                <div class="ranking-impact-stat"><strong>{{ $highestWeightLabel }}
                        <span>kg</span></strong><span>Setoran tertinggi</span>
                </div>
                <div class="ranking-impact-stat"><strong>{{ $materialCount }}</strong><span>Jenis material</span>
                </div>
            </div>
        </section>
    </main>
    <footer class="app-footer">
        <a class="brand" href="index.html">EcoBank<span>.</span></a><span>Mengubah sampah menjadi harapan.</span>
    </footer>
    <script src="app.js?v=prices-api-1"></script>
</body>

</html>
