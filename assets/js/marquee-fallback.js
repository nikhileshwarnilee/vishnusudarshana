(function () {
    'use strict';

    function prepareCloneAccessibility(clone) {
        var interactiveElements = clone.querySelectorAll('a, button, input, select, textarea, [tabindex]');

        Array.prototype.forEach.call(interactiveElements, function (element) {
            element.setAttribute('tabindex', '-1');
        });
    }

    function getDirectionClass(marquee) {
        var direction = (marquee.getAttribute('direction') || 'left').toLowerCase();

        return direction === 'right' ? 'vs-marquee--right' : 'vs-marquee--left';
    }

    function upgradeMarquee(marquee) {
        if (!marquee || !marquee.parentNode || marquee.dataset.vsMarqueeReady === 'true') {
            return;
        }

        var originalHtml = (marquee.innerHTML || '').trim();
        var originalText = (marquee.textContent || '').replace(/\s+/g, ' ').trim();

        if (!originalHtml) {
            return;
        }

        var replacement = document.createElement('div');

        Array.prototype.forEach.call(marquee.attributes, function (attribute) {
            replacement.setAttribute(attribute.name, attribute.value);
        });

        replacement.classList.add('vs-marquee', getDirectionClass(marquee));
        replacement.dataset.vsMarqueeReady = 'true';

        if (originalText && !replacement.hasAttribute('aria-label')) {
            replacement.setAttribute('aria-label', originalText);
        }

        var track = document.createElement('div');
        track.className = 'vs-marquee__track';
        replacement.appendChild(track);
        marquee.parentNode.replaceChild(replacement, marquee);

        var buildTrack = function () {
            // Add enough repeated copies to keep the loop seamless on wider screens.
            track.innerHTML = '';

            var content = document.createElement('div');
            content.className = 'vs-marquee__content';
            content.innerHTML = originalHtml;
            track.appendChild(content);

            var computedTrackStyle = window.getComputedStyle(track);
            var gap = parseFloat(computedTrackStyle.columnGap || computedTrackStyle.gap || '0') || 0;
            var distance = Math.ceil(content.scrollWidth + gap);
            var containerWidth = Math.ceil(replacement.clientWidth);

            if (!distance) {
                return;
            }

            var totalItems = Math.max(2, Math.ceil(Math.max(containerWidth - content.scrollWidth, 0) / distance) + 2);

            for (var index = 1; index < totalItems; index += 1) {
                var clone = content.cloneNode(true);
                clone.classList.add('is-clone');
                clone.setAttribute('aria-hidden', 'true');
                prepareCloneAccessibility(clone);
                track.appendChild(clone);
            }

            var marqueeSpeed = parseFloat(window.getComputedStyle(replacement).getPropertyValue('--vs-marquee-speed')) || 42;
            var durationInSeconds = distance / marqueeSpeed;

            replacement.style.setProperty('--vs-marquee-distance', distance + 'px');
            replacement.style.setProperty('--vs-marquee-duration', durationInSeconds + 's');
        };

        var pendingFrame = 0;
        var scheduleBuild = function () {
            if (pendingFrame) {
                window.cancelAnimationFrame(pendingFrame);
            }

            pendingFrame = window.requestAnimationFrame(function () {
                pendingFrame = 0;
                buildTrack();
            });
        };

        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(scheduleBuild);
        });

        window.addEventListener('load', scheduleBuild, { once: true });

        if (document.fonts && typeof document.fonts.ready === 'object' && typeof document.fonts.ready.then === 'function') {
            document.fonts.ready.then(scheduleBuild);
        }

        if ('ResizeObserver' in window) {
            var resizeObserver = new ResizeObserver(scheduleBuild);
            resizeObserver.observe(replacement);
        } else {
            window.addEventListener('resize', scheduleBuild);
        }
    }

    function upgradeScope(rootNode) {
        if (!rootNode || rootNode.nodeType !== 1) {
            return;
        }

        if (rootNode.tagName && rootNode.tagName.toLowerCase() === 'marquee') {
            upgradeMarquee(rootNode);
            return;
        }

        var marqueeNodes = rootNode.querySelectorAll ? rootNode.querySelectorAll('marquee') : [];

        Array.prototype.forEach.call(marqueeNodes, upgradeMarquee);
    }

    function initMarquees() {
        Array.prototype.forEach.call(document.querySelectorAll('marquee'), upgradeMarquee);

        if (!document.body || !('MutationObserver' in window)) {
            return;
        }

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                Array.prototype.forEach.call(mutation.addedNodes, upgradeScope);
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMarquees, { once: true });
    } else {
        initMarquees();
    }
})();
