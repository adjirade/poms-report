import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

// Expose globally: Livewire + inline chart scripts on analytics pages
// rely on window.Alpine / window.Chart.
window.Alpine = Alpine;
window.Chart = Chart;

Alpine.start();
