import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['shortcut', 'fromInput', 'toInput'];
    static values = { from: String, to: String };

    connect() {
        this.highlightActiveShortcut();
    }

    apply() {
        const from = this.fromInputTarget.value;
        const to = this.toInputTarget.value;

        if (!from || !to || from > to) {
            return;
        }

        this.updateFrames(from, to);
    }

    shortcutTargetConnected(element) {
        element.addEventListener('click', () => {
            const period = element.dataset.period;
            const { from, to } = this.computePeriod(period);
            this.fromInputTarget.value = from;
            this.toInputTarget.value = to;
            this.updateFrames(from, to);
        });
    }

    computePeriod(period) {
        const today = new Date();
        const to = this.formatDate(today);
        let from;

        switch (period) {
            case 'today':
                from = to;
                break;
            case '7days':
                from = this.formatDate(new Date(today.getTime() - 6 * 86400000));
                break;
            case '30days':
                from = this.formatDate(new Date(today.getTime() - 29 * 86400000));
                break;
            case 'month':
                from = this.formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
                break;
            default:
                from = this.formatDate(new Date(today.getTime() - 29 * 86400000));
        }

        return { from, to };
    }

    updateFrames(from, to) {
        const url = new URL(window.location);
        url.searchParams.set('from', from);
        url.searchParams.set('to', to);
        window.history.replaceState({}, '', url);

        document.querySelectorAll('turbo-frame[id^="ca-"]').forEach(frame => {
            const src = new URL(frame.src || frame.getAttribute('src'), window.location.origin);
            src.searchParams.set('from', from);
            src.searchParams.set('to', to);
            frame.src = src.toString();
        });

        this.fromValue = from;
        this.toValue = to;
        this.highlightActiveShortcut();
    }

    highlightActiveShortcut() {
        const from = this.fromInputTarget.value;
        const to = this.toInputTarget.value;

        this.shortcutTargets.forEach(btn => {
            const { from: pFrom, to: pTo } = this.computePeriod(btn.dataset.period);
            btn.classList.toggle('active', pFrom === from && pTo === to);
        });
    }

    formatDate(date) {
        return date.toISOString().slice(0, 10);
    }
}
