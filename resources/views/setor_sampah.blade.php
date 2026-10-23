<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Setor Sampah — EcoBank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="style.css?v=navbar-full-width-1" />
</head>

<body class="app-page user-tool-page" data-page="user-deposit">
    <header class="app-header">
        <a class="brand" href="beranda_user.html">EcoBank<span>.</span></a>
        <button class="menu-toggle" type="button" aria-label="Buka navigasi" aria-expanded="false">☰</button>
        <nav class="app-nav" aria-label="Navigasi utama">
            @if (auth()->user()->role === 'admin')
                <a href="dashboard_bank_sampah.html">Dashboard</a>
            @else
                <a href="beranda_user.html">Beranda</a>
                <a class="active" href="setor_sampah.html" aria-current="page">Setor Sampah</a>
                <a href="riwayat.html">Riwayat</a>
                <a href="peringkat_user.html">Peringkat</a>
            @endif
        </nav>
        <div class="profile-control" data-profile-control>
            <button class="profile-button user-profile" type="button" aria-label="Buka menu akun" aria-haspopup="true"
                aria-expanded="false" aria-controls="profile-menu" data-profile-toggle>A</button>
            <div class="profile-dropdown" id="profile-menu" hidden>
                <strong data-profile-name>Memuat akun...</strong>
                <a href="profil.html">Profil</a>
                <button type="button" data-profile-logout data-logout-target="auth.html">Keluar</button>
                <p data-profile-feedback role="status" hidden></p>
            </div>
        </div>
    </header>

    <main class="app-main user-tool-main">
        <div class="page-intro">
            <div>
                <p class="eyebrow eyebrow-green">Layanan EcoBank</p>
                <h1>Setor sampah</h1>
                <p>Pilih jenis sampah dan beratnya untuk mengajukan setoran.</p>
            </div>
        </div>
        <p class="user-status" data-user-status role="status" hidden></p>

        <section class="user-prices" aria-labelledby="prices-title">
            <div class="user-section-heading price-heading">
                <div>
                    <p class="eyebrow eyebrow-green">Harga tukar sampah</p>
                    <h2 id="prices-title">Harga aktif</h2>
                    <p>Nilai per kilogram mengikuti harga terbaru dari pengurus.</p>
                </div>
                <span class="price-updated"><i></i> Harga aktif</span>
            </div>
            <div class="user-price-grid">
                @foreach (['Plastik', 'Kertas', 'Logam', 'Kaca'] as $category)
                    <article class="user-price-category">
                        <h3>{{ $category }}</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Jenis sampah</th>
                                    <th>Harga /kg</th>
                                </tr>
                            </thead>
                            <tbody data-user-price-list="{{ $category }}">
                                <tr>
                                    <td colspan="2">Memuat harga...</td>
                                </tr>
                            </tbody>
                        </table>
                    </article>
                @endforeach
            </div>
            <p class="inline-notice" data-user-price-status role="status" hidden></p>
        </section>

        <section class="user-request" aria-labelledby="deposit-title">
            <div class="user-section-heading">
                <p class="eyebrow eyebrow-green">Permintaan baru</p>
                <h2 id="deposit-title">Ajukan setoran</h2>
                <p>Pilih semua jenis sampah yang akan disetorkan. Berat ditimbang oleh pengurus, lalu saldo masuk
                    setelah dikonfirmasi.</p>
            </div>
            <form class="deposit-request-form" data-deposit-form>
                <details class="waste-type-picker">
                    <summary>
                        <span>Jenis sampah</span>
                        <span class="waste-type-picker-selection" data-deposit-selected>Pilih jenis sampah</span>
                    </summary>
                    <label class="waste-type-search" for="deposit-type-search">Cari jenis sampah
                        <input id="deposit-type-search" type="search" placeholder="Cari jenis..." data-waste-search />
                    </label>
                    <div class="waste-type-options" data-deposit-types>
                        <p class="inline-notice">Memuat daftar jenis sampah...</p>
                    </div>
                </details>
                <fieldset class="delivery-method-choice">
                    <legend>Metode setoran</legend>
                    <label><input type="radio" name="deliveryMethod" value="self_delivery" checked />
                        <span><strong>Antar mandiri</strong><small>Bawa sampah ke bank sampah</small></span>
                    </label>
                    <label><input type="radio" name="deliveryMethod" value="pickup" />
                        <span><strong>Minta dijemput</strong><small>Pengurus mengonfirmasi penjemputan</small></span>
                    </label>
                </fieldset>
                <label class="pickup-address-field" data-pickup-address-wrap for="pickup-address" hidden>Alamat
                    penjemputan
                    <textarea id="pickup-address" name="pickupAddress" maxlength="255" rows="2"
                        placeholder="Nama jalan, nomor rumah, kelurahan"></textarea>
                </label>
                <button class="button button-primary" type="submit">Kirim permintaan <span
                        aria-hidden="true">→</span></button>
            </form>
            <p class="inline-notice" data-deposit-status role="status" aria-live="polite"></p>
        </section>

        <section class="panel history-panel" aria-labelledby="deposit-requests-title">
            <div class="panel-heading">
                <div>
                    <h2 id="deposit-requests-title">Setoran saya</h2>
                    <p>Antar mandiri bisa dibatalkan sampai disetujui. Penjemputan tidak bisa dibatalkan setelah
                        dikonfirmasi.</p>
                </div>
            </div>
            <div class="history-table-wrap">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis sampah</th>
                            <th>Berat</th>
                            <th>Status</th>
                            <th>Nilai</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="user-history-list" data-deposit-requests>
                        <tr>
                            <td colspan="6">Memuat setoran...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <dialog class="confirm-dialog weigh-dialog user-edit-dialog" data-user-edit-dialog
        aria-labelledby="user-edit-title">
        <form data-user-edit-form>
            <span class="confirm-icon">✎</span>
            <h2 id="user-edit-title">Edit permintaan setoran</h2>
            <p>Perubahan hanya dapat dilakukan sebelum setoran diproses pengurus. Berat akan ditentukan saat
                penimbangan.</p>
            <label for="user-edit-waste-type">Jenis sampah
                <select id="user-edit-waste-type" name="wasteTypeId" data-user-edit-type required></select>
            </label>
            <fieldset class="delivery-method-choice">
                <legend>Metode setoran</legend>
                <label><input type="radio" name="deliveryMethod" value="self_delivery" required />
                    <span><strong>Antar mandiri</strong></span>
                </label>
                <label><input type="radio" name="deliveryMethod" value="pickup" />
                    <span><strong>Minta dijemput</strong></span>
                </label>
            </fieldset>
            <label class="pickup-address-field" data-user-edit-address-wrap for="user-edit-address" hidden>Alamat
                penjemputan
                <textarea id="user-edit-address" name="pickupAddress" maxlength="255" rows="2"></textarea>
            </label>
            <p class="weigh-feedback" data-user-edit-feedback role="status" aria-live="polite"></p>
            <div class="dialog-actions">
                <button class="button button-secondary" type="button" data-user-edit-cancel>Kembali</button>
                <button class="button button-primary" type="submit">Simpan perubahan</button>
            </div>
        </form>
    </dialog>

    <dialog class="confirm-dialog waste-types-dialog" data-user-waste-types-dialog
        aria-labelledby="deposit-waste-types-title">
        <span class="confirm-icon" aria-hidden="true">♻</span>
        <h2 id="deposit-waste-types-title">Jenis sampah</h2>
        <p data-user-waste-types-summary></p>
        <ul data-user-waste-types-list></ul>
        <div class="dialog-actions">
            <button class="button button-secondary" type="button" data-user-waste-types-close>Tutup</button>
        </div>
    </dialog>

    <footer class="app-footer">
        <a class="brand" href="beranda_user.html">EcoBank<span>.</span></a>
        <span>Lebih bijak mengelola sampah, lebih baik untuk bumi.</span>
    </footer>
    <script src="app.js?v=grouped-history-details-1"></script>
    <script src="user-home.js?v=grouped-history-details-1"></script>
</body>

</html>
