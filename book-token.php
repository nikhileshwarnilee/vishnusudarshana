<?php
require_once __DIR__ . '/config/db.php';

$pageTitle = 'Book Token';
$tokenBookingCommonNote = '';
$tokenBookingCommonNoteEnabled = true;

try {
    $stmt = $pdo->prepare(
        "SELECT setting_key, setting_value
         FROM system_settings
         WHERE setting_key IN ('token_booking_common_note', 'token_booking_common_note_enabled')"
    );
    $stmt->execute();
    $settingsRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $settings = [];
    foreach ($settingsRows as $row) {
        $key = (string)($row['setting_key'] ?? '');
        if ($key === '') {
            continue;
        }
        $settings[$key] = (string)($row['setting_value'] ?? '');
    }

    $tokenBookingCommonNote = trim((string)($settings['token_booking_common_note'] ?? ''));
    if (function_exists('mb_substr')) {
        $tokenBookingCommonNote = mb_substr($tokenBookingCommonNote, 0, 3000);
    } else {
        $tokenBookingCommonNote = substr($tokenBookingCommonNote, 0, 3000);
    }

    $enabledRaw = strtolower(trim((string)($settings['token_booking_common_note_enabled'] ?? '1')));
    $tokenBookingCommonNoteEnabled = in_array($enabledRaw, ['1', 'true', 'yes', 'on', 'enabled'], true);
} catch (Throwable $e) {
    $tokenBookingCommonNote = '';
    $tokenBookingCommonNoteEnabled = false;
}

$showTokenBookingCommonNote = $tokenBookingCommonNoteEnabled && $tokenBookingCommonNote !== '';

