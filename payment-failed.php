<?php
session_start();
require_once 'header.php';
?>
<main class="main-content" style="background-color:#FFD700;">
    <div class="review-card" style="text-align:center;max-width:420px;margin:32px auto 0 auto;">
        <div style="font-size:2.5em;line-height:1;margin-bottom:10px;">❌</div>
        <h1 class="review-title" style="margin-bottom:10px;">Payment Failed</h1>
        <div style="color:#333;font-size:1.08em;margin-bottom:16px;">Your payment could not be completed. No amount has been deducted.</div>
        <div style="color:#555;font-size:0.98em;margin-bottom:22px;">You may try again or contact our support team for assistance.</div>
        <div style="display:flex;flex-direction:column;gap:12px;align-items:center;">
            
            <a href="services.php" class="review-back-link" style="width:100%;max-width:260px;">Back to Services</a>
        </div>
        <div style="margin-top:22px;color:#888;font-size:0.97em;">For help, please contact us with your details.</div>
    </div>
</main>
<?php require_once 'footer.php'; ?>
<style>
.main-content { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 18px; box-shadow: 0 4px 24px #e0bebe33; padding: 18px 12px 28px 12px; }
.review-title { font-size: 1.18em; font-weight: bold; margin-bottom: 8px; text-align: center; }
.review-card { background: #f9eaea; border-radius: 14px; box-shadow: 0 2px 8px #e0bebe33; padding: 24px 16px 22px 16px; margin-bottom: 18px; }
.pay-btn { background: #800000; color: #fff; border: none; border-radius: 8px; padding: 14px 0; font-size: 1.08em; font-weight: 600; margin-top: 0; cursor: pointer; box-shadow: 0 2px 8px #80000022; transition: background 0.15s; text-decoration:none; display:block; }
.pay-btn:active { background: #5a0000; }
.review-back-link { display:block;text-align:center;margin-top:0;color:#1a8917;font-size:0.98em;text-decoration:none;border:1px solid #1a8917;border-radius:8px;padding:12px 0;transition:background 0.15s; }
.review-back-link:active { background: #e0f7e0; }
@media (max-width: 700px) { .main-content { padding: 8px 2px 16px 2px; border-radius: 0; } }
</style>
