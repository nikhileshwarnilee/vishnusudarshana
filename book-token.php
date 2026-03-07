<?php 
$pageTitle = 'Book Token'; 
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
    <form id="bookTokenForm" autocomplete="off" style="background:linear-gradient(180deg,#fff7d6 0%,#fffef6 100%);border:1px solid #f2d98c;border-radius:20px;padding:26px 18px 20px 18px;box-shadow:0 6px 22px rgba(212,175,55,0.16);">
        <label class="form-label" style="font-weight:700;margin-bottom:14px;display:block;">Select Appointment Token Date
            <input type="date" name="token_date" class="form-input" required style="margin-top:6px;width:100%;">
        </label>
        <label class="form-label" style="font-weight:700;margin-bottom:14px;display:block;">Location
            <select name="location" class="form-select" required style="margin-top:6px;width:100%;">
                <option value="solapur" selected>Solapur</option>
                <option value="hyderabad">Hyderabad</option>
            </select>
        </label>
        <div id="tokenInfo" style="margin:-4px 0 14px 0;font-weight:700;color:#800000;background:#fff2b8;border:1px dashed #e7c25d;border-radius:12px;padding:8px 10px;">Select a date to see token availability.</div>
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
        <button id="bookTokenSubmit" type="submit" class="redesigned-cta-btn" style="width:100%;margin-top:6px;font-size:1.13em;">Book Office Visit Token</button>
    </form>
</main>
<script>
const dateInput = document.querySelector('input[name="token_date"]');
const locationSelect = document.querySelector('select[name="location"]');
const tokenInfo = document.getElementById('tokenInfo');
const serviceTimeInput = document.querySelector('input[name="service_time"]');
const form = document.getElementById('bookTokenForm');
const submitButton = document.getElementById('bookTokenSubmit');
const nameInput = document.querySelector('input[name="name"]');
const mobileInput = document.querySelector('input[name="mobile"]');
const nameFieldWrap = document.getElementById('nameFieldWrap');
const mobileFieldWrap = document.getElementById('mobileFieldWrap');

// Prevent selecting past dates
const today = new Date();
const yyyy = today.getFullYear();
const mm = String(today.getMonth() + 1).padStart(2, '0');
const dd = String(today.getDate()).padStart(2, '0');
dateInput.min = `${yyyy}-${mm}-${dd}`;
dateInput.value = '';

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
    if (!timeStr) return '';
    const [h, m] = timeStr.split(":");
    let hour = parseInt(h, 10);
    const min = m;
    const ampm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12;
    if (hour === 0) hour = 12;
    return `${hour}:${min} ${ampm}`;
}

function renderSameDayClosedMessage(cutoffDisplay, remaining, total) {
    const remainingText = Number.isFinite(remaining) && remaining >= 0 ? remaining : 0;
    const totalText = Number.isFinite(total) && total >= 0 ? total : 0;
    const cutoffRaw = String(cutoffDisplay || '9:00 AM').trim();
    const cutoffMatch = cutoffRaw.match(/^(\d{1,2}:\d{2})(?:\s*(AM|PM))?$/i);
    const cutoffMr = cutoffMatch ? cutoffMatch[1] : cutoffRaw;
    const cutoffEn = cutoffMatch
        ? (cutoffMatch[1] + (cutoffMatch[2] ? (' ' + cutoffMatch[2].toUpperCase()) : ''))
        : cutoffRaw;

    tokenInfo.innerHTML = `
        <div class="cutoff-closed-box">
            <p class="line">आजच्या दिवसासाठी ऑनलाइन टोकन बुकिंग सकाळी <b>${cutoffMr}</b> वाजता बंद झाली आहे.</p>
            <p class="line">एकूण <span class="token-count-highlight total">${totalText}</span> टोकनपैकी <span class="token-count-highlight available">${remainingText}</span> टोकन अद्याप उपलब्ध आहेत.</p>
            <p class="line">ही टोकन फक्त कार्यालयातील रिसेप्शन काउंटरवरून प्रत्यक्ष येऊन घ्यावीत.</p>
            <p class="line">कृपया पुढील तारखांसाठी ऑनलाइन टोकन बुक करण्याचा प्रयत्न करावा.</p>
            <p class="line line-en">Online token booking for today closed at <b>${cutoffEn}</b>.</p>
            <p class="line line-en">Out of <span class="token-count-highlight total">${totalText}</span> total tokens, <span class="token-count-highlight available">${remainingText}</span> tokens are still available.</p>
            <p class="line line-en">These tokens are available only at the office reception counter and can be collected by visiting the office.</p>
            <p class="line line-en">Please try booking an online token for the upcoming dates.</p>
        </div>
    `;
}

