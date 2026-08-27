import './bootstrap';
import { Chart, registerables } from 'chart.js';
import Swal from 'sweetalert2';

Chart.register(...registerables);
window.Chart = Chart;
window.Swal = Swal;

const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (el) => {
        el.addEventListener('mouseenter', Swal.stopTimer);
        el.addEventListener('mouseleave', Swal.resumeTimer);
    },
});
window.toast = toast;

(() => {
    // Safety net: if a navigation never completes (network error, cancelled
    // mid-flight), don't leave the overlay stuck forever.
    const MAX_VISIBLE_MS = 8000;
    let safetyTimeout = null;

    // 'livewire:navigate' fires the instant a navigation starts (before the
    // page is fetched) — this is what makes the overlay cover the actual
    // network wait. 'livewire:navigating' fires only after the fetch has
    // already resolved, which is too late to be useful here.
    document.addEventListener('livewire:navigate', () => {
        clearTimeout(safetyTimeout);
        document.documentElement.classList.add('is-page-loading');
        safetyTimeout = setTimeout(() => {
            document.documentElement.classList.remove('is-page-loading');
        }, MAX_VISIBLE_MS);
    });

    document.addEventListener('livewire:navigated', () => {
        clearTimeout(safetyTimeout);
        document.documentElement.classList.remove('is-page-loading');
    });
})();

document.addEventListener('livewire:init', () => {
    Livewire.on('notify', ({ type = 'success', message }) => {
        toast.fire({ icon: type, title: message, timer: type === 'success' ? 3000 : 6000 });
    });

    Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status === 419) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sessão expirada',
                    text: 'Sua sessão expirou. A página será recarregada.',
                    confirmButtonText: 'Recarregar',
                    confirmButtonColor: '#4f46e5',
                }).then(() => window.location.reload());
                preventDefault();

                return;
            }

            Swal.fire({
                icon: 'error',
                title: 'Ops, algo deu errado',
                text: 'Não foi possível concluir a ação. Tente novamente.',
                confirmButtonColor: '#4f46e5',
            });
            preventDefault();
        });
    });
});

document.addEventListener('alpine:init', () => {
    Alpine.data('trendChart', (data) => ({
        chart: null,
        init(canvas) {
            this.chart = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        { label: 'Receitas', data: data.income, backgroundColor: '#22c55e' },
                        { label: 'Despesas', data: data.expense, backgroundColor: '#ef4444' },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } },
                },
            });
        },
    }));

    Alpine.data('categoryChart', (data) => ({
        chart: null,
        init(canvas) {
            this.chart = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [
                        { data: data.totals, backgroundColor: data.colors },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                },
            });
        },
    }));

    Alpine.data('lineChart', (data) => ({
        chart: null,
        init(canvas) {
            this.chart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Patrimônio',
                            data: data.current,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            tension: 0.3,
                            fill: true,
                        },
                        {
                            label: 'Investido',
                            data: data.invested,
                            borderColor: '#94a3b8',
                            borderDash: [4, 4],
                            tension: 0.3,
                            fill: false,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: false } },
                },
            });
        },
    }));

    Alpine.data('barChart', (data) => ({
        chart: null,
        init(canvas) {
            this.chart = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        { label: data.label ?? '', data: data.values, backgroundColor: data.colors ?? '#4f46e5' },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                },
            });
        },
    }));

    Alpine.data('assetHistoryChart', (data) => ({
        chart: null,
        init(canvas) {
            this.chart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Cotação',
                            data: data.close,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            tension: 0.2,
                            fill: true,
                            pointRadius: 0,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: false } },
                    plugins: { legend: { display: false } },
                },
            });
        },
    }));
});
