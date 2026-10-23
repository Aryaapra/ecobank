<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="theme-color" content="#f3f5f2" />
    <meta name="description" content="Ajukan penarikan saldo EcoBank dan pantau statusnya." />
    <title>Tarik saldo — EcoBank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="style.css?v=navbar-full-width-1" />
</head>

<body class="app-page user-tool-page" data-page="user-withdrawals">
    <header class="app-header">
        <a class="brand" href="beranda_user.html">EcoBank<span>.</span></a>
        <button class="menu-toggle" type="button" aria-label="Buka navigasi" aria-expanded="false">☰</button>
        <nav class="app-nav" aria-label="Navigasi utama">
            <a href="beranda_user.html">Beranda</a>
            <a href="setor_sampah.html">Setor Sampah</a>
            <a href="riwayat.html">Riwayat</a>
            <a href="peringkat_user.html">Peringkat</a>
        </nav>
        <div class="profile-control" data-profile-control>
            <button class="profile-button user-profile" type="button" aria-label="Buka menu profil"
                aria-haspopup="true" aria-expanded="false" aria-controls="profile-menu" data-profile-toggle>A</button>
            <div class="profile-dropdown" id="profile-menu" hidden>
                <strong data-profile-name>Memuat akun...</strong>
                <a href="profil.html">Profil</a>
                <button type="button" data-profile-logout data-logout-target="auth.html">Keluar</button>
                <p data-profile-feedback role="status" hidden></p>
            </div>
        </div>
    </header>

    <main class="app-main user-tool-main withdrawal-page">
        <div class="page-intro">
            <div>
                <p class="eyebrow eyebrow-green">Kelola saldo</p>
                <h1>Tarik saldo</h1>
                <p>Kirim data rekening kamu. Penarikan akan diperiksa dan disetujui admin sebelum dana dikirim.</p>
                <a class="button button-secondary withdrawal-back-link" href="beranda_user.html">
                    ← Kembali ke beranda
                </a>
            </div>
        </div>
        <p class="user-status" data-withdrawal-feedback role="status" aria-live="polite" hidden></p>

        <div class="withdrawal-layout">
            <section class="withdrawal-form-card" aria-labelledby="withdrawal-form-title">
                <h2 class="sr-only" id="withdrawal-form-title">Formulir penarikan saldo</h2>
                <div class="withdrawal-available">
                    <div>
                        <span>Saldo tersedia</span>
                        <strong data-withdrawal-balance>Memuat saldo...</strong>
                    </div>
                    <span class="withdrawal-minimum">Minimal tarik <b data-withdrawal-minimum>Rp 5.000</b></span>
                </div>

                <form data-withdrawal-form>
                    <label for="withdrawal-amount">Nominal penarikan</label>
                    <div class="withdrawal-amount-field">
                        <span>Rp</span>
                        <input id="withdrawal-amount" name="amount" type="number" min="5000" step="1000"
                            inputmode="numeric" placeholder="Contoh: 50.000" required />
                        <button type="button" data-withdrawal-all>Tarik semua</button>
                    </div>
                    <div class="withdrawal-quick-amounts" aria-label="Pilihan nominal cepat">
                        <button type="button" data-withdrawal-quick="50000">Rp 50.000</button>
                        <button type="button" data-withdrawal-quick="75000">Rp 75.000</button>
                        <button type="button" data-withdrawal-quick="all">Semua saldo</button>
                    </div>
                    <small class="withdrawal-hint" data-withdrawal-hint>Minimal Rp 5.000 dan maksimal sesuai saldo tersedia.</small>

                    <label for="withdrawal-bank">Bank / e-wallet tujuan</label>
                    <select id="withdrawal-bank" name="payoutBank" required>
                        <option value="">Pilih bank / e-wallet</option>
                        <optgroup label="Bank">
                            <option value="BCA">BCA</option>
                            <option value="BRI">BRI</option>
                            <option value="BNI">BNI</option>
                            <option value="Mandiri">Mandiri</option>
                            <option value="BTN">BTN</option>
                        </optgroup>
                        <optgroup label="E-wallet">
                            <option value="DANA">DANA</option>
                            <option value="GoPay">GoPay</option>
                            <option value="OVO">OVO</option>
                            <option value="ShopeePay">ShopeePay</option>
                            <option value="LinkAja">LinkAja</option>
                        </optgroup>
                    </select>

                    <label for="withdrawal-account-number">Nomor rekening / akun</label>
                    <input id="withdrawal-account-number" name="accountNumber" type="text" inputmode="numeric"
                        autocomplete="off" pattern="[0-9]{8,20}" maxlength="20" placeholder="Contoh: 1234567890"
                        required />
                    <small class="withdrawal-hint">Periksa kembali nomor tujuan. Transfer ke nomor yang salah tidak bisa dibatalkan.</small>

                    <label for="withdrawal-account-name">Nama pemilik rekening</label>
                    <input id="withdrawal-account-name" name="accountName" type="text" autocomplete="name"
                        maxlength="100" placeholder="Nama sesuai rekening" required />
                    <small class="withdrawal-hint">Pastikan nama sesuai dengan pemilik rekening agar penarikan tidak ditolak.</small>

                    <label class="withdrawal-save-account">
                        <input type="checkbox" name="saveAccount" value="1" />
                        <span>Simpan rekening ini untuk penarikan berikutnya</span>
                    </label>

                    <div class="withdrawal-notice">
                        <span aria-hidden="true">ⓘ</span>
                        <p><strong>Perlu persetujuan admin</strong><small>Dana dikirim setelah admin menyetujui, maksimal 1×24 jam kerja. Kami kabari lewat status penarikan.</small></p>
                    </div>
                    <div class="withdrawal-form-actions">
                        <a class="button button-secondary" href="beranda_user.html">Kembali ke beranda</a>
                        <button class="button button-primary" type="submit" data-withdrawal-submit>
                            Ajukan penarikan
                        </button>
                    </div>
                </form>
            </section>

            <aside class="withdrawal-aside">
                <section class="withdrawal-summary" aria-labelledby="withdrawal-summary-title">
                    <h2 id="withdrawal-summary-title">Ringkasan penarikan</h2>
                    <dl>
                        <div><dt>Nominal penarikan</dt><dd data-withdrawal-summary-amount>Rp 0</dd></div>
                        <div><dt>Biaya admin</dt><dd class="withdrawal-free">Gratis</dd></div>
                        <div class="withdrawal-total"><dt>Total diterima</dt><dd data-withdrawal-summary-total>Rp 0</dd></div>
                        <div><dt>Sisa saldo</dt><dd data-withdrawal-summary-remaining>Rp 0</dd></div>
                    </dl>
                    <div class="withdrawal-destination">
                        <span>Rekening tujuan</span>
                        <strong data-withdrawal-destination>Belum dipilih</strong>
                        <small data-withdrawal-destination-name></small>
                    </div>
                </section>
                <section class="withdrawal-flow" aria-labelledby="withdrawal-flow-title">
                    <h2 id="withdrawal-flow-title">Alur penarikan</h2>
                    <ol>
                        <li><span>1</span><p><strong>Kamu ajukan penarikan</strong><small>Isi nominal dan data rekening.</small></p></li>
                        <li><span>2</span><p><strong>Admin memeriksa dan menyetujui</strong><small>Saldo ditahan selama menunggu pemeriksaan.</small></p></li>
                        <li><span>3</span><p><strong>Dana dikirim ke rekeningmu</strong><small>Status diperbarui setelah transfer dilakukan.</small></p></li>
                    </ol>
                </section>
            </aside>
        </div>

        <section class="panel withdrawal-history" aria-labelledby="withdrawal-history-title">
            <div class="panel-heading">
                <div>
                    <h2 id="withdrawal-history-title">Riwayat penarikan</h2>
                    <p>Lihat status permintaan dan alasan jika penarikan ditolak.</p>
                </div>
            </div>
            <div class="withdrawal-history-list" data-withdrawal-history>
                <p class="inline-notice">Memuat riwayat penarikan...</p>
            </div>
        </section>
    </main>

    <footer class="app-footer">
        <a class="brand" href="beranda_user.html">EcoBank<span>.</span></a>
        <span>Lebih bijak mengelola sampah, lebih baik untuk bumi.</span>
    </footer>
    <script src="app.js?v=profile-menu-1"></script>
    <script src="withdrawals.js?v=withdrawals-2"></script>
</body>

</html>
