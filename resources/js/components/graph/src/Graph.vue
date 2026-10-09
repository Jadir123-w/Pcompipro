<template>
    <div class="chart-container">
        <canvas ref="canvas"></canvas>
    </div>
</template>

<style>
    .chart-container {
        position: relative;
        margin: auto;
        height: 220px;
        width: 220px;
    }
    .chart-container .chartjs-render-monitor {
        height: inherit!important;
        width: inherit!important;
    }
</style>

<script>
    import Chart from 'chart.js';

    export default {
        props: ['type', 'allData'],
        data() {
            return {
                chart: null,
                options: {
                    maintainAspectRatio: false,
                    cutoutPercentage: 75,
                    rotation: -0.5 * Math.PI,
                    circumference: 2 * Math.PI,
                    legend: {
                        display: false,
                    },
                    plugins: {
                        doughnutlabel: {
                            labels: [
                                {
                                    text: this.getTotal(),
                                    font: {
                                        size: '20',
                                        family: "'Arial', sans-serif",
                                        weight: 'bold'
                                    },
                                    color: this.getTextColor()
                                },
                                {
                                    text: 'Total',
                                    font: {
                                        size: '16',
                                        family: "'Arial', sans-serif"
                                    },
                                    color: '#9B9B9B'
                                }
                            ]
                        }
                    },
                    elements: {
                        arc: {
                            borderWidth: 2,
                            borderColor: '#FFFFFF',
                            borderAlign: 'center',
                            borderRadius: 15,
                            spacing: 10,
                        }
                    }
                },
                colorMap: []
            }
        },
        created() {
            this.title = 'Balance';
        },
        mounted() {
            const style = getComputedStyle(this.$el);
            this.colors = {
                primary: style.getPropertyValue('--primary-color').trim(),
                danger: style.getPropertyValue('--danger').trim(),
                warning: style.getPropertyValue('--warning').trim()
            };

            Chart.pluginService.register({
                beforeDraw: function(chart) {
                    if (chart.config.options.plugins.doughnutlabel) {
                        const width = chart.width,
                            height = chart.height,
                            ctx = chart.ctx;

                        ctx.restore();

                        const mainFontSize = Math.min(height / 10, 16);
                        ctx.font = `bold ${mainFontSize}px Arial, sans-serif`;
                        ctx.textBaseline = "middle";
                        ctx.textAlign = "center";

                        const text = chart.config.options.plugins.doughnutlabel.labels[0].text;
                        ctx.fillStyle = chart.config.options.plugins.doughnutlabel.labels[0].color;
                        ctx.fillText(text, width / 2, height / 2 - 10);

                        const subFontSize = Math.min(height / 16, 12);
                        ctx.font = `${subFontSize}px Arial, sans-serif`;
                        ctx.fillStyle = chart.config.options.plugins.doughnutlabel.labels[1].color;
                        ctx.fillText('Total', width / 2, height / 2 + 15);

                        ctx.save();
                    }
                }
            });
        },
        watch: {
            allData() {
                this.createChart();
            }
        },
        methods: {
            getTotal() {
                if (!this.allData || !this.allData.datasets || !this.allData.datasets[0]) return 'S/ 0.00';
                const total = this.allData.datasets[0].data.reduce((a, b) => a + b, 0);
                return (total < 0 ? 'S/ -' : 'S/ ') + Math.abs(total).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            },
            getTextColor() {
                const isDarkMode = document.documentElement.classList.contains('dark');

                if (!this.allData || !this.allData.datasets || !this.allData.datasets[0]) {
                    return isDarkMode ? '#ffffff' : '#4A4A4A';
                }

                const total = this.allData.datasets[0].data.reduce((a, b) => a + b, 0);

                if (isDarkMode) {
                    return '#ffffff';
                }

                return total < 0 ? this.colors.danger : this.colors.primary;
            },
            hexToRgba(hex, alpha = 1) {
                let r = 0, g = 0, b = 0;

                if (hex.startsWith('#')) hex = hex.slice(1);

                if (hex.length === 3) {
                    r = parseInt(hex[0] + hex[0], 16);
                    g = parseInt(hex[1] + hex[1], 16);
                    b = parseInt(hex[2] + hex[2], 16);
                } else if (hex.length === 6) {
                    r = parseInt(hex.slice(0, 2), 16);
                    g = parseInt(hex.slice(2, 4), 16);
                    b = parseInt(hex.slice(4, 6), 16);
                }

                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            },
            getSegmentColors(data) {
                const baseColors = [this.colors.primary, this.colors.warning, this.colors.danger];

                return data.map((value, index) => {
                    const color = baseColors[index % baseColors.length];
                    return {
                        backgroundColor: this.hexToRgba(color, 0.6),
                        borderColor: this.hexToRgba(color, 1)
                    };
                });
            },
            createChart() {
                if (this.chart) {
                    this.chart.destroy();
                }

                this.options.plugins.doughnutlabel.labels[0].text = this.getTotal();
                this.options.plugins.doughnutlabel.labels[0].color = this.getTextColor();

                const datasets = this.allData.datasets.map(dataset => {
                    const segmentColors = this.getSegmentColors(dataset.data);

                    return {
                        ...dataset,
                        backgroundColor: segmentColors.map(c => c.backgroundColor),
                        borderColor: segmentColors.map(c => c.borderColor),
                        borderWidth: 2,
                    };
                });

                this.chart = new Chart(this.$refs.canvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: this.allData.labels,
                        datasets: datasets
                    },
                    options: this.options,
                });
            }
        }
    }
</script>
