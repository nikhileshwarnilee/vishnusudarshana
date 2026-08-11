<?php
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#800000">
	<title>Live Token Status</title>
	<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@500;700;800;900&display=swap');

:root {
	--present-bg: #e9fff8;
	--present-ink: #003d37;
	--present-accent: #00a884;
	--called-bg: #fff1d5;
	--called-ink: #4d0900;
	--called-accent: #ff3b1f;
	--idle-bg: #f5f1e8;
	--idle-ink: #48433a;
}

* {
	box-sizing: border-box;
}

html,
body {
	margin: 0;
	min-height: 100%;
}

body {
	overflow: hidden;
	background: #f5f1e8;
	font-family: 'Inter', Arial, sans-serif;
}

.live-token-wrap {
	min-height: 100vh;
	width: 100vw;
	background: #f5f1e8;
}

.live-token-shell {
	min-height: 100vh;
	width: 100vw;
}

.token-grid {
	display: grid;
	grid-template-columns: 1fr;
	min-height: 100vh;
	width: 100vw;
}

.token-grid.two-cards {
	grid-template-columns: repeat(2, minmax(0, 1fr));
}

.token-panel {
	min-height: 100vh;
	width: 100%;
	position: relative;
	display: grid;
	place-items: center;
	padding: clamp(18px, 3vw, 46px);
	overflow: hidden;
	background: var(--idle-bg);
	color: var(--idle-ink);
	cursor: pointer;
}

.token-grid.two-cards .token-panel:first-child {
	border-right: clamp(6px, 0.8vw, 14px) solid rgba(0, 0, 0, 0.12);
}

.token-panel::before {
	content: '';
	position: absolute;
	inset: 0;
	pointer-events: none;
	background:
		linear-gradient(180deg, rgba(255, 255, 255, 0.52), rgba(255, 255, 255, 0) 35%),
		radial-gradient(circle at 50% 52%, rgba(255, 255, 255, 0.42), rgba(255, 255, 255, 0) 58%);
}

.token-panel-inner {
	position: relative;
	z-index: 1;
	width: 100%;
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-direction: column;
	gap: clamp(10px, 2vh, 26px);
}

.token-watermark {
	position: absolute;
	z-index: 0;
	left: 50%;
	top: 50%;
	width: min(58vh, 42vw, 36rem);
	max-width: 82%;
	height: auto;
	transform: translate(-50%, -50%);
	opacity: 0.16;
	mix-blend-mode: multiply;
	pointer-events: none;
	user-select: none;
}

.token-grid.two-cards .token-watermark {
	width: min(58vh, 34vw, 34rem);
	max-width: 76%;
}

.token-city {
	position: absolute;
	z-index: 1;
	top: clamp(16px, 3.5vh, 42px);
	left: clamp(18px, 3vw, 54px);
	right: clamp(18px, 3vw, 54px);
	font-size: clamp(1rem, 2.4vh, 2rem);
	font-weight: 900;
	letter-spacing: 0;
	text-transform: uppercase;
	opacity: 0.7;
}

.token-number {
	position: relative;
	z-index: 1;
	width: 100%;
	display: grid;
	place-items: center;
	font-size: min(82vh, 64vw, 54rem);
	line-height: 0.74;
	font-weight: 900;
	letter-spacing: 0;
	font-variant-numeric: tabular-nums;
	text-align: center;
	text-shadow: 0 1.1vh 0 rgba(255, 255, 255, 0.72);
	transition: transform 0.35s ease, color 0.35s ease, text-shadow 0.35s ease;
}

.token-grid.two-cards .token-number {
	font-size: min(76vh, 42vw, 52rem);
}

.token-panel[data-digits="1"] .token-number {
	font-size: min(90vh, 78vw, 64rem);
}

.token-panel[data-digits="2"] .token-number {
	font-size: min(82vh, 64vw, 54rem);
}

.token-panel[data-digits="3"] .token-number {
	font-size: min(76vh, 66vw, 50rem);
}

.token-panel[data-digits="4"] .token-number {
	font-size: min(62vh, 50vw, 40rem);
}

