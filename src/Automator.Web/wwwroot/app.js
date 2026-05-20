window.blazorCharts = (function () {
    const charts = {};

    function renderExecutionChart(canvasId, labels, total, success, failed) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        if (charts[canvasId]) {
            charts[canvasId].destroy();
            delete charts[canvasId];
        }

        charts[canvasId] = new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Total',
                        data: total,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13,110,253,0.07)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        borderWidth: 2
                    },
                    {
                        label: 'Successful',
                        data: success,
                        borderColor: '#198754',
                        backgroundColor: 'transparent',
                        tension: 0.35,
                        pointRadius: 3,
                        borderWidth: 2
                    },
                    {
                        label: 'Failed',
                        data: failed,
                        borderColor: '#dc3545',
                        backgroundColor: 'transparent',
                        tension: 0.35,
                        pointRadius: 3,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, padding: 16 } }
                },
                scales: {
                    x: { grid: { color: 'rgba(0,0,0,0.05)' } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            callback: v => Number.isInteger(v) ? v : null
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });
    }

    return { renderExecutionChart };
})();
