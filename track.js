const loginPanel = document.getElementById('loginPanel');
const adminDashboard = document.getElementById('adminDashboard');
const loginForm = document.getElementById('loginForm');
const loginMessage = document.getElementById('loginMessage');
const adminMessage = document.getElementById('adminMessage');
const bookingList = document.getElementById('bookingList');
const slotList = document.getElementById('slotList');
const feedbackList = document.getElementById('feedbackList');
let csrfToken = '';

function showAdminMessage(message, type = 'success') {
    adminMessage.textContent = message;
    adminMessage.className = `alert alert-${type}`;
}

function showLogin(message = '') {
    csrfToken = '';
    loginPanel.hidden = false;
    adminDashboard.hidden = true;
    loginMessage.textContent = message;
}

async function requestJson(path, options = {}) {
    const headers = new Headers(options.headers || {});
    if (csrfToken) {
        headers.set('X-CSRF-Token', csrfToken);
    }
    if (options.body && !(options.body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
    }

    const response = await fetch(path, { ...options, headers, cache: 'no-store' });
    const result = await response.json();
    if (response.status === 401) {
        showLogin('Your sign-in expired. Please sign in again.');
    }
    if (!response.ok || !result.success) {
        throw new Error(result.message || 'The request could not be completed.');
    }
    return result;
}

function createText(tag, className, value) {
    const element = document.createElement(tag);
    if (className) {
        element.className = className;
    }
    element.textContent = value ?? '';
    return element;
}

function createButton(label, className, onClick) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    button.textContent = label;
    button.addEventListener('click', onClick);
    return button;
}

function showDashboard() {
    loginPanel.hidden = true;
    adminDashboard.hidden = false;
    refreshDashboard();
}

async function checkSession() {
    try {
        const result = await requestJson('admin_session.php');
        if (result.authenticated) {
            csrfToken = result.csrfToken;
            showDashboard();
        } else {
            showLogin();
        }
    } catch (error) {
        showLogin(error.message);
    }
}

loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = document.getElementById('loginButton');
    button.disabled = true;
    loginMessage.textContent = 'Signing in…';
    try {
        const response = await fetch('admin_login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: document.getElementById('adminPassword').value }),
            cache: 'no-store',
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Could not sign in.');
        }
        csrfToken = result.csrfToken;
        loginForm.reset();
        showDashboard();
    } catch (error) {
        loginMessage.textContent = error.message;
    } finally {
        button.disabled = false;
    }
});

document.getElementById('logoutButton').addEventListener('click', async () => {
    try {
        await requestJson('admin_logout.php', { method: 'POST', body: '{}' });
        showLogin('You have signed out.');
    } catch (error) {
        showAdminMessage(error.message, 'danger');
    }
});

document.getElementById('slotForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const values = {
        action: 'add',
        date: document.getElementById('slotDate').value,
        startTime: document.getElementById('slotStart').value,
        endTime: document.getElementById('slotEnd').value,
    };
    try {
        await requestJson('manage_slots.php', { method: 'POST', body: JSON.stringify(values) });
        form.reset();
        showAdminMessage('Available time added.');
        await refreshDashboard();
    } catch (error) {
        showAdminMessage(error.message, 'danger');
    }
});

async function updateBooking(id, status) {
    try {
        const result = await requestJson('update_status.php', {
            method: 'POST',
            body: JSON.stringify({ id, status }),
        });
        showAdminMessage(result.message, result.emailSent && result.feedbackLinkSent !== false ? 'success' : 'warning');
        await refreshDashboard();
    } catch (error) {
        showAdminMessage(error.message, 'danger');
    }
}

async function removeSlot(id) {
    try {
        await requestJson('manage_slots.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'delete', id }),
        });
        showAdminMessage('Available time removed.');
        await refreshDashboard();
    } catch (error) {
        showAdminMessage(error.message, 'danger');
    }
}

function formatDay(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    });
}

