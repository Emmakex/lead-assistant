(function () {
    'use strict';

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-lead-assistant-form]');
        if (!form) {
            return;
        }

        event.preventDefault();

        var root = form.closest('[data-lead-assistant]');
        var input = form.querySelector('input[type="text"]');
        var messages = root ? root.querySelector('.lead-assistant__messages') : null;
        var value = input ? input.value.trim() : '';

        if (!root || !input || !messages || value === '') {
            return;
        }

        appendMessage(messages, value, 'user');
        input.value = '';

        appendMessage(
            messages,
            'Foundation build ready. The AI connection and governed conversation runtime are added in the next development cut.',
            'assistant'
        );
    });

    function appendMessage(container, text, role) {
        var message = document.createElement('div');
        message.className = 'lead-assistant__message lead-assistant__message--' + role;
        message.textContent = text;
        container.appendChild(message);
        container.scrollTop = container.scrollHeight;
    }
}());
