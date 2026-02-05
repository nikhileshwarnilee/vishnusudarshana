<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Send Push Notification</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        html,body{font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif!important;}
        .admin-container { max-width: 800px; margin: 0 auto; padding: 24px 12px; }
        .push-form-box { 
            margin-bottom: 20px; 
            padding: 24px; 
            border: 1px solid #ddd; 
            border-radius: 12px; 
            background: #fff; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .form-group { margin-bottom: 16px; }
        .form-group label { 
            display: block; 
            font-weight: 600; 
            margin-bottom: 6px; 
            color: #333; 
        }
        .form-group input, .form-group textarea { 
            width: 100%; 
            padding: 10px 12px; 
            border: 1px solid #ddd; 
            border-radius: 6px; 
            font-size: 1em; 
            box-sizing: border-box;
            font-family: inherit;
        }
        .form-group input:focus, .form-group textarea:focus { 
            border-color: #800000; 
            outline: none; 
        }
        .btn-send { 
            padding: 12px 24px; 
            background: #800000; 
            color: #fff; 
            border: none; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer;
            font-size: 1em;
            width: 100%;
        }
        .btn-send:hover { 
            background: #600000; 
        }
        .btn-send:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        #pushStatus {
            margin-top: 16px;
            padding: 12px;
            border-radius: 8px;
            display: none;
        }
        #pushStatus.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }
        #pushStatus.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/top-menu.php'; ?>
<div class="admin-container">
    <h1 style="margin-bottom: 24px; color: #800000;">Send Push Notification</h1>
    
    <div style="background: #e7f3ff; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2196F3;">
        💡 <strong>Tip:</strong> View notification delivery logs and statistics in 
        <a href="fcm-monitoring.php" style="color: #800000; font-weight: 600;">FCM Monitoring Dashboard</a>
    </div>
    
    <div class="push-form-box">
        <form id="pushForm">
            <div class="form-group">
                <label for="notifTitle">Notification Title</label>
                <input type="text" id="notifTitle" name="title" required placeholder="Enter notification title">
            </div>
            
            <div class="form-group">
                <label for="notifMessage">Notification Message</label>
                <textarea id="notifMessage" name="message" rows="5" required placeholder="Enter notification message"></textarea>
            </div>
            
            <button type="submit" class="btn-send" id="sendBtn">Send Notification</button>
            
            <div id="pushStatus"></div>
        </form>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('pushForm');
    const status = document.getElementById('pushStatus');
    const sendBtn = document.getElementById('sendBtn');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        
        status.className = '';
        status.style.display = 'none';
        status.textContent = '';
        
        sendBtn.disabled = true;
        sendBtn.textContent = 'Sending...';

        const formData = new FormData(form);
        
        fetch('../send_notification.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                status.className = 'success';
                const duration = data.duration ? ` in ${data.duration}s` : '';
                const total = data.total ? ` out of ${data.total}` : '';
                status.textContent = `✓ Successfully sent ${data.sent || 0}${total} notification(s)${duration}. Failed: ${data.failed || 0}`;
                form.reset();
            } else {
                status.className = 'error';
                status.textContent = `✗ ${data.message || 'Failed to send notification.'}`;
            }
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send Notification';
        })
        .catch(error => {
            status.className = 'error';
            status.textContent = '✗ Network error. Please try again.';
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send Notification';
        });
    });
})();
</script>
</body>
</html>
