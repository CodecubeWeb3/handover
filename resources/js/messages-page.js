
import "./bootstrap";

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? "";

const elements = {
    app: document.querySelector('[data-messages-app]'),
    threadList: document.querySelector('[data-thread-list]'),
    threadSearch: document.querySelector('[data-thread-search]'),
    includeArchived: document.querySelector('[data-include-archived]'),
    threadHeader: document.querySelector('[data-thread-header]'),
    threadTitle: document.querySelector('[data-thread-title]'),
    threadSubtitle: document.querySelector('[data-thread-subtitle]'),
    threadStatus: document.querySelector('[data-thread-status]'),
    threadActions: document.querySelector('[data-thread-actions]'),
    messageScroll: document.querySelector('[data-message-scroll]'),
    messageForm: document.querySelector('[data-message-form]'),
    messageInput: document.querySelector('[data-message-input]'),
    messageSubmit: document.querySelector('[data-message-submit]'),
    typingIndicator: document.querySelector('[data-typing-indicator]'),
    readIndicator: document.querySelector('[data-read-indicator]'),
    mutedNotice: document.querySelector('[data-muted-notice]'),
};

const state = {
    threads: [],
    filteredThreads: [],
    activeThreadId: null,
    activeThreadPayload: null,
    includeArchived: false,
    searchTerm: "",
    refreshTimer: null,
    typingTimeout: null,
    highlightMessageId: null,
    user: {
        id: parseInt(document.body.dataset.userId ?? "", 10) || null,
        role: document.body.dataset.userRole ?? null,
    },
};

state.user.canModerate = ["admin", "moderator"].includes(state.user.role ?? "");

if (elements.app) {
    init();
}

function init() {
    parseQueryParams();
    bindEvents();
    fetchThreads().then(() => {
        if (state.activeThreadId) {
            loadThread(state.activeThreadId);
        } else if (state.filteredThreads[0]) {
            loadThread(state.filteredThreads[0].id);
        } else {
            renderEmptyThread();
        }
    });
}

function parseQueryParams() {
    const params = new URLSearchParams(window.location.search);
    state.includeArchived = params.get("include_archived") === "1";
    if (elements.includeArchived) {
        elements.includeArchived.checked = state.includeArchived;
    }

    if (params.get("thread")) {
        state.activeThreadId = Number(params.get("thread")) || null;
    }

    if (params.get("message")) {
        state.highlightMessageId = Number(params.get("message")) || null;
    }

    if (params.get("search")) {
        state.searchTerm = params.get("search") ?? "";
        if (elements.threadSearch) {
            elements.threadSearch.value = state.searchTerm;
        }
    }
}

function bindEvents() {
    elements.threadSearch?.addEventListener("input", (event) => {
        state.searchTerm = event.target.value;
        applySearch();
        renderThreadList();
        updateUrl();
    });

    elements.includeArchived?.addEventListener("change", () => {
        state.includeArchived = Boolean(elements.includeArchived.checked);
        updateUrl();
        fetchThreads().then(() => {
            if (state.activeThreadId && !state.threads.some((thread) => thread.id === state.activeThreadId)) {
                state.activeThreadId = state.filteredThreads[0]?.id ?? null;
                if (state.activeThreadId) {
                    loadThread(state.activeThreadId);
                } else {
                    renderEmptyThread();
                }
            }
        });
    });

    elements.messageForm?.addEventListener("submit", handleSendMessage);
    elements.messageInput?.addEventListener("input", handleTyping);
    elements.messageInput?.addEventListener("blur", () => sendTypingState("stopped"));
}

async function fetchThreads() {
    try {
        const params = new URLSearchParams({ include_archived: state.includeArchived ? "1" : "0" });
        const response = await fetch(`/api/messages?${params.toString()}`, {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
        });

        if (!response.ok) {
            throw new Error("Unable to load conversations");
        }

        const payload = await response.json();
        state.threads = Array.isArray(payload.data) ? payload.data : [];
        applySearch();
        renderThreadList();
    } catch (error) {
        console.error(error);
        if (elements.threadList) {
            elements.threadList.innerHTML = '<div class="text-center text-danger py-4">Failed to load conversations.</div>';
        }
    }
}

