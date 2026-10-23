(() => {
    "use strict";

    const revealSelector = [
        ".landing-page main > section:not(.hero)",
        ".about-grid article",
        ".impact-grid > *",
        ".map-card",
        ".app-main > .stats-grid",
        ".app-main > .dashboard-grid",
        ".app-main > .panel",
        ".ranking-board",
        ".ranking-details > *",
        ".price-grid > *",
        ".transaction-layout > *",
        ".user-cover",
        ".user-summary > *",
        ".user-rankings > *",
        ".user-prices",
        ".user-request",
        ".user-history",
        ".user-news",
    ].join(",");
    const revealTargets = document.querySelectorAll(revealSelector);
    const prefersReducedMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)",
    ).matches;

    if (
        revealTargets.length &&
        !prefersReducedMotion &&
        "IntersectionObserver" in window
    ) {
        document.documentElement.classList.add("motion-ready");
        const groupSteps = new WeakMap();
        const revealObserver = new IntersectionObserver(
            (entries, observer) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.dataset.motionState = "visible";
                    observer.unobserve(entry.target);
                });
            },
            { rootMargin: "0px 0px -48px 0px", threshold: 0.08 },
        );

        revealTargets.forEach((target) => {
            const group = target.parentElement;
            const step = groupSteps.get(group) ?? 0;
            groupSteps.set(group, step + 1);
            target.style.setProperty(
                "--motion-delay",
                `${Math.min(step * 85, 340)}ms`,
            );
            target.dataset.motionState = "pending";
            revealObserver.observe(target);
        });

        const revealInViewport = () => {
            revealTargets.forEach((target) => {
                if (target.dataset.motionState !== "pending") return;
                const bounds = target.getBoundingClientRect();
                if (
                    bounds.top < window.innerHeight * 0.92 &&
                    bounds.bottom > 0
                ) {
                    target.dataset.motionState = "visible";
                    revealObserver.unobserve(target);
                }
            });
        };
        window.addEventListener("scroll", revealInViewport, {
            passive: true,
        });
        window.addEventListener("resize", revealInViewport, {
            passive: true,
        });
        revealInViewport();
    }

    const adminPages = new Set([
        "dashboard",
        "transactions",
        "members",
        "prices",
        "ranking",
        "withdrawal-admin",
    ]);
    if (adminPages.has(document.body.dataset.page)) {
        fetch("api/auth/me", { headers: { Accept: "application/json" } })
            .then(async (response) => {
                const result = await response.json();
                if (response.status === 401) {
                    window.location.replace("auth.html");
                    return;
                }
                if (!response.ok || result.data?.role !== "admin") {
                    window.location.replace("beranda_user.html");
                }
            })
            .catch(() => window.location.replace("auth.html"));
    }

    const rupiah = new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 });
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
    let prices = [];
    let transactions = [];

    const requestApi = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: "same-origin",
            ...options,
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN":
                    document.querySelector('meta[name="csrf-token"]')
                        ?.content ?? "",
                ...(options.headers ?? {}),
            },
        });
        const result = await response.json();
        if (!response.ok) {
            const error = new Error(
                result.error || "Permintaan ke server gagal.",
            );
            error.status = response.status;
            throw error;
        }
        return result.data;
    };

    if (document.querySelector("[data-profile-control]")) {
        fetch("api/auth/me", { headers: { Accept: "application/json" } })
            .then(async (response) => {
                if (!response.ok) return;
                const result = await response.json();
                const user = result.data;
                const initials = user.name
                    .trim()
                    .split(/\s+/)
                    .slice(0, 2)
                    .map((part) => part[0])
                    .join("")
                    .toUpperCase();
                document
                    .querySelectorAll("[data-profile-name]")
                    .forEach((node) => {
                        node.textContent = user.name;
                    });
                document
                    .querySelectorAll("[data-profile-toggle]")
                    .forEach((node) => {
                        node.textContent = initials;
                    });
            })
            .catch(() => {});
    }

    const formatPrice = (value) => `Rp ${rupiah.format(value)}`;
    const formatWeight = (value) =>
        `${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 1 }).format(value)} kg`;
    const groupTransactions = (entries) => {
        const groups = new Map();
        entries.forEach((entry) => {
            const groupId = entry.groupId || entry.code || entry.id;
            if (!groups.has(groupId)) groups.set(groupId, []);
            groups.get(groupId).push(entry);
        });

        return Array.from(groups, ([groupId, items]) => {
            const first = items[0];
            const wasteCategories = Array.from(
                new Set(items.map((item) => item.waste)),
            );
            return {
                ...first,
                id: items.length > 1 ? groupId : first.id,
                code: groupId,
                detail: items.map((item) => item.detail).join(", "),
                waste: wasteCategories.join(" "),
                wasteCategories,
                weight: items.reduce(
                    (total, item) => total + Number(item.weight),
                    0,
                ),
                value: items.reduce(
                    (total, item) => total + Number(item.value),
                    0,
                ),
                items,
            };
        });
    };
    const setPendingCounters = () => {
        const count = groupTransactions(transactions).filter((transaction) =>
            ["pending", "pickup_confirmed", "weighing"].includes(
                transaction.status,
            ),
        ).length;
        document
            .querySelectorAll("[data-pending-count], [data-pending-total]")
            .forEach((node) => {
                node.textContent = String(count);
            });
        document.querySelectorAll("[data-pending-label]").forEach((node) => {
            node.textContent = `${count} menunggu`;
        });
    };

    document.querySelectorAll(".menu-toggle").forEach((button) => {
        button.addEventListener("click", () => {
            const navigation = button.closest("header")?.querySelector("nav");
            if (!navigation) return;
            const isOpen = navigation.classList.toggle("is-open");
            button.setAttribute("aria-expanded", String(isOpen));
            if (isOpen) {
                button
                    .closest("header")
                    ?.querySelector(".profile-dropdown")
                    ?.setAttribute("hidden", "");
                button
                    .closest("header")
                    ?.querySelector("[data-profile-toggle]")
                    ?.setAttribute("aria-expanded", "false");
            }
        });
    });

    document.querySelectorAll("[data-profile-toggle]").forEach((button) => {
        button.addEventListener("click", () => {
            const control = button.closest("[data-profile-control]");
            const dropdown = control?.querySelector(".profile-dropdown");
            if (!control || !dropdown) return;
            const isOpen = dropdown.hidden;
            dropdown.hidden = !isOpen;
            button.setAttribute("aria-expanded", String(isOpen));
            if (isOpen) {
                control
                    .closest("header")
                    ?.querySelector(".app-nav")
                    ?.classList.remove("is-open");
                control
                    .closest("header")
                    ?.querySelector(".menu-toggle")
                    ?.setAttribute("aria-expanded", "false");
            }
        });
    });

    document.addEventListener("click", (event) => {
        document
            .querySelectorAll("[data-profile-control]")
            .forEach((control) => {
                const dropdown = control.querySelector(".profile-dropdown");
                const toggle = control.querySelector("[data-profile-toggle]");
                if (!dropdown || !toggle) return;
                if (control.contains(event.target)) return;
                dropdown.hidden = true;
                toggle.setAttribute("aria-expanded", "false");
            });
    });

    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape") return;
        document
            .querySelectorAll("[data-profile-control]")
            .forEach((control) => {
                const dropdown = control.querySelector(".profile-dropdown");
                const button = control.querySelector("[data-profile-toggle]");
                if (!dropdown || !button || dropdown.hidden) return;
                dropdown.hidden = true;
                button.setAttribute("aria-expanded", "false");
                button.focus();
            });
    });

    const logoutButtons = document.querySelectorAll("[data-profile-logout]");
    if (logoutButtons.length) {
        const logoutDialog = document.createElement("dialog");
        logoutDialog.className = "confirm-dialog logout-confirm-dialog";
        logoutDialog.setAttribute("aria-labelledby", "logout-confirm-title");
        logoutDialog.innerHTML = `
            <span class="confirm-icon danger" aria-hidden="true">!</span>
            <h2 id="logout-confirm-title">Yakin ingin keluar?</h2>
            <p>Anda perlu masuk kembali untuk mengakses akun EcoBank.</p>
            <div class="dialog-actions">
                <button class="button button-secondary" type="button" data-logout-cancel>Batal</button>
                <button class="button button-cancel" type="button" data-logout-confirm>Ya, keluar</button>
            </div>
        `;
        document.body.append(logoutDialog);

        let selectedLogoutButton = null;
        logoutButtons.forEach((button) => {
            button.addEventListener("click", (event) => {
                event.preventDefault();
                selectedLogoutButton = button;
                logoutDialog.showModal();
                logoutDialog.querySelector("[data-logout-cancel]").focus();
            });
        });

        logoutDialog
            .querySelector("[data-logout-cancel]")
            .addEventListener("click", () => {
                selectedLogoutButton = null;
                logoutDialog.close();
            });

        logoutDialog
            .querySelector("[data-logout-confirm]")
            .addEventListener("click", async (event) => {
                if (!selectedLogoutButton) return;

                const button = selectedLogoutButton;
                const control = button.closest("[data-profile-control]");
                const feedback = control?.querySelector(
                    "[data-profile-feedback]",
                );
                const confirmButton = event.currentTarget;
                confirmButton.disabled = true;
                button.disabled = true;

                try {
                    const response = await fetch("api/auth", {
                        method: "POST",
                        headers: {
                            Accept: "application/json",
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN":
                                document.querySelector(
                                    'meta[name="csrf-token"]',
                                )?.content ?? "",
                        },
                        body: JSON.stringify({ action: "logout" }),
                    });
                    const result = await response.json();
                    if (!response.ok)
                        throw new Error(result.error || "Tidak dapat keluar.");
                    window.location.assign(
                        button.dataset.logoutTarget || "auth.html",
                    );
                } catch (error) {
                    if (feedback) {
                        feedback.hidden = false;
                        feedback.textContent =
                            location.protocol === "file:"
                                ? "Buka EcoBank melalui localhost untuk keluar dari akun."
                                : error.message;
                    }
                    button.disabled = false;
                    confirmButton.disabled = false;
                    logoutDialog.close();
                    selectedLogoutButton = null;
                }
            });
    }

    const renderPrices = (query = "") => {
        const normalizedQuery = query.trim().toLocaleLowerCase("id");
        document.querySelectorAll("[data-waste-list]").forEach((list) => {
            const category = list.dataset.wasteList;
            const categoryItems = prices.filter(
                (item) => item.category === category,
            );
            const filteredItems = categoryItems.filter((item) =>
                `${item.name} ${item.category}`
                    .toLocaleLowerCase("id")
                    .includes(normalizedQuery),
            );
            const card = list.closest(".price-category");
            card.classList.toggle("is-empty", filteredItems.length === 0);
            const categoryTotal = card.querySelector(".category-total");
            categoryTotal.textContent = `${categoryItems.length} jenis`;
            list.innerHTML = filteredItems
                .map(
                    (item) => `
                <div class="waste-row">
                    <strong title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</strong>
                    <span>${formatPrice(item.price)}<small> /kg</small></span>
                    <div class="waste-actions">
                        <button class="icon-action" type="button" data-edit-price="${escapeHtml(item.id)}" aria-label="Ubah ${escapeHtml(item.name)}" title="Ubah harga">✎</button>
                        <button class="icon-action delete" type="button" data-delete-price="${escapeHtml(item.id)}" aria-label="Hapus ${escapeHtml(item.name)}" title="Hapus jenis sampah">×</button>
                    </div>
                </div>`,
                )
                .join("");
        });
        const total = document.querySelector("[data-price-count]");
        if (total) total.textContent = `${prices.length} jenis sampah`;
    };

    const priceDialog = document.querySelector("[data-price-dialog]");
    const priceForm = document.querySelector("[data-price-form]");
    const priceNotice = document.querySelector("[data-price-notice]");
    const loadPrices = async () => {
        if (!priceDialog || !priceForm) return;
        priceNotice.textContent = "Memuat daftar harga...";
        try {
            prices = await requestApi("api/prices");
            renderPrices(
                document.querySelector("[data-price-search]")?.value ?? "",
            );
            priceNotice.textContent = "";
        } catch (error) {
            prices = [];
            renderPrices();
            priceNotice.textContent =
                location.protocol === "file:"
                    ? "Buka EcoBank melalui localhost untuk memuat harga dari database."
                    : error.message;
        }
    };
    const openPriceDialog = (item = null) => {
        if (!priceDialog || !priceForm) return;
        priceForm.reset();
        priceForm.elements.id.value = item?.id ?? "";
        priceForm.elements.name.value = item?.name ?? "";
        priceForm.elements.category.value = item?.category ?? "Plastik";
        priceForm.elements.price.value = item?.price ?? "";
        priceDialog.querySelector("[data-dialog-title]").textContent = item
            ? "Ubah jenis sampah"
            : "Tambah jenis sampah";
        priceDialog.showModal();
        priceForm.elements.name.focus();
    };
    document
        .querySelector("[data-add-waste]")
        ?.addEventListener("click", () => openPriceDialog());
    document
        .querySelector("[data-price-search]")
        ?.addEventListener("input", (event) =>
            renderPrices(event.target.value),
        );
    document
        .querySelector(".price-grid")
        ?.addEventListener("click", async (event) => {
            const editButton = event.target.closest("[data-edit-price]");
            const deleteButton = event.target.closest("[data-delete-price]");
            if (editButton) {
                const item = prices.find(
                    (entry) =>
                        Number(entry.id) ===
                        Number(editButton.dataset.editPrice),
                );
                if (item) openPriceDialog(item);
            }
            if (!deleteButton) return;

            const item = prices.find(
                (entry) =>
                    Number(entry.id) ===
                    Number(deleteButton.dataset.deletePrice),
            );
            if (
                !item ||
                !window.confirm(`Hapus ${item.name} dari daftar harga?`)
            )
                return;

            deleteButton.disabled = true;
            try {
                await requestApi(`api/prices/${encodeURIComponent(item.id)}`, {
                    method: "DELETE",
                });
                prices = prices.filter(
                    (entry) => Number(entry.id) !== Number(item.id),
                );
                renderPrices(
                    document.querySelector("[data-price-search]")?.value ?? "",
                );
                priceNotice.textContent = `${item.name} berhasil dihapus.`;
            } catch (error) {
                priceNotice.textContent =
                    location.protocol === "file:"
                        ? "Buka EcoBank melalui localhost untuk mengubah harga."
                        : error.message;
                deleteButton.disabled = false;
            }
        });
    document
        .querySelector("[data-dialog-cancel]")
        ?.addEventListener("click", () => priceDialog?.close());
    priceForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!priceForm.reportValidity()) return;
        const item = {
            name: priceForm.elements.name.value.trim(),
            category: priceForm.elements.category.value,
            price: Number(priceForm.elements.price.value),
        };
        if (!item.name || !Number.isFinite(item.price) || item.price < 1)
            return;
        const id = priceForm.elements.id.value;
        const submitButton = priceForm.querySelector('[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = "Menyimpan...";
        try {
            const data = await requestApi(
                id ? `api/prices/${encodeURIComponent(id)}` : "api/prices",
                {
                    method: id ? "PUT" : "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(item),
                },
            );
            const savedItem = {
                ...data,
                id: Number(data.id),
                price: Number(data.price),
            };
            if (id) {
                prices = prices.map((entry) =>
                    Number(entry.id) === Number(id) ? savedItem : entry,
                );
            } else {
                prices.push(savedItem);
            }
            renderPrices(
                document.querySelector("[data-price-search]")?.value ?? "",
            );
            priceNotice.textContent = `${savedItem.name} disimpan dengan harga ${formatPrice(savedItem.price)}/kg.`;
            priceDialog.close();
        } catch (error) {
            priceNotice.textContent =
                location.protocol === "file:"
                    ? "Buka EcoBank melalui localhost untuk menyimpan harga."
                    : error.message;
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = "Simpan perubahan";
        }
    });
    if (priceDialog) loadPrices();

    const statusLabels = {
        pending: "Pending",
        approved: "Selesai",
        cancelled: "Dibatalkan",
    };
    const stageLabels = {
        requested: "Menunggu proses",
        pickup_confirmed: "Jemput dikonfirmasi",
        weighing: "Menunggu penimbangan",
        completed: "Selesai ditimbang",
        cancelled: "Dibatalkan",
    };
    const transactionStatusLabel = (transaction) =>
        transaction.status === "pending"
            ? `Pending · ${stageLabels[transaction.stage] ?? "Menunggu proses"}`
            : (statusLabels[transaction.status] ?? "Menunggu");
    const selectedReportMonth = () =>
        document.querySelector("[data-report-month]")?.value ?? "";
    const transactionsForSelectedMonth = () => {
        const month = selectedReportMonth();
        const selected = month
            ? transactions.filter((transaction) => transaction.month === month)
            : transactions;
        return groupTransactions(selected);
    };
    const renderRecentTransactions = () => {
        document
            .querySelectorAll("[data-recent-transactions]")
            .forEach((list) => {
                const monthTransactions = transactionsForSelectedMonth();
                list.innerHTML = monthTransactions.length
                    ? monthTransactions
                          .slice(0, 3)
                          .map((transaction) => {
                              const isApproved =
                                  transaction.status === "approved";
                              const weightLabel =
                                  Number(transaction.weight) > 0
                                      ? formatWeight(transaction.weight)
                                      : "Belum ditimbang";
                              const transactionValue = isApproved
                                  ? `<b class="amount-positive">+ ${formatPrice(transaction.value)}</b>`
                                  : `<b>${escapeHtml(transactionStatusLabel(transaction))}</b>`;

                              return `<div class="recent-row"><span class="recent-icon">♻</span><strong>${escapeHtml(transaction.detail)}</strong><small>${escapeHtml(transaction.name)} · ${weightLabel}</small><time>${escapeHtml(transaction.date)}</time>${transactionValue}</div>`;
                          })
                          .join("")
                    : `<p class="inline-notice">${transactions.length ? "Tidak ada transaksi pada bulan yang dipilih." : "Belum ada transaksi tercatat."}</p>`;
            });
    };
    const transactionRows = document.querySelector("[data-transaction-rows]");
    const renderTransactions = () => {
        if (!transactionRows) {
            setPendingCounters();
            return;
        }
        const query = (
            document.querySelector("[data-transaction-search]")?.value ?? ""
        )
            .trim()
            .toLocaleLowerCase("id");
        const status =
            document.querySelector("[data-status-filter]")?.value ?? "all";
        const stage =
            document.querySelector("[data-stage-filter]")?.value ?? "all";
        const wasteType =
            document.querySelector("[data-type-filter]")?.value ?? "all";
        const month = selectedReportMonth();
        const pendingGroups = groupTransactions(transactions).filter(
            (transaction) => transaction.status === "pending",
        );
        const filtered = pendingGroups.filter((transaction) => {
            const matchesQuery =
                `${transaction.name} ${transaction.id} ${transaction.waste} ${transaction.detail} ${transaction.items.map((item) => item.code).join(" ")}`
                    .toLocaleLowerCase("id")
                    .includes(query);
            return (
                matchesQuery &&
                (!month || transaction.month === month) &&
                (status === "all" || transaction.status === status) &&
                (stage === "all" || transaction.stage === stage) &&
                (wasteType === "all" ||
                    transaction.wasteCategories.includes(wasteType))
            );
        });
        const periodTransactions = transactions.filter(
            (transaction) => !month || transaction.month === month,
        );
        const periodGroups = groupTransactions(periodTransactions);
        const approvedTransactions = periodTransactions.filter(
            (transaction) => transaction.status === "approved",
        );
        const reportCount = document.querySelector(
            "[data-report-transaction-count]",
        );
        const reportPending = document.querySelector(
            "[data-report-pending-count]",
        );
        const reportWeight = document.querySelector(
            "[data-report-approved-weight]",
        );
        const reportValue = document.querySelector(
            "[data-report-approved-value]",
        );
        if (reportCount)
            reportCount.textContent = rupiah.format(periodGroups.length);
        if (reportPending) {
            reportPending.textContent = rupiah.format(
                periodGroups.filter(
                    (transaction) => transaction.status === "pending",
                ).length,
            );
        }
        if (reportWeight) {
            reportWeight.textContent = approvedTransactions
                .reduce(
                    (total, transaction) => total + Number(transaction.weight),
                    0,
                )
                .toLocaleString("id-ID", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
        }
        if (reportValue) {
            reportValue.textContent = `Rp ${rupiah.format(
                approvedTransactions.reduce(
                    (total, transaction) => total + Number(transaction.value),
                    0,
                ),
            )}`;
        }
        transactionRows.innerHTML = filtered
            .map((transaction) => {
                const active = transaction.status === "pending";
                const action = !active
                    ? null
                    : transaction.stage === "weighing"
                      ? ["finalize_weighing", "Konfirmasi berat"]
                      : transaction.deliveryMethod === "pickup" &&
                          transaction.stage === "requested"
                        ? ["confirm_pickup", "Konfirmasi jemput"]
                        : ["start_weighing", "Mulai timbang"];
                const service =
                    transaction.deliveryMethod === "pickup"
                        ? `<strong>Jemput</strong><small>${escapeHtml(transaction.pickupAddress || "Alamat belum diisi")}</small><small>${escapeHtml(transaction.userPhone || transaction.userDistrict || "")}</small>`
                        : `<strong>Antar mandiri</strong>`;
                const amount =
                    transaction.status === "approved"
                        ? formatPrice(transaction.value)
                        : transaction.status === "cancelled"
                          ? "—"
                          : Number(transaction.weight) > 0
                            ? `Estimasi ${formatPrice(transaction.value)}`
                            : "Menunggu timbang";
                const weight =
                    Number(transaction.weight) > 0
                        ? formatWeight(transaction.weight)
                        : "Belum ditimbang";
                const actions = active
                    ? `<div class="table-actions">${action ? `<button class="table-action" type="button" data-transaction-action="${action[0]}" data-transaction-id="${escapeHtml(transaction.dbId)}">${action[1]}</button>` : ""}<button class="table-action cancel" type="button" data-transaction-action="cancel" data-transaction-id="${escapeHtml(transaction.dbId)}">Batalkan</button></div>`
                    : "—";

                return `
            <tr>
                <td><div class="user-cell"><span class="user-avatar">${escapeHtml(transaction.avatar || "♻")}</span><span>${escapeHtml(transaction.name)}<small>${escapeHtml(transaction.id)}</small></span></div></td>
                <td><button class="table-action view-waste-types" type="button" data-view-waste-types="${escapeHtml(transaction.dbId)}">Lihat jenis sampah</button></td><td class="transaction-service-cell">${service}</td><td>${weight}${Number(transaction.weight) > 0 && transaction.status !== "approved" && transaction.status !== "cancelled" ? " · perkiraan" : ""}</td><td>${amount}</td><td>${escapeHtml(transaction.date)}</td>
                <td><span class="status-tag ${escapeHtml(transaction.status)}">${escapeHtml(transactionStatusLabel(transaction))}</span></td>
                <td>${actions}</td>
            </tr>`;
            })
            .join("");
        const emptyState = document.querySelector("[data-transactions-empty]");
        if (emptyState) emptyState.hidden = filtered.length > 0;
        const count = document.querySelector("[data-transaction-count]");
        if (count)
            count.textContent = `Menampilkan ${filtered.length} dari ${pendingGroups.length} setoran yang belum selesai${month ? ` · ${month}` : ""}`;
        setPendingCounters();
    };

    const loadTransactions = async () => {
        try {
            transactions = await requestApi("api/transactions");
            renderTransactions();
            renderRecentTransactions();
        } catch (error) {
            if (error.status === 401) {
                window.location.replace("auth.html");
                return;
            }
            if (error.status === 403) {
                window.location.replace("beranda_user.html");
                return;
            }
            const count = document.querySelector("[data-transaction-count]");
            if (count) count.textContent = error.message;
        }
    };

    let pendingAction = null;
    const confirmationDialog = document.querySelector("[data-confirm-dialog]");
    const weighingDialog = document.querySelector("[data-weigh-dialog]");
    const weighingForm = document.querySelector("[data-weigh-form]");
    const wasteTypesDialog = document.querySelector(
        "[data-waste-types-dialog]",
    );
    const runTransactionAction = async (
        transaction,
        action,
        actualWeights = null,
    ) => {
        const result = await requestApi(
            `api/transactions/${transaction.dbId}`,
            {
                method: "PUT",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    action,
                    ...(actualWeights ? { actualWeights } : {}),
                }),
            },
        );
        (result.items ?? [result]).forEach((item) => {
            const updated = transactions.find(
                (entry) => Number(entry.dbId) === Number(item.id),
            );
            if (!updated) return;
            updated.status = item.status;
            updated.stage = item.stage;
            updated.weight = item.weight;
            updated.value = item.value;
        });
        confirmationDialog?.close();
        weighingDialog?.close();
        pendingAction = null;
        const feedback = document.querySelector("[data-transaction-feedback]");
        if (feedback) {
            feedback.hidden = false;
            feedback.textContent =
                action === "confirm_pickup"
                    ? "Penjemputan dikonfirmasi. Setoran tetap berstatus pending."
                    : action === "start_weighing"
                      ? "Penimbangan dimulai. Setoran tetap berstatus pending hingga berat aktual disimpan."
                      : action === "finalize_weighing"
                        ? "Berat aktual tersimpan dan saldo pengguna sudah diperbarui."
                        : "Setoran dibatalkan.";
        }
        renderTransactions();
        renderRecentTransactions();
    };

    const askForTransactionConfirmation = (transaction, action) => {
        pendingAction = { transaction, action };
        if (action === "finalize_weighing") {
            document.querySelector("[data-weigh-dialog] h2").textContent =
                "Konfirmasi hasil timbang";
            document.querySelector(
                "[data-weigh-form] [type=submit]",
            ).textContent = "Simpan berat & masukkan saldo";
            document.querySelector("[data-weigh-summary]").textContent =
                `${transaction.name} · timbang setiap jenis sampah secara terpisah.`;
            document.querySelector("[data-weigh-feedback]").textContent = "";
            const weighItems = document.querySelector("[data-weigh-items]");
            weighItems.innerHTML = transaction.items
                .map(
                    (item) => `
                        <label>${escapeHtml(item.detail)} · ${formatPrice(item.unitPrice)}/kg
                            <input type="number" min="0.01" max="10000" step="0.01" required data-actual-weight data-transaction-id="${Number(item.dbId)}" />
                        </label>`,
                )
                .join("");
            weighingForm.reset();
            weighingDialog.showModal();
            weighItems.querySelector("input")?.focus();
            return;
        }
        if (!confirmationDialog) return;
        const cancel = action === "cancel";
        confirmationDialog
            .querySelector("[data-confirm-icon]")
            .classList.toggle("danger", cancel);
        confirmationDialog.querySelector("[data-confirm-icon]").textContent =
            cancel ? "×" : "⚖";
        confirmationDialog.querySelector("[data-confirm-title]").textContent =
            cancel ? "Batalkan setoran?" : "Batalkan setoran?";
        confirmationDialog.querySelector("[data-confirm-copy]").textContent =
            cancel
                ? `Setoran ${transaction.name} akan dibatalkan.`
                : `Setoran ${transaction.name} akan dibatalkan.`;
        const acceptButton = confirmationDialog.querySelector(
            "[data-confirm-accept]",
        );
        acceptButton.textContent = cancel ? "Ya, batalkan" : "Ya, batalkan";
        acceptButton.classList.toggle("button-primary", !cancel);
        acceptButton.classList.toggle("button-cancel", cancel);
        confirmationDialog.showModal();
    };
    document
        .querySelector("[data-confirm-cancel]")
        ?.addEventListener("click", () => {
            pendingAction = null;
            confirmationDialog?.close();
        });
    document
        .querySelector("[data-confirm-accept]")
        ?.addEventListener("click", async (event) => {
            if (!pendingAction) return;
            const { transaction, action } = pendingAction;
            const button = event.currentTarget;
            button.disabled = true;
            try {
                await runTransactionAction(transaction, action);
            } catch (error) {
                const feedback = document.querySelector(
                    "[data-transaction-feedback]",
                );
                if (feedback) {
                    feedback.hidden = false;
                    feedback.textContent = error.message;
                }
            } finally {
                button.disabled = false;
            }
        });
    document
        .querySelector("[data-weigh-cancel]")
        ?.addEventListener("click", () => {
            pendingAction = null;
            weighingDialog?.close();
        });
    weighingForm?.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!pendingAction) return;
        const button = weighingForm.querySelector('[type="submit"]');
        button.disabled = true;
        try {
            const actualWeights = Object.fromEntries(
                Array.from(
                    weighingForm.querySelectorAll("[data-actual-weight]"),
                    (input) => [input.dataset.transactionId, input.value],
                ),
            );
            await runTransactionAction(
                pendingAction.transaction,
                pendingAction.action,
                actualWeights,
            );
        } catch (error) {
            document.querySelector("[data-weigh-feedback]").textContent =
                error.message;
        } finally {
            button.disabled = false;
        }
    });
    transactionRows?.addEventListener("click", (event) => {
        const wasteTypesButton = event.target.closest(
            "[data-view-waste-types]",
        );
        if (wasteTypesButton) {
            const transaction = groupTransactions(transactions).find(
                (entry) =>
                    Number(entry.dbId) ===
                    Number(wasteTypesButton.dataset.viewWasteTypes),
            );
            if (!transaction || !wasteTypesDialog) return;

            document.querySelector("[data-waste-types-summary]").textContent =
                `${transaction.name} · ${transaction.items.length} jenis sampah`;
            document.querySelector("[data-waste-types-list]").innerHTML =
                transaction.items
                    .map((item) => {
                        const itemWeight =
                            Number(item.weight) > 0
                                ? formatWeight(item.weight)
                                : "Belum ditimbang";
                        const itemValue =
                            item.status === "approved"
                                ? formatPrice(item.value)
                                : item.status === "cancelled"
                                  ? "—"
                                  : Number(item.weight) > 0
                                    ? `Estimasi ${formatPrice(item.value)}`
                                    : "Menunggu timbang";
                        return `<li><span><strong>${escapeHtml(item.detail)}</strong><small>${escapeHtml(item.waste)} · ${itemWeight} · ${formatPrice(item.unitPrice)}/kg</small></span><b>${itemValue}</b></li>`;
                    })
                    .join("");
            wasteTypesDialog.showModal();
            return;
        }

        const button = event.target.closest("[data-transaction-action]");
        if (!button) return;
        const transaction = groupTransactions(transactions).find(
            (entry) =>
                Number(entry.dbId) === Number(button.dataset.transactionId),
        );
        if (!transaction || !button.dataset.transactionAction) return;
        const action = button.dataset.transactionAction;
        if (
            action === "finalize_weighing" ||
            action === "edit_weight" ||
            action === "cancel"
        ) {
            askForTransactionConfirmation(transaction, action);
            return;
        }

        button.disabled = true;
        button.textContent = "Memproses...";
        runTransactionAction(transaction, action)
            .catch((error) => {
                const feedback = document.querySelector(
                    "[data-transaction-feedback]",
                );
                if (feedback) {
                    feedback.hidden = false;
                    feedback.textContent = error.message;
                }
            })
            .finally(() => {
                button.disabled = false;
            });
    });
    document
        .querySelector("[data-waste-types-close]")
        ?.addEventListener("click", () => wasteTypesDialog?.close());
    const historyWasteTypesDialog = document.querySelector(
        "[data-history-waste-types-dialog]",
    );
    document.addEventListener("click", (event) => {
        const button = event.target.closest("[data-history-waste-types]");
        if (!button || !historyWasteTypesDialog) return;

        const summary = historyWasteTypesDialog.querySelector(
            "[data-history-waste-types-summary]",
        );
        const list = historyWasteTypesDialog.querySelector(
            "[data-history-waste-types-list]",
        );
        if (!summary || !list) return;

        let items;
        try {
            items = JSON.parse(button.dataset.historyWasteTypes);
        } catch {
            summary.textContent = "Rincian jenis sampah tidak dapat dibaca.";
            list.replaceChildren();
            historyWasteTypesDialog.showModal();
            return;
        }
        if (!Array.isArray(items) || !summary || !list) return;

        const transactionCode =
            button.closest("tr")?.querySelector("td")?.textContent?.trim() ??
            "";
        summary.textContent = `${transactionCode} · ${items.length} jenis sampah`;
        list.innerHTML = items
            .map(
                (item) =>
                    `<li><span><strong>${escapeHtml(item.name)}</strong><small>${escapeHtml(item.category)} · ${Number(item.weight) > 0 ? formatWeight(item.weight) : "Belum ditimbang"} · ${formatPrice(item.unitPrice)}/kg</small></span><b>${formatPrice(item.value)}</b></li>`,
            )
            .join("");
        historyWasteTypesDialog.showModal();
    });
    document
        .querySelector("[data-history-waste-types-close]")
        ?.addEventListener("click", () => historyWasteTypesDialog?.close());
    [
        "[data-transaction-search]",
        "[data-status-filter]",
        "[data-stage-filter]",
        "[data-type-filter]",
    ].forEach((selector) => {
        document
            .querySelector(selector)
            ?.addEventListener(
                selector.includes("search") ? "input" : "change",
                renderTransactions,
            );
    });
    if (adminPages.has(document.body.dataset.page)) loadTransactions();
    if (document.body.dataset.page === "transactions") {
        const wasteRankingList = document.querySelector(
            "[data-monthly-waste-rankings]",
        );
        requestApi("api/rankings")
            .then(({ wasteRankings }) => {
                if (!wasteRankingList) return;
                wasteRankingList.innerHTML = wasteRankings.length
                    ? wasteRankings
                          .map(
                              (item, index) =>
                                  `<div class="leaderboard-row"><span>#${index + 1}</span><b>${escapeHtml(item.name)}</b><strong>${escapeHtml(item.weight_label)} kg</strong></div>`,
                          )
                          .join("")
                    : '<p class="inline-notice">Belum ada setoran disetujui bulan ini.</p>';
            })
            .catch((error) => {
                if (wasteRankingList)
                    wasteRankingList.innerHTML = `<p class="inline-notice">${escapeHtml(error.message)}</p>`;
            });
    }
    if (document.body.dataset.page === "dashboard") {
        const chartPeriodSelect = document.querySelector("[data-chart-period]");
        const chartDescription = document.querySelector(
            "[data-chart-description]",
        );
        const chartFeedback = document.querySelector("[data-chart-feedback]");
        const chart = document.querySelector("[data-weekly-chart]");
        const chartRequest = { id: 0 };
        const chartDescriptions = {
            "7d": "Berat setoran yang disetujui dalam 7 hari terakhir",
            "30d": "Berat setoran yang disetujui dalam 30 hari terakhir",
            "12m": "Berat setoran yang disetujui dalam 12 bulan terakhir",
        };

        const loadDashboard = async (period = "7d") => {
            const requestId = ++chartRequest.id;
            if (chartFeedback) {
                chartFeedback.hidden = false;
                chartFeedback.textContent = "Memuat data grafik...";
            }

            try {
                const summary = await requestApi(
                    `api/dashboard?period=${encodeURIComponent(period)}`,
                );
                if (requestId !== chartRequest.id) return;

                document
                    .querySelectorAll("[data-total-members]")
                    .forEach((node) => {
                        node.textContent = new Intl.NumberFormat("id-ID").format(
                            summary.totalMembers,
                        );
                    });
                document
                    .querySelectorAll("[data-total-transactions]")
                    .forEach((node) => {
                        node.textContent = String(summary.totalTransactions);
                    });
                document
                    .querySelectorAll("[data-pending-total]")
                    .forEach((node) => {
                        node.textContent = String(summary.pendingTransactions);
                    });
                document
                    .querySelectorAll("[data-total-weight]")
                    .forEach((node) => {
                        node.textContent = formatWeight(summary.totalWeight);
                    });
                document
                    .querySelectorAll("[data-total-value]")
                    .forEach((node) => {
                        node.textContent = formatPrice(summary.totalValue);
                    });
                document
                    .querySelectorAll("[data-month-weight]")
                    .forEach((node) => {
                        node.textContent = formatWeight(summary.monthlyWeight);
                    });
                document
                    .querySelectorAll("[data-month-transactions]")
                    .forEach((node) => {
                        node.textContent = String(summary.monthlyTransactions);
                    });

                const weeklyChart = document.querySelector(
                    "[data-weekly-chart]",
                );
                if (weeklyChart) {
                    const maximumWeight = Math.max(
                        ...summary.chartWeights.map((day) => day.weight),
                    );
                    weeklyChart.dataset.chartPeriod = summary.chartPeriod;
                    weeklyChart.innerHTML = maximumWeight
                        ? summary.chartWeights
                              .map(
                                  (day) =>
                                      `<div class="chart-column"><span class="${day.weight === 0 ? "bar-empty" : ""}" style="--bar: ${day.weight ? Math.max((day.weight / maximumWeight) * 100, 8) : 0}%" title="${escapeHtml(day.date || day.label)}: ${formatWeight(day.weight)}"></span><small>${escapeHtml(day.label)}</small></div>`,
                              )
                              .join("")
                        : '<p class="chart-empty-state">Belum ada setoran disetujui pada periode ini.</p>';
                }
                if (chartDescription) {
                    chartDescription.textContent =
                        chartDescriptions[summary.chartPeriod];
                }
                if (chartFeedback) {
                    chartFeedback.hidden = true;
                    chartFeedback.textContent = "";
                }
            } catch (error) {
                if (requestId !== chartRequest.id) return;
                if (chart) {
                    chart.innerHTML = `<p class="chart-empty-state">${escapeHtml(error.message)}</p>`;
                }
                if (chartFeedback) {
                    chartFeedback.hidden = false;
                    chartFeedback.textContent = "Grafik gagal dimuat.";
                }
            }
        };

        chartPeriodSelect?.addEventListener("change", () => {
            if (chartDescription) {
                chartDescription.textContent =
                    chartDescriptions[chartPeriodSelect.value] ??
                    "Berat setoran yang disetujui";
            }
            loadDashboard(chartPeriodSelect.value);
        });
        loadDashboard(chartPeriodSelect?.value || "7d");
    }
})();
