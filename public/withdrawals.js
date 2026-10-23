(() => {
    "use strict";

    const rupiah = new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: 0,
    });
    const formatRupiah = (value) => `Rp ${rupiah.format(Number(value) || 0)}`;
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
    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content ?? "";
    const feedback = document.querySelector("[data-withdrawal-feedback]");
    const setFeedback = (message, success = false) => {
        if (!feedback) return;
        feedback.hidden = !message;
        feedback.textContent = message;
        feedback.classList.toggle("is-success", success);
    };
    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: "same-origin",
            cache: "no-store",
            ...options,
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfToken(),
                ...(options.headers ?? {}),
            },
        });
        const result = await response.json();
        if (!response.ok) {
            const error = new Error(
                result.error || "Permintaan tidak dapat diproses.",
            );
            error.status = response.status;
            throw error;
        }
        return result;
    };
    const statusLabels = {
        pending: "Menunggu pemeriksaan",
        approved: "Disetujui · menunggu transfer",
        paid: "Dana sudah dikirim",
        rejected: "Ditolak",
        cancelled: "Dibatalkan",
    };
    const formatDate = (value) =>
        value
            ? new Intl.DateTimeFormat("id-ID", {
                  dateStyle: "medium",
                  timeStyle: "short",
              }).format(new Date(value))
            : "—";
    const renderHistory = (withdrawals) => {
        const list = document.querySelector("[data-withdrawal-history]");
        if (!list) return;
        list.classList.toggle("is-scrollable", withdrawals.length > 5);
        list.style.removeProperty("max-height");
        list.innerHTML = withdrawals.length
            ? withdrawals
                  .map(
                      (item) => `
                <article class="withdrawal-history-item">
                    <div class="withdrawal-history-main">
                        <strong>${escapeHtml(item.code)} · ${formatRupiah(item.amount)}</strong>
                        <small>${escapeHtml(item.payoutBank)} · ${escapeHtml(item.accountNumber)} · ${escapeHtml(item.accountName)}</small>
                        <time>${escapeHtml(formatDate(item.createdAt))}</time>
                        ${item.rejectionReason ? `<small class="withdrawal-reason">Alasan: ${escapeHtml(item.rejectionReason)}</small>` : ""}
                        ${item.transferReference ? `<small>Referensi transfer: ${escapeHtml(item.transferReference)}</small>` : ""}
                    </div>
                    <div class="withdrawal-history-status">
                        <span class="withdrawal-status status-${escapeHtml(item.status)}">${escapeHtml(statusLabels[item.status] || item.status)}</span>
                        ${item.status === "pending" && document.body.dataset.page === "user-withdrawals" ? `<button class="text-link" type="button" data-cancel-withdrawal="${Number(item.id)}">Batalkan permintaan</button>` : ""}
                    </div>
                </article>`,
                  )
                  .join("")
            : '<p class="inline-notice">Belum ada permintaan penarikan.</p>';
        const updateScrollHeight = () => {
            if (!list.classList.contains("is-scrollable")) return;
            const items = Array.from(
                list.querySelectorAll(".withdrawal-history-item"),
            );
            const visibleHeight = items
                .slice(0, 5)
                .reduce((height, item) => height + item.getBoundingClientRect().height, 0);
            list.style.maxHeight = `${Math.ceil(visibleHeight)}px`;
        };
        updateScrollHeight();
        if (list.dataset.scrollResizeBound !== "true") {
            window.addEventListener("resize", updateScrollHeight);
            list.dataset.scrollResizeBound = "true";
        }
    };

    if (document.body.dataset.page === "user-history") {
        request("api/withdrawals")
            .then((result) => renderHistory(result.data))
            .catch((error) => {
                if (error.status === 401) {
                    window.location.replace("auth.html");
                    return;
                }
                const list = document.querySelector("[data-withdrawal-history]");
                if (list) {
                    list.classList.remove("is-scrollable");
                    list.innerHTML = `<p class="inline-notice">${escapeHtml(error.message)}</p>`;
                }
            });
    }

    if (document.body.dataset.page === "user-withdrawals") {
        const form = document.querySelector("[data-withdrawal-form]");
        const amountInput = form?.elements.namedItem("amount");
        const bankInput = form?.elements.namedItem("payoutBank");
        const numberInput = form?.elements.namedItem("accountNumber");
        const nameInput = form?.elements.namedItem("accountName");
        const availableElement = document.querySelector(
            "[data-withdrawal-balance]",
        );
        const minimumElement = document.querySelector(
            "[data-withdrawal-minimum]",
        );
        let availableBalance = 0;
        let minimumAmount = 5000;
        let loadingUserData = false;

        const renderSummary = () => {
            const amount = Math.max(0, Number(amountInput?.value) || 0);
            document.querySelector(
                "[data-withdrawal-summary-amount]",
            ).textContent = formatRupiah(amount);
            document.querySelector(
                "[data-withdrawal-summary-total]",
            ).textContent = formatRupiah(amount);
            document.querySelector(
                "[data-withdrawal-summary-remaining]",
            ).textContent = formatRupiah(Math.max(0, availableBalance - amount));
            const bank = bankInput?.value || "";
            const accountNumber = numberInput?.value.trim() || "";
            document.querySelector(
                "[data-withdrawal-destination]",
            ).textContent = bank
                ? `${bank}${accountNumber ? ` · ${accountNumber}` : ""}`
                : "Belum dipilih";
            document.querySelector(
                "[data-withdrawal-destination-name]",
            ).textContent = nameInput?.value.trim() || "";
        };

        const loadUserWithdrawals = async () => {
            if (loadingUserData) return;
            loadingUserData = true;
            try {
                const result = await request("api/withdrawals");
                const payload = result.data;
                availableBalance = Number(result.summary.availableBalance) || 0;
                minimumAmount = Number(result.summary.minimumAmount) || 5000;
                availableElement.textContent = formatRupiah(availableBalance);
                minimumElement.textContent = formatRupiah(minimumAmount);
                amountInput.min = String(minimumAmount);
                amountInput.max = String(availableBalance);

                const saved = result.savedAccount;
                if (saved?.bank) bankInput.value = saved.bank;
                if (saved?.accountNumber) numberInput.value = saved.accountNumber;
                if (saved?.accountName) nameInput.value = saved.accountName;
                document.querySelectorAll("[data-withdrawal-quick]").forEach((button) => {
                    if (button.dataset.withdrawalQuick !== "all") {
                        button.disabled = Number(button.dataset.withdrawalQuick) > availableBalance;
                    }
                });
                const allButtons = document.querySelectorAll(
                    "[data-withdrawal-all], [data-withdrawal-quick='all']",
                );
                allButtons.forEach((button) => {
                    button.disabled = availableBalance < minimumAmount;
                });
                renderHistory(payload);
                renderSummary();
                const submit = document.querySelector("[data-withdrawal-submit]");
                if (submit) submit.disabled = availableBalance < minimumAmount;
                const hint = document.querySelector("[data-withdrawal-hint]");
                if (hint && availableBalance < minimumAmount) {
                    hint.textContent = `Saldo tersedia belum mencapai minimal penarikan ${formatRupiah(minimumAmount)}.`;
                }
            } catch (error) {
                if (error.status === 401) {
                    window.location.replace("auth.html");
                    return;
                }
                setFeedback(error.message);
                const list = document.querySelector("[data-withdrawal-history]");
                if (list) list.innerHTML = `<p class="inline-notice">${escapeHtml(error.message)}</p>`;
                if (availableElement) availableElement.textContent = "Saldo tidak tersedia";
            } finally {
                loadingUserData = false;
            }
        };

        amountInput?.addEventListener("input", renderSummary);
        [bankInput, numberInput, nameInput].forEach((input) =>
            input?.addEventListener("input", renderSummary),
        );
        bankInput?.addEventListener("change", renderSummary);
        document.querySelectorAll("[data-withdrawal-quick]").forEach((button) => {
            button.addEventListener("click", () => {
                amountInput.value =
                    button.dataset.withdrawalQuick === "all"
                        ? String(availableBalance)
                        : button.dataset.withdrawalQuick;
                renderSummary();
            });
        });
        document
            .querySelector("[data-withdrawal-all]")
            ?.addEventListener("click", () => {
                amountInput.value = String(availableBalance);
                renderSummary();
            });

        form?.addEventListener("submit", async (event) => {
            event.preventDefault();
            setFeedback("");
            const submit = document.querySelector("[data-withdrawal-submit]");
            submit.disabled = true;
            submit.textContent = "Mengirim permintaan...";
            try {
                await request("api/withdrawals", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        amount: Number(amountInput.value),
                        payoutBank: bankInput.value,
                        accountNumber: numberInput.value.trim(),
                        accountName: nameInput.value.trim(),
                        saveAccount: form.elements.namedItem("saveAccount").checked,
                    }),
                });
                setFeedback(
                    "Permintaan penarikan terkirim. Saldo sudah ditahan sementara menunggu pemeriksaan admin.",
                    true,
                );
                amountInput.value = "";
                await loadUserWithdrawals();
            } catch (error) {
                setFeedback(error.message);
            } finally {
                submit.textContent = "Ajukan penarikan";
                submit.disabled = availableBalance < minimumAmount;
            }
        });

        document
            .querySelector("[data-withdrawal-history]")
            ?.addEventListener("click", async (event) => {
                const button = event.target.closest("[data-cancel-withdrawal]");
                if (!button) return;
                if (!window.confirm("Batalkan permintaan penarikan ini? Saldo akan tersedia kembali.")) return;
                button.disabled = true;
                try {
                    await request(
                        `api/withdrawals/${Number(button.dataset.cancelWithdrawal)}/cancel`,
                        { method: "POST" },
                    );
                    setFeedback("Permintaan dibatalkan. Saldo kembali tersedia.", true);
                    await loadUserWithdrawals();
                } catch (error) {
                    setFeedback(error.message);
                    button.disabled = false;
                }
            });

        loadUserWithdrawals();
        window.addEventListener("focus", loadUserWithdrawals);
        document.addEventListener("visibilitychange", () => {
            if (!document.hidden) loadUserWithdrawals();
        });
        window.setInterval(() => {
            if (!document.hidden) loadUserWithdrawals();
        }, 30000);
    }

    if (document.body.dataset.page === "withdrawal-admin") {
        let withdrawals = [];
        const filter = document.querySelector(
            "[data-withdrawal-admin-filter]",
        );
        const list = document.querySelector("[data-withdrawal-admin-list]");
        const count = document.querySelector("[data-withdrawal-admin-count]");
        let loadingAdminData = false;
        const renderAdminList = () => {
            const selectedStatus = filter.value;
            const visible = withdrawals.filter(
                (item) => selectedStatus === "all" || item.status === selectedStatus,
            );
            count.textContent = `${visible.length} dari ${withdrawals.length} permintaan`;
            list.innerHTML = visible.length
                ? visible
                      .map(
                          (item) => `
                    <tr>
                        <td><strong>${escapeHtml(item.code)}</strong><small>${escapeHtml(formatDate(item.createdAt))}</small></td>
                        <td>${escapeHtml(item.userName || "Anggota")}</td>
                        <td><strong>${formatRupiah(item.amount)}</strong></td>
                        <td><strong>${escapeHtml(item.payoutBank)} · ${escapeHtml(item.accountNumber)}</strong><small>${escapeHtml(item.accountName)}</small></td>
                        <td><span class="withdrawal-status status-${escapeHtml(item.status)}">${escapeHtml(statusLabels[item.status] || item.status)}</span>${item.rejectionReason ? `<small class="withdrawal-reason">${escapeHtml(item.rejectionReason)}</small>` : ""}${item.transferReference ? `<small>Ref: ${escapeHtml(item.transferReference)}</small>` : ""}</td>
                        <td class="withdrawal-admin-actions">
                            ${item.status === "pending" ? `<button class="table-action" type="button" data-withdrawal-action="approve" data-withdrawal-id="${Number(item.id)}">Setujui</button><button class="table-action cancel" type="button" data-withdrawal-action="reject" data-withdrawal-id="${Number(item.id)}">Tolak</button>` : ""}
                            ${item.status === "approved" ? `<button class="table-action" type="button" data-withdrawal-action="mark_paid" data-withdrawal-id="${Number(item.id)}">Tandai terkirim</button>` : ""}
                            ${["paid", "rejected", "cancelled"].includes(item.status) ? "—" : ""}
                        </td>
                    </tr>`,
                      )
                      .join("")
                : '<tr><td colspan="6">Tidak ada permintaan dengan status ini.</td></tr>';
        };
        const loadAdminWithdrawals = async () => {
            if (loadingAdminData) return;
            loadingAdminData = true;
            try {
                const result = await request("api/withdrawals");
                withdrawals = result.data;
                renderAdminList();
            } catch (error) {
                if (error.status === 401 || error.status === 403) {
                    window.location.replace("beranda_user.html");
                    return;
                }
                setFeedback(error.message);
                list.innerHTML = `<tr><td colspan="6">${escapeHtml(error.message)}</td></tr>`;
            } finally {
                loadingAdminData = false;
            }
        };
        filter?.addEventListener("change", renderAdminList);
        list?.addEventListener("click", async (event) => {
            const button = event.target.closest("[data-withdrawal-action]");
            if (!button) return;
            const action = button.dataset.withdrawalAction;
            let reason;
            let reference;
            if (action === "reject") {
                reason = window.prompt("Masukkan alasan penolakan:");
                if (!reason?.trim()) return;
            }
            if (action === "mark_paid") {
                if (!window.confirm("Pastikan transfer sudah benar-benar dilakukan. Tandai dana sudah dikirim?")) return;
                reference = window.prompt("Nomor referensi transfer (opsional):") || "";
            }
            if (
                action === "approve" &&
                !window.confirm("Setujui penarikan ini? Saldo anggota tetap ditahan sampai transfer dikirim.")
            ) return;

            button.disabled = true;
            try {
                await request(`api/withdrawals/${Number(button.dataset.withdrawalId)}`, {
                    method: "PUT",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ action, reason, reference }),
                });
                setFeedback("Status penarikan berhasil diperbarui.", true);
                await loadAdminWithdrawals();
            } catch (error) {
                setFeedback(error.message);
                button.disabled = false;
            }
        });
        loadAdminWithdrawals();
        window.addEventListener("focus", loadAdminWithdrawals);
        document.addEventListener("visibilitychange", () => {
            if (!document.hidden) loadAdminWithdrawals();
        });
        window.setInterval(() => {
            if (!document.hidden) loadAdminWithdrawals();
        }, 30000);
    }
})();
