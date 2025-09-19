import './bootstrap';

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const tableBody = document.querySelector('[data-flag-rows]');
const tableWrapper = document.querySelector('[data-flag-table]');

if (tableBody) {
    initModeration();
}

function initModeration() {
    fetchFlags();
}

async function fetchFlags() {
    try {
        const response = await fetch('/api/messages/flags', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error('Unable to load moderation queue');
        const { data } = await response.json();
        renderFlags(data);
    } catch (error) {
        console.error(error);
        tableBody.innerHTML = '<tr><td colspan="5" class="text-danger text-center py-4">Failed to load moderation queue.</td></tr>';
    }
}

function renderFlags(flags) {
    if (!flags.length) {
        tableBody.innerHTML = '<tr><td colspan="5" class="text-muted-soft text-center py-4">No active reports. All clear! ??</td></tr>';
        return;
    }

    tableBody.innerHTML = '';
    flags.forEach((flag) => {
        const row = document.createElement('tr');
        row.innerHTML = 
            <td class="ps-4">
                <div class="fw-semibold">Message #</div>
                <small class="text-muted-soft"></small>
            </td>
            <td></td>
            <td></td>
            <td></td>
            <td class="text-end pe-4">
                <button class="btn btn-sm btn-outline-light" data-resolve="">Resolve</button>
            </td>
        ;
        tableBody.appendChild(row);
    });

    tableBody.querySelectorAll('[data-resolve]').forEach((button) => {
        button.addEventListener('click', () => resolveFlag(button.dataset.resolve));
    });
}

async function resolveFlag(messageId) {
    if (!confirm('Mark this report as resolved?')) {
        return;
    }

    try {
        const response = await fetch(/api/messages/flag//resolve, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) throw new Error('Failed to resolve flag');
        await fetchFlags();
    } catch (error) {
        console.error(error);
        alert('Unable to resolve flag.');
    }
}

function escapeHtml(value = '') {
    const div = document.createElement('div');
    div.innerText = value;
    return div.innerHTML;
}

function formatTimestamp(value) {
    if (!value) return '';
    const date = new Date(value);
    return date.toLocaleString();
}