function renderSlots(slots) {
    slotList.replaceChildren();
    if (!slots.length) {
        slotList.append(createText('p', 'text-muted', 'No upcoming times. Add a date and time above.'));
        return;
    }

    slots.forEach((slot) => {
        const row = document.createElement('div');
        row.className = 'admin-row';
        const details = document.createElement('div');
        details.append(
            createText('strong', '', formatDay(slot.appointment_date)),
            createText('p', '', `${slot.start_time.slice(0, 5)}–${slot.end_time.slice(0, 5)} · ${slot.status}`)
        );
        row.append(details);
        if (slot.status === 'Available') {
            row.append(createButton('Remove', 'btn btn-outline-danger', () => removeSlot(Number(slot.id))));
        }
        slotList.append(row);
    });
}

function renderBookings(bookings) {
    bookingList.replaceChildren();
    if (!bookings.length) {
        bookingList.append(createText('p', 'text-muted', 'No booking requests yet.'));
        return;
    }

    bookings.forEach((booking) => {
        const card = document.createElement('article');
        card.className = 'admin-row';
        const details = document.createElement('div');
        details.className = 'flex-grow-1';
        details.append(
            createText('h3', 'h5 mb-1', booking.customer_name),
            createText('p', '', `${booking.service} · ${formatDay(booking.appointment_date)} · ${booking.appointment_time.slice(0, 5)}${booking.end_time ? `–${booking.end_time.slice(0, 5)}` : ''}`),
            createText('p', '', `${booking.email} · ${booking.phone}`),
            createText('p', '', `Bringing hairpieces: ${booking.bring_hairpieces}`),
            createText('p', '', `Status: ${booking.status === 'Cancelled' ? 'Declined / cancelled' : booking.status}`)
        );
        if (booking.message) {
            details.append(createText('p', '', `Message: ${booking.message}`));
        }
        if (booking.style_image && /^uploads\/[A-Za-z0-9._-]+$/.test(booking.style_image)) {
            const image = document.createElement('img');
            image.src = `get_style_image.php?id=${encodeURIComponent(booking.id)}`;
            image.alt = 'Customer’s style reference';
            image.width = 100;
            image.loading = 'lazy';
            details.append(image);
        }

        const actions = document.createElement('div');
        actions.className = 'admin-actions';
        if (booking.status === 'Pending') {
            actions.append(
                createButton('Approve', 'btn btn-success', () => updateBooking(Number(booking.id), 'Confirmed')),
                createButton('Decline', 'btn btn-outline-danger', () => updateBooking(Number(booking.id), 'Declined'))
            );
        } else if (booking.status === 'Confirmed') {
            actions.append(
                createButton('Mark complete', 'btn btn-primary', () => updateBooking(Number(booking.id), 'Completed')),
                createButton('Cancel', 'btn btn-outline-danger', () => updateBooking(Number(booking.id), 'Cancelled'))
            );
        }
        card.append(details, actions);
        bookingList.append(card);
    });
}

function renderFeedback(feedback) {
    feedbackList.replaceChildren();
    if (!feedback.length) {
        feedbackList.append(createText('p', 'text-muted', 'No feedback has been submitted yet.'));
        return;
    }

    feedback.forEach((item) => {
        const card = document.createElement('article');
        card.className = 'admin-row';
        const details = document.createElement('div');
        details.className = 'admin-feedback flex-grow-1';
        const sender = item.name || 'Customer';
        const rating = item.rating ? ` · ${item.rating}/5` : '';
        details.append(
            createText('strong', '', `${item.feedback_type} feedback${rating}`),
            createText('p', '', `${sender}${item.email ? ` · ${item.email}` : ''}`),
            createText('p', '', item.message),
            createText('small', 'text-muted', item.created_at)
        );
        card.append(details);
        feedbackList.append(card);
    });
}

async function refreshDashboard() {
    try {
        const [bookingResult, slotResult, feedbackResult] = await Promise.all([
            requestJson('get_bookings.php'),
            requestJson('manage_slots.php', { method: 'POST', body: JSON.stringify({ action: 'list' }) }),
            requestJson('get_feedback.php'),
        ]);
        renderBookings(bookingResult.bookings);
        renderSlots(slotResult.slots);
        renderFeedback(feedbackResult.feedback);
    } catch (error) {
        if (!adminDashboard.hidden) {
            showAdminMessage(error.message, 'danger');
        }
    }
}

const today = new Date();
document.getElementById('slotDate').min = [
    today.getFullYear(),
    String(today.getMonth() + 1).padStart(2, '0'),
    String(today.getDate()).padStart(2, '0'),
].join('-');

checkSession();