function applySearch() {
    const term = state.searchTerm.trim().toLowerCase();
    if (!term) {
        state.filteredThreads = [...state.threads];
        return;
    }

    state.filteredThreads = state.threads.filter((thread) => {
        const participantNames = thread.participants?.map((participant) => (participant.name ?? "").toLowerCase()) ?? [];
        const lastMessage = thread.last_message?.body?.toLowerCase() ?? "";
        const bookingId = thread.booking_id ? String(thread.booking_id) : "";
        return participantNames.some((name) => name.includes(term)) || lastMessage.includes(term) || bookingId.includes(term);
    });
}

function renderThreadList() {
    if (!elements.threadList) {
        return;
    }

    elements.threadList.innerHTML = "";

    if (state.filteredThreads.length === 0) {
        elements.threadList.innerHTML = '<div class="text-center text-muted-soft py-4">No conversations yet.</div>';
        return;
    }

    state.filteredThreads.forEach((thread) => {
        const item = document.createElement("div");
        item.className = "message-thread-item list-group-item list-group-item-action bg-transparent border-0";
        item.dataset.threadId = String(thread.id);
        if (thread.id === state.activeThreadId) {
            item.classList.add("active");
        }

        const wrapper = document.createElement("div");
        wrapper.className = "d-flex justify-content-between align-items-start gap-2";

        const left = document.createElement("div");
        const title = document.createElement("div");
        title.className = "fw-semibold";
        title.textContent = formatThreadTitle(thread);
        const subtitle = document.createElement("small");
        subtitle.className = "text-muted-soft";
        subtitle.textContent = formatThreadSubtitle(thread);
        left.appendChild(title);
        left.appendChild(subtitle);

        const right = document.createElement("div");
        right.className = "text-end";
        if (thread.unread) {
            const unreadBadge = document.createElement("span");
            unreadBadge.className = "badge rounded-pill bg-primary-subtle text-primary me-2";
            unreadBadge.textContent = "New";
            right.appendChild(unreadBadge);
        }
        if (thread.archived_at) {
            const archivedBadge = document.createElement("span");
            archivedBadge.className = "badge rounded-pill bg-secondary-subtle text-secondary";
            archivedBadge.textContent = "Archived";
            right.appendChild(archivedBadge);
        }

        wrapper.appendChild(left);
        wrapper.appendChild(right);
        item.appendChild(wrapper);

        item.addEventListener("click", () => {
            if (thread.id !== state.activeThreadId) {
                state.activeThreadId = thread.id;
                updateUrl();
                loadThread(thread.id);
                renderThreadList();
            }
        });

        elements.threadList.appendChild(item);
    });
}

function renderEmptyThread() {
    if (elements.threadTitle) {
        elements.threadTitle.textContent = "Select a conversation";
    }
    if (elements.threadSubtitle) {
        elements.threadSubtitle.textContent = "Messages will appear here.";
    }
    if (elements.threadStatus) {
        elements.threadStatus.textContent = "";
    }
    if (elements.threadActions) {
        elements.threadActions.innerHTML = "";
    }
    if (elements.messageScroll) {
        elements.messageScroll.innerHTML = '<p class="text-muted-soft text-center">No messages to display.</p>';
    }
    disableComposer();
}

async function loadThread(threadId, options = {}) {
    if (!threadId) {
        renderEmptyThread();
        return;
    }

    const silent = Boolean(options.silent);

    try {
        const response = await fetch(`/api/messages/${threadId}`, {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
        });

        if (!response.ok) {
            throw new Error("Unable to load thread");
        }

        const payload = await response.json();
        const data = payload.data;
        state.activeThreadPayload = data;
        state.activeThreadId = threadId;
        updateUrl();

        renderThreadHeader(data);
        renderMessages(data);
        updateTypingIndicator(data.typing_states ?? []);
        updateReadIndicator(data.read_states ?? []);
        enableComposer(data.thread);
        markMessagesRead(data);

        if (!silent) {
            clearInterval(state.refreshTimer);
            state.refreshTimer = setInterval(() => loadThread(threadId, { silent: true }), 12000);
        }
    } catch (error) {
        console.error(error);
        if (!silent) {
            alert("Unable to load this conversation.");
        }
    }
}

