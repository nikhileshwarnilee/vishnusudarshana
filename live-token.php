<?php 
$pageTitle = 'Live Token'; 
include 'header.php'; 
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Work+Sans:wght@400;600;700&display=swap');

:root {
	--ink: #2a2a2a;
	--sand: #f7f2e8;
	--clay: #ead9c3;
	--teal: #1f6f6d;
	--sun: #f3b94e;
	--shadow: 0 14px 30px rgba(0,0,0,0.12);
}

.live-token-wrap {
	background: radial-gradient(1200px 600px at 10% 10%, #fff8ea 0%, #f4ead6 45%, #efe1c8 100%);
	padding: 48px 16px 72px;
	min-height: 70vh;
}

.live-token-shell {
	max-width: 1200px;
	margin: 0 auto;
}

.live-token-title {
	font-family: 'Playfair Display', serif;
	font-size: 2.2rem;
	color: var(--ink);
	text-align: center;
	margin-bottom: 10px;
}

.live-token-subtitle {
	font-family: 'Work Sans', sans-serif;
	text-align: center;
	color: #5b5b5b;
	margin-bottom: 28px;
}

.cards-row {
	display: grid;
	grid-template-columns: repeat(3, minmax(260px, 1fr));
	gap: 22px;
}

.calendar-card {
	background: white;
	border-radius: 18px;
	box-shadow: var(--shadow);
	overflow: hidden;
	position: relative;
	border: 1px solid #f0e2cc;
}

.calendar-card::before {
	content: '';
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	height: 8px;
	background: linear-gradient(90deg, var(--teal), var(--sun));
}

.calendar-top {
	padding: 18px 18px 8px;
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.calendar-city {
	font-family: 'Playfair Display', serif;
	font-size: 1.2rem;
	color: var(--ink);
}

.calendar-date {
	font-family: 'Work Sans', sans-serif;
	font-size: 0.85rem;
	color: #7a7a7a;
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
	background: var(--sand);
	border: 1px solid var(--clay);
	font-family: 'Work Sans', sans-serif;
	font-weight: 700;
	color: var(--teal);
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
	font-family: 'Work Sans', sans-serif;
}

.meta-box {
	background: #fcf8f1;
	border: 1px solid #f1e4cf;
	border-radius: 10px;
	padding: 10px;
	text-align: center;
}

.meta-label {
	font-size: 0.75rem;
	color: #8b7a62;
	text-transform: uppercase;
	letter-spacing: 0.6px;
}

.meta-value {
	font-size: 1.1rem;
	color: var(--ink);
	font-weight: 700;
}

.times-list {
	border-top: 1px dashed #ead9c3;
	padding: 14px 18px 18px;
	max-height: 240px;
	overflow: auto;
	font-family: 'Work Sans', sans-serif;
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
		<h1 class="live-token-title">Live Token Calendar</h1>
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
						<div class="meta-label">Previous Token</div>
						<div class="meta-value" data-last>--</div>
					</div>
					<div class="meta-box">
						<div class="meta-label">Current Token</div>
						<div class="meta-value" data-current>--</div>
					</div>
					<div class="meta-box">
						<div class="meta-label">Next Token</div>
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
						<div class="meta-label">Previous Token</div>
						<div class="meta-value" data-last>--</div>
					</div>
					<div class="meta-box">
						<div class="meta-label">Current Token</div>
						<div class="meta-value" data-current>--</div>
					</div>
					<div class="meta-box">
						<div class="meta-label">Next Token</div>
						<div class="meta-value" data-next>--</div>
					</div>
				</div>
			</section>

		</div>
	</div>
</main>

<script>

const cards = document.querySelectorAll('.calendar-card');
const apiUrl = 'api/live-tokens.php';
const visibilityUrl = 'api/live-token-visibility.php';

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
	// Show previous token as the one before the last completed token
	let prevToken = '-';
	const curr = parseInt(data.current_token);
	if (!isNaN(curr) && curr > 1) {
		prevToken = curr - 1;
	} else if (!isNaN(curr) && curr === 1) {
		prevToken = '-';
	}
	card.querySelector('[data-last]').textContent = prevToken;
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
			if (visible.includes(city)) {
				card.style.display = '';
			} else {
				card.style.display = 'none';
			}
		});
	} catch (err) {}
}

async function liveUpdate() {
	await Promise.all([fetchLiveTokens(), updateVisibility()]);
}

liveUpdate();
setInterval(liveUpdate, 10000);
</script>

<?php include 'footer.php'; ?>
