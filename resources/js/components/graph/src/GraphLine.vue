<template>
    <div class="chart-line-container">
        <canvas ref="canvas"></canvas>
    </div>
</template>

<style>
    .chart-line-container {
        position: relative;
        height: 99%;
        width: 99%;
    }
</style>

<script>
import Chart from 'chart.js';

export default {
    props: ['allData'],
    data() {
        return {
            chart: null,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    display: false,
                },
                scales: {
                    xAxes: [{
                    gridLines: {
                        display: false
                    }
                    }],
                    yAxes: [{
                    gridLines: {
                        display: false
                    },
                    ticks: {
                        count: 6,
                        autoSkip: false
                    }
                    }]
                }
            }
        }
    },
    mounted() {
        this.createChart();
    },
    watch: {
        allData() {
            this.createChart();
        }
    },
    methods: {
        getCSSVariable(name) {
            return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        },
        createChart() {
            if (this.chart) {
                this.chart.destroy();
            }

            const ctx = this.$refs.canvas.getContext('2d');

            const cssColors = {
                info: this.getCSSVariable('--info'),
                danger: this.getCSSVariable('--danger'),
                primary: this.getCSSVariable('--primary-color')
            };

            const gradientColors = [
                {
                    base: cssColors.info,
                    start: this.hexToRgba(cssColors.info, 0.7),
                    end: this.hexToRgba(cssColors.info, 0.1)
                },
                {
                    base: cssColors.danger,
                    start: this.hexToRgba(cssColors.danger, 0.8),
                    end: this.hexToRgba(cssColors.danger, 0.1)
                },
                {
                    base: cssColors.primary,
                    start: this.hexToRgba(cssColors.primary, 0.8),
                    end: this.hexToRgba(cssColors.primary, 0.1)
                }
            ];

            const coloredDatasets = (this.allData?.datasets || []).map((dataset, index) => {
                const colorIndex = index % gradientColors.length;
                const gradient = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);

                gradient.addColorStop(0, gradientColors[colorIndex].start);
                gradient.addColorStop(1, gradientColors[colorIndex].end);

                return {
                    ...dataset,
                    borderColor: gradientColors[colorIndex].base,
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: gradientColors[colorIndex].base,
                    pointRadius: 0,
                    pointHoverRadius: 5
                };
            });

            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: this.allData.labels,
                    datasets: coloredDatasets,
                },
                options: {
                    ...this.options,
                    elements: {
                        line: {
                            borderJoinStyle: 'round',
                            borderWidth: 3
                        }
                    },
                    plugins: {
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: s/${context.parsed.y.toFixed(2)}`;
                                }
                            }
                        }
                    }
                },
            });
        },
        hexToRgba(hex, alpha) {
            if (hex.length === 7) {
                const r = parseInt(hex.slice(1, 3), 16);
                const g = parseInt(hex.slice(3, 5), 16);
                const b = parseInt(hex.slice(5, 7), 16);
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            }
            else if (hex.length === 4) {
                const r = parseInt(hex.slice(1, 2).repeat(2), 16);
                const g = parseInt(hex.slice(2, 3).repeat(2), 16);
                const b = parseInt(hex.slice(3, 4).repeat(2), 16);
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            }
            return hex;
        }
    }
}
</script>
