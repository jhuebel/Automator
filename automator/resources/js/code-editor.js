import { EditorState } from '@codemirror/state';
import { EditorView, keymap, lineNumbers, highlightActiveLine } from '@codemirror/view';
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';
import { syntaxHighlighting, defaultHighlightStyle, indentUnit } from '@codemirror/language';
import { oneDark } from '@codemirror/theme-one-dark';
import { python } from '@codemirror/lang-python';
import { yaml } from '@codemirror/lang-yaml';
import { StreamLanguage } from '@codemirror/language';
import { shell } from '@codemirror/legacy-modes/mode/shell';
import { powerShell } from '@codemirror/legacy-modes/mode/powershell';
import { toml } from '@codemirror/legacy-modes/mode/toml';

const fillHeightTheme = EditorView.theme({
    '&': { height: '100%' },
    '.cm-scroller': { overflow: 'auto' },
});

const LANGUAGE_EXTENSIONS = {
    shell: () => StreamLanguage.define(shell),
    powershell: () => StreamLanguage.define(powerShell),
    python: () => python(),
    yaml: () => yaml(),
    hcl: () => StreamLanguage.define(toml),
};

function languageExtension(mode) {
    const factory = LANGUAGE_EXTENSIONS[mode];
    return factory ? [factory()] : [];
}

/**
 * Alpine component wrapping a CodeMirror 6 editor. The Livewire component owns the
 * authoritative value; edits are deferred into `$wire.content` (not live) to avoid a
 * network round-trip per keystroke.
 */
export default function codeEditor({ value, language, readOnly = false }) {
    return {
        view: null,
        readOnly,

        init() {
            // Guards against double-mount: Alpine can re-run x-init on a wire:ignore
            // element that Livewire's morph pass skips-but-revisits during a re-render.
            if (this.$el.dataset.cmMounted) return;
            this.$el.dataset.cmMounted = 'true';

            this.view = new EditorView({
                parent: this.$el,
                state: this.buildState(value, language, readOnly),
            });

            // Exposes this instance (keyed by element id) so unrelated components,
            // like the AI assistant panel, can stream tokens into the editor without
            // Alpine cross-component ref plumbing.
            if (this.$el.id) {
                window.__codeEditors = window.__codeEditors || {};
                window.__codeEditors[this.$el.id] = this;
            }
        },

        buildState(doc, mode, readOnly) {
            return EditorState.create({
                doc,
                extensions: [
                    lineNumbers(),
                    history(),
                    highlightActiveLine(),
                    syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
                    keymap.of([...defaultKeymap, ...historyKeymap]),
                    indentUnit.of('    '),
                    fillHeightTheme,
                    oneDark,
                    EditorView.editable.of(!readOnly),
                    ...languageExtension(mode),
                    EditorView.updateListener.of((update) => {
                        if (update.docChanged && !readOnly) {
                            this.$wire.set('content', update.state.doc.toString(), false);
                            this.$el.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }),
                ],
            });
        },

        getValue() {
            return this.view.state.doc.toString();
        },

        setValue(newValue) {
            if (newValue === this.getValue()) return;

            this.view.dispatch({
                changes: { from: 0, to: this.view.state.doc.length, insert: newValue },
            });
        },

        setLanguage(mode) {
            this.view.setState(this.buildState(this.getValue(), mode, this.readOnly));
        },

        appendText(text) {
            const end = this.view.state.doc.length;
            this.view.dispatch({
                changes: { from: end, to: end, insert: text },
                selection: { anchor: end + text.length },
                scrollIntoView: true,
            });
            this.$wire.set('content', this.getValue(), false);
        },

        destroy() {
            if (this.$el.id && window.__codeEditors) {
                delete window.__codeEditors[this.$el.id];
            }
            this.view?.destroy();
        },
    };
}