function updateTokenInfo() {
    const date = dateInput.value;
    const location = locationSelect.value;
    if (!date) {
        tokenInfo.textContent = 'Select a date to see token availability.';
        serviceTimeInput.value = '';
        setBookingFieldsEnabled(true);
        setSubmitButtonEnabled(false);
        return;
    }
    fetch(`book-token-availability.php?date=${encodeURIComponent(date)}&location=${encodeURIComponent(location)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const t = data.data;
                const remaining = parseInt(t.unbooked_tokens, 10);
                const total = parseInt(t.total_tokens, 10);
                const from12 = formatTime12hr(t.from_time);
                const to12 = formatTime12hr(t.to_time);
                const notes = t.notes || '';
                const sameDayClosed = Boolean(data.same_day_online_closed);
                const cutoffDisplay = data.same_day_online_cutoff_display || '9:00 AM';
                // Check if selected date is today and current time is past to_time
                const now = new Date();
                let timePassed = false;
                if (now.toISOString().slice(0,10) === date) {
                    if (sameDayClosed) {
                        renderSameDayClosedMessage(cutoffDisplay, remaining, total);
                        serviceTimeInput.value = '';
                        setBookingFieldsEnabled(false);
                        setSubmitButtonEnabled(false);
                        return;
                    }
                    // Compare current time with to_time
                    const [toHour, toMin] = t.to_time.split(":");
                    const toTime = new Date(now.getFullYear(), now.getMonth(), now.getDate(), parseInt(toHour,10), parseInt(toMin,10), 0);
                    if (now > toTime) timePassed = true;
                }
                if (timePassed) {
                    tokenInfo.textContent = `Booking closed: Service time for (${from12} to ${to12}) has already passed for today. Pls select another date.`;
                    serviceTimeInput.value = '';
                    setBookingFieldsEnabled(true);
                    setSubmitButtonEnabled(false);
                } else {
                    if (total === 0) {
                        if (notes) {
                            tokenInfo.innerHTML = `Tokens remaining: ${remaining} <span style="color:#fff;background:#ff0600;padding:2px 10px;border-radius:8px;font-weight:900;box-shadow:0 2px 8px #e5393555;display:inline-block;margin-left:8px;">${notes}</span>`;
                        } else {
                            tokenInfo.textContent = `Tokens remaining: ${remaining}`;
                        }
                        serviceTimeInput.value = '';
                    } else {
                        tokenInfo.textContent = `Tokens remaining: ${remaining} | Service time: ${from12} to ${to12}`;
                        serviceTimeInput.value = from12 + ' to ' + to12;
                    }
                    setBookingFieldsEnabled(true);
                    setSubmitButtonEnabled(true);
                }
            } else {
                tokenInfo.textContent = 'No tokens Available/Released for this date/location. Please select another date.';
                serviceTimeInput.value = '';
                setBookingFieldsEnabled(true);
                setSubmitButtonEnabled(false);
            }
        })
        .catch(() => {
            tokenInfo.textContent = 'Unable to fetch token availability right now.';
            serviceTimeInput.value = '';
            setBookingFieldsEnabled(true);
            setSubmitButtonEnabled(false);
        });
}

dateInput.addEventListener('change', updateTokenInfo);
locationSelect.addEventListener('change', updateTokenInfo);
window.addEventListener('DOMContentLoaded', function () {
    tokenInfo.textContent = 'Select a date to see token availability.';
    setBookingFieldsEnabled(true);
    setSubmitButtonEnabled(false);
});

form.addEventListener('submit', function(e) {
    e.preventDefault();
    const loader = document.getElementById('loader');
    loader.style.display = 'flex';
    const date = dateInput.value;
    fetch(`book-token-availability.php?date=${encodeURIComponent(date)}&location=${encodeURIComponent(locationSelect.value)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const t = data.data;
                const remaining = parseInt(t.unbooked_tokens, 10);
                const total = parseInt(t.total_tokens, 10);
                const sameDayClosed = Boolean(data.same_day_online_closed);
                const cutoffDisplay = data.same_day_online_cutoff_display || '9:00 AM';
                const now = new Date();
                if (now.toISOString().slice(0,10) === date) {
                    if (sameDayClosed) {
                        loader.style.display = 'none';
                        renderSameDayClosedMessage(cutoffDisplay, remaining, total);
                        setBookingFieldsEnabled(false);
                        setSubmitButtonEnabled(false);
                        return;
                    }
                    const [toHour, toMin] = t.to_time.split(":");
                    const toTime = new Date(now.getFullYear(), now.getMonth(), now.getDate(), parseInt(toHour,10), parseInt(toMin,10), 0);
                    if (now > toTime) {
                        loader.style.display = 'none';
                        tokenInfo.textContent = `Booking closed: Service time has already passed for today.`;
                        setBookingFieldsEnabled(true);
                        setSubmitButtonEnabled(false);
                        return;
                    }
                }
                // Proceed with booking
                const formData = new FormData(form);
                fetch('save-book-token.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    loader.style.display = 'none';
                    if (data.success && data.token_no) {
                        // Remove form and show success card
                        form.style.display = 'none';
                        const successCard = document.createElement('div');
                        successCard.style = 'background:#fff7d6;border:1px solid #f2d98c;border-radius:20px;padding:26px 18px 20px 18px;box-shadow:0 6px 22px rgba(212,175,55,0.16);margin-top:18px;text-align:center;';
                        successCard.innerHTML = `
                            <h2 style="color:#800000;margin-bottom:10px;">🎉 Token Booked Successfully!</h2>
                            <div style="font-size:1.13em;color:#6b0000;margin-bottom:12px;">📅 Appointment Date: <b>${dateInput.value}</b></div>
                            <div style="font-size:1.13em;color:#6b0000;margin-bottom:12px;">⏰ Service Time: <b>${serviceTimeInput.value}</b></div>
                            <div style="font-size:1.13em;color:#800000;margin-bottom:18px;">🎟️ Your Token Number: <b>${data.token_no}</b></div>
                            <div style="font-size:1.05em;color:#008000;margin-bottom:10px;">📱 You will be notified on <stronge> WhatsApp </stronge> when you are closest to your appointment number.</div>
                        `;
                        form.parentNode.appendChild(successCard);
                    } else {
                        if (data.same_day_closed) {
                            renderSameDayClosedMessage(
                                data.same_day_online_cutoff_display || '9:00 AM',
                                Number(data.remaining_tokens),
                                Number(data.total_tokens)
                            );
                            setBookingFieldsEnabled(false);
                            setSubmitButtonEnabled(false);
                            return;
                        }
                        tokenInfo.textContent = data.error || 'Failed to book token.';
                    }
                })
                .catch(() => {
                    loader.style.display = 'none';
                    tokenInfo.textContent = 'Error saving your booking.';
                });
            } else {
                loader.style.display = 'none';
                tokenInfo.textContent = 'No tokens available for this date/location.';
                form.querySelector('button[type="submit"]').disabled = true;
            }
        })
        .catch(() => {
            loader.style.display = 'none';
            tokenInfo.textContent = 'Error processing your request.';
        });
});
</script>
<?php include 'footer.php'; ?>
