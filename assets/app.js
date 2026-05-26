const state = {
    seats: [],
    selected: [],
    prices: { kat1: 40, kat2: 30, student: 20 },
    isStudent: false,
    delivery: 'pickup',
    bookingEnabled: true,
};

async function init() {
    try {
        const res = await fetch('api/get-seats.php');
        if (!res.ok) throw new Error('Failed to load seat data');
        const data = await res.json();
        state.seats = data.seats;
        state.prices = data.prices;
        state.bookingEnabled = data.booking_enabled;

        renderGrid(document.getElementById('grid-container'), state.seats, createSeatElement);
        renderCart();

        if (!state.bookingEnabled) {
            document.getElementById('booking-disabled-overlay').classList.add('show');
            document.documentElement.style.overflow = 'hidden';
            document.body.style.overflow = 'hidden';
        }
    } catch (err) {
        console.error(err);
        document.getElementById('grid-container').innerHTML =
            '<div class="alert error show">Fehler beim Laden des Sitzplans. Bitte versuche es später erneut.</div>';
    }
}

function createSeatElement(seat) {
    const el = document.createElement('div');
    const isSelected = state.selected.includes(seat.number);
    const status = seat.status;
    const cat = seat.cat;

    let classes = 'seat-cell';
    if (cat === '1') classes += ' kat1';
    else classes += ' kat2';

    if (isSelected) {
        classes += ' selected';
    } else if (status === 'available') {
        classes += ' available';
    } else if (status === 'reserved') {
        classes += ' reserved';
    } else if (status === 'pending') {
        classes += ' pending';
    } else if (status === 'disabled') {
        classes += ' disabled';
    }

    if (seat.is_bodan) {
        classes += ' bodan-mark';
    }

    el.className = classes;
    el.textContent = String(seat.number).padStart(2, '0');

    if (seat.is_bodan) {
        const tip = document.createElement('div');
        tip.className = 'tooltip-text';
        tip.textContent = 'Nur in der Bodan Buchhandlung erhältlich';
        el.appendChild(tip);
    }

    if (seat.status === 'disabled') {
        const tip = document.createElement('div');
        tip.className = 'tooltip-text';
        tip.textContent = 'nicht verfügbar';
        el.appendChild(tip);
    }

    if ((status === 'available' || isSelected) && !seat.is_bodan) {
        el.addEventListener('click', () => toggleSeat(seat.number));
    }
    return el;
}

// ======== CART ========

function toggleSeat(num) {
    if (!state.bookingEnabled) return;

    const idx = state.selected.indexOf(num);
    if (idx >= 0) {
        state.selected.splice(idx, 1);
    } else {
        state.selected.push(num);
    }

    renderGrid(document.getElementById('grid-container'), state.seats, createSeatElement);
    renderCart();
}

function renderCart() {
    const cart = document.getElementById('cart-items');
    const totalEl = document.getElementById('cart-total');
    const countEl = document.getElementById('cart-count');
    const submitBtn = document.getElementById('submit-btn');
    const emptyEl = document.getElementById('cart-empty');

    if (state.selected.length === 0) {
        cart.innerHTML = '';
        emptyEl.style.display = '';
        countEl.textContent = '0';
        totalEl.innerHTML = 'CHF 0.00';
        submitBtn.disabled = true;
        return;
    }

    emptyEl.style.display = 'none';
    countEl.textContent = state.selected.length;

    const seatMap = {};
    state.seats.forEach(s => { seatMap[s.number] = s; });

    let total = 0;
    let html = '';

    state.selected.forEach(num => {
        const s = seatMap[num];
        if (!s) return;
        const price = state.isStudent ? state.prices.student :
            (s.cat === '1' ? state.prices.kat1 : state.prices.kat2);
        total += price;

        html += `
            <li class="cart-item">
                <div class="seat-info">
                    <span class="seat-badge kat${s.cat}">${num}</span>
                    <span class="seat-number">Reihe ${s.row}, Platz ${num}</span>
                </div>
                <div>
                    <span class="seat-price">CHF ${price.toFixed(2)}</span>
                    <button class="remove-btn" onclick="toggleSeat(${num})" title="Entfernen">&times;</button>
                </div>
            </li>
        `;
    });

    const deliveryFee = state.delivery === 'mail' ? 5 : 0;
    total += deliveryFee;

    cart.innerHTML = html;
    let totalHtml = `CHF ${total.toFixed(2)}`;
    if (state.isStudent) totalHtml += ' <span class="student-note">(Studentenpreis)</span>';
    if (deliveryFee) totalHtml += ' <span class="student-note">(+ Fr. 5.-- Zustellung)</span>';
    totalEl.innerHTML = totalHtml;
    submitBtn.disabled = false;
}

