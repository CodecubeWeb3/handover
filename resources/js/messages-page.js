import './bootstrap';

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

const elements = {
    app: document.querySelector('[data-messages-app]'),
    threadList: document.querySelector('[data-thread-list]'),
    threadSearch: document.querySelector('[data-thread-search]'),
    threadHeader: document.querySelector('[data-thread-header]'),
    messageScroll: document.querySelector('[data-message-scroll]'),
    messageForm: document.querySelector('[data-message-form]'),
    messageInput: document.querySelector('[data-message-input]'),
    messageSubmit: document.querySelector('[data-message-submit]'),
    typingIndicator: document.querySelector('[data-typing-indicator]'),
    readIndicator: document.querySelector('[data-read-indicator]'),
};

const state = {
    threads: [],
    filteredThreads: [],
    activeThreadId: null,
    typingTimeout: null,
    refreshTimer: null,
};

if (elements.app) {
    init();
}

function init() {
    fetchThreads();
    elements.messageForm?.addEventListener('submit', handleSendMessage);
    elements.messageInput?.addEventListener('input', handleTyping);
    elements.messageInput?.addEventListener('blur', () => sendTypingState('stopped'));
    elements.threadSearch?.addEventListener('input', handleSearch);
}

function handleSearch(event) {
    const term = event.target.value.toLowerCase();
    state.filteredThreads = state.threads.filter((thread) => {
        const participantNames = thread.participants.map((p) => p.name?.toLowerCase() ?? '');
        const lastMessage = thread.last_message?.body?.toLowerCase() ?? '';
        return participantNames.some((name) => name.includes(term)) || lastMessage.includes(term);
    });
    renderThreadList();
}

