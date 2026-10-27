(function () {
    'use strict';

    var SESSION_KEY = 'lead_assistant_session_token_v1';

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
                session_token: getSessionToken()
            })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        var error = new Error(data && data.message ? data.message : 'request_failed');
                        error.code = data && data.code ? data.code : '';
                        throw error;
                    }
                    return data;
                });
            })
            .then(function (data) {
                var reply = typeof data.reply === 'string' ? data.reply.trim() : '';
                var question = typeof data.follow_up_question === 'string' ? data.follow_up_question.trim() : '';
                var visibleReply = reply;

                if (typeof data.session_token === 'string' && /^[a-f0-9]{64}$/.test(data.session_token)) {
                    setSessionToken(data.session_token);
                }

                if (question !== '') {
                    visibleReply += (visibleReply !== '' ? '\n\n' : '') + question;
                }

                if (visibleReply === '') {
                    throw new Error('empty_reply');
                }

                appendMessage(messages, visibleReply, 'assistant');
            })
            .catch(function (error) {
                if (error && error.code === 'lead_assistant_invalid_session') {
                    clearSessionToken();
                }

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

    function getSessionToken() {
        try {
            return window.sessionStorage.getItem(SESSION_KEY) || '';
        } catch (error) {
            return '';
        }
    }

    function setSessionToken(token) {
        try {
            window.sessionStorage.setItem(SESSION_KEY, token);
        } catch (error) {
            // The conversation still works for the current request if storage is unavailable.
        }
    }

    function clearSessionToken() {
        try {
            window.sessionStorage.removeItem(SESSION_KEY);
        } catch (error) {
            // No action required.
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
