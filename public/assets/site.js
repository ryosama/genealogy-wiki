'use strict';

document.querySelectorAll('input[name="last_name"]').forEach(field => {
    function capitalize() {
        const original = field.value;
        const uppercase = original.toUpperCase();
        if (original === uppercase) return;
        // Garder le curseur au même endroit, même lorsqu'une lettre devient plusieurs caractères.
        const start = original.slice(0, field.selectionStart).toUpperCase().length;
        const end = original.slice(0, field.selectionEnd).toUpperCase().length;
        const direction = field.selectionDirection;
        field.value = uppercase;
        field.setSelectionRange(start, end, direction);
    }
    field.addEventListener('input', event => {
        if (!event.isComposing) capitalize();
    });
    field.addEventListener('compositionend', capitalize);
});

// Les fiches utilisent l'impression native. L'arbre prépare son propre SVG dans tree.js.
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
    // La zone visuelle n'est pas un champ de formulaire. Le textarea sert aussi de repli sans JavaScript.
    const box = editor.querySelector('[contenteditable]');
    const source = editor.querySelector('textarea');
    const toolbar = editor.querySelector('.editor-toolbar');
    const sync = () => { source.value = box.innerHTML; };
    source.hidden = true;
    toolbar.hidden = false;
    box.hidden = false;
    box.addEventListener('input', sync);
    box.addEventListener('paste', event => {
        // Garder le texte collé. PHP nettoie ensuite le HTML envoyé par l'éditeur.
        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
        sync();
    });
    toolbar.querySelectorAll('button').forEach(button => {
        // Éviter que le clic dans la barre d'outils fasse perdre la sélection à mettre en forme.
        button.addEventListener('mousedown', event => event.preventDefault());
        button.addEventListener('click', () => {
            box.focus();
            document.execCommand(button.dataset.command, false, button.dataset.value ?? null);
            sync();
        });
    });
    source.form.addEventListener('submit', sync);
});
