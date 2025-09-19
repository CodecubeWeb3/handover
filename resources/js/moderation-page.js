import "./bootstrap";

const csrfToken = document.querySelector("meta[name=\"csrf-token\"]")?.content ?? "";

const elements = {
    tableBody: document.querySelector("[data-flag-rows]"),
    status: document.querySelector("[data-flag-status]"),
    pagination: document.querySelector("[data-pagination]") ?? undefined,
    filterForm: document.querySelector("[data-filter-form]") ?? undefined,
    resetButton: document.querySelector("[data-filter-reset]") ?? undefined,
    perPage: document.querySelector("[data-per-page]") ?? undefined,
};

const state = {
    filters: {},
    page: 1,
    perPage: Number(elements.perPage?.value ?? 15),
    lastPage: 1,
    total: 0,
    loading: false,
};

if (elements.tableBody) {
    init();
}

function init() {
    loadDefaultsFromQuery();
    bindEvents();
    fetchFlags();
}

function loadDefaultsFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const defaults = {
        reason: params.get("reason") ?? "",
        reporter: params.get("reporter") ?? "",
        booking_id: params.get("booking_id") ?? "",
        thread_id: params.get("thread") ?? params.get("thread_id") ?? "",
        message_id: params.get("message_id") ?? "",
        date_from: params.get("date_from") ?? "",
        date_to: params.get("date_to") ?? "",
    };

    state.page = Number(params.get("page") ?? 1) || 1;
    state.perPage = Number(params.get("per_page") ?? state.perPage) || state.perPage;
    state.filters = Object.fromEntries(
        Object.entries(defaults).filter(([, value]) => value !== null && value !== "")
    );

    if (elements.perPage) {
        elements.perPage.value = String(state.perPage);
    }

    if (!elements.filterForm) {
        return;
    }

    const form = new FormData(elements.filterForm);
    Object.entries(defaults).forEach(([key, value]) => {
        if (!form.has(key)) {
            return;
        }

        const field = elements.filterForm.querySelector(`[name="${key}"]`);
        if (field) {
            field.value = value;
        }
    });
}

function bindEvents() {
    elements.filterForm?.addEventListener("submit", (event) => {
        event.preventDefault();
        applyFilters();
    });

    elements.resetButton?.addEventListener("click", (event) => {
        event.preventDefault();
        resetFilters();
    });

    elements.perPage?.addEventListener("change", () => {
        state.perPage = Number(elements.perPage.value) || state.perPage;
        state.page = 1;
        fetchFlags();
    });

    elements.pagination?.addEventListener("click", (event) => {
        const target = event.target.closest("[data-page]");
        if (!target) {
            return;
        }

        event.preventDefault();
        const page = Number(target.dataset.page);

        if (!Number.isFinite(page) || page < 1 || page > state.lastPage || page === state.page) {
            return;
        }

        state.page = page;
        fetchFlags();
    });
}

function applyFilters() {
    if (!elements.filterForm) {
        return;
    }

    const formData = new FormData(elements.filterForm);
    const filters = {};

    formData.forEach((value, key) => {
        const trimmed = String(value).trim();
        if (trimmed !== "") {
            filters[key] = trimmed;
        }
    });

    state.filters = filters;
    state.page = 1;
    fetchFlags();
}

function resetFilters() {
    elements.filterForm?.reset();
    state.filters = {};
    state.page = 1;
    fetchFlags();
}

async function fetchFlags() {
    if (!elements.tableBody) {
        return;
    }

    state.loading = true;
    renderLoading();

    const params = new URLSearchParams({
        ...state.filters,
        page: String(state.page),
        per_page: String(state.perPage),
    });

    try {
        const response = await fetch(`/api/messages/flags?${params.toString()}`, {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
        });

        if (!response.ok) {
            throw new Error("Unable to load moderation queue");
        }

        const payload = await response.json();

        state.page = payload.meta?.current_page ?? 1;
        state.lastPage = payload.meta?.last_page ?? 1;
        state.total = payload.meta?.total ?? 0;
        state.filters = payload.filters ?? {};

        renderFlags(Array.isArray(payload.data) ? payload.data : []);
        renderStatus(payload.meta ?? {});
        renderPagination(payload.meta ?? {});
        updateQueryString();
    } catch (error) {
        console.error(error);
        renderError();
    } finally {
        state.loading = false;
    }
}

function renderLoading() {
    if (!elements.tableBody) {
        return;
    }

    elements.tableBody.innerHTML = "";
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = 5;
    cell.className = "text-center text-muted-soft py-4";
    cell.textContent = "Loading flagged messages...";
    row.appendChild(cell);
    elements.tableBody.appendChild(row);
}

function renderError() {
    if (!elements.tableBody) {
        return;
    }

    elements.tableBody.innerHTML = "";
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = 5;
    cell.className = "text-center text-danger py-4";
    cell.textContent = "Failed to load moderation queue.";
    row.appendChild(cell);
    elements.tableBody.appendChild(row);
}

