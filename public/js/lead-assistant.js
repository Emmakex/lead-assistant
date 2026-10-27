(function () {
    'use strict';

    var SESSION_KEY = 'lead_assistant_session_token_v1';
    var memorySessionToken = '';

    document.addEventListener('submit', function (event) {
        var chatForm = event.target.closest('[data-lead-assistant-form]');
        if (chatForm) {
            handleMessageSubmit(event, chatForm);
            return;
        }

        var captureForm = event.target.closest('[data-lead-assistant-capture-form]');
        if (captureForm) {
            handleCaptureSubmit(event, captureForm);
        }
    });

    function handleMessageSubmit(event, form) {
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
        setChatBusy(messages, input, button, true);

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
            .then(readJsonResponse)
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

                if (data.lead_capture_required === true) {
                    showCapture(root);
                }
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
                setChatBusy(messages, input, button, false);
                input.focus();
            });
    }

    function handleCaptureSubmit(event, form) {
        event.preventDefault();

        var root = form.closest('[data-lead-assistant]');
        var capture = root ? root.querySelector('[data-lead-assistant-capture]') : null;
        var status = form.querySelector('[data-lead-assistant-capture-status]');
        var messages = root ? root.querySelector('.lead-assistant__messages') : null;
        var config = window.LeadAssistantConfig || {};
        var token = getSessionToken();
        var name = getFieldValue(form, 'name');
        var email = getFieldValue(form, 'email');
        var phone = getFieldValue(form, 'phone');
        var consent = form.querySelector('input[name="privacy_consent"]');

        if (!root || !capture || !status || !messages || !config.leadEndpoint || token === '') {
            setCaptureStatus(status, getString('captureError', 'We could not save your contact details.'));
            return;
        }

        if (!name || !email || !phone || !consent || !consent.checked) {
            if (typeof form.reportValidity === 'function') {
                form.reportValidity();
            }
            return;
        }

        setCaptureBusy(form, true);
        setCaptureStatus(status, '');

        fetch(config.leadEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                session_token: token,
                name: name,
                email: email,
                phone: phone,
                privacy_consent: true
            })
        })
            .then(readJsonResponse)
            .then(function (data) {
                if (!data || data.captured !== true) {
                    throw new Error('capture_failed');
                }

                capture.hidden = true;
                appendMessage(messages, getString('captureDone', 'Thank you. Your contact details have been saved.'), 'assistant');
            })
            .catch(function (error) {
                if (error && error.code === 'lead_assistant_invalid_session') {
                    clearSessionToken();
                }

                setCaptureStatus(status, getString('captureError', 'We could not save your contact details. Please try again.'));
            })
            .finally(function () {
                setCaptureBusy(form, false);
            });
    }

    function readJsonResponse(response) {
        return response.json().then(function (data) {
            if (!response.ok) {
                var error = new Error(data && data.message ? data.message : 'request_failed');
                error.code = data && data.code ? data.code : '';
                throw error;
            }
            return data;
        });
    }

    function showCapture(root) {
        var capture = root.querySelector('[data-lead-assistant-capture]');
        if (!capture) {
            return;
        }

        capture.hidden = false;
        var firstField = capture.querySelector('input[name="name"]');
        if (firstField) {
            firstField.focus();
        }
    }

    function getFieldValue(form, name) {
        var field = form.querySelector('[name="' + name + '"]');
        return field ? field.value.trim() : '';
    }

    function getString(key, fallback) {
        var config = window.LeadAssistantConfig || {};
        var strings = config.strings || {};
        return typeof strings[key] === 'string' ? strings[key] : fallback;
    }

    function setCaptureStatus(status, text) {
        if (status) {
            status.textContent = text;
        }
    }

    function setCaptureBusy(form, busy) {
        Array.prototype.forEach.call(form.querySelectorAll('input, button'), function (control) {
            control.disabled = busy;
        });
        form.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function getSessionToken() {
        try {
            return window.sessionStorage.getItem(SESSION_KEY) || memorySessionToken;
        } catch (error) {
            return memorySessionToken;
        }
    }

    function setSessionToken(token) {
        memorySessionToken = token;

        try {
            window.sessionStorage.setItem(SESSION_KEY, token);
        } catch (error) {
            // The in-memory fallback keeps this tab functional.
        }
    }

    function clearSessionToken() {
        memorySessionToken = '';

        try {
            window.sessionStorage.removeItem(SESSION_KEY);
        } catch (error) {
            // No action required.
        }
    }

    function setChatBusy(messages, input, button, busy) {
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
