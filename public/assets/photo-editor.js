'use strict';

const photoEditor = document.querySelector('[data-photo-editor]');

if (photoEditor) {
    const source = photoEditor.querySelector('[data-photo-source]');
    const frameOverlay = photoEditor.querySelector('[data-photo-frame]');
    const circleOverlay = photoEditor.querySelector('[data-portrait-frame]');
    const preview = photoEditor.querySelector('[data-portrait-preview]');
    const adjusted = photoEditor.querySelector('[data-portrait-adjusted]');
    const dimensions = photoEditor.querySelector('[data-photo-dimensions]');
    const tools = photoEditor.querySelector('[data-photo-tools]');
    const buttons = [...tools.querySelectorAll('button')];
    const panels = [...photoEditor.querySelectorAll('[data-photo-panel]')];
    const fields = photoEditor.elements;
    const {x: circleX, y: circleY, size: circleSize, use_portrait: usePortrait, mode} = fields;
    const frameControls = {x: fields.frame_x, y: fields.frame_y, width: fields.frame_width, height: fields.frame_height};
    const width = Number(source.getAttribute('width'));
    const height = Number(source.getAttribute('height'));
    const portraitMinimum = Number(photoEditor.dataset.portraitMinimum);
    // Les sélections restent dans les pixels du WebP. Les pourcentages servent seulement au rendu.
    const frame = Object.fromEntries(Object.entries(frameControls).map(([key, control]) => [key, control.valueAsNumber]));
    const circle = {x: circleX.valueAsNumber, y: circleY.valueAsNumber, size: circleSize.valueAsNumber};
    const clamp = (value, min, max) => Math.min(max, Math.max(min, Math.round(value)));
    const percent = (value, total) => 100 * value / total + '%';

    function drawOverlay(element, box) {
        Object.assign(element.style, {
            left: percent(box.x, width), top: percent(box.y, height),
            width: percent(box.width, width), height: percent(box.height, height),
        });
    }

    function drawCircle() {
        // Le médaillon reste à l'intérieur du futur fichier, y compris si son cadre est plus petit que le minimum habituel.
        const limit = Math.min(frame.width, frame.height);
        const minimum = Math.min(portraitMinimum, limit);
        circle.size = clamp(circle.size, minimum, limit);
        const maxX = frame.x + frame.width - circle.size;
        const maxY = frame.y + frame.height - circle.size;
        circle.x = clamp(circle.x, frame.x, maxX);
        circle.y = clamp(circle.y, frame.y, maxY);
        circleSize.min = minimum;
        circleSize.max = limit;
        circleSize.value = circle.size;
        circleX.min = frame.x;
        circleX.max = maxX;
        circleX.value = circle.x;
        circleY.min = frame.y;
        circleY.max = maxY;
        circleY.value = circle.y;

        drawOverlay(circleOverlay, {...circle, width: circle.size, height: circle.size});
        Object.assign(preview.style, {
            left: percent(-circle.x, circle.size), top: percent(-circle.y, circle.size),
            width: percent(width, circle.size), height: percent(height, circle.size),
        });
    }

    function drawFrame() {
        frame.width = clamp(frame.width, 1, width);
        frame.height = clamp(frame.height, 1, height);
        frame.x = clamp(frame.x, 0, width - frame.width);
        frame.y = clamp(frame.y, 0, height - frame.height);
        frameControls.x.max = width - frame.width;
        frameControls.y.max = height - frame.height;
        for (const [key, control] of Object.entries(frameControls)) control.value = frame[key];
        dimensions.value = frame.width + ' × ' + frame.height + ' px';
        drawOverlay(frameOverlay, frame);

        const previous = {...circle};
        drawCircle();
        if (usePortrait.checked && (circle.x !== previous.x || circle.y !== previous.y || circle.size !== previous.size)) {
            adjusted.hidden = false;
        }
    }

    function selectMode(value) {
        mode.value = value;
        frameOverlay.classList.toggle('is-guide', value === 'portrait');
        circleOverlay.hidden = value !== 'portrait';
        for (const button of buttons) button.setAttribute('aria-pressed', String(button.dataset.photoMode === value));
        for (const panel of panels) panel.hidden = panel.dataset.photoPanel !== value;
        if (value === 'portrait') adjusted.hidden = true;
    }

    function resizeCircle(size, centerX, centerY) {
        circle.size = clamp(size, Number(circleSize.min), Number(circleSize.max));
        circle.x = centerX - circle.size / 2;
        circle.y = centerY - circle.size / 2;
        usePortrait.checked = true;
        drawCircle();
    }

    for (const [key, control] of Object.entries(frameControls)) {
        control.addEventListener('input', () => {
            frame[key] = control.valueAsNumber;
            drawFrame();
        });
    }
    circleSize.addEventListener('input', () => {
        resizeCircle(circleSize.valueAsNumber, circle.x + circle.size / 2, circle.y + circle.size / 2);
    });
    for (const [key, control] of Object.entries({x: circleX, y: circleY})) {
        control.addEventListener('input', () => {
            circle[key] = control.valueAsNumber;
            usePortrait.checked = true;
            drawCircle();
        });
    }

    function bindDrag(overlay, shape, move) {
        let gesture;
        overlay.addEventListener('pointerdown', event => {
            if (event.button !== 0 || !event.isPrimary) return;
            // La capture continue le geste lorsque le pointeur quitte la sélection.
            event.preventDefault();
            overlay.setPointerCapture(event.pointerId);
            const bounds = source.getBoundingClientRect();
            gesture = {
                pointerId: event.pointerId, x: event.clientX, y: event.clientY, start: {...shape},
                scaleX: width / bounds.width, scaleY: height / bounds.height,
                handle: event.target.closest('[data-photo-resize]')?.dataset.photoResize,
            };
        });
        overlay.addEventListener('pointermove', event => {
            if (event.pointerId !== gesture?.pointerId) return;
            move(gesture.start, (event.clientX - gesture.x) * gesture.scaleX, (event.clientY - gesture.y) * gesture.scaleY, gesture.handle);
        });
        overlay.addEventListener('lostpointercapture', () => { gesture = null; });
    }

    bindDrag(frameOverlay, frame, (start, dx, dy, corner) => {
        if (corner) {
            // Chaque coin déplace deux côtés. Les côtés opposés restent ancrés.
            const right = start.x + start.width;
            const bottom = start.y + start.height;
            frame.x = corner.includes('w') ? clamp(start.x + dx, 0, right - 1) : start.x;
            frame.y = corner.includes('n') ? clamp(start.y + dy, 0, bottom - 1) : start.y;
            frame.width = (corner.includes('e') ? clamp(right + dx, frame.x + 1, width) : right) - frame.x;
            frame.height = (corner.includes('s') ? clamp(bottom + dy, frame.y + 1, height) : bottom) - frame.y;
        } else {
            frame.x = start.x + dx;
            frame.y = start.y + dy;
        }
        drawFrame();
    });
    bindDrag(circleOverlay, circle, (start, dx, dy, handle) => {
        if (handle) {
            resizeCircle(start.size + dx + dy, start.x + start.size / 2, start.y + start.size / 2);
        } else {
            circle.x = start.x + dx;
            circle.y = start.y + dy;
            usePortrait.checked = true;
            drawCircle();
        }
    });

    photoEditor.querySelector('[data-reset-frame]').addEventListener('click', () => {
        Object.assign(frame, {x: 0, y: 0, width, height});
        drawFrame();
    });
    for (const button of buttons) button.addEventListener('click', () => selectMode(button.dataset.photoMode));
    source.addEventListener('dragstart', event => event.preventDefault());
    drawFrame();
    selectMode(mode.value);
    frameOverlay.hidden = false;
    tools.hidden = false;
    photoEditor.querySelector('[data-photo-controls]').hidden = false;
    photoEditor.querySelector('[data-photo-workspace]').classList.add('is-ready');
}
