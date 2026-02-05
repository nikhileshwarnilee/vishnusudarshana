<?php
$pageTitle = 'Send Push Notification';
require_once __DIR__ . '/../header.php';
?>
<main class="main-content" style="max-width:600px;margin:24px auto;padding:16px;">
    <h1 style="margin-bottom:16px;">Send Push Notification</h1>
    <form id="pushForm" style="display:flex;flex-direction:column;gap:12px;">
        <label>
            Title
            <input type="text" name="title" required style="width:100%;padding:10px;" />
        </label>
        <label>
            Message
            <textarea name="message" rows="4" required style="width:100%;padding:10px;"></textarea>
        </label>
        <button type="submit" style="background:#800000;color:#fff;border:none;padding:12px;border-radius:8px;cursor:pointer;">Send Notification</button>
        <div id="pushStatus" style="margin-top:8px;color:#333;"></div>
    </form>
</main>
<script>
(function () {
    const form = document.getElementById('pushForm');
    const status = document.getElementById('pushStatus');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        status.textContent = 'Sending...';

        const formData = new FormData(form);
        fetch('/send_notification.php', {
            method: 'POST',
            body: formData
        }).then(res => res.json())
          .then(data => {
              if (data.success) {
                  status.textContent = `Sent: ${data.sent || 0}, Failed: ${data.failed || 0}`;
              } else {
                  status.textContent = data.message || 'Failed to send.';
              }
          })
          .catch(() => {
              status.textContent = 'Failed to send.';
          });
    });
})();
</script>
<?php require_once __DIR__ . '/../footer.php'; ?>