include 'header.php';
?>
<style>
.book-token-main .form-input,
.book-token-main .form-select {
    border: 1.5px solid #f0d27a;
    border-radius: 14px;
    padding: 12px 14px;
    font-size: 1.03em;
    background: #fff;
    box-shadow: 0 2px 10px rgba(212,175,55,0.08);
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.12s ease;
}
.book-token-main .form-input:focus,
.book-token-main .form-select:focus {
    border-color: #d3a12c;
    box-shadow: 0 4px 16px rgba(212,175,55,0.18);
    outline: none;
}
.book-token-main .form-input::placeholder {
    color: #b08b2b;
}
.book-token-main .form-label {
    color: #6b0000;
}
.cutoff-closed-box {
    background: linear-gradient(180deg, #fff4f4 0%, #fff9ee 100%);
    border: 1px solid #f2b6b6;
    border-radius: 14px;
    padding: 12px 12px;
    color: #6b0000;
    line-height: 1.55;
}
.cutoff-closed-box .line {
    margin: 0 0 6px 0;
    font-weight: 700;
}
.cutoff-closed-box .line:last-child {
    margin-bottom: 0;
}
.cutoff-closed-box .line-en {
    color: #4a3b1a;
    font-weight: 600;
}
.token-count-highlight {
    display: inline-block;
    min-width: 34px;
    padding: 2px 10px;
    margin: 0 2px;
    border-radius: 999px;
    font-weight: 900;
    letter-spacing: 0.2px;
    color: #fff;
    text-shadow: 0 1px 1px rgba(0, 0, 0, 0.24);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
    transform: translateZ(0);
}
.token-count-highlight.total {
    background: linear-gradient(135deg, #7a7a7a 0%, #4f4f4f 100%);
}
.token-count-highlight.available {
    background: linear-gradient(135deg, #0ea95a 0%, #08793f 100%);
    animation: tokenPulse 1.35s ease-in-out infinite;
}
.booking-fields-disabled {
    opacity: 0.38;
    pointer-events: none;
    filter: grayscale(0.15);
    transition: opacity 0.18s ease;
}
.token-common-note-box {
    margin: 0 0 16px 0;
    background: linear-gradient(180deg, #fff8dc 0%, #fff2c2 100%);
    border: 1px solid #e7c25d;
    border-radius: 14px;
    color: #6b0000;
    box-shadow: 0 4px 14px rgba(212, 175, 55, 0.17);
    padding: 12px 14px;
    font-size: 1em;
    line-height: 1.55;
    white-space: pre-line;
}
.booking-calendar-wrap {
    margin: 0 0 12px 0;
    padding: 10px 10px 12px;
    background: #fff9e8;
    border: 1px solid #ecd08a;
    border-radius: 14px;
}
.booking-calendar-head {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 8px;
}
.calendar-month-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.calendar-month-title {
    margin: 0;
    color: #6b0000;
    font-size: 1.02em;
    font-weight: 700;
}
.calendar-next-btn {
    border: 1px solid #c48f1f;
    background: linear-gradient(135deg, #ffd76e 0%, #ffb703 100%);
    color: #5a2000;
    border-radius: 999px;
    font-weight: 800;
    font-size: 0.79em;
    padding: 5px 12px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(255, 183, 3, 0.35);
    transition: transform 0.12s ease, box-shadow 0.12s ease;
}
.calendar-next-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(255, 183, 3, 0.45);
}
.calendar-next-btn:active {
    transform: translateY(0);
}
.booking-calendar-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}
.calendar-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.78em;
    color: #6b0000;
    font-weight: 600;
}
.calendar-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    display: inline-block;
}
.calendar-dot.green { background: #0a7a35; }
.calendar-dot.red { background: #b71c1c; }
.calendar-dot.orange { background: #cc5e00; }
.booking-calendar-weekdays,
.booking-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 6px;
}
.booking-calendar-weekdays span {
    text-align: center;
    color: #8a5a00;
    font-size: 0.75em;
    font-weight: 700;
    padding: 2px 0;
}
.calendar-empty-cell {
    min-height: 62px;
}
.calendar-day-btn {
    border: 1px solid transparent;
    border-radius: 10px;
    min-height: 62px;
    padding: 6px 4px;
    background: #fff;
    color: #5f2d00;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    transition: transform 0.12s ease, box-shadow 0.12s ease, border-color 0.12s ease;
}
.calendar-day-btn .day-num {
    font-weight: 800;
    font-size: 0.92em;
}
.calendar-day-btn .day-meta {
    font-size: 0.66em;
    font-weight: 700;
    letter-spacing: 0.1px;
    line-height: 1.15;
}
.calendar-day-btn.green {
    background: #cdf4dc;
    border-color: #0b8f3a;
    color: #075726;
}
.calendar-day-btn.green:hover {
    transform: translateY(-1px);
    box-shadow: 0 5px 14px rgba(11, 143, 58, 0.34);
}
.calendar-day-btn.orange {
    background: #f2a94a;
    border-color: #c45b00;
    color: #5f2a00;
}
.calendar-day-btn.red {
    background: #ffd9d9;
    border-color: #c62828;
    color: #7a0000;
}
.calendar-day-btn.past {
    background: #f4f4f4;
    border-color: #d8d8d8;
    color: #8c8c8c;
}
.calendar-day-btn:disabled {
    cursor: not-allowed;
    opacity: 0.86;
}
.calendar-day-btn.selected {
    border-color: #6b0000;
    box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.2);
}
.selected-date-label {
    margin: 0 0 12px;
    padding: 8px 10px;
    border-radius: 10px;
    border: 1px dashed #e7c25d;
    background: #fff7d6;
    color: #6b0000;
    font-weight: 700;
    text-align: center;
    font-size: 0.93em;
}
@keyframes tokenPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12); }
    50% { transform: scale(1.06); box-shadow: 0 8px 16px rgba(14, 169, 90, 0.32); }
}
</style>
<main class="book-token-main" style="max-width:520px;margin:0 auto;padding:36px 10px;">
    <div style="display:flex;justify-content:center;margin-bottom:22px;">
        <button class="redesigned-cta-btn" style="flex:1 1 260px;max-width:340px;border:3px solid #FFD700;box-shadow:0 4px 18px rgba(212,175,55,0.13);" onclick="window.location.href='live-token.php'">Live Token Status</button>
    </div>
    <div id="loader" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(255,255,255,0.7);z-index:9999;align-items:center;justify-content:center;"><div style="background:#fff7d6;border:2px solid #f2d98c;border-radius:50%;padding:30px 40px;font-size:1.3em;color:#800000;font-weight:bold;box-shadow:0 2px 12px #e7c25d;">Processing...</div></div>
    <h1 style="color:#800000;margin-bottom:10px;text-align:center;letter-spacing:0.3px;">Book Your Token</h1>
    <p style="text-align:center;color:#6b0000;margin-bottom:18px;font-weight:600;">Fast, simple booking for in-person services.</p>
    <?php if ($showTokenBookingCommonNote): ?>
        <div class="token-common-note-box"><?php echo htmlspecialchars($tokenBookingCommonNote); ?></div>
    <?php endif; ?>
    <form id="bookTokenForm" autocomplete="off" style="background:linear-gradient(180deg,#fff7d6 0%,#fffef6 100%);border:1px solid #f2d98c;border-radius:20px;padding:26px 18px 20px 18px;box-shadow:0 6px 22px rgba(212,175,55,0.16);">
        <label class="form-label" style="font-weight:700;margin-bottom:12px;display:block;">City
            <select name="location" class="form-select" required style="margin-top:6px;width:100%;">
                <option value="solapur" selected>Solapur</option>
                <option value="hyderabad">Hyderabad</option>
            </select>
        </label>
        <div class="booking-calendar-wrap">
            <div class="booking-calendar-head">
                <div class="calendar-month-row">
                    <p id="calendarMonthTitle" class="calendar-month-title"></p>
                    <button type="button" id="calendarNextBtn" class="calendar-next-btn" aria-label="Show next month">Next ➜</button>
                </div>
                <div class="booking-calendar-legend">
                    <span class="calendar-legend-item"><span class="calendar-dot green"></span> Available</span>
                    <span class="calendar-legend-item"><span class="calendar-dot orange"></span> Full</span>
                    <span class="calendar-legend-item"><span class="calendar-dot red"></span> Not Released</span>
                </div>
            </div>
            <div class="booking-calendar-weekdays">
                <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
            </div>
            <div id="calendarGrid" class="booking-calendar-grid"></div>
        </div>
        <input type="hidden" name="token_date" id="selectedTokenDate" required>
        <div id="selectedDateLabel" class="selected-date-label">Select a green date from the calendar.</div>
        <div id="tokenInfo" style="margin:0 0 14px 0;font-weight:700;color:#800000;background:#fff2b8;border:1px dashed #e7c25d;border-radius:12px;padding:8px 10px;">Select city and date to see token details.</div>
        <label id="nameFieldWrap" class="form-label" style="font-weight:700;margin-bottom:14px;display:block;">Name
            <input type="text" name="name" class="form-input" required style="margin-top:6px;width:100%;">
        </label>
        <label id="mobileFieldWrap" class="form-label" style="font-weight:700;margin-bottom:14px;display:block;">WhatsApp Number <span style="color:#c00">*</span>
            <input type="tel" name="mobile" class="form-input" pattern="[0-9]{10}" maxlength="10" required style="margin-top:6px;width:100%;">
            <span style="font-size:0.98em;color:#800000;display:block;margin-top:6px;">Please enter your WhatsApp number to get updates on your token queue.</span>
        </label>
        <label class="form-label" style="font-weight:700;margin-bottom:16px;display:none;">Service Time
            <input type="text" name="service_time" class="form-input" readonly style="margin-top:6px;width:100%;background:#fff;display:none;">
        </label>
        <button id="bookTokenSubmit" type="submit" class="redesigned-cta-btn" style="width:100%;margin-top:6px;font-size:1.13em;">Book Token</button>
    </form>
