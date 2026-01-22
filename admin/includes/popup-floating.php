<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Floating Popup</title>
    <meta name="viewport" content="width=600, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
        .popup-container { max-width: 520px; margin: 32px auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px #80000033; padding: 32px 24px; }
        h2 { color: #800000; margin-bottom: 18px; }
        .close-btn { background: #800000; color: #fff; border: none; border-radius: 8px; padding: 8px 22px; font-weight: 600; cursor: pointer; float: right; }
    </style>
</head>
<body>
    <div class="popup-container">
        <button class="close-btn" onclick="window.close()">Close</button>
        <h2>Floating Popup</h2>
        <div style="color:#444; font-size:1.1em;">This is a new popup window opened from the floating icon.<br></div>
    </div>
</body>
</html>
