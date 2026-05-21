window.codeEditor = (function () {
    const _editors = {};
    const _observers = {};

    const _modeMap = {
        'Bash':            'shell',
        'PowerShell':      'powershell',
        'Python':          'python',
        'AnsiblePlaybook': 'yaml'
    };

    function _mode(language) {
        return _modeMap[language] || 'shell';
    }

    function init(id, language, value, readOnly, dotnetRef) {
        const el = document.getElementById(id);
        if (!el) return;

        if (_editors[id]) {
            _editors[id].toTextArea();
            delete _editors[id];
        }
        if (_observers[id]) {
            _observers[id].disconnect();
            delete _observers[id];
        }

        const textarea = document.createElement('textarea');
        el.appendChild(textarea);

        const cm = CodeMirror.fromTextArea(textarea, {
            mode:           _mode(language),
            theme:          'material-darker',
            lineNumbers:    true,
            readOnly:       readOnly,
            lineWrapping:   false,
            tabSize:        4,
            indentWithTabs: false,
            autofocus:      !readOnly
        });

        cm.setValue(value || '');
        cm.setSize('100%', '100%');

        // Refresh whenever the container becomes visible (e.g. tab switch from hidden state)
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => { if (entry.isIntersecting) cm.refresh(); });
        });
        observer.observe(el);
        _observers[id] = observer;

        if (!readOnly && dotnetRef) {
            cm.on('change', function () {
                dotnetRef.invokeMethodAsync('OnChange', cm.getValue());
            });
        }

        _editors[id] = cm;
    }

    function getValue(id) {
        return _editors[id] ? _editors[id].getValue() : '';
    }

    function setValue(id, value) {
        const cm = _editors[id];
        if (!cm) return;
        if (cm.getValue() === value) return;
        const cursor = cm.getCursor();
        cm.setValue(value);
        try { cm.setCursor(cursor); } catch (_) {}
    }

    function setLanguage(id, language) {
        if (_editors[id]) _editors[id].setOption('mode', _mode(language));
    }

    function refresh(id) {
        if (_editors[id]) _editors[id].refresh();
    }

    function destroy(id) {
        if (_editors[id]) {
            _editors[id].toTextArea();
            delete _editors[id];
        }
        if (_observers[id]) {
            _observers[id].disconnect();
            delete _observers[id];
        }
        const el = document.getElementById(id);
        if (el) el.innerHTML = '';
    }

    return { init, getValue, setValue, setLanguage, refresh, destroy };
})();