</main>
<script>
const locationSelect = document.querySelector('select[name="location"]');
const tokenInfo = document.getElementById('tokenInfo');
const serviceTimeInput = document.querySelector('input[name="service_time"]');
const form = document.getElementById('bookTokenForm');
const submitButton = document.getElementById('bookTokenSubmit');
const nameInput = document.querySelector('input[name="name"]');
const mobileInput = document.querySelector('input[name="mobile"]');
const nameFieldWrap = document.getElementById('nameFieldWrap');
const mobileFieldWrap = document.getElementById('mobileFieldWrap');
const selectedTokenDateInput = document.getElementById('selectedTokenDate');
const selectedDateLabel = document.getElementById('selectedDateLabel');
const calendarGrid = document.getElementById('calendarGrid');
const calendarMonthTitle = document.getElementById('calendarMonthTitle');
const calendarNextBtn = document.getElementById('calendarNextBtn');

const today = new Date();
today.setHours(0, 0, 0, 0);

let displayedYear = today.getFullYear();
let displayedMonth = today.getMonth() + 1;
const monthLabelFormatter = new Intl.DateTimeFormat('en-IN', { month: 'long', year: 'numeric' });
const fullDateFormatter = new Intl.DateTimeFormat('en-IN', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });

let monthAvailability = {};
let selectedDate = '';

