//

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
import codeEditor from './code-editor';
import scriptTerminal from './script-terminal';
import aiAssistant from './ai-assistant';
import chartWidget from './chart-widget';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('codeEditor', codeEditor);
    window.Alpine.data('scriptTerminal', scriptTerminal);
    window.Alpine.data('aiAssistant', aiAssistant);
    window.Alpine.data('chartWidget', chartWidget);
    window.Alpine.store('help', { open: false });
});
