import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';

import zoomPlugin from 'chartjs-plugin-zoom';
import dragDataPlugin from 'chartjs-plugin-dragdata';
import dataLabelsPlugin from 'chartjs-plugin-datalabels';
import { ForceDirectedGraphController, EdgeLine } from 'chartjs-chart-graph';

// register globally for all charts
document.addEventListener('chartjs:init', function (event) {
    const Chart = event.detail.Chart;
    Chart.register(zoomPlugin);
    Chart.register(dragDataPlugin);
    Chart.register(dataLabelsPlugin);
    Chart.register(ForceDirectedGraphController, EdgeLine);
});
