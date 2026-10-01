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

    const formatPrice = (value) => `Rp ${rupiah.format(value)}`;
    const formatWeight = (value) =>
        `${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 1 }).format(value)} kg`;
    const setPendingCounters = () => {
        const count = transactions.filter(
            (transaction) => transaction.status === "pending",
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
                if (control.contains(event.target)) return;
                control.querySelector(".profile-dropdown").hidden = true;
                control
                    .querySelector("[data-profile-toggle]")
                    .setAttribute("aria-expanded", "false");
            });
    });

    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape") return;
        document
            .querySelectorAll("[data-profile-control]")
            .forEach((control) => {
                if (control.querySelector(".profile-dropdown").hidden) return;
                control.querySelector(".profile-dropdown").hidden = true;
                const button = control.querySelector("[data-profile-toggle]");
                button.setAttribute("aria-expanded", "false");
                button.focus();
            });
    });

    document.querySelectorAll("[data-profile-logout]").forEach((button) => {
        button.addEventListener("click", async () => {
            const control = button.closest("[data-profile-control]");
            const feedback = control?.querySelector("[data-profile-feedback]");
            button.disabled = true;
            try {
                const response = await fetch("api/auth", {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN":
                            document.querySelector('meta[name="csrf-token"]')
                                ?.content ?? "",
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
            }
        });
    });

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
        pending: "Menunggu",
        approved: "Disetujui",
        cancelled: "Dibatalkan",
    };
    const selectedReportMonth = () =>
        document.querySelector("[data-report-month]")?.value ?? "";
    const transactionsForSelectedMonth = () => {
        const month = selectedReportMonth();
        return month
            ? transactions.filter((transaction) => transaction.month === month)
            : transactions;
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
                              const transactionValue = isApproved
                                  ? `<b class="amount-positive">+ ${formatPrice(transaction.value)}</b>`
                                  : `<b>${statusLabels[transaction.status] ?? "Menunggu"}</b>`;

                              return `<div class="recent-row"><span class="recent-icon">♻</span><strong>${escapeHtml(transaction.detail)}</strong><small>${escapeHtml(transaction.name)} · ${formatWeight(transaction.weight)}</small><time>${escapeHtml(transaction.date)}</time>${transactionValue}</div>`;
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
        const wasteType =
            document.querySelector("[data-type-filter]")?.value ?? "all";
        const month = selectedReportMonth();
        const filtered = transactions.filter((transaction) => {
            const matchesQuery =
                `${transaction.name} ${transaction.id} ${transaction.waste} ${transaction.detail}`
                    .toLocaleLowerCase("id")
                    .includes(query);
            return (
                matchesQuery &&
                (!month || transaction.month === month) &&
                (status === "all" || transaction.status === status) &&
                (wasteType === "all" || transaction.waste === wasteType)
            );
        });
        transactionRows.innerHTML = filtered
            .map(
                (transaction) => `
            <tr>
                <td><div class="user-cell"><span class="user-avatar">${escapeHtml(transaction.avatar || "♻")}</span><span>${escapeHtml(transaction.name)}<small>${escapeHtml(transaction.id)}</small></span></div></td>
                <td>${escapeHtml(transaction.detail)}</td><td>${formatWeight(transaction.weight)}</td><td>${formatPrice(transaction.value)}</td><td>${escapeHtml(transaction.date)}</td>
                <td><span class="status-tag ${escapeHtml(transaction.status)}">${statusLabels[transaction.status] ?? "Menunggu"}</span></td>
                        <td>${transaction.status === "pending" ? `<div class="table-actions"><button class="table-action" type="button" data-transaction-action="approved" data-transaction-id="${escapeHtml(transaction.dbId)}">Setujui</button><button class="table-action cancel" type="button" data-transaction-action="cancelled" data-transaction-id="${escapeHtml(transaction.dbId)}">Batalkan</button></div>` : `<span class="pagination-label">Selesai</span>`}</td>
            </tr>`,
            )
            .join("");
        const emptyState = document.querySelector("[data-transactions-empty]");
        if (emptyState) emptyState.hidden = filtered.length > 0;
        const count = document.querySelector("[data-transaction-count]");
        if (count)
            count.textContent = `Menampilkan ${filtered.length} dari ${transactions.length} transaksi${month ? ` · ${month}` : ""}`;
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
    const askForTransactionConfirmation = (transaction, nextStatus) => {
        pendingAction = { transaction, nextStatus };
        if (!confirmationDialog) return;
        const approve = nextStatus === "approved";
        confirmationDialog
            .querySelector("[data-confirm-icon]")
            .classList.toggle("danger", !approve);
        confirmationDialog.querySelector("[data-confirm-icon]").textContent =
            approve ? "✓" : "×";
        confirmationDialog.querySelector("[data-confirm-title]").textContent =
            approve ? "Setujui setoran?" : "Batalkan setoran?";
        confirmationDialog.querySelector("[data-confirm-copy]").textContent =
            approve
                ? `Setoran ${transaction.name} (${formatWeight(transaction.weight)} ${transaction.waste.toLowerCase()}) akan disetujui dan tercatat.`
                : `Setoran ${transaction.name} akan dibatalkan. Tindakan ini mengubah status transaksi.`;
        const acceptButton = confirmationDialog.querySelector(
            "[data-confirm-accept]",
        );
        acceptButton.textContent = approve ? "Ya, setujui" : "Ya, batalkan";
        acceptButton.classList.toggle("button-primary", approve);
        acceptButton.classList.toggle("button-cancel", !approve);
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
            const { transaction, nextStatus } = pendingAction;
            const button = event.currentTarget;
            button.disabled = true;
            try {
                const result = await requestApi(
                    `api/transactions/${transaction.dbId}`,
                    {
                        method: "PUT",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ status: nextStatus }),
                    },
                );
                transaction.status = result.status;
                pendingAction = null;
                confirmationDialog?.close();
                renderTransactions();
            } catch (error) {
                const count = document.querySelector(
                    "[data-transaction-count]",
                );
                if (count) count.textContent = error.message;
            } finally {
                button.disabled = false;
            }
        });
    transactionRows?.addEventListener("click", (event) => {
        const button = event.target.closest("[data-transaction-action]");
        if (!button) return;
        const transaction = transactions.find(
            (entry) =>
                Number(entry.dbId) === Number(button.dataset.transactionId),
        );
        if (transaction?.status === "pending")
            askForTransactionConfirmation(
                transaction,
                button.dataset.transactionAction,
            );
    });
    [
        "[data-transaction-search]",
        "[data-status-filter]",
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
        requestApi("api/dashboard")
            .then((summary) => {
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
                        ...summary.weeklyWeights.map((day) => day.weight),
                    );
                    weeklyChart.innerHTML = maximumWeight
                        ? summary.weeklyWeights
                              .map(
                                  (day) =>
                                      `<div class="chart-column"><span class="${day.weight === 0 ? "bar-empty" : ""}" style="--bar: ${day.weight ? Math.max((day.weight / maximumWeight) * 100, 8) : 0}%" title="${formatWeight(day.weight)}"></span><small>${escapeHtml(day.label)}</small></div>`,
                              )
                              .join("")
                        : '<p class="chart-empty-state">Belum ada setoran disetujui minggu ini.</p>';
                }
            })
            .catch((error) => {
                document
                    .querySelectorAll("[data-transaction-count]")
                    .forEach((node) => {
                        node.textContent = error.message;
                    });
            });
    }
})();
