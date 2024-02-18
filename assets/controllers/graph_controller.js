import { Controller } from '@hotwired/stimulus';
import Chart from "chart.js";

export default class extends Controller {
    static chart;

    connect() {
        if (this.element.id === 'graph') {
            this.element.addEventListener('chartjs:pre-connect', this._onPreConnect);
            this.element.addEventListener('chartjs:connect', this._onConnect);
        }
    }

    disconnect() {
        if (this.element.id === 'graph') {
            this.element.removeEventListener('chartjs:pre-connect', this._onPreConnect);
            this.element.removeEventListener('chartjs:connect', this._onConnect);
        }
    }

    _onPreConnect(event) {
        console.log(event.detail.config);
        // For instance, you can format Y axis
        // event.detail.config.options.scales = {
        //     y: {
        //         ticks: {
        //             callback: function (value, index, values) {
        //                 /* ... */
        //             },
        //         },
        //     },
        // };
    }

    _onConnect(event) {
        self.chart = event.detail.chart;

        // For instance, you can listen to additional events
        // event.detail.chart.options.onHover = (mouseEvent) => {
        //     /* ... */
        // };
        // event.detail.chart.options.onClick = (mouseEvent) => {
        //     /* ... */
        // };
    }

    updateSelection(event) {
        const isChecked = event.target.checked;
        const webPageId = event.params.webpageid;

        console.log(self.chart);
    }
}
