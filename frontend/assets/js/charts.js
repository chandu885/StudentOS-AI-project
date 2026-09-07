/**
 * frontend/assets/js/charts.js - Chart.js Helpers for StudentOS AI
 */

const ChartHelper = {
    // Default theme colors matching variables.css
    colors: {
        primary: '#6366F1',
        secondary: '#8B5CF6',
        success: '#22C55E',
        warning: '#F59E0B',
        danger: '#EF4444',
        info: '#3B82F6',
        cardBg: '#1A2332',
        border: '#1F2937',
        text: '#F8FAFC',
        muted: '#94A3B8'
    },

    renderDoughnut(canvasId, labels, data, colors = null) {
        const ctx = document.getElementById(canvasId);
        if (!ctx || typeof Chart === 'undefined') return null;

        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors || [this.colors.success, this.colors.warning, this.colors.danger],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: this.colors.muted, font: { family: 'Inter' } }
                    }
                },
                cutout: '70%'
            }
        });
    },

    renderLine(canvasId, labels, data, label = 'Performance') {
        const ctx = document.getElementById(canvasId);
        if (!ctx || typeof Chart === 'undefined') return null;

        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: data,
                    borderColor: this.colors.primary,
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: this.colors.secondary
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { color: this.colors.border },
                        ticks: { color: this.colors.muted }
                    },
                    y: {
                        grid: { color: this.colors.border },
                        ticks: { color: this.colors.muted },
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: { labels: { color: this.colors.text } }
                }
            }
        });
    },

    renderBar(canvasId, labels, data, label = 'Scores') {
        const ctx = document.getElementById(canvasId);
        if (!ctx || typeof Chart === 'undefined') return null;

        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: data,
                    backgroundColor: this.colors.primary,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { color: 'transparent' },
                        ticks: { color: this.colors.muted }
                    },
                    y: {
                        grid: { color: this.colors.border },
                        ticks: { color: this.colors.muted },
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: { labels: { color: this.colors.text } }
                }
            }
        });
    }
};
