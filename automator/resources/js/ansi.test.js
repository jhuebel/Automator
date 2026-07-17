import { describe, expect, it } from 'vitest';
import { ansiToHtml } from './ansi';

const ESC = String.fromCharCode(27);

/** Wraps text in an SGR sequence with the given semicolon-separated codes. */
function sgr(codes, text) {
    return `${ESC}[${codes}m${text}`;
}

describe('ansiToHtml', () => {
    it('returns an empty string for empty or missing input', () => {
        expect(ansiToHtml('')).toBe('');
        expect(ansiToHtml(null)).toBe('');
        expect(ansiToHtml(undefined)).toBe('');
    });

    it('passes plain text through untouched, with no wrapping span', () => {
        expect(ansiToHtml('hello world')).toBe('hello world');
    });

    it('HTML-escapes plain text so script output cannot inject markup', () => {
        expect(ansiToHtml('<script>alert(1)</script>')).toBe(
            '&lt;script&gt;alert(1)&lt;/script&gt;'
        );
        expect(ansiToHtml(`a & b "c" 'd'`)).toBe('a &amp; b &quot;c&quot; &#39;d&#39;');
    });

    it('HTML-escapes text inside a styled span too', () => {
        expect(ansiToHtml(sgr(31, '<b>x</b>') + `${ESC}[0m`)).toBe(
            '<span style="color:#f87171">&lt;b&gt;x&lt;/b&gt;</span>'
        );
    });

    it('renders the real PowerShell Select-String reverse-video case', () => {
        // The exact bytes captured from `Select-String` highlighting a match
        // (SGR 7 = reverse video, SGR 0 = reset) with no explicit color set —
        // this is the bug report this renderer was built to fix.
        const input = `${ESC}[7mMemTotal${ESC}[0m: 65634712 kB`;

        expect(ansiToHtml(input)).toBe(
            '<span style="color:#111827;background-color:#f3f4f6">MemTotal</span>: 65634712 kB'
        );
    });

    it('renders standard foreground colors', () => {
        expect(ansiToHtml(sgr(31, 'red') + `${ESC}[0m`)).toBe(
            '<span style="color:#f87171">red</span>'
        );
        expect(ansiToHtml(sgr(32, 'green') + `${ESC}[0m`)).toBe(
            '<span style="color:#4ade80">green</span>'
        );
        expect(ansiToHtml(sgr(36, 'cyan') + `${ESC}[0m`)).toBe(
            '<span style="color:#22d3ee">cyan</span>'
        );
    });

    it('renders bright foreground colors (90-97)', () => {
        expect(ansiToHtml(sgr(91, 'bright red') + `${ESC}[0m`)).toBe(
            '<span style="color:#fca5a5">bright red</span>'
        );
    });

    it('renders background colors', () => {
        expect(ansiToHtml(sgr(44, 'on blue') + `${ESC}[0m`)).toBe(
            '<span style="background-color:#60a5fa">on blue</span>'
        );
    });

    it('combines foreground and background colors set in one sequence', () => {
        expect(ansiToHtml(sgr('31;44', 'x') + `${ESC}[0m`)).toBe(
            '<span style="color:#f87171;background-color:#60a5fa">x</span>'
        );
    });

    it('renders bold, dim, italic, and underline', () => {
        expect(ansiToHtml(sgr(1, 'bold') + `${ESC}[0m`)).toBe(
            '<span style="font-weight:600">bold</span>'
        );
        expect(ansiToHtml(sgr(2, 'dim') + `${ESC}[0m`)).toBe(
            '<span style="opacity:0.65">dim</span>'
        );
        expect(ansiToHtml(sgr(3, 'italic') + `${ESC}[0m`)).toBe(
            '<span style="font-style:italic">italic</span>'
        );
        expect(ansiToHtml(sgr(4, 'underline') + `${ESC}[0m`)).toBe(
            '<span style="text-decoration:underline">underline</span>'
        );
    });

    it('combines multiple SGR codes given in one sequence (e.g. bold + color)', () => {
        expect(ansiToHtml(sgr('1;32', 'bold green') + `${ESC}[0m`)).toBe(
            '<span style="color:#4ade80;font-weight:600">bold green</span>'
        );
    });

    it('treats a bare "\\x1b[m" (no digits) as a reset, per the ANSI spec', () => {
        expect(ansiToHtml(sgr(31, 'red') + `${ESC}[m` + 'plain')).toBe(
            '<span style="color:#f87171">red</span>plain'
        );
    });

    it('turns off individual attributes without resetting everything', () => {
        // bold + underline, then cancel just bold (22) — underline should remain.
        const input = sgr('1;4', 'both') + `${ESC}[22m` + 'no-bold-still-underlined';

        expect(ansiToHtml(input)).toBe(
            '<span style="font-weight:600;text-decoration:underline">both</span>' +
                '<span style="text-decoration:underline">no-bold-still-underlined</span>'
        );
    });

    it('resets foreground (39) and background (49) independently', () => {
        const input = sgr('31;44', 'colored') + `${ESC}[39m` + 'no-fg' + `${ESC}[49m` + 'no-bg';

        expect(ansiToHtml(input)).toBe(
            '<span style="color:#f87171;background-color:#60a5fa">colored</span>' +
                '<span style="background-color:#60a5fa">no-fg</span>' +
                'no-bg'
        );
    });

    it('turns off reverse video with SGR 27', () => {
        const input = sgr(7, 'reversed') + `${ESC}[27m` + 'normal';

        expect(ansiToHtml(input)).toBe(
            '<span style="color:#111827;background-color:#f3f4f6">reversed</span>normal'
        );
    });

    it('applies reverse video on top of an explicit color by swapping fg/bg', () => {
        // Explicit red foreground, then reverse — red should become the
        // background, with the default terminal background as the text color.
        const input = sgr(31, '') + sgr(7, 'x');

        expect(ansiToHtml(input)).toBe('<span style="color:#111827;background-color:#f87171">x</span>');
    });

    it('handles multiple styled segments in sequence', () => {
        const input = sgr(31, 'red') + sgr(32, 'green') + `${ESC}[0m` + 'plain';

        expect(ansiToHtml(input)).toBe(
            '<span style="color:#f87171">red</span>' +
                '<span style="color:#4ade80">green</span>' +
                'plain'
        );
    });

    it('drops non-SGR escape sequences without corrupting surrounding text', () => {
        // \x1b[2K = erase line, \x1b[?25l = hide cursor — neither is a color
        // code; both should simply vanish, not appear as literal text.
        const input = `before${ESC}[2K${ESC}[?25lafter`;

        expect(ansiToHtml(input)).toBe('beforeafter');
    });

    it('preserves plain text and newlines around styled segments', () => {
        const input = `line one\n${sgr(33, 'warn')}${ESC}[0m\nline three`;

        expect(ansiToHtml(input)).toBe(
            'line one\n<span style="color:#facc15">warn</span>\nline three'
        );
    });

    it('renders slow blink (SGR 5) as the ansi-blink class', () => {
        expect(ansiToHtml(sgr(5, 'blinking') + `${ESC}[0m`)).toBe(
            '<span class="ansi-blink">blinking</span>'
        );
    });

    it('renders rapid blink (SGR 6) the same as slow blink', () => {
        expect(ansiToHtml(sgr(6, 'blinking') + `${ESC}[0m`)).toBe(
            '<span class="ansi-blink">blinking</span>'
        );
    });

    it('combines blink with a color, class before style', () => {
        expect(ansiToHtml(sgr('5;31', 'urgent') + `${ESC}[0m`)).toBe(
            '<span class="ansi-blink" style="color:#f87171">urgent</span>'
        );
    });

    it('turns off blink with SGR 25 without affecting other attributes', () => {
        const input = sgr('5;1', 'both') + `${ESC}[25m` + 'bold-only';

        expect(ansiToHtml(input)).toBe(
            '<span class="ansi-blink" style="font-weight:600">both</span>' +
                '<span style="font-weight:600">bold-only</span>'
        );
    });

    it('clears blink on a full reset (SGR 0)', () => {
        const input = sgr(5, 'blinking') + `${ESC}[0m` + 'plain';

        expect(ansiToHtml(input)).toBe('<span class="ansi-blink">blinking</span>plain');
    });
});
