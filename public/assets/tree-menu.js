// Les menus utilisent des coordonnées d'écran. Ils restent distincts du dessin et de son zoom.
export class GraphMenu {
    constructor(panel, surface, onAction) {
        this.menu = panel.querySelector('[data-context-menu]');
        this.personGroup = this.menu.querySelector('[data-person-menu]');
        this.edgeGroup = this.menu.querySelector('[data-edge-menu]');
        this.onAction = onAction;

        surface.addEventListener('contextmenu', event => {
            const target = this.target(event.target);
            if (target) {
                event.preventDefault();
                this.open(target, event.clientX, event.clientY, event.pointerType === 'touch');
            }
        });
        surface.addEventListener('keydown', event => {
            if (event.key !== 'ContextMenu' && !(event.shiftKey && event.key === 'F10')) {
                return;
            }
            const target = this.target(event.target);
            if (target) {
                event.preventDefault();
                const bounds = target.getBoundingClientRect();
                this.open(target, bounds.left + bounds.width / 2, bounds.top + bounds.height / 2);
            }
        });

        this.menu.addEventListener('click', event => {
            const item = event.target.closest('[role="menuitem"]');
            if (item && this.anchor) {
                const target = this.anchor;
                this.close(true);
                if (item.dataset.menuAction) {
                    this.onAction(item, target);
                }
            }
        });
        this.menu.addEventListener('keydown', event => this.handleKey(event));
        document.addEventListener('pointerdown', event => {
            // Un nouveau geste peut sélectionner une commande, y compris juste après un appui prolongé.
            this.ignoreTouchRelease = false;
            if (!this.menu.contains(event.target)) {
                this.close();
            }
        });
        document.addEventListener('click', event => {
            if (this.ignoreTouchRelease && event.detail !== 0) {
                // Le clic généré au relâchement peut viser le menu apparu sous le doigt.
                this.ignoreTouchRelease = false;
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
        window.addEventListener('resize', () => this.close(true));
        window.addEventListener('scroll', event => {
            // Sur mobile le menu peut lui-même défiler. Seul le déplacement de son ancrage le ferme.
            if (!(event.target instanceof Node && this.menu.contains(event.target))) {
                this.close(true);
            }
        }, true);
    }

    target(element) {
        return element.closest('[data-person]') ?? (this.edgeGroup ? element.closest('[data-edge]') : null);
    }

    open(target, x, y, fromTouch = false) {
        if (fromTouch) {
            this.ignoreTouchRelease = true;
        }
        this.anchor = target;
        const id = target.dataset.person;
        this.personGroup.hidden = !id;
        if (this.edgeGroup) {
            this.edgeGroup.hidden = Boolean(id);
        }
        if (id) {
            for (const link of this.personGroup.querySelectorAll('[data-person-link]')) {
                const url = new URL(link.href);
                url.searchParams.set('id', id);
                link.href = url;
            }
        }
        this.menu.querySelector('[data-context-title]').textContent = target.dataset.menuTitle;
        this.menu.hidden = false;
        this.menu.scrollTop = 0;
        const gutter = 8;
        this.menu.style.left = Math.max(gutter, Math.min(x, window.innerWidth - this.menu.offsetWidth - gutter)) + 'px';
        this.menu.style.top = Math.max(gutter, Math.min(y, window.innerHeight - this.menu.offsetHeight - gutter)) + 'px';
        this.items()[0].focus({preventScroll: true});
    }

    items() {
        const group = this.personGroup.hidden ? this.edgeGroup : this.personGroup;
        return [...group.querySelectorAll('[role="menuitem"]')];
    }

    close(restoreFocus = false) {
        const target = this.anchor;
        if (target) {
            this.anchor = null;
            this.menu.hidden = true;
            if (restoreFocus) {
                target.focus({preventScroll: true});
            }
        }
    }

    handleKey(event) {
        const items = this.items();
        const index = items.indexOf(event.target);
        switch (event.key) {
            case 'ArrowDown':
            case 'ArrowUp': {
                event.preventDefault();
                const step = event.key === 'ArrowDown' ? 1 : -1;
                items[(index + step + items.length) % items.length].focus();
                break;
            }
            case 'Home':
            case 'End':
                event.preventDefault();
                items[event.key === 'Home' ? 0 : items.length - 1].focus();
                break;
            case 'Escape':
                event.preventDefault();
                event.stopPropagation();
                this.close(true);
                break;
            case 'Tab':
                this.close(true);
                break;
            case ' ':
                if (event.target.matches('a')) {
                    event.preventDefault();
                    event.target.click();
                }
                break;
        }
    }
}
