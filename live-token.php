<?php 
$pageTitle = 'Live Token'; 
include 'header.php'; 
?>

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

.live-token-wrap {
	background: radial-gradient(1200px 600px at 10% 10%, #fffdf1 0%, #fff3c9 45%, #f7e6b8 100%);
	padding: 48px 16px 72px;
	min-height: 70vh;
}

.live-token-shell {
	max-width: 1200px;
	margin: 0 auto;
}

.live-token-title {
	font-family: 'Marcellus', serif;
	font-size: 2.1rem;
	color: var(--maroon);
	text-align: center;
	margin-bottom: 10px;
}

.live-token-subtitle {
	font-family: 'Marcellus', sans-serif;
	text-align: center;
	color: #6b0000;
	margin-bottom: 28px;
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

.token-meta {
	padding: 6px 18px 18px;
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 10px;
	font-family: 'Marcellus', serif;
}

.meta-box {
	background: #fff7e0;
	border: 1px solid var(--gold);
	border-radius: 10px;
	padding: 10px;
	text-align: center;
}

.meta-label {
	font-size: 0.75rem;
	color: #7a4d4d;
	text-transform: uppercase;
	letter-spacing: 0.6px;
}

.meta-value {
	font-size: 1.1rem;
	color: var(--maroon-dark);
	font-weight: 700;
}

body.fullscreen-mode header.header,
body.fullscreen-mode .mobile-nav,
body.fullscreen-mode .footer,
body.fullscreen-mode .lang-popup,
body.fullscreen-mode .lang-popup-overlay,
body.fullscreen-mode .welcome-intro-overlay,
body.fullscreen-mode .welcome-intro-popup {
	display: none !important;
}

body.fullscreen-mode .live-token-wrap {
	padding: 24px 12px 36px;
	min-height: 100vh;
	display: flex;
	align-items: center;
	justify-content: center;
}

body.fullscreen-mode .live-token-title,
body.fullscreen-mode .live-token-subtitle,
body.fullscreen-mode .live-token-actions {
	display: none !important;
}

body.fullscreen-mode .calendar-card {
	max-width: 1020px;
	transform: scale(1.1);
	transform-origin: center;
}

.times-list {
	border-top: 1px dashed #ead9c3;
	padding: 14px 18px 18px;
	max-height: 240px;
	overflow: auto;
	font-family: 'Marcellus', serif;
}

.time-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 8px 0;
	border-bottom: 1px solid #f4eadb;
	font-size: 0.95rem;
	color: #4a4a4a;
}

.time-item:last-child {
	border-bottom: none;
}

.token-tag {
	font-weight: 700;
	color: var(--teal);
}

.status-pill {
	background: #e7f4f1;
	color: #1d6b68;
	padding: 3px 8px;
	border-radius: 999px;
	font-size: 0.75rem;
	font-weight: 600;
	text-transform: uppercase;
}

.status-pill.completed {
	background: #f3eee4;
	color: #7a6b55;
}

.empty-state {
	text-align: center;
	color: #907d63;
	padding: 14px 0 6px;
	font-size: 0.95rem;
}

@media (max-width: 980px) {
	.cards-row {
		grid-template-columns: 1fr;
	}
	.token-meta {
		grid-template-columns: 1fr;
	}
}
</style>

<main class="live-token-wrap">
	<div class="live-token-shell">
		<div class="live-token-actions" style="display:flex;justify-content:center;margin-bottom:22px;">
			<button class="redesigned-cta-btn" style="flex:1 1 260px;max-width:340px;border:3px solid #FFD700;box-shadow:0 4px 18px rgba(212,175,55,0.13);" onclick="window.location.href='book-token.php'">Book Token</button>
		</div>
		<h1 class="live-token-title">Live Token Status</h1>
		<p class="live-token-subtitle">Live status updates, refreshed every 10 seconds</p>

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
				<div class="token-meta">
					<div class="meta-box">
						<div class="meta-label">Previous Token | मागील टोकन | మునుపటి టోకెన్</div>
						<div class="meta-value" data-last>--</div>
					</div>
					<div class="meta-box">
						<div class="meta-label">Current Token | चालू टोकन | ప్రస్తుత టోకెన్</div>
						<div class="meta-value" data-current>--</div>
					</div>
					<div class="meta-box">
						<div class="meta-label">Next Token | पुढील टोकन | తదుపరి టోకెన్</div>
						<div class="meta-value" data-next>--</div>
					</div>
				</div>
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
				<div class="token-meta">
					<div class="meta-box">
						<div class="meta-label">Previous Token | मागील टोकन | మునుపటి టోకెన్</div>
						<div class="meta-value" data-last>--</div>
					</div>
					<div class="meta-box">
						<div class="meta-label">Current Token | चालू टोकन | ప్రస్తుత టోకెన్</div>
						<div class="meta-value" data-current>--</div>
					</div>
					<div class="meta-box">
						<div class="meta-label">Next Token | पुढील टोकन | తదుపరి టోకెన్</div>
						<div class="meta-value" data-next>--</div>
					</div>
				</div>
			</section>

		</div>
	</div>
</main>

<script>

const cards = document.querySelectorAll('.calendar-card');
const cardsRow = document.querySelector('.cards-row');
const apiUrl = 'api/live-tokens-previous.php';
const visibilityUrl = 'api/live-token-visibility.php';
const urlParams = new URLSearchParams(window.location.search);
const fullscreenCity = urlParams.get('fullscreen');

function formatDateLabel(dateStr) {
	const d = new Date(dateStr + 'T00:00:00');
	if (isNaN(d)) return dateStr;
	return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function updateFlip(card, value) {
	const flip = card.querySelector('[data-flip]');
	const front = card.querySelector('[data-front]');
	const back = card.querySelector('[data-back]');

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
	// Show previous token as the one with previous latest updated_at
	card.querySelector('[data-last]').textContent = data.previous_token || '-';
	card.querySelector('[data-current]').textContent = data.current_token || '--';
	card.querySelector('[data-next]').textContent = data.next_token || '--';
	updateFlip(card, data.current_token || '--');
}

async function fetchLiveTokens() {
	try {
		const res = await fetch(apiUrl, { cache: 'no-store' });
		const data = await res.json();
		if (!data.success) return;

		cards.forEach(card => {
			const city = card.getAttribute('data-city');
			renderCard(card, data.cities[city], data.date);
		});
	} catch (err) {
		// Silent fail for live refresh
	}
}


async function updateVisibility() {
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
	} catch (err) {}
}

async function liveUpdate() {
	await Promise.all([fetchLiveTokens(), updateVisibility()]);
}

liveUpdate();
setInterval(liveUpdate, 10000);

if (fullscreenCity) {
	document.body.classList.add('fullscreen-mode');
}

cards.forEach(card => {
	card.addEventListener('click', () => {
		const city = card.getAttribute('data-city');
		window.location.href = `live-token.php?fullscreen=${encodeURIComponent(city)}`;
	});
});
</script>

<?php include 'footer.php'; ?>
