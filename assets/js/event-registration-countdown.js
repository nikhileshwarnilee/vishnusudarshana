(function () {
    const widgets = Array.from(document.querySelectorAll('[data-registration-countdown]'));
    if (!widgets.length) {
        return;
    }

    const formatRemaining = function (secondsLeft) {
        if (secondsLeft >= 86400) {
            const days = Math.floor(secondsLeft / 86400);
            return {
                value: String(days),
                unit: days === 1 ? 'Day Left' : 'Days Left',
            };
        }

        if (secondsLeft >= 3600) {
            const hours = Math.floor(secondsLeft / 3600);
            return {
                value: String(hours),
                unit: hours === 1 ? 'Hour Left' : 'Hours Left',
            };
        }

        const minutes = Math.floor(secondsLeft / 60);
        const seconds = secondsLeft % 60;

        return {
            value: minutes + 'm ' + String(seconds).padStart(2, '0') + 's',
            unit: 'Left',
        };
    };

    const setClosedState = function (widget) {
        const valueNode = widget.querySelector('[data-countdown-value]');
        const unitNode = widget.querySelector('[data-countdown-unit]');
        const noteNode = widget.querySelector('[data-countdown-note]');

        widget.classList.remove('is-live');
        widget.classList.add('is-closed');

        if (valueNode) {
            valueNode.textContent = 'Closed';
        }
        if (unitNode) {
            unitNode.textContent = 'Registration Ended';
        }
        if (noteNode) {
            noteNode.textContent = widget.getAttribute('data-closed-note') || 'This registration window has closed.';
        }
    };

    const tick = function () {
        const now = Date.now();

        widgets.forEach(function (widget) {
            if (widget.getAttribute('data-force-closed') === '1') {
                setClosedState(widget);
                return;
            }

            const deadlineValue = widget.getAttribute('data-deadline') || '';
            const deadlineMs = Date.parse(deadlineValue);
            if (!Number.isFinite(deadlineMs)) {
                setClosedState(widget);
                return;
            }

            const secondsLeft = Math.max(0, Math.floor((deadlineMs - now) / 1000));
            if (secondsLeft <= 0) {
                setClosedState(widget);
                return;
            }

            const valueNode = widget.querySelector('[data-countdown-value]');
            const unitNode = widget.querySelector('[data-countdown-unit]');
            const noteNode = widget.querySelector('[data-countdown-note]');
            const parts = formatRemaining(secondsLeft);

            widget.classList.remove('is-closed');
            widget.classList.add('is-live');

            if (valueNode) {
                valueNode.textContent = parts.value;
            }
            if (unitNode) {
                unitNode.textContent = parts.unit;
            }
            if (noteNode && widget.getAttribute('data-live-note')) {
                noteNode.textContent = widget.getAttribute('data-live-note');
            }
        });
    };

    tick();
    window.setInterval(tick, 1000);
})();