function renderThreadHeader(payload) {
    const thread = payload.thread;

    if (elements.threadTitle) {
        elements.threadTitle.textContent = formatThreadTitle(thread);
    }

    if (elements.threadSubtitle) {
        elements.threadSubtitle.textContent = thread.booking_id ? `Booking #${thread.booking_id}` : "No booking reference";
    }

    if (elements.threadStatus) {
        const statuses = [];
        if (thread.archived_at) {
            statuses.push(`Archived ${formatDateTime(thread.archived_at)}`);
        }
        if (thread.participant_mutes?.length) {
            const mutedSummary = thread.participant_mutes
                .map((mute) => {
                    const name = mute.user?.name ?? "Participant";
                    const until = mute.muted_until ? formatDateTime(mute.muted_until) : "until cleared";
                    return `${name} muted until ${until}`;
                })
                .join(" - ");
            statuses.push(mutedSummary);
        }
        elements.threadStatus.textContent = statuses.join(" - ");
    }

    if (elements.threadActions) {
        elements.threadActions.innerHTML = "";
        renderThreadActions(thread);
    }
}

function renderThreadActions(thread) {
    if (!elements.threadActions || !state.user.canModerate) {
        return;
    }

    const permissions = thread.permissions ?? {};

    if (permissions.can_archive) {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "btn btn-sm btn-outline-light";
        if (thread.archived_at) {
            button.textContent = "Unarchive";
            button.addEventListener("click", () => toggleArchive("DELETE"));
        } else {
            button.textContent = "Archive";
            button.addEventListener("click", () => toggleArchive("POST"));
        }
        elements.threadActions.appendChild(button);
    }

    if (permissions.can_mute && thread.participants?.length) {
        const form = document.createElement("div");
        form.className = "d-flex flex-wrap gap-2 align-items-center";

        const select = document.createElement("select");
        select.className = "form-select form-select-sm";
        thread.participants.forEach((participant) => {
            if (!participant.id) {
                return;
            }
            const option = document.createElement("option");
            option.value = String(participant.id);
            option.textContent = participant.name ?? "Participant";
            select.appendChild(option);
        });

        const minutes = document.createElement("input");
        minutes.type = "number";
        minutes.min = "5";
        minutes.value = "60";
        minutes.className = "form-control form-control-sm";
        minutes.placeholder = "Minutes";

        const button = document.createElement("button");
        button.type = "button";
        button.className = "btn btn-sm btn-outline-light";
        button.textContent = "Mute";
        button.addEventListener("click", () => handleMute(select.value, minutes.value));

        form.appendChild(select);
        form.appendChild(minutes);
        form.appendChild(button);

        elements.threadActions.appendChild(form);
    }

    if (permissions.can_unmute && thread.participant_mutes?.length) {
        thread.participant_mutes.forEach((mute) => {
            if (!mute.user?.id) {
                return;
            }
            const button = document.createElement("button");
            button.type = "button";
            button.className = "btn btn-sm btn-outline-warning";
            button.textContent = `Unmute ${mute.user.name ?? "Participant"}`;
            button.addEventListener("click", () => handleUnmute(mute.user.id));
            elements.threadActions.appendChild(button);
        });
    }
}

function renderMessages(payload) {
    if (!elements.messageScroll) {
        return;
    }

    const messages = payload.messages ?? [];
    elements.messageScroll.innerHTML = "";

    if (messages.length === 0) {
        elements.messageScroll.innerHTML = '<p class="text-muted-soft text-center">No messages exchanged yet.</p>';
        return;
    }

    messages.forEach((message) => {
        const wrapper = document.createElement("div");
        wrapper.className = "d-flex flex-column";
        wrapper.dataset.messageId = String(message.id ?? "");

        const isMine = message.sender?.id === state.user.id;
        const bubble = document.createElement("div");
        bubble.className = `bubble ${isMine ? "me" : "them"}`;
        bubble.textContent = message.body ?? "";
        wrapper.appendChild(bubble);

        if (message.attachments?.length) {
            const list = document.createElement("ul");
            list.className = "list-unstyled small mt-2 ms-2";
            message.attachments.forEach((attachment) => {
                const item = document.createElement("li");
                const link = document.createElement("span");
                link.textContent = `${attachment.mime ?? "Attachment"} (${formatBytes(attachment.bytes ?? 0)})`;
                item.appendChild(link);
                list.appendChild(item);
            });
            wrapper.appendChild(list);
        }

        const metaRow = document.createElement("div");
        metaRow.className = "d-flex justify-content-between align-items-center mt-1";

        const meta = document.createElement("small");
        meta.className = "text-muted-soft";
        meta.textContent = `${message.sender?.name ?? "Unknown"} - ${formatDateTime(message.created_at)}`;
        metaRow.appendChild(meta);

        if (!isMine) {
            const flagButton = document.createElement("button");
            flagButton.type = "button";
            flagButton.className = "btn btn-link btn-sm text-decoration-none text-muted-soft p-0";
            flagButton.textContent = "Flag";
            flagButton.addEventListener("click", () => flagMessage(message.id));
            metaRow.appendChild(flagButton);
        }

        wrapper.appendChild(metaRow);
        elements.messageScroll.appendChild(wrapper);
    });

    if (state.highlightMessageId) {
        const target = elements.messageScroll.querySelector(`[data-message-id="${state.highlightMessageId}"]`);
        if (target) {
            target.classList.add("message-highlight");
            target.scrollIntoView({ behavior: "smooth", block: "center" });
            setTimeout(() => target.classList.remove("message-highlight"), 4000);
        }
        state.highlightMessageId = null;
        updateUrl();
    } else {
        elements.messageScroll.scrollTop = elements.messageScroll.scrollHeight;
    }
}

