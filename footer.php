    <nav class="mobile-nav">
        <ul>
            <li>
                <a href="index.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        <span>Home</span>
                </a>
            </li>
            <li>
                <a href="services.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>
                        <span>Services</span>
                </a>
            </li>
            <li>
                <a href="blogs.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="12" y2="16"/></svg>
                        <span>Articles</span>
                </a>
            </li>
            <li>
                <a href="track.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                        <span>Track</span>
                </a>
            </li>
            <li>
                <a href="about-us.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M6 20c0-2.5 3-4 6-4s6 1.5 6 4"/></svg>
                        <span>About Us</span>
                </a>
            </li>
        </ul>
    </nav>

    <footer class="footer">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> Vishnusudarshana. All rights reserved.</p>
            <p style="font-size:0.97em;color:#800000;margin-top:6px;">Designed and Developed by <a href="https://www.contysi.com" target="_blank" style="color:#800000;text-decoration:underline;">ContySi</a></p>
        </div>
    </footer>

    <!-- Firebase Web Push (FCM) -->
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js" defer></script>
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js" defer></script>
    <script defer>
        // Verify Firebase SDK loaded
        window.addEventListener('load', function() {
            setTimeout(function() {
                if (typeof firebase === 'undefined') {
                    console.error('[FCM] Firebase SDK failed to load from CDN');
                    // Fallback: try loading from local copy if available
                    console.log('[FCM] Attempting local Firebase fallback...');
                } else {
                    console.log('[FCM] Firebase SDK loaded successfully');
                }
            }, 2000);
        });
    </script>
    <script src="/assets/js/fcm-init.js" defer></script>
