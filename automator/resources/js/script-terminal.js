import { ansiToHtml } from './ansi';

/**
 * Alpine component that subscribes to a private Reverb channel for a running
 * script execution and renders output lines directly in the DOM, bypassing
 * Livewire re-renders on the hot (per-line) path.
 */
export default function scriptTerminal() {
    return {
        channelName: null,
        lines: [],

        init() {
            this.$watch('lines', () => {
                this.$nextTick(() => {
                    this.$refs.output.scrollTop = this.$refs.output.scrollHeight;
                });
            });
        },

        subscribe(executionId) {
            this.unsubscribe();
            this.lines = [];
            this.channelName = `execution.${executionId}`;

            window.Echo.private(this.channelName)
                .listen('.output-line', (e) => {
                    this.lines.push({ ...e, html: ansiToHtml(e.text) });
                })
                .listen('.finished', (e) => {
                    this.$wire.call('markFinished', e.exitCode);
                    this.unsubscribe();
                });
        },

        unsubscribe() {
            if (this.channelName) {
                window.Echo.leave(this.channelName);
                this.channelName = null;
            }
        },

        // Called when the selected script changes. Unsubscribing (not just
        // clearing `lines`) matters if the previous script was still
        // running — otherwise its output-line events would keep arriving
        // and silently repopulate the terminal for the newly-selected
        // script, since the underlying execution isn't cancelled by merely
        // switching selection.
        clear() {
            this.unsubscribe();
            this.lines = [];
        },

        destroy() {
            this.unsubscribe();
        },
    };
}