function enableComposer(thread) {
    if (!elements.messageInput || !elements.messageSubmit || !elements.mutedNotice) {
        return;
    }

    const mutedUntil = thread?.muted_until ? new Date(thread.muted_until) : null;
    const isMuted = mutedUntil && mutedUntil.getTime() > Date.now();

    elements.messageInput.disabled = Boolean(isMuted);
    elements.messageSubmit.disabled = Boolean(isMuted);

    if (isMuted) {
        elements.mutedNotice.textContent = `You are muted in this conversation until ${formatDateTime(mutedUntil.toISOString())}.`;
    } else {
        elements.mutedNotice.textContent = "";
    }
}

function disableComposer() {
    if (!elements.messageInput || !elements.messageSubmit || !elements.mutedNotice) {
        return;
    }

    elements.messageInput.value = "";
    elements.messageInput.disabled = true;
    elements.messageSubmit.disabled = true;
    elements.mutedNotice.textContent = "";
}

async function handleSendMessage(event) {
    event.preventDefault();

    if (!state.activeThreadId || !elements.messageInput || !elements.messageSubmit) {
        return;
    }

    const body = elements.messageInput.value.trim();
    if (!body) {
        return;
    }

    try {
        elements.messageSubmit.disabled = true;
        await apiFetch(`/api/messages/${state.activeThreadId}/send`, { method: "POST", body: { message: body } });
        elements.messageInput.value = "";
        sendTypingState("stopped");
        await loadThread(state.activeThreadId, { silent: true });
        await fetchThreads();
    } catch (error) {
        console.error(error);
        alert("Unable to send message.");
    } finally {
        elements.messageSubmit.disabled = false;
    }
}

function handleTyping() {
    if (!state.activeThreadId || !elements.messageInput) {
        return;
    }

    sendTypingState("started");
    clearTimeout(state.typingTimeout);
    state.typingTimeout = setTimeout(() => sendTypingState("stopped"), 2000);
}

async function sendTypingState(value) {
    if (!state.activeThreadId) {
        return;
    }

    try {
        await apiFetch(`/api/messages/${state.activeThreadId}/typing`, { method: "POST", body: { state: value } });
    } catch (error) {
        console.error(error);
    }
}

async function markMessagesRead(payload) {
    if (!state.activeThreadId || !payload.messages?.length) {
        return;
    }

    const lastMessageId = payload.messages[payload.messages.length - 1].id;

    try {
        await apiFetch(`/api/messages/${state.activeThreadId}/read`, { method: "POST", body: { message_id: lastMessageId } });
    } catch (error) {
        console.error(error);
    }
}

async function flagMessage(messageId) {
    if (!messageId) {
        return;
    }

    const reason = window.prompt("Provide a reason for this flag", "Inappropriate content");
    if (!reason) {
        return;
    }

    try {
        await apiFetch(`/api/messages/flag/${messageId}`, { method: "POST", body: { reason } });
        alert("Message flagged. Moderators will review shortly.");
    } catch (error) {
        console.error(error);
        alert("Failed to flag message.");
    }
}

async function toggleArchive(method) {
    if (!state.activeThreadId) {
        return;
    }

    try {
        await apiFetch(`/api/messages/${state.activeThreadId}/archive`, { method });
        await loadThread(state.activeThreadId, { silent: true });
        await fetchThreads();
    } catch (error) {
        console.error(error);
        alert("Unable to update archive state.");
    }
}

