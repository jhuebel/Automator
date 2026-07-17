/**
 * Minimal ANSI SGR (Select Graphic Rendition) → HTML renderer for script
 * output. Not a full terminal emulator — cursor movement, clear-line, and
 * other non-color escape sequences are recognized and dropped rather than
 * acted on. Tools commonly used from scripts emit SGR color/style codes even
 * when their output is piped rather than attached to a real TTY (e.g.
 * PowerShell's Select-String match highlighting, grep --color, ansible,
 * many CLI tools with "always" color modes), so those codes are real
 * formatting information worth rendering, not noise to strip.
 */

const FG_COLORS = {
    30: '#4b5563', 31: '#f87171', 32: '#4ade80', 33: '#facc15',
    34: '#60a5fa', 35: '#c084fc', 36: '#22d3ee', 37: '#e5e7eb',
    90: '#6b7280', 91: '#fca5a5', 92: '#86efac', 93: '#fde047',
    94: '#93c5fd', 95: '#d8b4fe', 96: '#67e8f9', 97: '#f9fafb',
};

const BG_COLORS = {
    40: '#4b5563', 41: '#f87171', 42: '#4ade80', 43: '#facc15',
    44: '#60a5fa', 45: '#c084fc', 46: '#22d3ee', 47: '#e5e7eb',
    100: '#6b7280', 101: '#fca5a5', 102: '#86efac', 103: '#fde047',
    104: '#93c5fd', 105: '#d8b4fe', 106: '#67e8f9', 107: '#f9fafb',
};

// Matches the terminal's own bg-gray-900 / text-gray-100 so reverse video
// (SGR 7) with no explicit color set swaps to sane values.
const DEFAULT_FG = '#f3f4f6';
const DEFAULT_BG = '#111827';

// eslint-disable-next-line no-control-regex
const ANSI_PATTERN = /\x1b\[([0-9;?]*)([a-zA-Z])/g;

function escapeHtml(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function freshState() {
    return { fg: null, bg: null, bold: false, dim: false, italic: false, underline: false, reverse: false, blink: false };
}

function applyCode(state, code) {
    if (code === 0) Object.assign(state, freshState());
    else if (code === 1) state.bold = true;
    else if (code === 2) state.dim = true;
    else if (code === 3) state.italic = true;
    else if (code === 4) state.underline = true;
    else if (code === 5 || code === 6) state.blink = true; // slow / rapid blink — rendered identically
    else if (code === 7) state.reverse = true;
    else if (code === 22) { state.bold = false; state.dim = false; }
    else if (code === 23) state.italic = false;
    else if (code === 24) state.underline = false;
    else if (code === 25) state.blink = false;
    else if (code === 27) state.reverse = false;
    else if (code === 39) state.fg = null;
    else if (code === 49) state.bg = null;
    else if (FG_COLORS[code]) state.fg = FG_COLORS[code];
    else if (BG_COLORS[code]) state.bg = BG_COLORS[code];
}

function styleFor(state) {
    const styles = [];
    const fg = state.fg ?? DEFAULT_FG;
    const bg = state.bg ?? DEFAULT_BG;

    if (state.reverse) {
        styles.push(`color:${bg}`, `background-color:${fg}`);
    } else {
        if (state.fg) styles.push(`color:${fg}`);
        if (state.bg) styles.push(`background-color:${bg}`);
    }
    if (state.bold) styles.push('font-weight:600');
    if (state.dim) styles.push('opacity:0.65');
    if (state.italic) styles.push('font-style:italic');
    if (state.underline) styles.push('text-decoration:underline');

    return styles.join(';');
}

function wrapIfStyled(escapedText, state) {
    const style = styleFor(state);
    // Blink needs a CSS animation (see resources/css/app.css's .ansi-blink /
    // @keyframes ansi-blink), which can't be expressed as an inline style —
    // it's a class, not a property.
    const className = state.blink ? 'ansi-blink' : '';

    if (!style && !className) return escapedText;

    const attrs = [
        className && `class="${className}"`,
        style && `style="${style}"`,
    ].filter(Boolean).join(' ');

    return `<span ${attrs}>${escapedText}</span>`;
}

export function ansiToHtml(text) {
    if (!text) return '';

    const state = freshState();
    let html = '';
    let lastIndex = 0;
    let match;

    ANSI_PATTERN.lastIndex = 0;
    while ((match = ANSI_PATTERN.exec(text)) !== null) {
        const plain = text.slice(lastIndex, match.index);
        if (plain) html += wrapIfStyled(escapeHtml(plain), state);

        const [, params, final] = match;
        if (final === 'm') {
            const codes = params.length ? params.split(';').map(Number) : [0];
            codes.forEach((code) => applyCode(state, code));
        }
        // Non-SGR sequences (cursor moves, clear-line, etc.) are dropped.

        lastIndex = ANSI_PATTERN.lastIndex;
    }

    const rest = text.slice(lastIndex);
    if (rest) html += wrapIfStyled(escapeHtml(rest), state);

    return html;
}
