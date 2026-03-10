<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Payment Failed | Vishnusudarshana';
$paymentId = trim((string)($_GET['payment_id'] ?? ''));

if ($paymentId === '' && !empty($_SERVER['HTTP_REFERER'])) {
    $refQuery = (string)parse_url((string)$_SERVER['HTTP_REFERER'], PHP_URL_QUERY);
    if ($refQuery !== '') {
        $refParams = [];
        parse_str($refQuery, $refParams);
        $paymentId = trim((string)($refParams['payment_id'] ?? ''));
    }
}

$retryUrl = 'services.php';
if ($paymentId !== '') {
    $retryUrl = 'payment-init.php?payment_id=' . urlencode($paymentId);
}

require_once 'header.php';
?>
<main class="main-content payment-failed-main">
    <section class="payment-failed-card">
        <div class="fail-icon-wrap" aria-hidden="true">
            <span class="fail-icon">!</span>
        </div>
        <h1 class="payment-failed-title">Payment Failed</h1>
        <p class="payment-failed-text">
            Your payment didn't go through due to a temporary issue.
        </p>
        <p class="payment-failed-refund">
            Any debited amount will be refunded in
            <strong>4-5 business days</strong>.
        </p>
        <div class="payment-failed-actions">
            <a href="<?php echo htmlspecialchars($retryUrl, ENT_QUOTES, 'UTF-8'); ?>" class="payment-failed-btn payment-failed-btn-primary">
                Retry Payment
            </a>
            <a href="services.php" class="payment-failed-btn payment-failed-btn-secondary">
                Back to Services
            </a>
        </div>
        <p class="payment-failed-help">If this issue repeats, please contact support.</p>
    </section>
</main>
<style>
.payment-failed-main {
    max-width: 560px;
    margin: 0 auto;
    padding: 28px 14px 30px;
}

.payment-failed-card {
    position: relative;
    border-radius: 20px;
    padding: 26px 20px 24px;
    text-align: center;
    background:
        radial-gradient(circle at 10% 10%, rgba(255, 210, 120, 0.35), transparent 40%),
        radial-gradient(circle at 92% 0%, rgba(255, 118, 118, 0.22), transparent 45%),
        #fff8f4;
    box-shadow: 0 16px 38px rgba(128, 0, 0, 0.18);
    border: 1px solid rgba(128, 0, 0, 0.16);
}

.fail-icon-wrap {
    width: 70px;
    height: 70px;
    margin: 0 auto 14px;
    border-radius: 999px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, #ff5252, #c40000);
    box-shadow: 0 10px 25px rgba(196, 0, 0, 0.35);
}

.fail-icon {
    color: #fff;
    font-size: 2rem;
    line-height: 1;
    font-weight: 800;
}

.payment-failed-title {
    margin: 0 0 10px;
    color: #7a0000;
    font-size: 1.55rem;
    letter-spacing: 0.2px;
}

.payment-failed-text {
    margin: 0 0 12px;
    color: #2f2f2f;
    font-size: 1.08rem;
    line-height: 1.5;
}

.payment-failed-refund {
    margin: 0 0 20px;
    color: #7a2f00;
    font-size: 1.02rem;
    line-height: 1.45;
    background: linear-gradient(120deg, #fff6c7, #ffe8bd);
    border: 1px solid #ffd38b;
    border-radius: 12px;
    padding: 12px 14px;
}

.payment-failed-actions {
    display: grid;
    gap: 10px;
    margin: 0 auto 12px;
    max-width: 290px;
}

.payment-failed-btn {
    display: inline-block;
    text-decoration: none;
    padding: 13px 14px;
    border-radius: 11px;
    font-size: 1rem;
    font-weight: 700;
    transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}

.payment-failed-btn:hover {
    transform: translateY(-1px);
}

.payment-failed-btn-primary {
    color: #fff;
    background: linear-gradient(135deg, #8f0000, #c90000);
    box-shadow: 0 10px 20px rgba(143, 0, 0, 0.28);
}

.payment-failed-btn-secondary {
    color: #7a0000;
    background: #fff;
    border: 1px solid #e8b8b8;
}

.payment-failed-help {
    margin: 8px 0 0;
    color: #6a6a6a;
    font-size: 0.95rem;
}

@media (max-width: 680px) {
    .payment-failed-main {
        padding: 20px 10px 18px;
    }

    .payment-failed-card {
        border-radius: 16px;
        padding: 22px 14px 18px;
    }

    .payment-failed-title {
        font-size: 1.35rem;
    }
}
</style>
<?php require_once 'footer.php'; ?>