async function handleMute(userId, minutes) {
    if (!state.activeThreadId || !userId) {
        return;
    }

    const parsedMinutes = Number(minutes);
    if (!Number.isFinite(parsedMinutes) || parsedMinutes <= 0) {
        alert("Provide a mute duration greater than zero minutes.");
        return;
    }

    try {
        await apiFetch(`/api/messages/${state.activeThreadId}/mute`, {
            method: "POST",
            body: { user_id: Number(userId), minutes: parsedMinutes },
        });
        await loadThread(state.activeThreadId, { silent: true });
    } catch (error) {
        console.error(error);
        alert("Unable to mute participant.");
    }
}

async function handleUnmute(userId) {
    if (!state.activeThreadId || !userId) {
        return;
    }

    try {
        await apiFetch(`/api/messages/${state.activeThreadId}/mute/${userId}`, { method: "DELETE" });
        await loadThread(state.activeThreadId, { silent: true });
    } catch (error) {
        console.error(error);
        alert("Unable to unmute participant.");
    }
}

function updateTypingIndicator(entries) {
    if (!elements.typingIndicator) {
        return;
    }

    const viewerId = state.user.id;
    const active = (entries ?? [])
        .filter((entry) => entry.state === "started" && entry.user?.id && entry.user.id !== viewerId)
        .map((entry) => entry.user?.name ?? "Participant");

    elements.typingIndicator.textContent = active.length ? `${active.join(", ")} is typing...` : "";
}

function updateReadIndicator(entries) {
    if (!elements.readIndicator) {
        return;
    }

    const viewerId = state.user.id;
    const others = (entries ?? []).filter((entry) => entry.user?.id && entry.user.id !== viewerId);

    if (!others.length) {
        elements.readIndicator.textContent = "";
        return;
    }

    const summaries = others.map((entry) => {
        const name = entry.user?.name ?? "Participant";
        const readAt = entry.read_at ? formatDateTime(entry.read_at) : "earlier";
        return `${name} read up to message #${entry.message_id} (${readAt})`;
    });

    elements.readIndicator.textContent = summaries.join(" - ");
}

async function apiFetch(url, options = {}) {
    const method = options.method ?? "GET";
    const fetchOptions = {
        method,
        headers: { Accept: "application/json" },
        credentials: "same-origin",
    };

    if (method !== "GET" || options.body !== undefined) {
        fetchOptions.headers["X-CSRF-TOKEN"] = csrfToken;
    }

    if (options.body !== undefined) {
        fetchOptions.headers["Content-Type"] = "application/json";
        fetchOptions.body = JSON.stringify(options.body);
    }

    const response = await fetch(url, fetchOptions);
    if (!response.ok) {
        const error = new Error(`Request failed with status ${response.status}`);
        error.response = response;
        throw error;
    }

    if (response.status === 204) {
        return null;
    }

    return response.json();
}

function formatThreadTitle(thread) {
    const names = thread.participants?.map((participant) => participant.name ?? "Participant") ?? [];
    return names.length ? names.join(", ") : "Conversation";
}

function formatThreadSubtitle(thread) {
    const parts = [];
    if (thread.booking_id) {
        parts.push(`Booking #${thread.booking_id}`);
    }
    if (thread.last_message?.sender?.name) {
        parts.push(`Last from ${thread.last_message.sender.name}`);
    }
    return parts.join(" - ") || "No recent activity";
}

function formatDateTime(value) {
    if (!value) {
        return "";
    }

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleString();
}

function formatBytes(bytes) {
    const value = Number(bytes);
    if (!Number.isFinite(value) || value <= 0) {
        return "0 B";
    }

    const units = ["B", "KB", "MB", "GB"];
    const exponent = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1);
    const num = value / Math.pow(1024, exponent);
    return `${num.toFixed(exponent === 0 ? 0 : 1)} ${units[exponent]}`;
}

function updateUrl() {
    const params = new URLSearchParams();
    if (state.activeThreadId) {
        params.set("thread", String(state.activeThreadId));
    }
    if (state.includeArchived) {
        params.set("include_archived", "1");
    }
    if (state.searchTerm.trim()) {
        params.set("search", state.searchTerm.trim());
    }
    if (state.highlightMessageId) {
        params.set("message", String(state.highlightMessageId));
    }

    const query = params.toString();
    const url = query ? `${window.location.pathname}?${query}` : window.location.pathname;
    window.history.replaceState({}, "", url);
}

window.Laravel = window.Laravel || {};
window.Laravel.user = window.Laravel.user || state.user;