.token-grid.two-cards .token-panel[data-digits="1"] .token-number {
	font-size: min(86vh, 48vw, 62rem);
}

.token-grid.two-cards .token-panel[data-digits="2"] .token-number {
	font-size: min(78vh, 42vw, 54rem);
}

.token-grid.two-cards .token-panel[data-digits="3"] .token-number {
	font-size: min(66vh, 31vw, 42rem);
}

.token-grid.two-cards .token-panel[data-digits="4"] .token-number {
	font-size: min(54vh, 24vw, 34rem);
}

.token-digit.back {
	display: none;
}

.token-state {
	position: absolute;
	z-index: 1;
	left: clamp(18px, 3vw, 54px);
	right: clamp(18px, 3vw, 54px);
	bottom: clamp(18px, 4vh, 54px);
	min-height: 1.2em;
	font-size: clamp(1.25rem, 4.8vh, 4.8rem);
	line-height: 1;
	font-weight: 900;
	text-align: center;
	letter-spacing: 0;
	text-transform: uppercase;
	opacity: 0.88;
}

.token-panel.state-present {
	background: linear-gradient(135deg, #effff9 0%, var(--present-bg) 48%, #d9fff3 100%);
	color: var(--present-ink);
}

.token-panel.state-present::after {
	content: '';
	position: absolute;
	inset: auto 0 0;
	height: clamp(14px, 2vh, 30px);
	background: var(--present-accent);
}

.token-panel.state-present .token-state::before {
	content: 'PRESENT';
}

.token-panel.state-called {
	background: linear-gradient(135deg, #fff8e8 0%, var(--called-bg) 50%, #ffd6c2 100%);
	color: var(--called-ink);
	animation: calledBackground 1.05s ease-in-out infinite;
}

.token-panel.state-called::after {
	content: '';
	position: absolute;
	inset: 0;
	border: clamp(14px, 2vw, 32px) solid var(--called-accent);
	pointer-events: none;
	animation: calledBorder 1.05s ease-in-out infinite;
}

.token-panel.state-called .token-number {
	color: var(--called-ink);
	text-shadow: 0 1vh 0 rgba(255, 255, 255, 0.75), 0 0 5vh rgba(255, 59, 31, 0.35);
	animation: calledNumber 1.05s ease-in-out infinite;
}

.token-panel.state-called .token-state::before {
	content: 'CALLING';
}

@keyframes calledNumber {
	0%, 100% {
		transform: scale(1);
	}
	50% {
		transform: scale(1.055);
	}
}

@keyframes calledBackground {
	0%, 100% {
		filter: saturate(1);
	}
	50% {
		filter: saturate(1.25);
	}
}

@keyframes calledBorder {
	0%, 100% {
		opacity: 0.95;
	}
	50% {
		opacity: 0.25;
	}
}

.token-panel.state-idle .token-state::before {
	content: 'WAIT';
}

.token-panel[data-hidden="true"] {
	display: none;
}

@media (max-width: 980px) {
	body {
		overflow: auto;
	}
	.token-grid,
	.token-grid.two-cards {
		grid-template-columns: 1fr;
	}
	.token-grid.two-cards .token-panel:first-child {
		border-right: 0;
		border-bottom: clamp(6px, 1vh, 12px) solid rgba(0, 0, 0, 0.12);
	}
	.token-panel {
		min-height: 50vh;
	}
	.token-grid.two-cards .token-watermark,
	.token-watermark {
		width: min(32vh, 58vw, 22rem);
		max-width: 64%;
	}
	.token-grid.two-cards .token-number,
	.token-number {
		font-size: min(42vh, 84vw, 28rem);
	}
	.token-grid.two-cards .token-panel[data-digits="1"] .token-number,
	.token-panel[data-digits="1"] .token-number {
		font-size: min(44vh, 90vw, 30rem);
	}
	.token-grid.two-cards .token-panel[data-digits="2"] .token-number,
	.token-panel[data-digits="2"] .token-number {
		font-size: min(42vh, 84vw, 28rem);
	}
	.token-grid.two-cards .token-panel[data-digits="3"] .token-number,
	.token-panel[data-digits="3"] .token-number {
		font-size: min(36vh, 68vw, 23rem);
	}
	.token-grid.two-cards .token-panel[data-digits="4"] .token-number,
	.token-panel[data-digits="4"] .token-number {
		font-size: min(30vh, 52vw, 19rem);
	}
}
	</style>
</head>
<body>
	<main class="live-token-wrap">
		<div class="live-token-shell">
			<div class="token-grid">
				<section class="token-panel" data-city="solapur">
					<div class="token-panel-inner">
						<img class="token-watermark" src="assets/images/logo/logo-icon.png?v=<?= (int) @filemtime(__DIR__ . '/assets/images/logo/logo-icon.png'); ?>" alt="" aria-hidden="true">
						<div class="token-city">Solapur</div>
						<div class="token-number" data-flip>
							<span class="token-digit front" data-front>--</span>
							<span class="token-digit back" data-back>--</span>
						</div>
						<div class="token-state" data-message></div>
						<span hidden data-date>--</span>
					</div>
				</section>

				<section class="token-panel" data-city="hyderabad">
					<div class="token-panel-inner">
						<img class="token-watermark" src="assets/images/logo/logo-icon.png?v=<?= (int) @filemtime(__DIR__ . '/assets/images/logo/logo-icon.png'); ?>" alt="" aria-hidden="true">
						<div class="token-city">Hyderabad</div>
						<div class="token-number" data-flip>
							<span class="token-digit front" data-front>--</span>
							<span class="token-digit back" data-back>--</span>
						</div>
						<div class="token-state" data-message></div>
						<span hidden data-date>--</span>
					</div>
				</section>
			</div>
		</div>
	</main>

	<script src="js/announcement-helper.js?v=<?= (int) @filemtime(__DIR__ . '/js/announcement-helper.js'); ?>"></script>
	<script>
const cards = document.querySelectorAll('.token-panel');
const cardsRow = document.querySelector('.token-grid');
const apiUrl = 'api/live-tokens-previous.php';
const visibilityUrl = 'api/live-token-visibility.php';
const settingsUrl = 'api/get-settings.php';
const LIVE_TOKEN_REFRESH_MS = 1000;
const VISIBILITY_REFRESH_MS = 10000;
const SETTINGS_REFRESH_MS = 30000;
const CALL_ANNOUNCEMENT_REPEAT_MS = 9000;
const CALL_BELL_URL = 'assets/sounds/tokentv.mp3?v=<?= (int) @filemtime(__DIR__ . '/assets/sounds/tokentv.mp3'); ?>';
const urlParams = new URLSearchParams(window.location.search);
const fullscreenCity = urlParams.get('fullscreen') || urlParams.get('city');
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
const activeCalledTokenByCity = {};

function normalizeAnnouncementLanguage(value) {
	const v = String(value || '').trim().toLowerCase();
	return v === 'english' ? 'english' : 'marathi';
}

function normalizeAnnouncementEnabled(value) {
	if (typeof value === 'boolean') {
		return value;
	}
	const v = String(value || '').trim().toLowerCase();
	return ['1', 'true', 'yes', 'on', 'enabled'].includes(v);
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
	if (left.booking_id < right.booking_id) {
		return -1;
	}
	if (left.booking_id > right.booking_id) {
		return 1;
	}
	return 0;
}

function setAnnouncementCursor(cursor) {
	const normalized = normalizeAnnouncementCursor(cursor);
	if (!normalized) {
		return;
	}
	if (compareAnnouncementCursors(normalized, announcementCursor) > 0) {
		announcementCursor = normalized;
	}
}

function setProcessedAnnouncementCursor(cursor) {
	const normalized = normalizeAnnouncementCursor(cursor);
	if (!normalized) {
		return;
	}
	if (compareAnnouncementCursors(normalized, lastProcessedAnnouncementCursor) > 0) {
		lastProcessedAnnouncementCursor = normalized;
	}
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

function buildLiveTokenRequestUrl() {
	const params = new URLSearchParams();
	if (announcementCursor && announcementCursor.updated_at) {
		params.set('announcement_since', announcementCursor.updated_at);
		params.set('announcement_after_id', String(announcementCursor.booking_id || 0));
	}
	const queryString = params.toString();
	return queryString ? `${apiUrl}?${queryString}` : apiUrl;
}

function primeAnnouncementState(city, currentTokenRaw) {
	const normalizedCity = String(city || '').trim().toLowerCase();
	const token = parseTokenValue(currentTokenRaw);
	initializedTokenByCity[normalizedCity] = true;
	announcedTokenByCity[normalizedCity] = Number.isFinite(token) && token > 0 ? token : null;
}

function enqueueLiveTokenAnnouncement(token, nextTokenRaw) {
	// TV page should stay silent for present/completed token changes.
	return;
}

function playCallBell() {
	if (!announcementEnabled) {
		return Promise.resolve();
	}
	return new Promise(resolve => {
		try {
			const audio = new Audio(CALL_BELL_URL);
			audio.preload = 'auto';
			audio.currentTime = 0;
			let settled = false;
			const finish = () => {
				if (settled) return;
				settled = true;
				resolve();
			};
			audio.onended = finish;
			audio.onerror = finish;
			const playPromise = audio.play();
			if (playPromise && typeof playPromise.catch === 'function') {
				playPromise.catch(finish);
			}
			window.setTimeout(finish, 5000);
		} catch (err) {
			resolve();
		}
	});
}

function enqueueTokenCallAnnouncement(token) {
	if (!announcementEnabled) {
		return;
	}
	announcementQueue = announcementQueue
		.catch(() => {})
		.then(() => playCallBell());
}

function maybeAnnounceCurrentToken(city, currentTokenRaw, nextTokenRaw) {
	const normalizedCity = String(city || '').trim().toLowerCase();
	if (!Object.prototype.hasOwnProperty.call(initializedTokenByCity, normalizedCity)) {
		primeAnnouncementState(normalizedCity, currentTokenRaw);
		return;
	}

	const token = parseTokenValue(currentTokenRaw);
	if (!Number.isFinite(token) || token <= 0) {
		return;
	}

	if (announcedTokenByCity[normalizedCity] === token) {
		return;
	}

	initializedTokenByCity[normalizedCity] = true;
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
		if (!normalizedCity) {
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

function cityDataFor(card, citiesData) {
	const city = card.getAttribute('data-city');
	return (citiesData && citiesData[city]) ? citiesData[city] : {};
}

function primeAnnouncementStatesFromSnapshot(citiesData) {
	cards.forEach(card => {
		const city = card.getAttribute('data-city');
		const cityData = cityDataFor(card, citiesData);
		primeAnnouncementState(city, cityData.current_token);
	});
}

function maybeAnnounceSnapshotChanges(citiesData) {
	cards.forEach(card => {
		const city = card.getAttribute('data-city');
		const cityData = cityDataFor(card, citiesData);
		maybeAnnounceCurrentToken(city, cityData.current_token, cityData.next_token);
	});
}

function calledTokenKey(data, token) {
	const bookingId = parseInt(data.display_booking_id || 0, 10) || 0;
	const updatedAt = String(data.display_updated_at || '').trim();
	return [bookingId, token, updatedAt].join(':');
}

function maybeAnnounceCalledToken(city, data) {
	const normalizedCity = String(city || '').trim().toLowerCase();
	if (!normalizedCity) {
		return;
	}

	if (!data || data.display_state !== 'called') {
		delete activeCalledTokenByCity[normalizedCity];
		return;
	}

	const token = parseTokenValue(data.display_token);
	if (!Number.isFinite(token) || token <= 0) {
		delete activeCalledTokenByCity[normalizedCity];
		return;
	}

	const now = Date.now();
	const key = calledTokenKey(data, token);
	const previous = activeCalledTokenByCity[normalizedCity] || {};
	const isNewCall = previous.key !== key;
	const shouldRepeat = !previous.lastAnnouncedAt || (now - previous.lastAnnouncedAt) >= CALL_ANNOUNCEMENT_REPEAT_MS;

	if (isNewCall || shouldRepeat) {
		activeCalledTokenByCity[normalizedCity] = {
			key: key,
			lastAnnouncedAt: now
		};
		enqueueTokenCallAnnouncement(token);
	}
}

function renderSnapshot(citiesData, dateStr) {
	cards.forEach(card => {
		const city = card.getAttribute('data-city');
		const cityData = cityDataFor(card, citiesData);
		renderCard(card, cityData, dateStr);
		maybeAnnounceCalledToken(city, cityData);
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

function formatDateLabel(dateStr) {
	const d = new Date(dateStr + 'T00:00:00');
	if (isNaN(d)) return dateStr;
	return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function updateFlip(card, value, displayState) {
	const flip = card.querySelector('[data-flip]');
	const front = card.querySelector('[data-front]');
	const back = card.querySelector('[data-back]');

	if (displayState === 'called' || displayState === 'present' || displayState === 'idle') {
		front.textContent = value;
		back.textContent = value;
		flip.classList.remove('is-flipped');
		return;
	}

	if (front.textContent.trim() === value) return;

	back.textContent = value;
	flip.classList.add('is-flipped');

	setTimeout(() => {
		front.textContent = value;
		flip.classList.remove('is-flipped');
	}, 620);
}

function renderCard(card, data, dateStr) {
	card.querySelector('[data-date]').textContent = formatDateLabel(dateStr);
	const displayToken = data.display_token || data.current_token || '--';
	const displayState = data.display_state || (displayToken === '--' ? 'idle' : 'present');
	const message = data.display_message || (displayToken === '--' ? 'टोकन स्थिती लवकरच उपलब्ध होईल' : 'सध्या टोकन क्रमांक ' + displayToken + ' चालू आहे');

	card.classList.remove('state-called', 'state-present', 'state-idle');
	card.classList.add('state-' + displayState);
	card.dataset.digits = String(displayToken).replace(/\D/g, '').length || 2;
	card.setAttribute('aria-label', `${card.getAttribute('data-city')} ${displayState} token ${displayToken}`);
	card.querySelector('[data-message]').textContent = '';
	updateFlip(card, displayToken, displayState);
}

async function fetchLiveTokens() {
	if (liveTokensFetchInFlight) {
		return;
	}
	liveTokensFetchInFlight = true;
	try {
		const res = await fetch(buildLiveTokenRequestUrl(), { cache: 'no-store' });
		const data = await res.json();
		if (!data || !data.success) return;

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
		// Silent fail for live refresh
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
		if (!data.success) return;
		const visible = data.visible || [];
		cards.forEach(card => {
			const city = card.getAttribute('data-city');
			if (fullscreenCity) {
				card.style.display = city === fullscreenCity ? '' : 'none';
				return;
			}
			if (visible.includes(city)) {
				card.style.display = '';
			} else {
				card.style.display = 'none';
			}
		});
		if (cardsRow) {
			cardsRow.classList.toggle('two-cards', !fullscreenCity && visible.length === 2);
		}
	} catch (err) {
		// Silent fail for visibility refresh
	} finally {
		visibilityFetchInFlight = false;
	}
}

loadAnnouncementSettings();
updateVisibility();
fetchLiveTokens();
setInterval(loadAnnouncementSettings, SETTINGS_REFRESH_MS);
setInterval(updateVisibility, VISIBILITY_REFRESH_MS);
setInterval(fetchLiveTokens, LIVE_TOKEN_REFRESH_MS);

document.addEventListener('visibilitychange', () => {
	if (!document.hidden) {
		loadAnnouncementSettings();
		updateVisibility();
		fetchLiveTokens();
	}
});

cards.forEach(card => {
	card.addEventListener('click', () => {
		const city = card.getAttribute('data-city');
		window.location.href = `live-token-tv.php?fullscreen=${encodeURIComponent(city)}`;
	});
});
	</script>
</body>
</html>
