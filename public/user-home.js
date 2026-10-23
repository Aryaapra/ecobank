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
    let userTransactions = [];
    const groupUserTransactions = (items) => {
        const groups = new Map();
        items.forEach((item) => {
            const groupId = item.groupId || item.code || item.id;
            if (!groups.has(groupId)) groups.set(groupId, []);
            groups.get(groupId).push(item);
        });

        return Array.from(groups, ([groupId, groupItems]) => ({
            ...groupItems[0],
            id: groupItems.length > 1 ? groupId : groupItems[0].id,
            detail: groupItems.map((item) => item.detail).join(", "),
            weight: groupItems.reduce(
                (total, item) => total + Number(item.weight),
                0,
            ),
            value: groupItems.reduce(
                (total, item) => total + Number(item.value),
                0,
            ),
            items: groupItems,
        }));
    };
    const userWasteTypesDialog = document.querySelector(
        "[data-user-waste-types-dialog]",
    );
    const showUserWasteTypes = (transaction) => {
        if (!userWasteTypesDialog) return;
        const summary = userWasteTypesDialog.querySelector(
            "[data-user-waste-types-summary]",
        );
        const list = userWasteTypesDialog.querySelector(
            "[data-user-waste-types-list]",
        );
        if (!summary || !list) return;

        summary.textContent = `${transaction.items.length} jenis sampah`;
        list.innerHTML = transaction.items
            .map(
                (item) =>
                    `<li><span><strong>${escapeHtml(item.detail)}</strong><small>${escapeHtml(item.waste)} · ${Number(item.weight) > 0 ? `${formatWeight(item.weight)} kg` : "Belum ditimbang"} · ${formatRupiah(item.unitPrice)}/kg</small></span><b>${item.status === "approved" ? formatRupiah(item.value) : item.status === "cancelled" ? "—" : Number(item.weight) > 0 ? `Estimasi ${formatRupiah(item.value)}` : "Menunggu timbang"}</b></li>`,
            )
            .join("");
        userWasteTypesDialog.showModal();
    };
    const updateDepositHistoryScroll = () => {
        const wrapper = document.querySelector("[data-deposit-history-scroll]");
        const history = document.querySelector(".user-history-list");
        if (!wrapper || !history?.hasAttribute("data-full-history")) return;
        const rows = Array.from(history.querySelectorAll("tr"));
        if (rows.length <= 5) {
            wrapper.classList.remove("is-scrollable");
            wrapper.style.removeProperty("max-height");
            return;
        }
        wrapper.classList.add("is-scrollable");
        const headerHeight =
            wrapper.querySelector("thead")?.getBoundingClientRect().height || 0;
        const visibleRowsHeight = rows
            .slice(0, 5)
            .reduce(
                (height, row) => height + row.getBoundingClientRect().height,
                0,
            );
        wrapper.style.maxHeight = `${Math.ceil(headerHeight + visibleRowsHeight)}px`;
    };

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

            const depositTypes = document.querySelector("[data-deposit-types]");
            if (depositTypes) {
                depositTypes.innerHTML = result.data
                    .map(
                        (item) =>
                            `<label class="waste-type-option"><input type="checkbox" name="wasteTypeIds" value="${Number(item.id)}" /><span><strong>${escapeHtml(item.name)}</strong><small>${formatRupiah(item.price)}/kg</small></span></label>`,
                    )
                    .join("");
            }
            const editWasteSelect = document.querySelector(
                "[data-user-edit-type]",
            );
            if (editWasteSelect) {
                editWasteSelect.innerHTML = result.data
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

            const initials = result.data.name
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map((part) => part[0])
                .join("")
                .toUpperCase();
            const nameElement = document.querySelector("[data-user-name]");
            const districtElement = document.querySelector(
                "[data-user-district]",
            );
            const profileName = document.querySelector("[data-profile-name]");
            const userInitials = document.querySelector("[data-user-initials]");
            if (nameElement) nameElement.textContent = result.data.name;
            if (districtElement) {
                districtElement.lastChild.textContent = ` ${result.data.district}`;
            }
            if (profileName) profileName.textContent = result.data.name;
            if (userInitials) userInitials.textContent = initials;
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
                cache: "no-store",
            });
            const result = await response.json();
            if (!response.ok)
                throw new Error(result.error || "Riwayat tidak dapat dimuat.");

            const history = document.querySelector(".user-history-list");
            const transactions = groupUserTransactions(result.data);
            userTransactions = transactions;
            if (history) {
                const statusLabel = (transaction) => {
                    if (transaction.status === "approved") return "Selesai";
                    if (transaction.status === "cancelled") return "Dibatalkan";
                    return (
                        {
                            requested:
                                transaction.deliveryMethod === "pickup"
                                    ? "Menunggu konfirmasi penjemputan"
                                    : "Menunggu sampah diantar",
                            pickup_confirmed: "Penjemputan dikonfirmasi",
                            weighing: "Menunggu penimbangan",
                        }[transaction.stage] || "Menunggu proses"
                    );
                };
                const visibleTransactions = history.hasAttribute(
                    "data-full-history",
                )
                    ? transactions
                    : history.hasAttribute("data-deposit-requests")
                      ? transactions.filter(
                            (transaction) =>
                                transaction.status === "pending" &&
                                (transaction.stage === "requested" ||
                                    (transaction.deliveryMethod ===
                                        "self_delivery" &&
                                        transaction.stage === "weighing")),
                        )
                      : transactions.slice(0, 5);
                if (history.tagName === "TBODY") {
                    history.innerHTML = visibleTransactions.length
                        ? visibleTransactions
                              .map((transaction) => {
                                  const hasWeight =
                                      Number(transaction.weight) > 0;
                                  const weightText = hasWeight
                                      ? `${formatWeight(transaction.weight)} kg`
                                      : "Belum ditimbang";
                                  const valueText =
                                      transaction.status === "approved"
                                          ? formatRupiah(transaction.value)
                                          : transaction.status === "cancelled"
                                            ? "—"
                                            : hasWeight
                                              ? `Estimasi ${formatRupiah(transaction.value)}`
                                              : "Menunggu timbang";
                                  const canEdit =
                                      transaction.items.length === 1 &&
                                      transaction.status === "pending" &&
                                      transaction.stage === "requested";
                                  const canCancel =
                                      transaction.status === "pending" &&
                                      (transaction.stage === "requested" ||
                                          (transaction.deliveryMethod ===
                                              "self_delivery" &&
                                              transaction.stage ===
                                                  "weighing"));
                                  const actionButtons = [
                                      canEdit
                                          ? `<button class="table-action" type="button" data-user-transaction-action="edit" data-transaction-id="${Number(transaction.dbId)}">Edit</button>`
                                          : "",
                                      canCancel
                                          ? `<button class="table-action cancel" type="button" data-user-transaction-action="cancel" data-transaction-id="${Number(transaction.dbId)}">Batal</button>`
                                          : "",
                                  ].filter(Boolean);
                                  const actions = actionButtons.length
                                      ? `<div class="table-actions">${actionButtons.join("")}</div>`
                                      : "—";
                                  return `
                            <tr>
                                <td>${escapeHtml(transaction.date)}</td>
                                <td><button class="table-action view-waste-types" type="button" data-user-view-waste-types="${Number(transaction.dbId)}">Lihat jenis sampah</button><small>${escapeHtml(transaction.id)}</small></td>
                                <td>${weightText}</td>
                                <td><span class="history-status status-${escapeHtml(transaction.status)}">${statusLabel(transaction)}</span></td>
                                <td>${valueText}</td>
                                <td>${actions}</td>
                            </tr>`;
                              })
                              .join("")
                        : `<tr><td colspan="6">${history.hasAttribute("data-deposit-requests") ? "Tidak ada setoran yang menunggu diproses." : "Belum ada setoran. Ajukan setoran pertamamu."}</td></tr>`;
                    updateDepositHistoryScroll();
                } else {
                    history.innerHTML = visibleTransactions.length
                        ? visibleTransactions
                              .map((transaction) => {
                                  const hasWeight =
                                      Number(transaction.weight) > 0;
                                  const weightText = hasWeight
                                      ? `${formatWeight(transaction.weight)} kg`
                                      : "Belum ditimbang";
                                  const valueText =
                                      transaction.status === "approved"
                                          ? `+ ${formatRupiah(transaction.value)}`
                                          : transaction.status === "cancelled"
                                            ? "—"
                                            : hasWeight
                                              ? `Estimasi ${formatRupiah(transaction.value)}`
                                              : "Menunggu timbang";
                                  return `
                        <div class="user-history-row">
                            <span class="history-icon">♻</span>
                            <span><button class="table-action view-waste-types" type="button" data-user-view-waste-types="${Number(transaction.dbId)}">Lihat jenis sampah</button><small>${statusLabel(transaction)} · ${weightText} · ${escapeHtml(transaction.id)}</small></span>
                            <time>${escapeHtml(transaction.date)}</time>
                            <b>${valueText}</b>
                        </div>`;
                              })
                              .join("")
                        : '<p class="inline-notice">Belum ada setoran. Mulai dari setoran pertamamu.</p>';
                }
            }

            const balance = Number(result.summary.balance);
            const totalWeight = Number(result.summary.approvedWeight);
            const balanceElement = document.querySelector(
                "[data-user-balance]",
            );
            if (balanceElement)
                balanceElement.textContent = formatRupiah(balance);
            const historyWeight = document.querySelector(
                "[data-history-weight]",
            );
            const historyValue = document.querySelector("[data-history-value]");
            const historyPending = document.querySelector(
                "[data-history-pending]",
            );
            if (historyWeight)
                historyWeight.textContent = formatWeight(totalWeight);
            if (historyValue) historyValue.textContent = formatRupiah(balance);
            if (historyPending) {
                historyPending.textContent = `${Number(result.summary.pendingCount)} setoran`;
            }
        } catch (error) {
            showStatus(error.message);
        }
    };

    const loadRankings = async () => {
        try {
            const response = await fetch("api/rankings", {
                headers: { Accept: "application/json" },
                cache: "no-store",
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
            const message =
                error instanceof TypeError
                    ? "Peringkat belum dapat dimuat. Periksa koneksi lalu coba lagi."
                    : error.message;
            const podium = document.querySelector("[data-ranking-podium]");
            if (podium) {
                podium.setAttribute("aria-label", message);
                podium.innerHTML = `<span class="ranking-widget-empty">${escapeHtml(message)}</span>`;
            }
            const contributorList = document.querySelector(
                "[data-top-contributors]",
            );
            if (contributorList)
                contributorList.innerHTML = `<li class="ranking-widget-empty">${escapeHtml(message)}</li>`;
            const wasteList = document.querySelector(
                "[data-user-waste-rankings]",
            );
            if (wasteList)
                wasteList.innerHTML = `<li class="ranking-widget-empty">${escapeHtml(message)}</li>`;
            showStatus(message);
        }
    };

    const depositForm = document.querySelector("[data-deposit-form]");
    if (depositForm) {
        const depositTypes = depositForm.querySelector("[data-deposit-types]");
        const selectedTypesLabel = document.querySelector(
            "[data-deposit-selected]",
        );
        const updateSelectedTypesLabel = () => {
            const selectedCount = depositTypes.querySelectorAll(
                '[name="wasteTypeIds"]:checked',
            ).length;
            selectedTypesLabel.textContent = selectedCount
                ? `${selectedCount} jenis dipilih`
                : "Pilih jenis sampah";
        };
        depositTypes?.addEventListener("change", updateSelectedTypesLabel);
        document
            .querySelector("[data-waste-search]")
            ?.addEventListener("input", (event) => {
                const searchTerm = event.currentTarget.value
                    .trim()
                    .toLocaleLowerCase("id");
                depositTypes
                    .querySelectorAll(".waste-type-option")
                    .forEach((option) => {
                        option.hidden = !option.textContent
                            .toLocaleLowerCase("id")
                            .includes(searchTerm);
                    });
            });
        const pickupAddressWrap = document.querySelector(
            "[data-pickup-address-wrap]",
        );
        const pickupAddress = depositForm.elements.namedItem("pickupAddress");
        const deliveryMethod = depositForm.elements.namedItem("deliveryMethod");
        const updateDeliveryFields = () => {
            const needsPickup = deliveryMethod?.value === "pickup";
            if (pickupAddressWrap) pickupAddressWrap.hidden = !needsPickup;
            if (pickupAddress) pickupAddress.required = needsPickup;
        };
        depositForm
            .querySelectorAll('[name="deliveryMethod"]')
            .forEach((option) => {
                option.addEventListener("change", updateDeliveryFields);
            });
        updateDeliveryFields();

        depositForm.addEventListener("submit", async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('[type="submit"]');
            const statusMessage = document.querySelector(
                "[data-deposit-status]",
            );
            const wasteTypeIds = Array.from(
                form.querySelectorAll('[name="wasteTypeIds"]:checked'),
                (checkbox) => Number(checkbox.value),
            );
            if (!wasteTypeIds.length) {
                statusMessage.textContent =
                    "Pilih setidaknya satu jenis sampah.";
                return;
            }
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
                        wasteTypeIds,
                        deliveryMethod:
                            deliveryMethod?.value ?? "self_delivery",
                        pickupAddress: pickupAddress?.value.trim() ?? "",
                    }),
                });
                const result = await response.json();
                if (!response.ok)
                    throw new Error(result.error || "Setoran gagal dikirim.");
                const selectedNames = result.items
                    .map((transaction) => transaction.detail)
                    .join(", ");
                statusMessage.textContent =
                    result.data.deliveryMethod === "pickup"
                        ? `Permintaan ${selectedNames} tersimpan. Tunggu konfirmasi penjemputan dari pengurus.`
                        : `Permintaan ${selectedNames} tersimpan. Bawa sampah ke bank untuk penimbangan.`;
                form.reset();
                updateSelectedTypesLabel();
                document.querySelector(".waste-type-picker").open = false;
                document.querySelector("[data-waste-search]").value = "";
                depositTypes
                    .querySelectorAll(".waste-type-option")
                    .forEach((option) => {
                        option.hidden = false;
                    });
                updateDeliveryFields();
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
    }

    const userEditDialog = document.querySelector("[data-user-edit-dialog]");
    const userEditForm = document.querySelector("[data-user-edit-form]");
    let editingTransaction = null;
    const updateUserTransaction = async (transaction, payload) => {
        const response = await fetch(`api/transactions/${transaction.dbId}`, {
            method: "PUT",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken(),
            },
            body: JSON.stringify(payload),
        });
        const result = await response.json();
        if (!response.ok) {
            throw new Error(
                result.error || "Perubahan setoran gagal disimpan.",
            );
        }
        return result.data;
    };
    const updateEditAddress = () => {
        const method = userEditForm?.elements.namedItem("deliveryMethod");
        const address = userEditForm?.elements.namedItem("pickupAddress");
        const addressWrap = document.querySelector(
            "[data-user-edit-address-wrap]",
        );
        const needsPickup = method?.value === "pickup";
        if (addressWrap) addressWrap.hidden = !needsPickup;
        if (address) address.required = needsPickup;
    };
    userEditForm
        ?.querySelectorAll('[name="deliveryMethod"]')
        .forEach((option) =>
            option.addEventListener("change", updateEditAddress),
        );
    userEditForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!editingTransaction) return;
        const submitButton = userEditForm.querySelector('[type="submit"]');
        const feedback = document.querySelector("[data-user-edit-feedback]");
        submitButton.disabled = true;
        if (feedback) feedback.textContent = "";
        try {
            await updateUserTransaction(editingTransaction, {
                action: "edit",
                wasteTypeId: Number(userEditForm.elements.wasteTypeId.value),
                deliveryMethod: userEditForm.elements.deliveryMethod.value,
                pickupAddress: userEditForm.elements.pickupAddress.value.trim(),
            });
            userEditDialog.close();
            editingTransaction = null;
            await loadHistory();
            showStatus("Perubahan setoran berhasil disimpan.");
        } catch (error) {
            if (feedback) feedback.textContent = error.message;
        } finally {
            submitButton.disabled = false;
        }
    });
    document
        .querySelector("[data-user-edit-cancel]")
        ?.addEventListener("click", () => {
            editingTransaction = null;
            userEditDialog?.close();
        });
    document.addEventListener("click", (event) => {
        const button = event.target.closest("[data-user-view-waste-types]");
        if (!button) return;
        const transaction = userTransactions.find(
            (item) =>
                Number(item.dbId) ===
                Number(button.dataset.userViewWasteTypes),
        );
        if (transaction) showUserWasteTypes(transaction);
    });
    document
        .querySelector("[data-user-waste-types-close]")
        ?.addEventListener("click", () => userWasteTypesDialog?.close());
    document.addEventListener("click", async (event) => {
        const button = event.target.closest("[data-user-transaction-action]");
        if (!button) return;
        const transaction = userTransactions.find(
            (item) =>
                Number(item.dbId) === Number(button.dataset.transactionId),
        );
        if (!transaction) return;
        if (button.dataset.userTransactionAction === "edit") {
            if (!userEditDialog || !userEditForm) return;
            editingTransaction = transaction;
            userEditForm.elements.wasteTypeId.value = String(
                transaction.wasteTypeId,
            );
            userEditForm.elements.deliveryMethod.value =
                transaction.deliveryMethod;
            userEditForm.elements.pickupAddress.value =
                transaction.pickupAddress || "";
            updateEditAddress();
            userEditDialog.showModal();
            return;
        }
        if (!window.confirm(`Batalkan setoran ${transaction.id}?`)) return;
        button.disabled = true;
        try {
            await updateUserTransaction(transaction, { action: "cancel" });
            await loadHistory();
            showStatus("Setoran berhasil dibatalkan.");
        } catch (error) {
            showStatus(error.message);
        } finally {
            button.disabled = false;
        }
    });

    if (document.querySelector("[data-user-name]")) loadUser();
    if (
        !document.body.classList.contains("user-home-page") &&
        document.querySelector(
            "[data-user-price-list], [data-deposit-types], [data-user-edit-type]",
        )
    )
        loadPrices();
    if (document.querySelector(".user-history-list, [data-user-balance]")) {
        loadHistory();
        if (document.querySelector("[data-user-balance]")) {
            let historyRefreshInProgress = false;
            const refreshHistory = async () => {
                if (document.hidden || historyRefreshInProgress) return;
                historyRefreshInProgress = true;
                try {
                    await loadHistory();
                } finally {
                    historyRefreshInProgress = false;
                }
            };
            window.addEventListener("focus", refreshHistory);
            document.addEventListener("visibilitychange", refreshHistory);
            window.setInterval(refreshHistory, 30000);
        }
    }
    if (document.querySelector("[data-ranking-podium]")) loadRankings();
    window.addEventListener("resize", updateDepositHistoryScroll);
})();