function renderFlags(flags) {
    if (!elements.tableBody) {
        return;
    }

    elements.tableBody.innerHTML = "";

    if (flags.length === 0) {
        const row = document.createElement("tr");
        const cell = document.createElement("td");
        cell.colSpan = 5;
        cell.className = "text-center text-muted-soft py-4";
        cell.textContent = "No active reports. All clear.";
        row.appendChild(cell);
        elements.tableBody.appendChild(row);
        return;
    }

    flags.forEach((flag) => {
        const row = document.createElement("tr");
        row.dataset.messageId = String(flag.message_id ?? "");

        const messageCell = document.createElement("td");
        messageCell.className = "ps-4";
        const messageTitle = document.createElement("div");
        messageTitle.className = "fw-semibold";
        messageTitle.textContent = `Message #${flag.message_id ?? ""}`;
        const messageMeta = document.createElement("small");
        messageMeta.className = "text-muted-soft";
        const bookingPart = flag.booking_id ? `Booking #${flag.booking_id}` : "";
        const threadPart = flag.thread_id ? `Thread #${flag.thread_id}` : "";
        messageMeta.textContent = [bookingPart, threadPart].filter(Boolean).join(" · ");
        const messagePreview = document.createElement("div");
        messagePreview.className = "text-muted mt-1";
        messagePreview.textContent = truncate(flag.preview ?? "", 120);

        messageCell.appendChild(messageTitle);
        if (messageMeta.textContent) {
            messageCell.appendChild(messageMeta);
        }
        if (messagePreview.textContent) {
            messageCell.appendChild(messagePreview);
        }

        const reasonCell = document.createElement("td");
        reasonCell.textContent = flag.reason ?? "";

        const reporterCell = document.createElement("td");
        reporterCell.textContent = flag.reporter?.name ?? "Unknown";

        const whenCell = document.createElement("td");
        whenCell.textContent = formatDate(flag.flagged_at);

        const actionsCell = document.createElement("td");
        actionsCell.className = "text-end pe-4";

        const viewLink = document.createElement("a");
        viewLink.className = "btn btn-sm btn-outline-light me-2";
        viewLink.textContent = "Open thread";
        if (flag.thread_id) {
            const params = new URLSearchParams({
                thread: String(flag.thread_id),
                message: String(flag.message_id ?? ""),
            });
            viewLink.href = `/messages?${params.toString()}`;
        } else {
            viewLink.href = "/messages";
        }

        const resolveButton = document.createElement("button");
        resolveButton.className = "btn btn-sm btn-primary";
        resolveButton.textContent = "Resolve";
        resolveButton.dataset.resolve = String(flag.message_id ?? "");
        resolveButton.addEventListener("click", () => resolveFlag(flag.message_id));

        actionsCell.appendChild(viewLink);
        actionsCell.appendChild(resolveButton);

        row.appendChild(messageCell);
        row.appendChild(reasonCell);
        row.appendChild(reporterCell);
        row.appendChild(whenCell);
        row.appendChild(actionsCell);

        elements.tableBody.appendChild(row);
    });
}

function renderStatus(meta) {
    if (!elements.status) {
        return;
    }

    const from = state.total === 0 ? 0 : (meta.from ?? (state.perPage * (state.page - 1) + 1));
    const to = meta.to ?? Math.min(state.total, state.perPage * state.page);

    elements.status.textContent = `Showing ${from} – ${to} of ${state.total} reports`;
}

function renderPagination(meta) {
    if (!elements.pagination) {
        return;
    }

    elements.pagination.innerHTML = "";

    const prev = document.createElement("button");
    prev.className = "btn btn-sm btn-outline-light";
    prev.textContent = "Previous";
    prev.disabled = state.page <= 1;
    prev.dataset.page = String(state.page - 1);

    const next = document.createElement("button");
    next.className = "btn btn-sm btn-outline-light";
    next.textContent = "Next";
    next.disabled = state.page >= (meta.last_page ?? state.lastPage);
    next.dataset.page = String(state.page + 1);

    const indicator = document.createElement("span");
    indicator.className = "small text-muted mx-2";
    indicator.textContent = `Page ${state.page} of ${meta.last_page ?? state.lastPage}`;

    elements.pagination.appendChild(prev);
    elements.pagination.appendChild(indicator);
    elements.pagination.appendChild(next);
}

async function resolveFlag(messageId) {
    if (!messageId) {
        return;
    }

    const confirmed = window.confirm("Mark this report as resolved?");
    if (!confirmed) {
        return;
    }

    try {
        const response = await fetch(`/api/messages/flag/${messageId}/resolve`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
            credentials: "same-origin",
        });

        if (!response.ok) {
            throw new Error("Failed to resolve flag");
        }

        fetchFlags();
    } catch (error) {
        console.error(error);
        alert("Unable to resolve flag. Please try again.");
    }
}

function updateQueryString() {
    const params = new URLSearchParams({ ...state.filters });
    params.set("page", String(state.page));
    params.set("per_page", String(state.perPage));

    const next = params.toString();
    const url = next ? `${window.location.pathname}?${next}` : window.location.pathname;
    window.history.replaceState({}, "", url);
}

function truncate(value, max) {
    if (!value || value.length <= max) {
        return value;
    }

    return `${value.slice(0, max - 1)}…`;
}

function formatDate(value) {
    if (!value) {
        return "";
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString();
}

