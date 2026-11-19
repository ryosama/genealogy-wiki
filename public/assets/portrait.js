'use strict';

const editor = document.querySelector('[data-portrait-editor]');

if (editor) {
    const photo = editor.querySelector('[data-crop-photo]');
    const selection = editor.querySelector('[data-crop-selection]');
    const preview = editor.querySelector('[data-crop-preview]');
    const {x: horizontal, y: vertical, size: sizeControl} = editor.elements;
    // Toutes les coordonnées sont exprimées dans la photo d'origine. Seul le rendu utilise des pourcentages.
    const width = Number(photo.getAttribute('width'));
    const height = Number(photo.getAttribute('height'));
    const crop = {x: horizontal.valueAsNumber, y: vertical.valueAsNumber, size: sizeControl.valueAsNumber};
    const clamp = (value, min, max) => Math.min(max, Math.max(min, Math.round(value)));
    const percent = (value, total) => 100 * value / total + '%';

    function drawCrop() {
        crop.x = clamp(crop.x, 0, width - crop.size);
        crop.y = clamp(crop.y, 0, height - crop.size);
        sizeControl.value = crop.size;
        horizontal.max = width - crop.size;
        horizontal.value = crop.x;
        vertical.max = height - crop.size;
        vertical.value = crop.y;

        Object.assign(selection.style, {
            left: percent(crop.x, width), top: percent(crop.y, height),
            width: percent(crop.size, width), height: percent(crop.size, height),
        });
        // Dans l'aperçu, le carré retenu prend toute la place. Décaler la grande image derrière le masque.
        Object.assign(preview.style, {
            left: percent(-crop.x, crop.size), top: percent(-crop.y, crop.size),
            width: percent(width, crop.size), height: percent(height, crop.size),
        });
    }

    function resizeCrop(size, centerX, centerY) {
        crop.size = clamp(size, Number(sizeControl.min), Number(sizeControl.max));
        crop.x = centerX - crop.size / 2;
        crop.y = centerY - crop.size / 2;
        drawCrop();
    }

    sizeControl.addEventListener('input', () => {
        resizeCrop(sizeControl.valueAsNumber, crop.x + crop.size / 2, crop.y + crop.size / 2);
    });
    horizontal.addEventListener('input', () => {
        crop.x = horizontal.valueAsNumber;
        drawCrop();
    });
    vertical.addEventListener('input', () => {
        crop.y = vertical.valueAsNumber;
        drawCrop();
    });

    let gesture;
    selection.addEventListener('pointerdown', event => {
        if (event.button !== 0 || !event.isPrimary) {
            return;
        }
        // Garder le geste même lorsque le pointeur dépasse le cercle ou le bord de la photo.
        event.preventDefault();
        selection.setPointerCapture(event.pointerId);
        gesture = {
            id: event.pointerId, x: event.clientX, y: event.clientY, start: {...crop},
            scale: width / photo.getBoundingClientRect().width,
            resize: Boolean(event.target.closest('[data-crop-resize]')),
        };
    });
    selection.addEventListener('pointermove', event => {
        if (event.pointerId !== gesture?.id) {
            return;
        }
        const {x, y, start, scale, resize} = gesture;
        const dx = (event.clientX - x) * scale;
        const dy = (event.clientY - y) * scale;
        if (resize) {
            // La poignée inférieure droite agrandit le cercle autour du cadrage de départ.
            resizeCrop(start.size + dx + dy, start.x + start.size / 2, start.y + start.size / 2);
        } else {
            crop.x = start.x + dx;
            crop.y = start.y + dy;
            drawCrop();
        }
    });
    selection.addEventListener('lostpointercapture', () => { gesture = null; });
    photo.addEventListener('dragstart', event => event.preventDefault());

    drawCrop();
    selection.hidden = false;
    editor.querySelector('[data-crop-instructions]').hidden = false;
    editor.querySelector('[data-crop-controls]').hidden = false;
}
