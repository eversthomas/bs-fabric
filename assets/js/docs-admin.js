(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.bs-fabric-docs-copy').forEach(function (button) {
            button.addEventListener('click', function () {
                var example = button.closest('.bs-fabric-docs-example');

                if (!example) {
                    return;
                }

                var code = example.querySelector('code');

                if (!code) {
                    return;
                }

                var text = code.textContent || '';

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () {
                        showCopied(button);
                    }).catch(function () {
                        fallbackCopy(text, button);
                    });

                    return;
                }

                fallbackCopy(text, button);
            });
        });
    });

    function fallbackCopy(text, button) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            showCopied(button);
        } catch (error) {
            window.alert('Kopieren fehlgeschlagen.');
        }

        document.body.removeChild(textarea);
    }

    function showCopied(button) {
        var original = button.textContent;
        button.textContent = 'Kopiert!';
        button.disabled = true;

        window.setTimeout(function () {
            button.textContent = original;
            button.disabled = false;
        }, 1500);
    }
}());