function pad2(value) {
    return String(value).padStart(2, '0');
}

function getIsoDate(dateObj) {
    return `${dateObj.getFullYear()}-${pad2(dateObj.getMonth() + 1)}-${pad2(dateObj.getDate())}`;
}

function parseIsoDate(isoDate) {
    const parts = String(isoDate || '').split('-');
    if (parts.length !== 3) return null;
    const year = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10);
    const day = parseInt(parts[2], 10);
    if (!Number.isFinite(year) || !Number.isFinite(month) || !Number.isFinite(day)) return null;
    const parsed = new Date(year, month - 1, day);
    parsed.setHours(0, 0, 0, 0);
    return parsed;
}

function isSelectedDateToday(isoDate) {
    return String(isoDate || '').trim() === getIsoDate(new Date());
}

function setSubmitButtonEnabled(isEnabled) {
    if (!submitButton) return;
    submitButton.disabled = !isEnabled;
}

function setBookingFieldsEnabled(isEnabled) {
    [nameFieldWrap, mobileFieldWrap, submitButton].forEach((el) => {
        if (!el) return;
        el.classList.toggle('booking-fields-disabled', !isEnabled);
    });

    if (nameInput) {
        nameInput.disabled = !isEnabled;
        nameInput.required = isEnabled;
    }
    if (mobileInput) {
        mobileInput.disabled = !isEnabled;
        mobileInput.required = isEnabled;
    }
}

function formatTime12hr(timeStr) {
    const raw = String(timeStr || '').trim();
    if (!raw) return '';

    const parts = raw.split(':');
    if (parts.length < 2) return raw;

    let hour = parseInt(parts[0], 10);
    const min = parts[1];
    if (!Number.isFinite(hour)) return raw;

    const ampm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12;
    if (hour === 0) hour = 12;
    return `${hour}:${min} ${ampm}`;
}

function renderSameDayClosedMessage(cutoffDisplay, remaining, total) {
    const remainingText = Number.isFinite(remaining) && remaining >= 0 ? remaining : 0;
    const totalText = Number.isFinite(total) && total >= 0 ? total : 0;
    const cutoffText = String(cutoffDisplay || '9:00 AM').trim();

    tokenInfo.innerHTML = `
        <div class="cutoff-closed-box">
            <p class="line">Online booking for today is closed after <b>${cutoffText}</b>.</p>
            <p class="line">Total tokens: <span class="token-count-highlight total">${totalText}</span> | Remaining at counter: <span class="token-count-highlight available">${remainingText}</span></p>
            <p class="line line-en">Please visit reception counter for remaining tokens or choose the next available online date.</p>
        </div>
    `;
}

function clearSelectedDate(keepMessage = false) {
    selectedDate = '';
    if (selectedTokenDateInput) {
        selectedTokenDateInput.value = '';
    }
    if (selectedDateLabel) {
        selectedDateLabel.textContent = 'Select a green date from the calendar.';
    }
    if (serviceTimeInput) {
        serviceTimeInput.value = '';
    }
    setBookingFieldsEnabled(false);
    setSubmitButtonEnabled(false);
    if (!keepMessage && tokenInfo) {
        tokenInfo.textContent = 'Select a green date to see token details.';
    }
}

function getDefaultDayData() {
    return {
        status: 'no_tokens',
        total_tokens: 0,
        available_tokens: 0,
        booked_tokens: 0,
        same_day_cutoff_closed: false
    };
}

