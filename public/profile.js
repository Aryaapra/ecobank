(() => {
    "use strict";

    const form = document.querySelector("[data-profile-form]");
    const feedback = document.querySelector("[data-profile-status]");
    const dialog = document.querySelector("[data-profile-edit-dialog]");
    const dialogFeedback = document.querySelector(
        "[data-profile-dialog-feedback]",
    );
    if (!form || !feedback) return;

    const fields = form.elements;
    const initialsFor = (name) =>
        name
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map((part) => part[0])
            .join("")
            .toUpperCase();

    const showError = (error) => {
        feedback.hidden = false;
        feedback.classList.remove("is-success");
        feedback.textContent =
            error instanceof TypeError
                ? "Tidak dapat menghubungi server EcoBank. Periksa koneksi dan database."
                : error.message;
    };

    const formatRupiah = (value) =>
        `Rp ${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(Number(value))}`;
    const formatWeight = (value) =>
        new Intl.NumberFormat("id-ID", { maximumFractionDigits: 2 }).format(
            Number(value),
        );
    const setText = (selector, value) => {
        document.querySelectorAll(selector).forEach((element) => {
            element.textContent = value;
        });
    };

    document.querySelectorAll("[data-profile-edit-open]").forEach((button) => {
        button.addEventListener("click", () => {
            if (!dialog) return;
            dialog.showModal();
            if (button.hasAttribute("data-focus-password")) {
                document.querySelector("#profile-current-password").focus();
            } else {
                form.elements.name.focus();
            }
        });
    });
    dialog?.addEventListener("click", (event) => {
        if (event.target === dialog) dialog.close();
    });

    const loadRoleSummary = async (role) => {
        const endpoint =
            role === "admin" ? "api/dashboard" : "api/transactions";
        const response = await fetch(endpoint, {
            headers: { Accept: "application/json" },
            cache: "no-store",
        });
        const result = await response.json();
        if (!response.ok) {
            throw new Error(
                result.error || "Ringkasan akun tidak dapat dimuat.",
            );
        }

        if (role === "admin") {
            const summary = result.data;
            setText(
                "[data-admin-total-transactions]",
                new Intl.NumberFormat("id-ID").format(
                    summary.totalTransactions,
                ),
            );
            const pending = new Intl.NumberFormat("id-ID").format(
                summary.pendingTransactions,
            );
            setText("[data-admin-pending]", pending);
            setText("[data-pending-count]", pending);
            setText("[data-admin-weight]", formatWeight(summary.totalWeight));
            setText("[data-admin-value]", formatRupiah(summary.totalValue));
            return;
        }

        const balance = Number(result.summary.balance);
        const approvedWeight = Number(result.summary.approvedWeight);
        const pendingCount = Number(result.summary.pendingCount);

        setText("[data-user-profile-balance]", formatRupiah(balance));
        setText("[data-user-profile-inline-balance]", formatRupiah(balance));
        setText("[data-user-profile-weight]", formatWeight(approvedWeight));
        setText(
            "[data-user-profile-pending]",
            `${new Intl.NumberFormat("id-ID").format(pendingCount)} menunggu`,
        );

        try {
            const rankingResponse = await fetch("api/rankings", {
                headers: { Accept: "application/json" },
            });
            if (rankingResponse.ok) {
                const ranking = await rankingResponse.json();
                const currentUser = ranking.data.currentUser;
                setText(
                    "[data-user-profile-rank]",
                    currentUser.rank ? `#${currentUser.rank}` : "—",
                );
            }
        } catch {
            setText("[data-user-profile-rank]", "—");
        }
    };

    const loadProfile = async () => {
        try {
            const response = await fetch("api/auth/me", {
                headers: { Accept: "application/json" },
            });
            const result = await response.json();
            if (response.status === 401) {
                window.location.replace("auth.html");
                return;
            }
            if (!response.ok)
                throw new Error(result.error || "Profil tidak dapat dimuat.");

            const user = result.data;
            fields.name.value = user.name;
            fields.email.value = user.email;
            fields.phone.value = user.phone;
            fields.district.value = user.district;
            setText("[data-profile-heading]", user.name);
            setText("[data-profile-email]", user.email);
            setText(
                "[data-profile-role]",
                user.role === "admin" ? "Pengurus" : "Anggota EcoBank",
            );
            setText("[data-profile-avatar]", initialsFor(user.name));
            setText("[data-profile-district]", user.district);
            setText("[data-row-name]", `${user.name},`);
            setText("[data-row-email]", user.email);
            setText("[data-row-district]", user.district);
            await loadRoleSummary(user.role);
        } catch (error) {
            showError(error);
        }
    };

    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        const button = form.querySelector('[type="submit"]');
        const password = fields.password.value;
        button.disabled = true;
        button.textContent = "Menyimpan...";
        feedback.hidden = true;
        if (dialogFeedback) dialogFeedback.textContent = "";

        try {
            const response = await fetch("api/profile", {
                method: "PUT",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN":
                        document.querySelector('meta[name="csrf-token"]')
                            ?.content ?? "",
                },
                body: JSON.stringify({
                    name: fields.name.value.trim(),
                    email: fields.email.value.trim(),
                    phone: fields.phone.value.trim(),
                    district: fields.district.value,
                    ...(password
                        ? {
                              password,
                              currentPassword: fields.currentPassword.value,
                          }
                        : {}),
                }),
            });
            const result = await response.json();
            if (!response.ok)
                throw new Error(
                    result.error || "Perubahan profil gagal disimpan.",
                );

            const user = result.data;
            setText("[data-profile-heading]", user.name);
            setText("[data-profile-email]", user.email);
            setText("[data-profile-avatar]", initialsFor(user.name));
            setText("[data-profile-district]", user.district);
            setText("[data-row-name]", `${user.name},`);
            setText("[data-row-email]", user.email);
            setText("[data-row-district]", user.district);
            document.querySelectorAll("[data-profile-name]").forEach((node) => {
                node.textContent = user.name;
            });
            document
                .querySelectorAll("[data-profile-toggle]")
                .forEach((node) => {
                    node.textContent = initialsFor(user.name);
                });
            fields.password.value = "";
            fields.currentPassword.value = "";
            feedback.hidden = false;
            feedback.classList.add("is-success");
            feedback.textContent = "Profil berhasil diperbarui.";
            dialog?.close();
        } catch (error) {
            feedback.classList.remove("is-success");
            showError(error);
            if (dialogFeedback) dialogFeedback.textContent = error.message;
        } finally {
            button.disabled = false;
            button.innerHTML =
                'Simpan perubahan <span aria-hidden="true">→</span>';
        }
    });

    loadProfile();
})();
