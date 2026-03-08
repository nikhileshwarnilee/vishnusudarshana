<?php
require_once __DIR__ . '/helpers/favicon.php';
$faviconBasePrefix = vs_get_base_url_prefix();
$faviconConfig = [
    'themeColor' => '#800000',
    'manifest' => $faviconBasePrefix . '/manifest.json',
    'icon32' => $faviconBasePrefix . '/assets/images/logo/logo-icon.png',
    'icon192' => $faviconBasePrefix . '/assets/images/logo/logo-iconpwa192.png',
    'apple' => $faviconBasePrefix . '/assets/images/logo/logo-iconpwa512.png',
];
$navPrefix = isset($assetPrefix) ? $assetPrefix : '';
$mobileCurrentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$mobileCurrentPage = basename($mobileCurrentPath ?: ($_SERVER['PHP_SELF'] ?? ''));
$isActiveMobileNav = static function (array $pages) use ($mobileCurrentPage) {
    return in_array($mobileCurrentPage, $pages, true);
};
?>
<script>
(function() {
    if (document.querySelector('link[rel="icon"], link[rel="shortcut icon"]')) {
        return;
    }
    const cfg = <?php echo json_encode($faviconConfig, JSON_UNESCAPED_SLASHES); ?>;
    const head = document.head || document.getElementsByTagName('head')[0];
    if (!head) return;

    const setMetaTheme = () => {
        let themeMeta = document.querySelector('meta[name="theme-color"]');
        if (!themeMeta) {
            themeMeta = document.createElement('meta');
            themeMeta.setAttribute('name', 'theme-color');
            head.appendChild(themeMeta);
        }
        themeMeta.setAttribute('content', cfg.themeColor);
    };

    const addLink = (rel, href, type, sizes) => {
        if (!href) return;
        const link = document.createElement('link');
        link.rel = rel;
        link.href = href;
        if (type) link.type = type;
        if (sizes) link.sizes = sizes;
        head.appendChild(link);
    };

    setMetaTheme();
    addLink('manifest', cfg.manifest, '', '');
    addLink('icon', cfg.icon32, 'image/png', '32x32');
    addLink('icon', cfg.icon192, 'image/png', '192x192');
    addLink('shortcut icon', cfg.icon32, '', '');
    addLink('apple-touch-icon', cfg.apple, '', '');
})();
</script>

    <nav class="mobile-nav">
        <ul>
            <li>
                <a href="<?php echo htmlspecialchars($navPrefix . 'index.php', ENT_QUOTES, 'UTF-8'); ?>" class="nav-link<?php echo $isActiveMobileNav(['index.php', '']) ? ' is-active' : ''; ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        <span>Home</span>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($navPrefix . 'services.php', ENT_QUOTES, 'UTF-8'); ?>" class="nav-link<?php echo $isActiveMobileNav(['services.php', 'category.php', 'service-form.php', 'service-review.php', 'service-review2.php', 'payment-init.php', 'payment-success.php', 'payment-failed.php']) ? ' is-active' : ''; ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15 15 0 0 1 4 10 15 15 0 0 1-4 10 15 15 0 0 1-4-10 15 15 0 0 1 4-10z"></path></svg>
                        <span class="nav-label nav-label-long">Online Services</span>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($navPrefix . 'offlineservices.php', ENT_QUOTES, 'UTF-8'); ?>" class="nav-link<?php echo $isActiveMobileNav(['offlineservices.php', 'book-token.php', 'live-token.php']) ? ' is-active' : ''; ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 22h18"></path><path d="M5 22V7l7-4 7 4v15"></path><rect x="9" y="10" width="2" height="2"></rect><rect x="13" y="10" width="2" height="2"></rect><path d="M10 22v-5h4v5"></path></svg>
                        <span class="nav-label nav-label-long">Offline Services</span>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($navPrefix . 'blogs.php', ENT_QUOTES, 'UTF-8'); ?>" class="nav-link<?php echo $isActiveMobileNav(['blogs.php', 'blog-detail.php']) ? ' is-active' : ''; ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="12" y2="16"/></svg>
                        <span>Articles</span>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($navPrefix . 'track.php', ENT_QUOTES, 'UTF-8'); ?>" class="nav-link<?php echo $isActiveMobileNav(['track.php']) ? ' is-active' : ''; ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                        <span>Track</span>
                </a>
            </li>
        </ul>
    </nav>

<script>
(function() {
    // Defensive cleanup in case an old cached footer still contains About Us nav item.
    var removeLegacyAboutItem = function() {
        var links = document.querySelectorAll('.mobile-nav a[href$="about-us.php"], .mobile-nav a[href*="/about-us.php"]');
        links.forEach(function(link) {
            var item = link.closest('li');
            if (item) {
                item.remove();
            } else {
                link.remove();
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', removeLegacyAboutItem);
    } else {
        removeLegacyAboutItem();
    }
})();
</script>