async function fetchThreads() {
    try {
        const response = await fetch('/api/messages', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error('Unable to load conversations');
        const { data } = await response.json();
        state.threads = data;
        state.filteredThreads = data;
        renderThreadList();
        if (state.activeThreadId) {
            loadThread(state.activeThreadId, { silent: true });
        }
    } catch (error) {
        console.error(error);
        elements.threadList.innerHTML = '<div class="text-center text-danger py-4">Failed to load conversations.</div>';
    }
}

function renderThreadList() {
    if (!elements.threadList) return;
    if (state.filteredThreads.length === 0) {
        elements.threadList.innerHTML = '<div class="text-center text-muted-soft py-4">No conversations yet.</div>';
        return;
    }

    elements.threadList.innerHTML = '';
    state.filteredThreads.forEach((thread) => {
        const item = document.createElement('div');
        item.className = 'message-thread-item list-group-item list-group-item-action bg-transparent border-0';
        if (thread.id === state.activeThreadId) {
            item.classList.add('active');
        }
        item.dataset.threadId = thread.id;
        item.innerHTML = 
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold"></div>
                    <small class="text-muted-soft"></small>
                </div>
                
            </div>
        ;
        item.addEventListener('click', () => loadThread(thread.id));
        elements.threadList.appendChild(item);
    });
}

async function loadThread(threadId, options = {}) {
    try {
        const response = await fetch(/api/messages/, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error('Unable to load thread');
        const { data } = await response.json();
        state.activeThreadId = threadId;
        renderThreadHeader(data);
        renderMessages(data);
        enableComposer();
        markMessagesRead(data);
        if (!options.silent) {
            clearInterval(state.refreshTimer);
            state.refreshTimer = setInterval(() => loadThread(threadId, { silent: true }), 12000);
        }
    } catch (error) {
        console.error(error);
    }
}

function renderThreadHeader(payload) {
    if (!elements.threadHeader) return;
    const participantNames = payload.thread.participants.map((p) => p.name ?? 'Unknown').join(', ');
    elements.threadHeader.innerHTML = 
        <h2 class="h5 mb-1"></h2>
        <small class="text-muted-soft">Booking #</small>
    ;
}

function renderMessages(payload) {
    if (!elements.messageScroll) return;
    const messages = payload.messages;
    elements.messageScroll.innerHTML = '';

    if (messages.length === 0) {
        elements.messageScroll.innerHTML = '<p class="text-muted-soft text-center">No messages exchanged yet.</p>';
        return;
    }

    messages.forEach((message) => {
        const bubble = document.createElement('div');
        const isMe = message.sender?.id === window.Laravel?.user?.id;
        bubble.className = ubble ;
        bubble.dataset.messageId = message.id;
        bubble.innerHTML = 
            <div class="small text-uppercase fw-semibold  mb-1">
                
            </div>
            <div class="fw-medium"></div>
            <div class="mt-2 d-flex justify-content-between align-items-center small text-muted">
                <span></span>
                <button class="btn btn-sm btn-outline-light btn-pill" type="button" data-flag-message="">Flag</button>
            </div>
        ;
        elements.messageScroll.appendChild(bubble);
    });

    elements.messageScroll.scrollTop = elements.messageScroll.scrollHeight;
    elements.messageScroll.querySelectorAll('[data-flag-message]').forEach((button) => {
        button.addEventListener('click', () => flagMessage(button.dataset.flagMessage));
    });

    updateTypingIndicator(payload.typing_states);
    updateReadIndicator(payload.read_states);
}

function formatTimestamp(value) {
    if (!value) return '';
    const date = new Date(value);
    return date.toLocaleString();
}

function escapeHtml(value = '') {
    const div = document.createElement('div');
    div.innerText = value;
    return div.innerHTML;
}

function enableComposer() {
    if (!elements.messageInput || !elements.messageSubmit) return;
    elements.messageInput.removeAttribute('disabled');
    elements.messageSubmit.removeAttribute('disabled');
    elements.messageInput.focus();
}

async function handleSendMessage(event) {
    event.preventDefault();
    if (!state.activeThreadId || !elements.messageInput.value.trim()) {
        return;
    }

    const payload = {
        message: elements.messageInput.value.trim(),
    };

    try {
        elements.messageSubmit.setAttribute('disabled', 'disabled');
        const response = await fetch(/api/messages//send, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error('Failed to send message');

        elements.messageInput.value = '';
        sendTypingState('stopped');
        await loadThread(state.activeThreadId, { silent: true });
        await fetchThreads();
    } catch (error) {
        console.error(error);
        alert('Unable to send message.');
    } finally {
        elements.messageSubmit.removeAttribute('disabled');
    }
}

async function flagMessage(messageId) {
    if (!confirm('Flag this message for moderator review?')) {
        return;
    }

    try {
        const response = await fetch(/api/messages/flag/, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify({ reason: 'inappropriate content' }),
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error('Unable to flag message');
        alert('Message flagged. Moderators will review shortly.');
    } catch (error) {
        console.error(error);
        alert('Failed to flag message.');
    }
}

async function markMessagesRead(payload) {
    if (!state.activeThreadId || payload.messages.length === 0) {
        return;
    }

    const lastMessageId = payload.messages[payload.messages.length - 1].id;

    await fetch(/api/messages//read, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            Accept: 'application/json',
        },
        body: JSON.stringify({ message_id: lastMessageId }),
        credentials: 'same-origin',
    });
}

function handleTyping() {
    if (!state.activeThreadId) return;
    sendTypingState('started');

    clearTimeout(state.typingTimeout);
    state.typingTimeout = setTimeout(() => sendTypingState('stopped'), 2000);
}

async function sendTypingState(stateValue) {
    if (!state.activeThreadId) return;

    await fetch(/api/messages//typing, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            Accept: 'application/json',
        },
        body: JSON.stringify({ state: stateValue }),
        credentials: 'same-origin',
    });
}

function updateTypingIndicator(typingStates) {
    if (!elements.typingIndicator) return;

    const active = typingStates
        .filter((state) => state.state === 'started')
        .map((state) => state.user?.name ?? 'Participant');

    elements.typingIndicator.textContent = active.length
        ? ${active.join(', ')}  typing…
        : '';
}

function updateReadIndicator(readStates) {
    if (!elements.readIndicator) return;

    const otherStates = readStates.filter((state) => state.user?.id !== window.Laravel?.user?.id);

    if (otherStates.length === 0) {
        elements.readIndicator.textContent = '';
        return;
    }

    const summaries = otherStates.map((state) => ${state.user?.name ?? 'Participant'} read up to message #);
    elements.readIndicator.textContent = summaries.join(' · ');
}

window.Laravel = window.Laravel || {};
window.Laravel.user = window.Laravel.user || {
    id: Number(document.body.dataset.userId || 0) || null,
};