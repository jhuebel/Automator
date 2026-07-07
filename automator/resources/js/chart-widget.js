import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

export default function chartWidget({ type, data, options = {} }) {
    return {
        chart: null,

        init() {
            // Guards against double-mount: Alpine can re-run x-init on a wire:ignore
            // element that Livewire's morph pass skips-but-revisits during a re-render.
            if (this.$el.dataset.chartMounted) return;
            this.$el.dataset.chartMounted = 'true';

            // Blade serializes an empty PHP array default as `[]`, not `{}`.
            const safeOptions = Array.isArray(options) ? {} : (options ?? {});
            this.chart = new Chart(this.$refs.canvas, { type, data, options: safeOptions });
        },

        destroy() {
            this.chart?.destroy();
        },
    };
}