function updateStudentToggle() {
    state.isStudent = document.getElementById('student-toggle').checked;
    renderCart();
}

async function submitOrder(event) {
    event.preventDefault();

    const submitBtn = document.getElementById('submit-btn');
    const alertEl = document.getElementById('form-alert');

    alertEl.className = 'alert';
    alertEl.style.display = 'none';
    submitBtn.disabled = true;
    submitBtn.classList.add('loading');

    if (state.selected.length === 0) {
        showAlert('Bitte wähle mindestens einen Platz aus.', 'error');
        submitBtn.disabled = false;
        submitBtn.classList.remove('loading');
        return;
    }

    const name = document.getElementById('field-name').value.trim();
    const email = document.getElementById('field-email').value.trim();
    const phone = document.getElementById('field-phone').value.trim();
    const notes = document.getElementById('field-notes')?.value.trim() || '';
    const honeypot = document.getElementById('field-hp').value;
    const delivery = document.querySelector('input[name="delivery"]:checked')?.value || 'pickup';
    const street = document.getElementById('field-street')?.value.trim() || '';
    const city = document.getElementById('field-city')?.value.trim() || '';

    if (honeypot) {
        showAlert('Deine Bestellung konnte nicht verarbeitet werden.', 'error');
        submitBtn.disabled = false;
        submitBtn.classList.remove('loading');
        return;
    }

    if (!name) {
        showAlert('Bitte gib deinen Namen ein.', 'error');
        submitBtn.disabled = false;
        submitBtn.classList.remove('loading');
        document.getElementById('field-name').focus();
        return;
    }

    if (delivery === 'mail') {
        if (!street) {
            showAlert('Bitte gib deine Strasse und Hausnummer für die Zustellung an.', 'error');
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
            document.getElementById('field-street').focus();
            return;
        }
        if (!city) {
            showAlert('Bitte gib PLZ und Ort für die Zustellung an.', 'error');
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
            document.getElementById('field-city').focus();
            return;
        }
    }

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showAlert('Bitte gib eine gültige E-Mail-Adresse ein.', 'error');
        submitBtn.disabled = false;
        submitBtn.classList.remove('loading');
        document.getElementById('field-email').focus();
        return;
    }

    try {
        const res = await fetch('api/reserve.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                seats: state.selected,
                name,
                email,
                phone,
                street,
                city,
                is_student: state.isStudent,
                delivery,
                notes,
            }),
        });

        const data = await res.json();

        if (!res.ok || data.error) {
            showAlert(data.error || 'Ein Fehler ist aufgetreten. Bitte versuche es später erneut.', 'error');
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
            return;
        }

        showAlert(
            'Deine Reservierung ist eingegangen! Wir haben dir eine Bestätigungs-E-Mail gesendet. Bitte klicke den Link in der E-Mail, um die Reservierung zu bestätigen.',
            'success'
        );
        state.selected = [];
        renderGrid(document.getElementById('grid-container'), state.seats, createSeatElement);
        renderCart();
        document.getElementById('order-form').reset();

    } catch (err) {
        console.error(err);
        showAlert('Ein Netzwerkfehler ist aufgetreten. Bitte versuche es später erneut.', 'error');
    }

    submitBtn.disabled = false;
    submitBtn.classList.remove('loading');
}

function showAlert(msg, type) {
    const el = document.getElementById('form-alert');
    el.className = `alert ${type} show`;
    el.textContent = msg;
    el.style.display = 'block';
}

document.addEventListener('DOMContentLoaded', () => {
    init();
    document.getElementById('student-toggle').addEventListener('change', updateStudentToggle);

    const deliveryRadios = document.querySelectorAll('input[name="delivery"]');
    deliveryRadios.forEach(r => {
        r.addEventListener('change', () => {
            state.delivery = document.querySelector('input[name="delivery"]:checked')?.value || 'pickup';
            const shippingFields = document.getElementById('shipping-fields');
            if (state.delivery === 'mail') {
                shippingFields.style.display = '';
            } else {
                shippingFields.style.display = 'none';
            }
            renderCart();
        });
    });

    document.getElementById('order-form').addEventListener('submit', submitOrder);

    const bodanToggle = document.getElementById('bodan-toggle');
    const bodanInfo = document.getElementById('bodan-info');
    if (bodanToggle && bodanInfo) {
        bodanToggle.addEventListener('click', () => {
            bodanInfo.classList.toggle('open');
        });
    }
});