function renderCalendar() {
    if (!calendarGrid) return;

    calendarGrid.innerHTML = '';

    const firstDate = new Date(displayedYear, displayedMonth - 1, 1);
    const totalDaysInMonth = new Date(displayedYear, displayedMonth, 0).getDate();
    if (calendarMonthTitle) {
        calendarMonthTitle.textContent = monthLabelFormatter.format(firstDate);
    }

    const firstWeekday = firstDate.getDay();
    for (let i = 0; i < firstWeekday; i += 1) {
        const emptyCell = document.createElement('div');
        emptyCell.className = 'calendar-empty-cell';
        calendarGrid.appendChild(emptyCell);
    }

    for (let day = 1; day <= totalDaysInMonth; day += 1) {
        const dateObj = new Date(displayedYear, displayedMonth - 1, day);
        dateObj.setHours(0, 0, 0, 0);
        const dayKey = getIsoDate(dateObj);

        const dayData = (monthAvailability && monthAvailability[dayKey]) ? monthAvailability[dayKey] : getDefaultDayData();
        const available = Math.max(0, parseInt(dayData.available_tokens, 10) || 0);
        const isPastDate = dateObj < today;
        const isSelectable = !isPastDate && dayData.status === 'available' && !dayData.same_day_cutoff_closed;

        let stateClass = 'red';
        if (dayData.status === 'available' && !dayData.same_day_cutoff_closed) {
            stateClass = 'green';
        } else if (dayData.status === 'full' || dayData.same_day_cutoff_closed) {
            stateClass = 'orange';
        }
        if (isPastDate) {
            stateClass = 'past';
        }

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `calendar-day-btn ${stateClass}`;
        btn.dataset.date = dayKey;
        if (selectedDate === dayKey) {
            btn.classList.add('selected');
        }

        let statusText = 'No tokens released';
        if (stateClass === 'green') {
            statusText = 'Tokens available';
        } else if (stateClass === 'orange') {
            statusText = dayData.same_day_cutoff_closed ? 'Online booking closed for today' : 'Tokens full';
        } else if (stateClass === 'past') {
            statusText = 'Past date';
        }

        btn.innerHTML = `<span class="day-num">${day}</span><span class="day-meta">Token ${available}</span>`;
        btn.title = `${fullDateFormatter.format(dateObj)} | ${statusText}`;

        if (!isSelectable) {
            btn.disabled = true;
        } else {
            btn.addEventListener('click', () => {
                handleDateSelection(dayKey);
            });
        }

        calendarGrid.appendChild(btn);
    }
}

async function loadCalendarAvailability() {
    const location = locationSelect ? locationSelect.value : '';
    clearSelectedDate(true);
    tokenInfo.textContent = 'Loading token calendar...';
    monthAvailability = {};
    renderCalendar();

    if (!location) {
        tokenInfo.textContent = 'Select a city to load token calendar.';
        return;
    }

    try {
        const response = await fetch(
            `book-token-calendar-availability.php?location=${encodeURIComponent(location)}&year=${displayedYear}&month=${displayedMonth}`,
            { cache: 'no-store' }
        );
        const data = await response.json();

        if (data.success && data.days && typeof data.days === 'object') {
            monthAvailability = data.days;
            renderCalendar();
            tokenInfo.textContent = 'Select a green date to see token details.';
            return;
        }

        monthAvailability = {};
        renderCalendar();
        tokenInfo.textContent = data.message || 'Unable to load token calendar.';
    } catch (error) {
        monthAvailability = {};
        renderCalendar();
        tokenInfo.textContent = 'Unable to load token calendar right now.';
    }
}

