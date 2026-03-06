<?php
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$location = isset($_GET['location']) ? strtolower(trim((string) $_GET['location'])) : 'solapur';
if ($location === '' || !preg_match('/^[a-z0-9 _-]+$/', $location)) {
    $location = 'solapur';
}

$locationLabel = ucwords(str_replace(['-', '_'], ' ', $location));
$receiptLocationLabel = $locationLabel;
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#6d0014">
    <title>&#x92A;&#x94D;&#x930;&#x924;&#x94D;&#x92F;&#x915;&#x94D;&#x937; &#x915;&#x93E;&#x930;&#x94D;&#x92F;&#x93E;&#x932;&#x92F; &#x92D;&#x947;&#x91F;&#x940;&#x938;&#x93E;&#x920;&#x940; &#x91F;&#x94B;&#x915;&#x928; &#x92A;&#x94D;&#x930;&#x923;&#x93E;&#x932;&#x940;</title>
    <link rel="stylesheet" href="css/tablet-token.css?v=20260307v">
</head>
<body>
    <main class="tablet-app" data-location="<?= htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?>">
        <section class="kiosk-panel info-panel" aria-label="&#x92E;&#x93E;&#x939;&#x93F;&#x924;&#x940; &#x935;&#x93F;&#x92D;&#x93E;&#x917;">
            <img class="brand-logo" src="assets/images/logo/logomain.png" alt="Vishnusudarshana Logo">

            <h1 class="info-title">&#x92A;&#x94D;&#x930;&#x924;&#x94D;&#x92F;&#x915;&#x94D;&#x937; &#x915;&#x93E;&#x930;&#x94D;&#x92F;&#x93E;&#x932;&#x92F; &#x92D;&#x947;&#x91F;&#x940;&#x938;&#x93E;&#x920;&#x940; &#x91F;&#x94B;&#x915;&#x928; &#x92A;&#x94D;&#x930;&#x923;&#x93E;&#x932;&#x940;</h1>

            <div class="info-text">
                <p>&#x92A;&#x902;&#x921;&#x93F;&#x924;&#x91C;&#x940;&#x902;&#x928;&#x93E; &#x92A;&#x94D;&#x930;&#x924;&#x94D;&#x92F;&#x915;&#x94D;&#x937; &#x92D;&#x947;&#x91F;&#x923;&#x94D;&#x92F;&#x93E;&#x938;&#x93E;&#x920;&#x940; &#x915;&#x943;&#x92A;&#x92F;&#x93E; &#x906;&#x92A;&#x932;&#x93E; &#x967;&#x966; &#x905;&#x902;&#x915;&#x940; &#x92E;&#x94B;&#x92C;&#x93E;&#x908;&#x932; &#x915;&#x94D;&#x930;&#x92E;&#x93E;&#x902;&#x915; &#x91F;&#x93E;&#x915;&#x93E; &#x906;&#x923;&#x93F; &#x91F;&#x94B;&#x915;&#x928; &#x918;&#x94D;&#x92F;&#x93E;.</p>
                <p>
                    &#x906;&#x92A;&#x932;&#x94D;&#x92F;&#x93E; &#x91F;&#x94B;&#x915;&#x928;&#x935;&#x93F;&#x937;&#x92F;&#x940;&#x91A;&#x940; &#x92E;&#x93E;&#x939;&#x93F;&#x924;&#x940; &#x906;&#x923;&#x93F; &#x92A;&#x941;&#x922;&#x940;&#x932; &#x905;&#x92A;&#x921;&#x947;&#x91F;&#x94D;&#x938;
                    <span class="whatsapp-pill" aria-hidden="true">WA</span>
                    WhatsApp &#x935;&#x930; &#x92A;&#x93E;&#x920;&#x935;&#x932;&#x947; &#x91C;&#x93E;&#x924;&#x940;&#x932;.
                </p>
            </div>

            <div id="remainingTokensCard" class="remaining-tokens-card" aria-live="polite">
                <p class="remaining-label">&#x906;&#x91C; &#x909;&#x92A;&#x932;&#x92C;&#x94D;&#x927; &#x91F;&#x94B;&#x915;&#x928;</p>
                <p id="remainingTokensCount" class="remaining-value">--</p>
                <p id="remainingTokensMeta" class="remaining-meta">&#x938;&#x94D;&#x925;&#x93F;&#x924;&#x940; &#x905;&#x92A;&#x921;&#x947;&#x91F; &#x939;&#x94B;&#x924; &#x906;&#x939;&#x947;...</p>
            </div>

            <div class="terms-card" aria-label="&#x905;&#x91F;&#x940; &#x935; &#x936;&#x930;&#x94D;&#x924;&#x940;">
                <h2 class="terms-title">Terms &amp; Conditions</h2>
                <ul class="terms-list">
                    <li>&#x915;&#x943;&#x92A;&#x92F;&#x93E; &#x906;&#x92A;&#x932;&#x94D;&#x92F;&#x93E; &#x91F;&#x94B;&#x915;&#x928; &#x915;&#x94D;&#x930;&#x92E;&#x93E;&#x902;&#x915;&#x93E;&#x928;&#x941;&#x938;&#x93E;&#x930;&#x91A; &#x92D;&#x947;&#x91F; &#x926;&#x93F;&#x932;&#x940; &#x91C;&#x93E;&#x908;&#x932;.</li>
                    <li>&#x906;&#x92A;&#x932;&#x93E; &#x91F;&#x94B;&#x915;&#x928; &#x915;&#x94D;&#x930;&#x92E;&#x93E;&#x902;&#x915; &#x935;&#x947;&#x933;&#x947;&#x935;&#x930; &#x924;&#x92A;&#x93E;&#x938;&#x924; &#x930;&#x93E;&#x939;&#x93E;.</li>
                    <li>&#x915;&#x93E;&#x930;&#x94D;&#x92F;&#x93E;&#x932;&#x92F;&#x93E;&#x924;&#x940;&#x932; &#x932;&#x93E;&#x908;&#x935;&#x94D;&#x939; &#x91F;&#x94B;&#x915;&#x928; &#x938;&#x94D;&#x915;&#x94D;&#x930;&#x940;&#x928; &#x915;&#x93F;&#x902;&#x935;&#x93E; &#x935;&#x947;&#x92C;&#x938;&#x93E;&#x907;&#x91F;&#x935;&#x930; &#x91A;&#x93E;&#x932;&#x942; &#x91F;&#x94B;&#x915;&#x928; &#x92A;&#x93E;&#x939;&#x942; &#x936;&#x915;&#x924;&#x93E;.</li>
                    <li>&#x915;&#x943;&#x92A;&#x92F;&#x93E; &#x935;&#x947;&#x933;&#x947;&#x935;&#x930; &#x909;&#x92A;&#x938;&#x94D;&#x925;&#x93F;&#x924; &#x930;&#x93E;&#x939;&#x93E;. &#x909;&#x936;&#x93F;&#x930;&#x93E; &#x906;&#x932;&#x94D;&#x92F;&#x93E;&#x938; &#x91F;&#x94B;&#x915;&#x928; &#x92A;&#x941;&#x922;&#x947; &#x91C;&#x93E;&#x90A; &#x936;&#x915;&#x924;&#x947;.</li>
                    <li>&#x92A;&#x94D;&#x930;&#x924;&#x94D;&#x92F;&#x947;&#x915; &#x92D;&#x947;&#x91F;&#x940;&#x938;&#x93E;&#x920;&#x940; &#x915;&#x943;&#x92A;&#x92F;&#x93E; &#x91C;&#x93E;&#x938;&#x94D;&#x924; &#x935;&#x947;&#x933; &#x918;&#x947;&#x90A; &#x928;&#x92F;&#x947;.</li>
                    <li>&#x939;&#x93E; &#x91F;&#x94B;&#x915;&#x928; &#x92B;&#x915;&#x94D;&#x924; &#x906;&#x91C;&#x91A;&#x94D;&#x92F;&#x93E; &#x926;&#x93F;&#x935;&#x938;&#x93E;&#x938;&#x93E;&#x920;&#x940; &#x906;&#x923;&#x93F; &#x92A;&#x94D;&#x930;&#x924;&#x94D;&#x92F;&#x915;&#x94D;&#x937; &#x915;&#x93E;&#x930;&#x94D;&#x92F;&#x93E;&#x932;&#x92F; &#x92D;&#x947;&#x91F;&#x940;&#x938;&#x93E;&#x920;&#x940; &#x935;&#x948;&#x927; &#x906;&#x939;&#x947;.</li>
                    <li>&#x909;&#x926;&#x94D;&#x92F;&#x93E;&#x91A;&#x947; &#x915;&#x93F;&#x902;&#x935;&#x93E; &#x92A;&#x941;&#x922;&#x940;&#x932; &#x924;&#x93E;&#x930;&#x916;&#x947;&#x91A;&#x947; &#x91F;&#x94B;&#x915;&#x928; &#x935;&#x947;&#x92C;&#x938;&#x93E;&#x907;&#x91F;&#x935;&#x930;&#x942;&#x928; &#x92C;&#x941;&#x915; &#x915;&#x930;&#x924;&#x93E; &#x92F;&#x947;&#x908;&#x932;.</li>
                </ul>
            </div>

            <p class="location-chip">&#x915;&#x947;&#x902;&#x926;&#x94D;&#x930;: <strong><?= htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8'); ?></strong></p>
        </section>

        <section class="kiosk-panel interaction-panel" aria-label="&#x91F;&#x94B;&#x915;&#x928; &#x907;&#x928;&#x92A;&#x941;&#x91F; &#x935;&#x93F;&#x92D;&#x93E;&#x917;">
            <div class="interaction-shell">
                <h2 class="interaction-title">&#x92E;&#x94B;&#x92C;&#x93E;&#x908;&#x932; &#x928;&#x902;&#x92C;&#x930; &#x91F;&#x93E;&#x915;&#x93E;</h2>

                <div id="entrySection" class="entry-section">
                    <input
                        id="phoneDisplay"
                        class="phone-display"
                        type="text"
                        value="__________"
                        readonly
                        inputmode="none"
                        autocomplete="off"
                        aria-readonly="true"
                        aria-label="&#x92E;&#x94B;&#x92C;&#x93E;&#x908;&#x932; &#x928;&#x902;&#x92C;&#x930;"
                    >

                    <div id="keypad" class="keypad" aria-label="Numeric keypad">
                        <button type="button" class="keypad-btn" data-digit="1">1</button>
                        <button type="button" class="keypad-btn" data-digit="2">2</button>
                        <button type="button" class="keypad-btn" data-digit="3">3</button>
                        <button type="button" class="keypad-btn" data-digit="4">4</button>
                        <button type="button" class="keypad-btn" data-digit="5">5</button>
                        <button type="button" class="keypad-btn" data-digit="6">6</button>
                        <button type="button" class="keypad-btn" data-digit="7">7</button>
                        <button type="button" class="keypad-btn" data-digit="8">8</button>
                        <button type="button" class="keypad-btn" data-digit="9">9</button>
                        <button type="button" class="keypad-btn keypad-action" id="backspaceButton" aria-label="Backspace">&#9003;</button>
                        <button type="button" class="keypad-btn" data-digit="0">0</button>
                        <div class="keypad-spacer" aria-hidden="true"></div>
                    </div>

                    <button id="getTokenButton" class="token-button" type="button" disabled>&#x91F;&#x94B;&#x915;&#x928; &#x918;&#x94D;&#x92F;&#x93E;</button>
                </div>

                <div id="tokensFullNotice" class="tokens-full-notice hidden" aria-live="polite">
                    <h3 class="tokens-full-title">&#x906;&#x91C;&#x91A;&#x947; &#x938;&#x930;&#x94D;&#x935; &#x91F;&#x94B;&#x915;&#x928; &#x92A;&#x942;&#x930;&#x94D;&#x923; &#x91D;&#x93E;&#x932;&#x947; &#x906;&#x939;&#x947;&#x924;</h3>
                    <p class="tokens-full-text">&#x915;&#x943;&#x92A;&#x92F;&#x93E; &#x909;&#x926;&#x94D;&#x92F;&#x93E; &#x92A;&#x941;&#x928;&#x94D;&#x939;&#x93E; &#x92F;&#x93E; &#x915;&#x93F;&#x902;&#x935;&#x93E; &#x935;&#x947;&#x92C;&#x938;&#x93E;&#x907;&#x91F;&#x935;&#x930;&#x942;&#x928; &#x909;&#x926;&#x94D;&#x92F;&#x93E;&#x91A;&#x947; / &#x92A;&#x941;&#x922;&#x940;&#x932; &#x926;&#x93F;&#x935;&#x938;&#x93E;&#x91A;&#x947; &#x91F;&#x94B;&#x915;&#x928; &#x92C;&#x941;&#x915; &#x915;&#x930;&#x93E;.</p>
                    <p class="tokens-full-text">
                        <span class="whatsapp-pill" aria-hidden="true">WA</span>
                        WhatsApp &#x905;&#x92A;&#x921;&#x947;&#x91F;&#x94D;&#x938;&#x938;&#x93E;&#x920;&#x940; &#x92C;&#x941;&#x915;&#x93F;&#x902;&#x917;&#x92E;&#x927;&#x94D;&#x92F;&#x947; &#x92E;&#x94B;&#x92C;&#x93E;&#x908;&#x932; &#x928;&#x902;&#x92C;&#x930; &#x928;&#x915;&#x94D;&#x915;&#x940; &#x92D;&#x930;&#x93E;.
                    </p>
                    <ol class="tokens-full-steps">
                        <li>&#x92E;&#x94B;&#x92C;&#x93E;&#x908;&#x932;&#x92E;&#x927;&#x94D;&#x92F;&#x947; &#x92C;&#x94D;&#x930;&#x93E;&#x909;&#x91D;&#x930; &#x909;&#x918;&#x921;&#x93E; &#x906;&#x923;&#x93F; <strong>vishnusudarshana.com</strong> &#x932;&#x93E; &#x92D;&#x947;&#x91F; &#x926;&#x94D;&#x92F;&#x93E;.</li>
                        <li><strong>Book Token</strong> &#x92A;&#x947;&#x91C; &#x909;&#x918;&#x921;&#x93E; &#x915;&#x93F;&#x902;&#x935;&#x93E; &#x925;&#x947;&#x91F; <strong>/book-token.php</strong> &#x935;&#x93E;&#x92A;&#x930;&#x93E;.</li>
                        <li>&#x924;&#x93E;&#x930;&#x940;&#x916;, &#x932;&#x94B;&#x915;&#x947;&#x936;&#x928;, &#x928;&#x93E;&#x935; &#x906;&#x923;&#x93F; WhatsApp &#x928;&#x902;&#x92C;&#x930; &#x92D;&#x930;&#x93E;.</li>
                        <li><strong>Book Office Visit Token</strong> &#x935;&#x930; &#x915;&#x94D;&#x932;&#x93F;&#x915; &#x915;&#x930;&#x942;&#x928; &#x91F;&#x94B;&#x915;&#x928; &#x928;&#x93F;&#x936;&#x94D;&#x91A;&#x93F;&#x924; &#x915;&#x930;&#x93E;.</li>
                    </ol>
                    <div class="online-qr-wrap" aria-label="Online booking QR code">
                        <p class="online-qr-title">&#x911;&#x928;&#x932;&#x93E;&#x907;&#x928; &#x91F;&#x94B;&#x915;&#x928; &#x938;&#x93E;&#x920;&#x940; QR &#x938;&#x94D;&#x915;&#x945;&#x928; &#x915;&#x930;&#x93E;</p>
                        <img
                            class="online-book-qr"
                            src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=https%3A%2F%2Fvishnusudarshana.com%2Fbook-token.php"
                            alt="Scan QR code for online token booking"
                            loading="lazy"
                        >
                        <p class="online-qr-url">vishnusudarshana.com/book-token.php</p>
                    </div>
                </div>

                <div id="printerStage" class="printer-stage hidden" aria-live="polite">
                    <div class="printer-shell">
                        <div class="printer-head">
                            <span class="printer-led" aria-hidden="true"></span>
                            <span>&#x91F;&#x93F;&#x915;&#x93F;&#x91F; &#x92A;&#x94D;&#x930;&#x93F;&#x902;&#x91F;&#x930;</span>
                        </div>
                        <div class="printer-slot-wrap">
                            <div id="printingTicket" class="printing-ticket" aria-hidden="true">
                                <div id="ticketProgressBlock" class="ticket-progress-block">
                                    <p class="printing-ticket-title">&#x92A;&#x94D;&#x930;&#x924;&#x94D;&#x92F;&#x915;&#x94D;&#x937; &#x915;&#x93E;&#x930;&#x94D;&#x92F;&#x93E;&#x932;&#x92F; &#x92D;&#x947;&#x91F;&#x940;&#x938;&#x93E;&#x920;&#x940; &#x91F;&#x94B;&#x915;&#x928;</p>
                                    <p class="printing-ticket-line">&#x906;&#x92A;&#x932;&#x947; &#x91F;&#x94B;&#x915;&#x928; &#x91B;&#x93E;&#x92A;&#x932;&#x947; &#x91C;&#x93E;&#x924; &#x906;&#x939;&#x947;...</p>
                                    <p class="printing-ticket-dots">....................</p>
                                </div>

                                <div id="ticketDetailsBlock" class="ticket-details-block hidden">
                                    <p class="ticket-brand">VISHNUSUDARSHANA.COM</p>
                                    <p class="ticket-main-title">&#x92A;&#x94D;&#x930;&#x924;&#x94D;&#x92F;&#x915;&#x94D;&#x937; &#x92D;&#x947;&#x91F; &#x91F;&#x94B;&#x915;&#x928; &#x92A;&#x93E;&#x935;&#x924;&#x940;</p>
                                    <p class="ticket-token-label">&#x906;&#x92A;&#x932;&#x947; &#x91F;&#x94B;&#x915;&#x928;</p>
                                    <p id="receiptTokenNumber" class="ticket-token-number">#--</p>

                                    <div class="ticket-row">
                                        <span>&#x92E;&#x94B;&#x92C;&#x93E;&#x908;&#x932;</span>
                                        <strong id="receiptPhoneNumber">--</strong>
                                    </div>
                                    <div class="ticket-row">
                                        <span>&#x915;&#x947;&#x902;&#x926;&#x94D;&#x930;</span>
                                        <strong id="receiptLocationName"><?= htmlspecialchars($receiptLocationLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="ticket-row">
                                        <span>&#x926;&#x93F;&#x928;&#x93E;&#x902;&#x915;</span>
                                        <strong id="receiptDateValue">--</strong>
                                    </div>
                                    <div class="ticket-row">
                                        <span>&#x935;&#x947;&#x933;</span>
                                        <strong id="receiptTimeValue">--</strong>
                                    </div>

                                    <p class="ticket-note-line">&#x915;&#x943;&#x92A;&#x92F;&#x93E; live &#x938;&#x94D;&#x915;&#x94D;&#x930;&#x940;&#x928;&#x935;&#x930; &#x91A;&#x93E;&#x932;&#x942; &#x91F;&#x94B;&#x915;&#x928; &#x92A;&#x93E;&#x939;&#x924; &#x930;&#x939;&#x93E;.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p id="printingStatus" class="printing-status">&#x906;&#x92A;&#x932;&#x947; &#x91F;&#x94B;&#x915;&#x928; &#x91B;&#x93E;&#x92A;&#x932;&#x947; &#x91C;&#x93E;&#x924; &#x906;&#x939;&#x947;...</p>
                </div>

                <div id="cooldownBlock" class="cooldown-block hidden" aria-live="polite">
                    <p class="cooldown-text">&#x92A;&#x941;&#x922;&#x940;&#x932; &#x91F;&#x94B;&#x915;&#x928; &#x909;&#x92A;&#x932;&#x92C;&#x94D;&#x927; &#x939;&#x94B;&#x908;&#x932;</p>
                    <p class="cooldown-timer"><span id="cooldownSeconds">&#x969;&#x966;</span> &#x938;&#x947;&#x915;&#x902;&#x926; &#x92C;&#x93E;&#x915;&#x940;</p>
                </div>

                <p id="statusMessage" class="status-message" aria-live="polite"></p>
            </div>
        </section>
    </main>

    <script charset="UTF-8" src="js/announcement-helper.js?v=20260307a1"></script>
    <script charset="UTF-8" src="js/tablet-token.js?v=20260307a1"></script>
</body>
</html>
