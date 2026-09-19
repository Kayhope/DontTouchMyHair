// track.js — Admin bookings view for the no-server version.
// Reads/writes the same "dtmh_bookings" localStorage key that
// Book/booking.html saves to. Must be opened in the same browser
// on the same computer as where bookings were made.

document.addEventListener('DOMContentLoaded', loadBookings);

function getBookings() {
    try {
        return JSON.parse(localStorage.getItem('dtmh_bookings')) || [];
    } catch (e) {
        return [];
    }
}

function saveBookings(bookings) {
    localStorage.setItem('dtmh_bookings', JSON.stringify(bookings));
}

function loadBookings() {
    const emptyMsg = document.getElementById('emptyMsg');
    const table = document.getElementById('bookingTable');
    const bookingList = document.getElementById('bookingList');

    const bookings = getBookings().sort((a, b) => (b.createdAt || '').localeCompare(a.createdAt || ''));

    if (bookings.length === 0) {
        emptyMsg.classList.remove('d-none');
        table.classList.add('d-none');
        return;
    }

    emptyMsg.classList.add('d-none');
    table.classList.remove('d-none');
    bookingList.innerHTML = '';

    bookings.forEach((booking) => {
        const row = document.createElement('tr');
        const when = `${booking.date} ${booking.time}`;
        const imageCell = booking.styleImage
            ? `<img src="${booking.styleImage}" alt="Style" style="width: 50px; height: 50px; object-fit: cover;">`
            : 'No image';

        row.innerHTML = `
            <td>${escapeHtml(booking.name)}</td>
            <td>${escapeHtml(booking.email)}<br>${escapeHtml(booking.phone)}</td>
            <td>${escapeHtml(booking.service)}</td>
            <td>${escapeHtml(when)}</td>
            <td>${escapeHtml(booking.bringHairpieces)}</td>
            <td id="status-${booking.id}">${escapeHtml(booking.status)}</td>
            <td>${imageCell}</td>
            <td>
                <button class="confirm" data-id="${booking.id}" data-status="Confirmed">Confirm</button>
                <button class="complete" data-id="${booking.id}" data-status="Completed">Complete</button>
                <button class="cancel" data-id="${booking.id}" data-status="Cancelled">Cancel</button>
            </td>
        `;

        bookingList.appendChild(row);
    });

    bookingList.querySelectorAll('button[data-id]').forEach((btn) => {
        btn.addEventListener('click', () => updateStatus(Number(btn.dataset.id), btn.dataset.status));
    });
}

function updateStatus(id, status) {
    const bookings = getBookings();
    const booking = bookings.find((b) => b.id === id);
    if (booking) {
        booking.status = status;
        saveBookings(bookings);
        loadBookings();
    }
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
