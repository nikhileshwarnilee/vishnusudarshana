<?php
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$city = isset($_GET['city']) ? strtolower(trim((string) $_GET['city'])) : '';
$allowedCities = ['solapur', 'hyderabad'];
if ($city !== '' && !in_array($city, $allowedCities, true)) {
    $city = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#61000f">
    <title>Live Token TV Display</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Marcellus:wght@500;700&family=Mulish:wght@500;700;800&display=swap');

        :root {
            --bg: #fff5d5;
            --panel: #fffdf3;
            --ink: #3f1717;
            --muted: #805a4d;
            --gold: #e7c35a;
            --gold-soft: #fff0b8;
            --maroon: #78000d;
            --maroon-dark: #4c0008;
            --green: #116149;
            --shadow: 0 24px 70px rgba(92, 0, 0, 0.18);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            margin: 0;
            overflow: hidden;
            background:
                radial-gradient(circle at 12% 12%, rgba(255, 255, 255, 0.95) 0 12%, transparent 34%),
                linear-gradient(135deg, #fff7da 0%, #f6dda4 47%, #fff1bd 100%);
            color: var(--ink);
            font-family: Mulish, Arial, sans-serif;
        }

        .tv-display {
            min-height: 100vh;
            width: 100vw;
            display: flex;
            flex-direction: column;
            gap: clamp(18px, 2.4vh, 32px);
            padding: clamp(22px, 3vw, 54px);
        }

        .tv-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            min-height: 72px;
        }

        .brand-block {
            display: flex;
            align-items: center;
            gap: 18px;
            min-width: 0;
        }

        .brand-logo {
            width: clamp(58px, 6vw, 98px);
            height: clamp(58px, 6vw, 98px);
            object-fit: contain;
            flex: 0 0 auto;
        }

        .brand-title {
            margin: 0;
            font-family: Marcellus, Georgia, serif;
            font-size: clamp(1.7rem, 3vw, 4rem);
            line-height: 1;
            color: var(--maroon);
            white-space: nowrap;
        }

        .brand-subtitle {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: clamp(0.95rem, 1.25vw, 1.45rem);
            font-weight: 700;
        }

        .clock {
            color: var(--maroon-dark);
            font-family: Marcellus, Georgia, serif;
            font-size: clamp(1.2rem, 2vw, 2.8rem);
            text-align: right;
            white-space: nowrap;
        }

        .cards-row {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(520px, 100%), 1fr));
            gap: clamp(24px, 3vw, 52px);
            align-items: stretch;
            min-height: 0;
        }

        .cards-row.single-card {
            grid-template-columns: minmax(min(760px, 100%), 1180px);
            justify-content: center;
        }

        .token-card {
            display: flex;
            flex-direction: column;
            min-height: 0;
            background: var(--panel);
            border: 3px solid rgba(120, 0, 13, 0.16);
            border-radius: 8px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .token-card[hidden] {
            display: none;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: clamp(20px, 2vw, 34px);
            background: linear-gradient(90deg, var(--maroon), #9d1b12);
            color: #fff9de;
        }

        .city-name {
            font-family: Marcellus, Georgia, serif;
            font-size: clamp(2rem, 3.2vw, 5rem);
            line-height: 0.95;
        }

        .date-label {
            font-size: clamp(1rem, 1.3vw, 1.8rem);
            font-weight: 800;
            text-align: right;
            white-space: nowrap;
        }

        .current-zone {
            flex: 1;
            display: grid;
            place-items: center;
            padding: clamp(28px, 4vw, 80px);
            background:
                linear-gradient(180deg, rgba(255, 250, 229, 0.96), rgba(255, 239, 185, 0.96)),
                var(--gold-soft);
        }

        .current-label {
            margin: 0 0 clamp(16px, 2vh, 32px);
            text-align: center;
            color: var(--muted);
            font-size: clamp(1.1rem, 1.8vw, 2.8rem);
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .flip-stack {
            position: relative;
            width: min(100%, 720px);
            aspect-ratio: 2.35 / 1;
            perspective: 1200px;
        }

        .flip-card {
            position: absolute;
            inset: 0;
            transform-style: preserve-3d;
            transition: transform 0.6s ease;
        }

        .flip-card.is-flipped {
            transform: rotateX(-180deg);
        }

        .flip-face {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            backface-visibility: hidden;
            border: 5px solid var(--gold);
            border-radius: 8px;
            background: #fffdf4;
            color: var(--maroon);
            font-family: Marcellus, Georgia, serif;
            font-size: clamp(6rem, 13vw, 18rem);
            font-weight: 700;
            line-height: 1;
            box-shadow: inset 0 -16px 0 rgba(120, 0, 13, 0.07);
        }

        .flip-face.back {
            transform: rotateX(180deg);
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: clamp(12px, 1.4vw, 24px);
            padding: clamp(18px, 2vw, 32px);
            background: #fffaf0;
            border-top: 2px solid rgba(231, 195, 90, 0.58);
        }

        .meta-box {
            min-width: 0;
            padding: clamp(14px, 1.7vw, 28px);
            border: 2px solid rgba(231, 195, 90, 0.82);
            border-radius: 8px;
            background: #fff7df;
            text-align: center;
        }

        .meta-label {
            margin-bottom: 10px;
            color: var(--muted);
            font-size: clamp(0.85rem, 1vw, 1.35rem);
            font-weight: 800;
            text-transform: uppercase;
        }

        .meta-value {
            color: var(--maroon-dark);
            font-family: Marcellus, Georgia, serif;
            font-size: clamp(2.4rem, 4vw, 6.2rem);
            font-weight: 700;
            line-height: 1;
        }

        .next .meta-value {
            color: var(--green);
        }

        .empty-state {
            display: none;
            flex: 1;
            place-items: center;
            padding: 40px;
            text-align: center;
            color: var(--maroon);
            font-family: Marcellus, Georgia, serif;
            font-size: clamp(2rem, 4vw, 5.5rem);
        }

        .empty-state.visible {
            display: grid;
        }

        @media (max-width: 900px) {
            html,
            body {
                overflow: auto;
            }

            .tv-display {
                min-height: 100vh;
            }

            .tv-topbar {
                align-items: flex-start;
            }

            .brand-title {
                white-space: normal;
            }

            .meta-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="tv-display" data-city="<?= htmlspecialchars($city, ENT_QUOTES, 'UTF-8'); ?>">
        <header class="tv-topbar" aria-label="Display status">
            <div class="brand-block">
                <img class="brand-logo" src="assets/images/logo/logomain.png" alt="Vishnusudarshana">
                <div>
                    <h1 class="brand-title">Live Token Status</h1>
                    <p class="brand-subtitle">Updates automatically</p>
                </div>
            </div>
            <div class="clock" data-clock>--:--</div>
        </header>

        <section class="cards-row" aria-label="Live token cards">
            <article class="token-card" data-city-card="solapur">
                <div class="card-header">
                    <div class="city-name">Solapur</div>
                    <div class="date-label" data-date>--</div>
                </div>
                <div class="current-zone">
                    <div>
                        <p class="current-label">Current Token</p>
                        <div class="flip-stack">
                            <div class="flip-card" data-flip>
                                <div class="flip-face front" data-front>--</div>
                                <div class="flip-face back" data-back>--</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="meta-grid">
                    <div class="meta-box">
                        <div class="meta-label">Previous</div>
                        <div class="meta-value" data-previous>--</div>
                    </div>
                    <div class="meta-box">
                        <div class="meta-label">Current</div>
                        <div class="meta-value" data-current>--</div>
                    </div>
                    <div class="meta-box next">
                        <div class="meta-label">Next</div>
                        <div class="meta-value" data-next>--</div>
                    </div>
                </div>
            </article>

            <article class="token-card" data-city-card="hyderabad">
                <div class="card-header">
                    <div class="city-name">Hyderabad</div>
                    <div class="date-label" data-date>--</div>
                </div>
                <div class="current-zone">
                    <div>
                        <p class="current-label">Current Token</p>
                        <div class="flip-stack">
                            <div class="flip-card" data-flip>
                                <div class="flip-face front" data-front>--</div>
                                <div class="flip-face back" data-back>--</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="meta-grid">
                    <div class="meta-box">
                        <div class="meta-label">Previous</div>
                        <div class="meta-value" data-previous>--</div>
                    </div>
                    <div class="meta-box">
                        <div class="meta-label">Current</div>
                        <div class="meta-value" data-current>--</div>
                    </div>
                    <div class="meta-box next">
                        <div class="meta-label">Next</div>
                        <div class="meta-value" data-next>--</div>
                    </div>
                </div>
            </article>
        </section>

        <section class="empty-state" data-empty>No live token is active right now</section>
    </main>

    <script src="js/announcement-helper.js?v=<?= (int) @filemtime(__DIR__ . '/js/announcement-helper.js'); ?>"></script>
    <script>
        const display = document.querySelector('.tv-display');
        const selectedCity = display.dataset.city || '';
        const cards = Array.from(document.querySelectorAll('[data-city-card]'));
        const cardsRow = document.querySelector('.cards-row');
        const emptyState = document.querySelector('[data-empty]');
        const clock = document.querySelector('[data-clock]');
        const apiUrl = 'api/live-tokens-previous.php';
        const visibilityUrl = 'api/live-token-visibility.php';
        const settingsUrl = 'api/get-settings.php';
        const LIVE_TOKEN_REFRESH_MS = 1000;
        const VISIBILITY_REFRESH_MS = 10000;
        const SETTINGS_REFRESH_MS = 30000;

        let announcementLanguage = 'marathi';
        let announcementEnabled = true;
        let announcementCursor = null;
        let lastProcessedAnnouncementCursor = null;
        let hasLoadedInitialSnapshot = false;
        let liveTokensFetchInFlight = false;
        let visibilityFetchInFlight = false;
        let settingsFetchInFlight = false;
        let announcementQueue = Promise.resolve();
        const announcedTokenByCity = {};
        const initializedTokenByCity = {};

        function normalizeAnnouncementLanguage(value) {
            return String(value || '').trim().toLowerCase() === 'english' ? 'english' : 'marathi';
        }

        function normalizeAnnouncementEnabled(value) {
            if (typeof value === 'boolean') {
                return value;
            }
            return ['1', 'true', 'yes', 'on', 'enabled'].includes(String(value || '').trim().toLowerCase());
        }

        function parseTokenValue(value) {
            const parsed = Number(value);
            if (Number.isFinite(parsed)) {
                return Math.trunc(parsed);
            }
            const cleaned = String(value || '').replace(/\D+/g, '');
            return cleaned ? parseInt(cleaned, 10) : NaN;
        }

        function normalizeAnnouncementCursor(cursor) {
            if (!cursor || typeof cursor !== 'object') {
                return null;
            }
            const updatedAt = String(cursor.updated_at || '').trim();
            if (!updatedAt) {
                return null;
            }
            return {
                updated_at: updatedAt,
                booking_id: Math.max(0, parseInt(cursor.booking_id || cursor.id || 0, 10) || 0)
            };
        }

        function compareAnnouncementCursors(left, right) {
            if (!left && !right) {
                return 0;
            }
            if (!left) {
                return -1;
            }
            if (!right) {
                return 1;
            }
            if (left.updated_at < right.updated_at) {
                return -1;
            }
            if (left.updated_at > right.updated_at) {
                return 1;
            }
            return left.booking_id - right.booking_id;
        }

        function setAnnouncementCursor(cursor) {
            const normalized = normalizeAnnouncementCursor(cursor);
            if (normalized && compareAnnouncementCursors(normalized, announcementCursor) > 0) {
                announcementCursor = normalized;
            }
        }

        function setProcessedAnnouncementCursor(cursor) {
            const normalized = normalizeAnnouncementCursor(cursor);
            if (normalized && compareAnnouncementCursors(normalized, lastProcessedAnnouncementCursor) > 0) {
                lastProcessedAnnouncementCursor = normalized;
            }
        }

        function formatDateLabel(dateStr) {
            const d = new Date(`${dateStr}T00:00:00`);
            if (Number.isNaN(d.getTime())) {
                return dateStr || '--';
            }
            return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function updateClock() {
            const now = new Date();
            clock.textContent = now.toLocaleTimeString('en-IN', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        }

        function buildLiveTokenRequestUrl() {
            const params = new URLSearchParams();
            if (announcementCursor && announcementCursor.updated_at) {
                params.set('announcement_since', announcementCursor.updated_at);
                params.set('announcement_after_id', String(announcementCursor.booking_id || 0));
            }
            const queryString = params.toString();
            return queryString ? `${apiUrl}?${queryString}` : apiUrl;
        }

        async function loadAnnouncementSettings() {
            if (settingsFetchInFlight) {
                return;
            }
            settingsFetchInFlight = true;
            try {
                const res = await fetch(settingsUrl, { cache: 'no-store' });
                const data = await res.json();
                if (data && data.success) {
                    announcementLanguage = normalizeAnnouncementLanguage(data.announcement_language);
                    const enabledRaw = Object.prototype.hasOwnProperty.call(data, 'live_token_announcement_enabled')
                        ? data.live_token_announcement_enabled
                        : '1';
                    announcementEnabled = normalizeAnnouncementEnabled(enabledRaw);
                }
            } catch (err) {
                announcementLanguage = 'marathi';
                announcementEnabled = true;
            } finally {
                settingsFetchInFlight = false;
            }
        }

        function enqueueLiveTokenAnnouncement(token, nextTokenRaw) {
            if (!announcementEnabled || !window.TokenAnnouncement || typeof window.TokenAnnouncement.announceLiveTokenStatus !== 'function') {
                return;
            }
            announcementQueue = announcementQueue
                .catch(() => {})
                .then(() => window.TokenAnnouncement.announceLiveTokenStatus(token, nextTokenRaw, announcementLanguage));
        }

        function primeAnnouncementState(city, currentTokenRaw) {
            const normalizedCity = String(city || '').trim().toLowerCase();
            const token = parseTokenValue(currentTokenRaw);
            initializedTokenByCity[normalizedCity] = true;
            announcedTokenByCity[normalizedCity] = Number.isFinite(token) && token > 0 ? token : null;
        }

        function maybeAnnounceCurrentToken(city, currentTokenRaw, nextTokenRaw) {
            const normalizedCity = String(city || '').trim().toLowerCase();
            if (selectedCity && normalizedCity !== selectedCity) {
                return;
            }
            if (!Object.prototype.hasOwnProperty.call(initializedTokenByCity, normalizedCity)) {
                primeAnnouncementState(normalizedCity, currentTokenRaw);
                return;
            }

            const token = parseTokenValue(currentTokenRaw);
            if (!Number.isFinite(token) || token <= 0 || announcedTokenByCity[normalizedCity] === token) {
                return;
            }

            announcedTokenByCity[normalizedCity] = token;
            enqueueLiveTokenAnnouncement(token, nextTokenRaw);
        }

        function processAnnouncementEvents(events) {
            if (!Array.isArray(events) || events.length === 0) {
                return false;
            }

            events.forEach(event => {
                const eventCursor = normalizeAnnouncementCursor(event);
                if (eventCursor && compareAnnouncementCursors(eventCursor, lastProcessedAnnouncementCursor) <= 0) {
                    return;
                }

                const normalizedCity = String((event && event.city) || '').trim().toLowerCase();
                if (selectedCity && normalizedCity !== selectedCity) {
                    if (eventCursor) {
                        setProcessedAnnouncementCursor(eventCursor);
                    }
                    return;
                }

                const token = parseTokenValue(event.token_no);
                if (Number.isFinite(token) && token > 0) {
                    initializedTokenByCity[normalizedCity] = true;
                    if (announcedTokenByCity[normalizedCity] !== token) {
                        announcedTokenByCity[normalizedCity] = token;
                        enqueueLiveTokenAnnouncement(token, null);
                    }
                }

                if (eventCursor) {
                    setProcessedAnnouncementCursor(eventCursor);
                }
            });

            return true;
        }

        function updateFlip(card, value) {
            const normalizedValue = String(value || '--');
            const flip = card.querySelector('[data-flip]');
            const front = card.querySelector('[data-front]');
            const back = card.querySelector('[data-back]');

            if (front.textContent.trim() === normalizedValue) {
                return;
            }

            back.textContent = normalizedValue;
            flip.classList.add('is-flipped');
            window.setTimeout(() => {
                front.textContent = normalizedValue;
                flip.classList.remove('is-flipped');
            }, 620);
        }

        function renderCard(card, data, dateStr) {
            const current = data.current_token || '--';
            card.querySelector('[data-date]').textContent = formatDateLabel(dateStr);
            card.querySelector('[data-previous]').textContent = data.previous_token || '-';
            card.querySelector('[data-current]').textContent = current;
            card.querySelector('[data-next]').textContent = data.next_token || '--';
            updateFlip(card, current);
        }

        function cityDataFor(card, citiesData) {
            const city = card.dataset.cityCard;
            return citiesData && citiesData[city] ? citiesData[city] : {};
        }

        function renderSnapshot(citiesData, dateStr) {
            cards.forEach(card => renderCard(card, cityDataFor(card, citiesData), dateStr));
        }

        function primeAnnouncementStatesFromSnapshot(citiesData) {
            cards.forEach(card => {
                const city = card.dataset.cityCard;
                primeAnnouncementState(city, cityDataFor(card, citiesData).current_token);
            });
        }

        function maybeAnnounceSnapshotChanges(citiesData) {
            cards.forEach(card => {
                const city = card.dataset.cityCard;
                const cityData = cityDataFor(card, citiesData);
                maybeAnnounceCurrentToken(city, cityData.current_token, cityData.next_token);
            });
        }

        function applyAnnouncementCursorState(responseCursor, receivedEvents) {
            if (!hasLoadedInitialSnapshot) {
                if (responseCursor) {
                    setProcessedAnnouncementCursor(responseCursor);
                }
                return;
            }
            if (receivedEvents || responseCursor) {
                setProcessedAnnouncementCursor(responseCursor);
            }
        }

        async function fetchLiveTokens() {
            if (liveTokensFetchInFlight) {
                return;
            }
            liveTokensFetchInFlight = true;
            try {
                const res = await fetch(buildLiveTokenRequestUrl(), { cache: 'no-store' });
                const data = await res.json();
                if (!data || !data.success) {
                    return;
                }

                const citiesData = data.cities || {};
                const responseCursor = normalizeAnnouncementCursor(data.announcement && data.announcement.cursor);
                const receivedEvents = processAnnouncementEvents(data.announcement && data.announcement.events);

                renderSnapshot(citiesData, data.date);

                if (!hasLoadedInitialSnapshot) {
                    primeAnnouncementStatesFromSnapshot(citiesData);
                    hasLoadedInitialSnapshot = true;
                } else if (!receivedEvents) {
                    maybeAnnounceSnapshotChanges(citiesData);
                }

                applyAnnouncementCursorState(responseCursor, receivedEvents);
                setAnnouncementCursor(responseCursor);
            } catch (err) {
                // Keep the TV display stable if a refresh fails.
            } finally {
                liveTokensFetchInFlight = false;
            }
        }

        async function updateVisibility() {
            if (visibilityFetchInFlight) {
                return;
            }
            visibilityFetchInFlight = true;
            try {
                const res = await fetch(visibilityUrl, { cache: 'no-store' });
                const data = await res.json();
                if (!data || !data.success) {
                    return;
                }

                const visible = data.visible || [];
                let visibleCount = 0;
                cards.forEach(card => {
                    const city = card.dataset.cityCard;
                    const shouldShow = selectedCity ? city === selectedCity : visible.includes(city);
                    card.hidden = !shouldShow;
                    if (shouldShow) {
                        visibleCount += 1;
                    }
                });

                cardsRow.classList.toggle('single-card', visibleCount === 1);
                emptyState.classList.toggle('visible', visibleCount === 0);
            } catch (err) {
                // Keep the previous visibility state.
            } finally {
                visibilityFetchInFlight = false;
            }
        }

        updateClock();
        loadAnnouncementSettings();
        updateVisibility();
        fetchLiveTokens();
        window.setInterval(updateClock, 1000);
        window.setInterval(loadAnnouncementSettings, SETTINGS_REFRESH_MS);
        window.setInterval(updateVisibility, VISIBILITY_REFRESH_MS);
        window.setInterval(fetchLiveTokens, LIVE_TOKEN_REFRESH_MS);

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                updateClock();
                loadAnnouncementSettings();
                updateVisibility();
                fetchLiveTokens();
            }
        });
    </script>
</body>
</html>
