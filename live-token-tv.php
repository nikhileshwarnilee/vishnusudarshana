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
@import url('https://fonts.googleapis.com/css2?family=Marcellus:wght@500;700&family=Mulish:wght@400;600;700&display=swap');

:root {
	--ink: #4a2a2a;
	--cream: #fff7d6;
	--gold: #f2d98c;
	--gold-deep: #d3a12c;
	--maroon: #800000;
	--maroon-dark: #5b0000;
	--shadow: 0 14px 30px rgba(128,0,0,0.12);
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
	background: radial-gradient(1200px 600px at 10% 10%, #fffdf1 0%, #fff3c9 45%, #f7e6b8 100%);
}

.live-token-wrap {
	background: radial-gradient(1200px 600px at 10% 10%, #fffdf1 0%, #fff3c9 45%, #f7e6b8 100%);
	padding: 24px 12px 36px;
	min-height: 100vh;
	display: flex;
	align-items: center;
	justify-content: center;
	font-family: 'Mulish', sans-serif;
}

.live-token-shell {
	max-width: 1200px;
	width: 100%;
	margin: 0 auto;
}

.tv-logo {
	display: block;
	width: clamp(146px, 16vw, 226px);
	height: auto;
	margin: 0 auto 20px;
}

.live-token-title {
	font-family: 'Marcellus', serif;
	font-size: 2.1rem;
	color: var(--maroon);
	text-align: center;
	margin: 0 0 10px;
}

.live-token-subtitle {
	font-family: 'Marcellus', sans-serif;
	text-align: center;
	color: #6b0000;
	margin: 0 0 28px;
}

.cards-row {
	display: grid;
	grid-template-columns: minmax(260px, 360px);
	gap: 22px;
	justify-content: center;
}

.cards-row.two-cards {
	grid-template-columns: repeat(2, minmax(260px, 360px));
}

.calendar-card {
	background: #fffef6;
	border-radius: 18px;
	box-shadow: var(--shadow);
	overflow: hidden;
	position: relative;
	border: 1px solid var(--gold);
	max-width: 360px;
	width: 100%;
	justify-self: center;
}

.calendar-card::before {
	content: '';
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	height: 8px;
	background: linear-gradient(90deg, var(--maroon), var(--gold-deep));
}

.calendar-top {
	padding: 18px 18px 8px;
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.calendar-city {
	font-family: 'Marcellus', serif;
	font-size: 1.2rem;
	color: var(--maroon);
}

.calendar-date {
	font-family: 'Marcellus', sans-serif;
	font-size: 0.85rem;
	color: #7a4d4d;
}

.flip-window {
	padding: 0 18px 14px;
}

.flip-stack {
	position: relative;
	height: 68px;
	perspective: 800px;
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
	backface-visibility: hidden;
	display: grid;
	place-items: center;
	border-radius: 12px;
	background: var(--cream);
	border: 1px solid var(--gold);
	font-family: 'Marcellus', serif;
	font-weight: 700;
	color: var(--maroon);
	font-size: 1.5rem;
	letter-spacing: 1px;
}

.flip-face.back {
	transform: rotateX(180deg);
}

.token-status-message {
	margin: 0 18px 18px;
	padding: 12px 14px;
	border: 1px solid var(--gold);
	border-radius: 999px;
	background: #fff7e0;
	color: var(--maroon-dark);
	font-family: 'Marcellus', serif;
	font-size: 1.05rem;
	font-weight: 700;
	text-align: center;
	line-height: 1.35;
}

.calendar-card.state-called {
	animation: tokenPulse 2.8s ease-in-out infinite;
	border-color: #d3a12c;
}

.calendar-card.state-called .flip-card {
	animation: calledTokenSlowFlip 6s ease-in-out infinite;
}

.calendar-card.state-called .flip-face,
.calendar-card.state-called .token-status-message {
	background: #fff0bd;
	color: #7c4b00;
}

.calendar-card.state-present {
	border-color: #84c6a5;
}

.calendar-card.state-present .flip-card,
.calendar-card.state-idle .flip-card {
	animation: none;
	transform: none;
}

.calendar-card.state-present .flip-face,
.calendar-card.state-present .token-status-message {
	background: #e7f4f1;
	border-color: #84c6a5;
	color: #1d6b68;
}

@keyframes tokenPulse {
	0%, 100% {
		box-shadow: var(--shadow), 0 0 0 0 rgba(211, 161, 44, 0.45);
	}
	50% {
		box-shadow: var(--shadow), 0 0 0 14px rgba(211, 161, 44, 0);
	}
}

@keyframes calledTokenSlowFlip {
	0%, 64% {
		transform: rotateX(0deg);
	}
	78%, 88% {
		transform: rotateX(-180deg);
	}
	100% {
		transform: rotateX(-360deg);
	}
}

.calendar-card {
	max-width: 360px;
}

.calendar-card[data-hidden="true"] {
	display: none;
}

@media (max-width: 980px) {
	body {
		overflow: auto;
	}
	.cards-row {
		grid-template-columns: 1fr;
	}
}
	</style>
</head>
<body>
	<main class="live-token-wrap">
		<div class="live-token-shell">
			<img class="tv-logo" src="assets/images/logo/logomain.png" alt="Vishnusudarshana">
			<h1 class="live-token-title">Live Token Status</h1>
			<p class="live-token-subtitle">Live status updates, checked every second</p>

			<div class="cards-row">
				<section class="calendar-card" data-city="solapur">
					<div class="calendar-top">
						<div class="calendar-city">Solapur</div>
						<div class="calendar-date" data-date>--</div>
					</div>
					<div class="flip-window">
						<div class="flip-stack">
							<div class="flip-card" data-flip>
								<div class="flip-face front" data-front>--</div>
								<div class="flip-face back" data-back>--</div>
							</div>
						</div>
					</div>
					<div class="token-status-message" data-message>--</div>
				</section>

				<section class="calendar-card" data-city="hyderabad">
					<div class="calendar-top">
						<div class="calendar-city">Hyderabad</div>
						<div class="calendar-date" data-date>--</div>
					</div>
					<div class="flip-window">
						<div class="flip-stack">
							<div class="flip-card" data-flip>
								<div class="flip-face front" data-front>--</div>
								<div class="flip-face back" data-back>--</div>
							</div>
						</div>
					</div>
					<div class="token-status-message" data-message>--</div>
				</section>
			</div>
		</div>
	</main>

	<script src="js/announcement-helper.js?v=<?= (int) @filemtime(__DIR__ . '/js/announcement-helper.js'); ?>"></script>
	<script>
const cards = document.querySelectorAll('.calendar-card');
const cardsRow = document.querySelector('.cards-row');
const apiUrl = 'api/live-tokens-previous.php';
const visibilityUrl = 'api/live-token-visibility.php';
const settingsUrl = 'api/get-settings.php';
const LIVE_TOKEN_REFRESH_MS = 1000;
const VISIBILITY_REFRESH_MS = 10000;
const SETTINGS_REFRESH_MS = 30000;
const CALL_ANNOUNCEMENT_REPEAT_MS = 9000;
const CALL_BELL_URL = 'assets/sounds/beep.mp3';
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
			window.setTimeout(finish, 1200);
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
	card.querySelector('[data-message]').textContent = message;
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
