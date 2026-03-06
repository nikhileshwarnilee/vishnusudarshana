<?php
header('Content-Type: text/html; charset=UTF-8');

$location = isset($_GET['location']) ? strtolower(trim((string) $_GET['location'])) : 'solapur';
if ($location === '' || !preg_match('/^[a-z0-9 _-]+$/', $location)) {
    $location = 'solapur';
}

$locationLabel = ucwords(str_replace(['-', '_'], ' ', $location));
$receiptLocationMap = [
    'solapur' => 'सोलापूर',
];
$receiptLocationLabel = isset($receiptLocationMap[$location]) ? $receiptLocationMap[$location] : $locationLabel;
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#6d0014">
    <title>प्रत्यक्ष कार्यालय भेटीसाठी टोकन प्रणाली</title>
    <link rel="stylesheet" href="css/tablet-token.css?v=20260307m">
</head>
<body>
    <main class="tablet-app" data-location="<?= htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?>">
        <section class="kiosk-panel info-panel" aria-label="माहिती विभाग">
            <img class="brand-logo" src="assets/images/logo/logomain.png" alt="Vishnusudarshana Logo">

            <h1 class="info-title">प्रत्यक्ष कार्यालय भेटीसाठी टोकन प्रणाली</h1>

            <div class="info-text">
                <p>पंडितजींना प्रत्यक्ष भेटण्यासाठी कृपया आपला १० अंकी मोबाईल क्रमांक टाका आणि टोकन घ्या.</p>
                <p>आपल्या टोकनविषयीची माहिती आणि पुढील अपडेट्स WhatsApp वर पाठवले जातील.</p>
            </div>

            <div class="terms-card" aria-label="अटी व शर्ती">
                <h2 class="terms-title">Terms &amp; Conditions</h2>
                <ul class="terms-list">
                    <li>कृपया आपल्या टोकन क्रमांकानुसारच भेट दिली जाईल.</li>
                    <li>आपला टोकन क्रमांक वेळेवर तपासत राहा.</li>
                    <li>कार्यालयातील लाईव्ह टोकन स्क्रीन किंवा वेबसाइटवर चालू टोकन पाहू शकता.</li>
                    <li>कृपया वेळेवर उपस्थित राहा. उशिरा आल्यास टोकन पुढे जाऊ शकते.</li>
                    <li>प्रत्येक भेटीसाठी कृपया जास्त वेळ घेऊ नये.</li>
                    <li>हा टोकन फक्त आजच्या दिवसासाठी आणि प्रत्यक्ष कार्यालय भेटीसाठी वैध आहे.</li>
                    <li>उद्याचे किंवा पुढील तारखेचे टोकन वेबसाइटवरून बुक करता येईल.</li>
                </ul>
            </div>

            <p class="location-chip">केंद्र: <strong><?= htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8'); ?></strong></p>
        </section>

        <section class="kiosk-panel interaction-panel" aria-label="टोकन इनपुट विभाग">
            <div class="interaction-shell">
                <h2 class="interaction-title">मोबाईल नंबर टाका</h2>

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
                        aria-label="मोबाईल नंबर"
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

                    <button id="getTokenButton" class="token-button" type="button" disabled>टोकन घ्या</button>
                </div>

                <div id="printerStage" class="printer-stage hidden" aria-live="polite">
                    <div class="printer-shell">
                        <div class="printer-head">
                            <span class="printer-led" aria-hidden="true"></span>
                            <span>टिकिट प्रिंटर</span>
                        </div>
                        <div class="printer-slot-wrap">
                            <div id="printingTicket" class="printing-ticket" aria-hidden="true">
                                <div id="ticketProgressBlock" class="ticket-progress-block">
                                    <p class="printing-ticket-title">प्रत्यक्ष कार्यालय भेटीसाठी टोकन</p>
                                    <p class="printing-ticket-line">आपले टोकन छापले जात आहे...</p>
                                    <p class="printing-ticket-dots">....................</p>
                                </div>

                                <div id="ticketDetailsBlock" class="ticket-details-block hidden">
                                    <p class="ticket-brand">VISHNUSUDARSHANA.COM</p>
                                    <p class="ticket-main-title">प्रत्यक्ष भेट टोकन पावती</p>
                                    <p class="ticket-token-label">आपले टोकन</p>
                                    <p id="receiptTokenNumber" class="ticket-token-number">#--</p>

                                    <div class="ticket-row">
                                        <span>मोबाईल</span>
                                        <strong id="receiptPhoneNumber">--</strong>
                                    </div>
                                    <div class="ticket-row">
                                        <span>केंद्र</span>
                                        <strong id="receiptLocationName"><?= htmlspecialchars($receiptLocationLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="ticket-row">
                                        <span>दिनांक</span>
                                        <strong id="receiptDateValue">--</strong>
                                    </div>
                                    <div class="ticket-row">
                                        <span>वेळ</span>
                                        <strong id="receiptTimeValue">--</strong>
                                    </div>

                                    <p class="ticket-note-line">कृपया live स्क्रीनवर चालू टोकन पाहत रहा.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p id="printingStatus" class="printing-status">आपले टोकन छापले जात आहे...</p>
                </div>

                <div id="cooldownBlock" class="cooldown-block hidden" aria-live="polite">
                    <p class="cooldown-text">पुढील टोकन उपलब्ध होईल</p>
                    <p class="cooldown-timer"><span id="cooldownSeconds">३०</span> सेकंद बाकी</p>
                </div>

                <p id="statusMessage" class="status-message" aria-live="polite"></p>
            </div>
        </section>
    </main>

    <script charset="UTF-8" src="js/tablet-token.js?v=20260307r"></script>
</body>
</html>
