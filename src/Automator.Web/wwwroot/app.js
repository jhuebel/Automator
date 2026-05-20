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
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Successful',
                        data: success,
                        backgroundColor: 'rgba(25,135,84,0.85)',
                        borderColor: '#198754',
                        borderWidth: 1,
                        borderRadius: { topLeft: 0, topRight: 0, bottomLeft: 3, bottomRight: 3 },
                        borderSkipped: 'bottom'
                    },
                    {
                        label: 'Failed',
                        data: failed,
                        backgroundColor: 'rgba(220,53,69,0.85)',
                        borderColor: '#dc3545',
                        borderWidth: 1,
                        borderRadius: { topLeft: 3, topRight: 3, bottomLeft: 0, bottomRight: 0 },
                        borderSkipped: 'bottom'
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
                    x: {
                        stacked: true,
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    y: {
                        stacked: true,
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
