(() => {
    "use strict";

    const status = document.querySelector("[data-user-status]");
    const showStatus = (message) => {
        if (!status) return;
        status.hidden = false;
        status.textContent = message;
    };
    const escapeHtml = (value) =>
        String(value).replace(
            /[&<>"']/g,
            (character) =>
                ({
                    "&": "&amp;",
                    "<": "&lt;",
                    ">": "&gt;",
                    '"': "&quot;",
                    "'": "&#39;",
                })[character],
        );
    const formatRupiah = (value) =>
        `Rp ${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(Number(value))}`;
    const formatWeight = (value) =>
        new Intl.NumberFormat("id-ID", { maximumFractionDigits: 2 }).format(
            Number(value),
        );
    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content ?? "";

    const loadPrices = async () => {
        const statusMessage = document.querySelector(
            "[data-user-price-status]",
        );
        try {
            const response = await fetch("api/prices", {
                headers: { Accept: "application/json" },
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(
                    result.error || "Harga sampah tidak dapat dimuat.",
                );
            }

            document
                .querySelectorAll("[data-user-price-list]")
                .forEach((list) => {
                    const items = result.data.filter(
                        (item) => item.category === list.dataset.userPriceList,
                    );
                    list.innerHTML = items.length
                        ? items
                              .map(
                                  (item) =>
                                      `<tr><td>${escapeHtml(item.name)}</td><td>${formatRupiah(item.price)}</td></tr>`,
                              )
                              .join("")
                        : '<tr><td colspan="2">Belum ada harga aktif.</td></tr>';
                });

            const wasteSelect = document.querySelector("[data-deposit-type]");
            if (wasteSelect) {
                wasteSelect.innerHTML =
                    '<option value="">Pilih jenis sampah</option>' +
                    result.data
                        .map(
                            (item) =>
                                `<option value="${Number(item.id)}">${escapeHtml(item.name)} · ${formatRupiah(item.price)}/kg</option>`,
                        )
                        .join("");
            }
        } catch (error) {
            if (statusMessage) {
                statusMessage.hidden = false;
                statusMessage.textContent =
                    error instanceof TypeError
                        ? "Harga belum dapat dimuat dari server. Coba muat ulang halaman."
                        : error.message;
            }
        }
    };

    const loadUser = async () => {
        if (location.protocol === "file:") {
            showStatus(
                "Buka EcoBank melalui http://localhost/EcoBank/ untuk memuat akun.",
            );
            return;
        }

        try {
            const response = await fetch("api/auth/me", {
                headers: { Accept: "application/json" },
            });
            const result = await response.json();
            if (response.status === 401) {
                window.location.replace("auth.html");
                return;
            }
            if (!response.ok) {
                throw new Error(result.error || "Akun tidak dapat dimuat.");
            }
            if (result.data.role === "admin") {
                window.location.replace("dashboard_bank_sampah.html");
                return;
            }

            document.querySelector("[data-user-name]").textContent =
                result.data.name;
            document.querySelector(
                "[data-user-district]",
            ).lastChild.textContent = ` ${result.data.district}`;
            document.querySelector("[data-profile-name]").textContent =
                result.data.name;
            document.querySelector("[data-user-initials]").textContent =
                result.data.name
                    .trim()
                    .split(/\s+/)
                    .slice(0, 2)
                    .map((part) => part[0])
                    .join("")
                    .toUpperCase();
        } catch (error) {
            showStatus(
                error instanceof TypeError
                    ? "Tidak dapat menghubungi server EcoBank. Periksa koneksi Apache dan MySQL."
                    : error.message,
            );
        }
    };

    const loadHistory = async () => {
        try {
            const response = await fetch("api/transactions", {
                headers: { Accept: "application/json" },
            });
            const result = await response.json();
            if (!response.ok)
                throw new Error(result.error || "Riwayat tidak dapat dimuat.");

            const history = document.querySelector(".user-history-list");
            const transactions = result.data;
            if (history) {
                history.innerHTML = transactions.length
                    ? transactions
                          .slice(0, 5)
                          .map(
                              (transaction) => `
                        <div class="user-history-row">
                            <span class="history-icon">♻</span>
                            <span><strong>${escapeHtml(transaction.detail)}</strong><small>${escapeHtml(transaction.status)} · ${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 1 }).format(transaction.weight)} kg · ${escapeHtml(transaction.id)}</small></span>
                            <time>${escapeHtml(transaction.date)}</time>
                            <b>${transaction.status === "approved" ? "+ " : ""}${formatRupiah(transaction.value)}</b>
                        </div>`,
                          )
                          .join("")
                    : '<p class="inline-notice">Belum ada setoran. Mulai dari setoran pertamamu.</p>';
            }

            const balance = transactions
                .filter((transaction) => transaction.status === "approved")
                .reduce(
                    (sum, transaction) => sum + Number(transaction.value),
                    0,
                );
            const balanceElement = document.querySelector(
                "[data-user-balance]",
            );
            if (balanceElement)
                balanceElement.textContent = formatRupiah(balance);
        } catch (error) {
            showStatus(error.message);
        }
    };

    const loadRankings = async () => {
        try {
            const response = await fetch("api/rankings", {
                headers: { Accept: "application/json" },
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(
                    result.error || "Peringkat tidak dapat dimuat.",
                );
            }

            const { rankings, wasteRankings, currentUser } = result.data;
            const podium = document.querySelector("[data-ranking-podium]");
            const topThree = rankings.slice(0, 3);
            const podiumClasses = [
                "person-second",
                "person-first",
                "person-third",
            ];
            const podiumPositions = [topThree[1], topThree[0], topThree[2]];
            if (podium) {
                podium.setAttribute(
                    "aria-label",
                    topThree.length
                        ? "Tiga kontributor teratas bulan ini; setoran menunggu verifikasi ditampilkan sementara"
                        : "Belum ada setoran disetujui bulan ini",
                );
                podium.innerHTML = topThree.length
                    ? podiumPositions
                          .map((entry, index) =>
                              entry
                                  ? `<span class="podium-person ${podiumClasses[index]}" title="${entry.has_pending ? "Termasuk setoran menunggu verifikasi" : "Setoran disetujui"}"><i>${escapeHtml(entry.initials)}</i><b>${entry.rank}</b></span>`
                                  : "",
                          )
                          .join("") +
                      `<span class="mini-podium-base base-second"></span><span class="mini-podium-base base-first"></span><span class="mini-podium-base base-third"></span>`
                    : '<span class="ranking-widget-empty">Belum ada setoran disetujui bulan ini.</span>';
            }

            const rankState = document.querySelector("[data-user-rank-state]");
            const rankPosition = document.querySelector(
                "[data-user-rank-position]",
            );
            const rankWeight = document.querySelector(
                "[data-user-rank-weight]",
            );
            if (rankState && rankPosition && rankWeight) {
                rankState.textContent = currentUser.hasPending
                    ? "Sementara"
                    : currentUser.hasContribution
                      ? currentUser.rank
                          ? "Anda"
                          : "Di luar 100 besar"
                      : "Belum setor";
                rankPosition.textContent = currentUser.rank
                    ? `#${currentUser.rank}`
                    : "—";
                rankWeight.textContent = `${currentUser.weight_label} kg`;
            }

            const contributorList = document.querySelector(
                "[data-top-contributors]",
            );
            if (contributorList) {
                contributorList.innerHTML = topThree.length
                    ? topThree
                          .map(
                              (entry) =>
                                  `<li><span>#${entry.rank}</span><i class="leader-avatar">${escapeHtml(entry.initials)}</i><strong>${escapeHtml(entry.name)}${entry.has_pending ? '<small class="leaderboard-pending">Ada setoran menunggu</small>' : ""}</strong><b>${escapeHtml(entry.weight_label)} kg</b></li>`,
                          )
                          .join("")
                    : '<li class="ranking-widget-empty">Belum ada kontributor bulan ini.</li>';
            }

            const wasteList = document.querySelector(
                "[data-user-waste-rankings]",
            );
            if (wasteList) {
                wasteList.innerHTML = wasteRankings.length
                    ? wasteRankings
                          .map(
                              (item) =>
                                  `<li><span>${escapeHtml(item.name)}</span><b>${escapeHtml(item.weight_label)} <small>kg</small></b></li>`,
                          )
                          .join("")
                    : '<li class="ranking-widget-empty">Belum ada material terkumpul bulan ini.</li>';
            }
        } catch (error) {
            showStatus(error.message);
        }
    };

    document
        .querySelector("[data-deposit-form]")
        ?.addEventListener("submit", async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('[type="submit"]');
            const statusMessage = document.querySelector(
                "[data-deposit-status]",
            );
            button.disabled = true;
            button.textContent = "Mengirim...";
            try {
                const response = await fetch("api/transactions", {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrfToken(),
                    },
                    body: JSON.stringify({
                        wasteTypeId: Number(form.elements.wasteTypeId.value),
                        weight: form.elements.weight.value,
                    }),
                });
                const result = await response.json();
                if (!response.ok)
                    throw new Error(result.error || "Setoran gagal dikirim.");
                statusMessage.textContent = `Permintaan ${result.data.id} berhasil dikirim dan menunggu verifikasi.`;
                form.reset();
                await loadHistory();
                await loadRankings();
            } catch (error) {
                statusMessage.textContent = error.message;
            } finally {
                button.disabled = false;
                button.innerHTML =
                    'Kirim permintaan <span aria-hidden="true">→</span>';
            }
        });

    loadUser();
    loadPrices();
    loadHistory();
    loadRankings();
})();
