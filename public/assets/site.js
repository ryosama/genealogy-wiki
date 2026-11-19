'use strict';

document.querySelectorAll('[data-print]').forEach(button => {
    button.addEventListener('click', () => window.print());
});

document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('[data-rich-editor]').forEach(editor => {
    const box = editor.querySelector('[contenteditable]');
    const source = editor.querySelector('textarea');
    const toolbar = editor.querySelector('.editor-toolbar');
    const sync = () => { source.value = box.innerHTML; };
    source.hidden = true;
    toolbar.hidden = false;
    box.hidden = false;
    box.addEventListener('input', sync);
    box.addEventListener('paste', event => {
        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
        sync();
    });
    toolbar.querySelectorAll('button').forEach(button => {
        button.addEventListener('mousedown', event => event.preventDefault());
        button.addEventListener('click', () => {
            box.focus();
            document.execCommand(button.dataset.command, false, button.dataset.value ?? null);
            sync();
        });
    });
    source.form.addEventListener('submit', sync);
});