async function updateTokenInfoForDate(date) {
    if (!date) {
        clearSelectedDate();
        return;
    }

    const location = locationSelect ? locationSelect.value : '';
    tokenInfo.textContent = 'Checking token details...';
    serviceTimeInput.value = '';
    setBookingFieldsEnabled(false);
    setSubmitButtonEnabled(false);

    try {
        const response = await fetch(
            `book-token-availability.php?date=${encodeURIComponent(date)}&location=${encodeURIComponent(location)}`,
            { cache: 'no-store' }
        );
        const data = await response.json();

        if (!(data.success && data.data)) {
            tokenInfo.textContent = 'No tokens available/released for this date and city.';
            return;
        }

        const t = data.data;
        const remaining = Math.max(0, parseInt(t.unbooked_tokens, 10) || 0);
        const total = Math.max(0, parseInt(t.total_tokens, 10) || 0);
        const from12 = formatTime12hr(t.from_time);
        const to12 = formatTime12hr(t.to_time);
        const sameDayClosed = Boolean(data.same_day_online_closed);
        const cutoffDisplay = data.same_day_online_cutoff_display || '9:00 AM';

        if (sameDayClosed && isSelectedDateToday(date)) {
            renderSameDayClosedMessage(cutoffDisplay, remaining, total);
            return;
        }

        if (isSelectedDateToday(date) && String(t.to_time || '').trim() !== '') {
            const now = new Date();
            const timeParts = String(t.to_time).split(':');
            if (timeParts.length >= 2) {
                const toHour = parseInt(timeParts[0], 10);
                const toMin = parseInt(timeParts[1], 10);
                if (Number.isFinite(toHour) && Number.isFinite(toMin)) {
                    const toTime = new Date(now.getFullYear(), now.getMonth(), now.getDate(), toHour, toMin, 0, 0);
                    if (now > toTime) {
                        tokenInfo.textContent = `Booking is closed for today because service time (${from12} to ${to12}) has passed.`;
                        return;
                    }
                }
            }
        }

        if (total <= 0) {
            tokenInfo.textContent = 'No tokens released for this date.';
            return;
        }

        if (remaining <= 0) {
            tokenInfo.textContent = 'Tokens are full for this date. Available: 0';
            return;
        }

        let infoText = `Available tokens: ${remaining}`;
        if (from12 && to12) {
            infoText += ` | Service time: ${from12} to ${to12}`;
            serviceTimeInput.value = `${from12} to ${to12}`;
        }
        tokenInfo.textContent = infoText;
        setBookingFieldsEnabled(true);
        setSubmitButtonEnabled(true);
    } catch (error) {
        tokenInfo.textContent = 'Unable to fetch token details right now.';
    }
}

async function handleDateSelection(dateKey) {
    selectedDate = dateKey;
    if (selectedTokenDateInput) {
        selectedTokenDateInput.value = dateKey;
    }

    const parsedDate = parseIsoDate(dateKey);
    if (selectedDateLabel) {
        selectedDateLabel.textContent = parsedDate
            ? `Selected: ${fullDateFormatter.format(parsedDate)}`
            : `Selected: ${dateKey}`;
    }

    renderCalendar();
    await updateTokenInfoForDate(dateKey);
}

locationSelect.addEventListener('change', async () => {
    await loadCalendarAvailability();
});

if (calendarNextBtn) {
    calendarNextBtn.addEventListener('click', async () => {
        displayedMonth += 1;
        if (displayedMonth > 12) {
            displayedMonth = 1;
            displayedYear += 1;
        }
        await loadCalendarAvailability();
    });
}

window.addEventListener('DOMContentLoaded', async () => {
    setBookingFieldsEnabled(false);
    setSubmitButtonEnabled(false);
    renderCalendar();
    await loadCalendarAvailability();
});

