(function () {
    'use strict';

    var contextHistory = [];
    var MAX_HISTORY_ITEMS = 10;

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-lead-assistant-form]');
        if (!form) {
            return;
        }

        event.preventDefault();

        var root = form.closest('[data-lead-assistant]');
        var input = form.querySelector('input[type="text"]');
        var button = form.querySelector('button[type="submit"]');
        var messages = root ? root.querySelector('.lead-assistant__messages') : null;
        var value = input ? input.value.trim() : '';
        var config = window.LeadAssistantConfig || {};

        if (!root || !input || !button || !messages || value === '' || !config.ready || !config.endpoint) {
            return;
        }

        appendMessage(messages, value, 'user');
        input.value = '';
        setBusy(messages, input, button, true);

        fetch(config.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                message: value,
                history: contextHistory
            })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data && data.message ? data.message : 'request_failed');
                    }
                    return data;
                });
            })
            .then(function (data) {
                var reply = typeof data.reply === 'string' ? data.reply.trim() : '';
                var question = typeof data.follow_up_question === 'string' ? data.follow_up_question.trim() : '';
                var scope = typeof data.scope === 'string' ? data.scope : '';
                var visibleReply = reply;

                if (question !== '') {
                    visibleReply += (visibleReply !== '' ? '\n\n' : '') + question;
                }

                if (visibleReply === '') {
                    throw new Error('empty_reply');
                }

                appendMessage(messages, visibleReply, 'assistant');

                // Off-topic turns remain visible to the visitor but are deliberately
                // excluded from future model context so the conversation keeps its focus.
                if (scope !== 'OFF_TOPIC') {
                    pushContext('user', value);
                    pushContext('assistant', visibleReply);
                }
            })
            .catch(function () {
                var strings = config.strings || {};
                appendMessage(
                    messages,
                    typeof strings.error === 'string' ? strings.error : 'I could not respond right now. Please try again.',
                    'assistant'
                );
            })
            .finally(function () {
                setBusy(messages, input, button, false);
                input.focus();
            });
    });

    function pushContext(role, content) {
        contextHistory.push({
            role: role,
            content: content
        });

        if (contextHistory.length > MAX_HISTORY_ITEMS) {
            contextHistory = contextHistory.slice(-MAX_HISTORY_ITEMS);
        }
    }

    function setBusy(messages, input, button, busy) {
        messages.setAttribute('aria-busy', busy ? 'true' : 'false');
        input.disabled = busy;
        button.disabled = busy;
    }

    function appendMessage(container, text, role) {
        var message = document.createElement('div');
        message.className = 'lead-assistant__message lead-assistant__message--' + role;
        message.textContent = text;
        container.appendChild(message);
        container.scrollTop = container.scrollHeight;
    }
}());
