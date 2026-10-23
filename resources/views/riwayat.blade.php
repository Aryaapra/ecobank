<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Riwayat Setoran — EcoBank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="style.css?v=navbar-full-width-1" />
</head>

<body class="app-page user-tool-page" data-page="user-history">
    <header class="app-header">
        <a class="brand" href="beranda_user.html">EcoBank<span>.</span></a>
        <button class="menu-toggle" type="button" aria-label="Buka navigasi" aria-expanded="false">☰</button>
        <nav class="app-nav" aria-label="Navigasi utama">
            @if (auth()->user()->role === 'admin')
                <a href="dashboard_bank_sampah.html">Dashboard</a>
            @else
                <a href="beranda_user.html">Beranda</a>
                <a href="setor_sampah.html">Setor Sampah</a>
                <a class="active" href="riwayat.html" aria-current="page">Riwayat</a>
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
                <p class="eyebrow eyebrow-green">Aktivitas akun</p>
                <h1>Riwayat setoran</h1>
                <p>Pantau setoran, status pemeriksaan, dan saldo yang sudah masuk.</p>
            </div>
        </div>
        <p class="user-status" data-user-status role="status" hidden></p>

        <section class="history-stats" aria-label="Ringkasan setoran">
            <article><span>Total setoran disetujui</span><strong><span data-history-weight>0</span> kg</strong>
            </article>
            <article><span>Saldo tersedia</span><strong data-history-value>Rp 0</strong></article>
            <article><span>Menunggu konfirmasi</span><strong data-history-pending>0 setoran</strong></article>
        </section>

        <section class="panel history-panel" aria-labelledby="history-title">
            <div class="panel-heading">
                <div>
                    <h2 id="history-title">Semua setoran</h2>
                    <p>Data tersimpan pada akun EcoBank kamu.</p>
                </div>
                <div class="history-actions">
                    <a class="button button-secondary button-small" href="penarikan_saldo.html">Tarik saldo <span
                            aria-hidden="true">→</span></a>
                    <a class="button button-secondary button-small" href="setor_sampah.html">Setor sampah <span
                            aria-hidden="true">→</span></a>
                </div>
            </div>
            <div class="history-table-wrap" data-deposit-history-scroll>
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
                    <tbody class="user-history-list" data-full-history>
                        <tr>
                            <td colspan="6">Memuat riwayat setoran...</td>
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
        aria-labelledby="user-waste-types-title">
        <span class="confirm-icon" aria-hidden="true">♻</span>
        <h2 id="user-waste-types-title">Jenis sampah</h2>
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