form.addEventListener('submit', function (e) {
    e.preventDefault();

    const date = selectedTokenDateInput ? selectedTokenDateInput.value : '';
    const location = locationSelect ? locationSelect.value : '';
    if (!date) {
        tokenInfo.textContent = 'Please select a green date from the calendar.';
        return;
    }

    const loader = document.getElementById('loader');
    loader.style.display = 'flex';

    fetch(`book-token-availability.php?date=${encodeURIComponent(date)}&location=${encodeURIComponent(location)}`, { cache: 'no-store' })
        .then((res) => res.json())
        .then((data) => {
            if (!(data.success && data.data)) {
                loader.style.display = 'none';
                tokenInfo.textContent = 'No tokens available for this date/location.';
                setSubmitButtonEnabled(false);
                setBookingFieldsEnabled(false);
                loadCalendarAvailability();
                return;
            }

            const t = data.data;
            const remaining = Math.max(0, parseInt(t.unbooked_tokens, 10) || 0);
            const total = Math.max(0, parseInt(t.total_tokens, 10) || 0);
            const sameDayClosed = Boolean(data.same_day_online_closed);
            const cutoffDisplay = data.same_day_online_cutoff_display || '9:00 AM';

            if (sameDayClosed && isSelectedDateToday(date)) {
                loader.style.display = 'none';
                renderSameDayClosedMessage(cutoffDisplay, remaining, total);
                setBookingFieldsEnabled(false);
                setSubmitButtonEnabled(false);
                loadCalendarAvailability();
                return;
            }

            if (total <= 0 || remaining <= 0) {
                loader.style.display = 'none';
                tokenInfo.textContent = 'Tokens are not available for this date. Please pick another green date.';
                setBookingFieldsEnabled(false);
                setSubmitButtonEnabled(false);
                loadCalendarAvailability();
                return;
            }

            if (isSelectedDateToday(date) && String(t.to_time || '').trim() !== '') {
                const now = new Date();
                const timeParts = String(t.to_time).split(':');
                if (timeParts.length >= 2) {
                    const toHour = parseInt(timeParts[0], 10);
                    const toMin = parseInt(timeParts[1], 10);
                    if (Number.isFinite(toHour) && Number.isFinite(toMin)) {
                        const toTime = new Date(now.getFullYear(), now.getMonth(), now.getDate(), toHour, toMin, 0, 0);
                        if (now > toTime) {
                            loader.style.display = 'none';
                            tokenInfo.textContent = 'Booking is closed for today because service time has passed.';
                            setBookingFieldsEnabled(false);
                            setSubmitButtonEnabled(false);
                            loadCalendarAvailability();
                            return;
                        }
                    }
                }
            }

            const formData = new FormData(form);
            fetch('save-book-token.php', {
                method: 'POST',
                body: formData
            })
                .then((saveRes) => saveRes.json())
                .then((saveData) => {
                    loader.style.display = 'none';
                    if (saveData.success && saveData.token_no) {
                        form.style.display = 'none';
                        const successCard = document.createElement('div');
                        successCard.style = 'background:#fff7d6;border:1px solid #f2d98c;border-radius:20px;padding:26px 18px 20px 18px;box-shadow:0 6px 22px rgba(212,175,55,0.16);margin-top:18px;text-align:center;';
                        successCard.innerHTML = `
                            <h2 style="color:#800000;margin-bottom:10px;">Token Booked Successfully</h2>
                            <div style="font-size:1.13em;color:#6b0000;margin-bottom:12px;">Appointment Date: <b>${date}</b></div>
                            <div style="font-size:1.13em;color:#6b0000;margin-bottom:12px;">Service Time: <b>${serviceTimeInput.value || '-'}</b></div>
                            <div style="font-size:1.13em;color:#800000;margin-bottom:18px;">Your Token Number: <b>${saveData.token_no}</b></div>
                            <div style="font-size:1.05em;color:#008000;margin-bottom:10px;">You will receive updates on your WhatsApp number.</div>
                        `;
                        form.parentNode.appendChild(successCard);
                        return;
                    }

                    if (saveData.duplicate_booking) {
                        const duplicateMessage = saveData.error || 'Token already booked with this number.';
                        tokenInfo.innerHTML = `<span style="color:#7a0000;font-weight:800;">${duplicateMessage}</span>`;
                        setBookingFieldsEnabled(false);
                        setSubmitButtonEnabled(false);
                        if (typeof window.alert === 'function') {
                            window.alert(duplicateMessage);
                        }
                        return;
                    }

                    if (saveData.same_day_closed) {
                        renderSameDayClosedMessage(
                            saveData.same_day_online_cutoff_display || '9:00 AM',
                            Number(saveData.remaining_tokens),
                            Number(saveData.total_tokens)
                        );
                        setBookingFieldsEnabled(false);
                        setSubmitButtonEnabled(false);
                        loadCalendarAvailability();
                        return;
                    }

                    tokenInfo.textContent = saveData.error || 'Failed to book token.';
                    setBookingFieldsEnabled(false);
                    setSubmitButtonEnabled(false);
                    loadCalendarAvailability();
                })
                .catch(() => {
                    loader.style.display = 'none';
                    tokenInfo.textContent = 'Error saving your booking.';
                    setBookingFieldsEnabled(false);
                    setSubmitButtonEnabled(false);
                });
        })
        .catch(() => {
            loader.style.display = 'none';
            tokenInfo.textContent = 'Error processing your request.';
            setBookingFieldsEnabled(false);
            setSubmitButtonEnabled(false);
        });
});
</script>
<?php include 'footer.php'; ?>
