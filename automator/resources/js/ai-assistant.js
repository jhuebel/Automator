/**
 * Alpine component driving the Script Editor's AI panel. Livewire's runAi() kicks
 * off a queued StreamClaudeCompletionJob and returns a request id; token deltas
 * stream back over a private Reverb channel and are appended directly into the
 * CodeMirror instance (Generate/Improve) or a local explanation panel (Explain),
 * bypassing Livewire re-renders on the hot per-token path.
 */
export default function aiAssistant(editorId) {
    return {
        mode: 'generate',
        prompt: '',
        explanation: '',
        generating: false,
        error: null,
        channelName: null,

        async run() {
            this.error = null;
            this.generating = true;

            if (this.mode === 'explain') {
                this.explanation = '';
            } else {
                window.__codeEditors?.[editorId]?.setValue('');
            }

            let requestId;
            try {
                requestId = await this.$wire.call('runAi', this.mode, this.prompt);
            } catch (e) {
                this.generating = false;
                this.error = 'Failed to start AI request.';

                return;
            }

            this.subscribe(requestId);
        },

        subscribe(requestId) {
            this.unsubscribe();
            this.channelName = `claude.completion.${requestId}`;

            window.Echo.private(this.channelName)
                .listen('.token', (e) => {
                    if (this.mode === 'explain') {
                        this.explanation += e.text;
                    } else {
                        window.__codeEditors?.[editorId]?.appendText(e.text);
                    }
                })
                .listen('.finished', () => {
                    this.generating = false;
                    this.unsubscribe();
                })
                .listen('.failed', (e) => {
                    this.generating = false;
                    this.error = e.message;
                    this.unsubscribe();
                });
        },

        unsubscribe() {
            if (this.channelName) {
                window.Echo.leave(this.channelName);
                this.channelName = null;
            }
        },

        destroy() {
            this.unsubscribe();
        },
    };
}
