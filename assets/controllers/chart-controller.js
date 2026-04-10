import { Controller } from '@hotwired/stimulus';
import uPlot from 'uplot';

export default class extends Controller {
    static values = {
        dates: Array,
        views: Array,
        visitors: Array,
    };

    connect() {
        this.renderChart();
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy();
        }
    }

    renderChart() {
        const timestamps = this.datesValue.map(d => new Date(d + 'T00:00:00').getTime() / 1000);

        const opts = {
            width: this.element.clientWidth - 40,
            height: 280,
            series: [
                { label: 'Date' },
                {
                    label: 'Pages vues',
                    stroke: '#2563eb',
                    width: 2,
                    fill: 'rgba(37, 99, 235, 0.08)',
                },
                {
                    label: 'Visiteurs uniques',
                    stroke: '#9ca3af',
                    width: 2,
                    dash: [5, 5],
                },
            ],
            axes: [
                {
                    values: (u, vals) => vals.map(v => {
                        const d = new Date(v * 1000);
                        return `${d.getDate()}/${d.getMonth() + 1}`;
                    }),
                },
                {},
            ],
        };

        const data = [timestamps, this.viewsValue, this.visitorsValue];
        this.chart = new uPlot(opts, data, this.element);
    }
}
