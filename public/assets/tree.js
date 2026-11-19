import {CARD, FamilyGraph, isParent, linkShapes} from './tree-layout.js';
import {GraphMenu} from './tree-menu.js';

// Les éléments graphiques créés par JavaScript appartiennent à l'espace de noms SVG.
const SVG_NS = 'http://www.w3.org/2000/svg';
// Correspondance avec relations.kind pour le titre et le nom accessible des tracés.
const KINSHIP = {
    biological: 'Filiation biologique',
    adoptive: 'Filiation adoptive',
    marriage: 'Mariage',
    union: 'Union',
    sibling: 'Frère ou sœur',
};
// Les mêmes valeurs sont stockées dans SQLite et utilisées par les symboles Font Awesome.
const SEX_LABELS = {male: 'Homme', female: 'Femme'};
// Durée en millisecondes d'un appui tactile immobile avant l'ouverture du menu.
const LONG_PRESS_MS = 500;
const svg = (tag, attributes = {}) => {
    const element = document.createElementNS(SVG_NS, tag);
    Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, value));
    return element;
};
const svgText = (tag, value, attributes) => {
    const element = svg(tag, attributes);
    element.textContent = value;
    return element;
};
const personUrl = id => './?view=personne&id=' + id;
const treeUrl = id => './?view=arbre&id=' + id;

// Le dessin affiché et celui de l'impression coexistent. Leurs définitions SVG ont des ID distincts.
let graphSequence = 0;

function shorten(text, limit) {
    const chars = [...text];
    return chars.length <= limit ? text : chars.slice(0, limit - 1).join('') + '…';
}

function linkLabel(family, link) {
    const from = family.people.get(link.a_id).name;
    const to = family.people.get(link.b_id).name;
    return KINSHIP[link.kind] + ' · ' + from + (isParent(link) ? ' vers ' : ' / ') + to
        + (link.end_year ? ' · fin en ' + link.end_year : '');
}

function drawGraph(family, model, interactive, editable) {
    // Utiliser le même modèle pour l'écran et le papier. Sur papier, les cartes sont de simples groupes SVG.
    const result = svg('svg', {viewBox: '0 0 ' + model.width + ' ' + model.height,
        width: model.width, height: model.height, class: 'family-svg',
        role: interactive ? 'group' : 'img', 'aria-label': 'Arbre familial'});
    const definitions = svg('defs');
    const arrowId = 'filiation-arrow-' + (++graphSequence);
    const labelId = arrowId + '-label';
    const portraitClip = new Map();
    const arrow = svg('marker', {id: arrowId, viewBox: '0 0 9 9', refX: 8, refY: 4.5,
        markerUnits: 'userSpaceOnUse', markerWidth: 8, markerHeight: 8, orient: 'auto-start-reverse'});
    arrow.append(svg('path', {d: 'M1 1 L8 4.5 L1 8', class: 'edge-arrow'}));
    // Le texte visible reste dans la carte. Le nom complet est fourni au survol et au lecteur d'écran.
    const labelArea = svg('clipPath', {id: labelId});
    labelArea.append(svg('rect', {x: 46, y: 0, width: CARD.width - 52, height: CARD.height}));
    definitions.append(arrow, labelArea);
    // Le médaillon reprend l'emplacement des initiales. Avec un symbole de sexe, il remonte de 8 unités.
    for (const centerY of [23, 31]) {
        const id = arrowId + '-portrait-' + centerY;
        const clip = svg('clipPath', {id});
        clip.append(svg('circle', {cx: 25, cy: centerY, r: 14}));
        definitions.append(clip);
        portraitClip.set(centerY, 'url(#' + id + ')');
    }
    result.append(definitions);

    // Dessiner d'abord les traits pour que les cartes passent devant leurs extrémités.
    const edges = svg('g', {class: 'tree-edges'});
    for (const shape of linkShapes(model)) {
        const group = svg('g', {class: 'tree-edge edge-' + shape.kind + (shape.ended ? ' ended-edge' : ''),
            'data-edge': shape.key, 'data-links': shape.links.map(link => link.id).join(',')});
        const label = shape.links.length === 1 ? linkLabel(family, shape.links[0])
            : 'Filiations partagées · ' + shape.links.length + ' liens';
        group.dataset.menuTitle = label;
        group.append(svgText('title', label));
        const visible = svg('path', {d: shape.d, class: 'edge-visible'});
        if (shape.arrow) {
            visible.setAttribute('marker-end', 'url(#' + arrowId + ')');
        }
        group.append(visible);
        if (interactive) {
            // La copie transparente agrandit la cible du pointeur sans épaissir le trait au repos.
            group.append(svg('path', {d: shape.d, class: 'edge-hit'}));
            if (editable) {
                group.setAttribute('tabindex', '0');
                group.setAttribute('role', 'button');
                group.setAttribute('aria-label', label);
                group.setAttribute('aria-haspopup', 'menu');
            }
        }
        edges.append(group);
    }
    result.append(edges);

    for (const [id, position] of model.positions) {
        const person = family.people.get(id);
        const sex = person.sex;
        const avatarY = sex ? 23 : 31;
        const name = [person.name, SEX_LABELS[sex], person.dates].filter(Boolean).join(' · ');
        const classes = ['tree-node', id === family.center ? 'tree-node-centre' : '', sex ? 'tree-node-' + sex : ''];
        const attributes = {transform: 'translate(' + position.x + ' ' + position.y + ')',
            class: classes.filter(Boolean).join(' '), 'data-person': id, 'data-menu-title': person.name};
        if (interactive) {
            Object.assign(attributes, {href: personUrl(id), tabindex: '0', 'aria-label': name, 'aria-haspopup': 'menu'});
        }
        const node = svg(interactive ? 'a' : 'g', attributes);
        const label = svg('g', {'clip-path': 'url(#' + labelId + ')'});
        label.append(
            svgText('text', shorten(person.name, 18), {x: 47, y: 27, class: 'node-name'}),
            svgText('text', shorten(person.dates, 23), {x: 47, y: 45, class: 'node-dates'})
        );
        // Réserver un emplacement sous les initiales quand le sexe a été renseigné.
        node.append(
            svgText('title', name),
            svg('rect', {width: CARD.width, height: CARD.height, rx: 10, class: 'node-box'}),
            svg('circle', {cx: 25, cy: avatarY, r: 14, class: 'node-avatar'}),
            person.portrait
                ? svg('image', {href: person.portrait, x: 11, y: avatarY - 14, width: 28, height: 28,
                    'clip-path': portraitClip.get(avatarY), class: 'node-portrait', 'aria-hidden': 'true'})
                : svgText('text', person.initials, {x: 25, y: avatarY + 4, 'text-anchor': 'middle', class: 'node-initials'}),
            label
        );
        if (sex) {
            node.append(svg('use', {href: './assets/fontawesome.svg#' + sex, x: 19, y: 42, width: 12, height: 12,
                class: 'node-sex-icon', 'aria-hidden': 'true'}));
        }
        result.append(node);
    }
    return result;
}

function updatePaths(drawing, model) {
    // Le déplacement met à jour les deux tracés d'un lien sans remplacer les éléments sous le pointeur.
    const paths = new Map(linkShapes(model).map(shape => [shape.key, shape.d]));
    for (const group of drawing.querySelectorAll('[data-edge]')) {
        for (const path of group.querySelectorAll('path')) {
            path.setAttribute('d', paths.get(group.dataset.edge));
        }
    }
}

function normalizeSearch(text) {
    return text.toLocaleLowerCase('fr').replaceAll('œ', 'oe').replaceAll('æ', 'ae').replaceAll('ß', 'ss')
        .normalize('NFD').replace(/\p{M}/gu, '').replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
}

class TreeUI {
    constructor(panel) {
        this.panel = panel;
        // La page fournit les données initiales dans un bloc JSON préparé par PHP.
        const data = JSON.parse(panel.querySelector('[data-graph-data]').textContent);
        this.center = Number(panel.dataset.center);
        this.family = new FamilyGraph(data, this.center);
        this.editable = panel.dataset.editable === 'true';
        this.links = new Map(data.relations.map(link => [link.id, link]));
        this.people = data.people.sort((a, b) => a.name.localeCompare(b.name, 'fr'));
        // Profondeur initiale de chaque branche et bornes calculées à partir des liens connus.
        this.levels = {ancestors: 2, descendants: 2};
        this.maximum = {
            ancestors: [...this.family.ancestors.values()].reduce((max, level) => Math.max(max, level), 0),
            descendants: [...this.family.descendants.values()].reduce((max, level) => Math.max(max, level), 0),
        };
        // Le zoom et les positions manuelles appartiennent à cette vue et restent en mémoire dans le navigateur.
        this.zoom = 1;
        this.fixed = new Map();
        this.viewport = panel.querySelector('.graph-viewport');
        this.surface = panel.querySelector('.graph-surface');
        this.printSurface = panel.querySelector('.print-graph');
        this.contextMenu = new GraphMenu(panel, this.surface, (item, target) => this.runContextAction(item, target));
        this.draw();
        this.fitZoom();
        this.bindControls();
        this.bindPointer();
        this.bindCenter();
        if (this.editable) {
            this.bindRelations(data.attempted);
            this.relativeDialog = panel.querySelector('[data-relative-dialog]');
            const rejected = data.attemptedRelative;
            if (rejected && this.family.people.has(rejected.id)) {
                const preset = [...panel.querySelectorAll('[data-preset]')].find(item => item.dataset.preset === rejected.preset);
                if (preset) {
                    this.openRelative(this.family.people.get(rejected.id), preset, rejected);
                }
            }
        }
        requestAnimationFrame(() => this.scrollToCenter());
    }

    draw() {
        this.contextMenu.close();
        this.model = this.family.layout(this.levels.ancestors, this.levels.descendants, this.fixed);
        this.drawing = drawGraph(this.family, this.model, true, this.editable);
        this.surface.replaceChildren(this.drawing);
        this.resize();
        this.panel.querySelectorAll('[data-expand]').forEach(button => {
            const direction = button.dataset.expand;
            button.disabled = this.levels[direction] >= this.maximum[direction];
            button.title = button.disabled ? 'Toutes les générations disponibles sont affichées'
                : 'Afficher une génération supplémentaire';
        });
    }

    resize() {
        this.drawing.style.width = this.model.width * this.zoom + 'px';
        this.drawing.style.height = this.model.height * this.zoom + 'px';
        this.panel.querySelector('[data-zoom-level]').textContent = Math.round(this.zoom * 100) + ' %';
        this.panel.querySelector('[data-zoom="in"]').disabled = this.zoom >= 1.75;
        this.panel.querySelector('[data-zoom="out"]').disabled = this.zoom <= 0.45;
    }

    fitZoom() {
        const width = this.viewport.clientWidth;
        // Sur un petit écran, laisser défiler l'arbre pour préserver une taille de lecture suffisante.
        this.zoom = width < 600 ? 0.82 : Math.max(0.62, Math.min(1, width / this.model.width));
        this.resize();
    }

    scrollToCenter() {
        const focus = this.model.positions.get(this.center);
        // Convertir les coordonnées de la carte en pixels affichés avant de centrer le défilement.
        this.viewport.scrollLeft = (focus.x + CARD.width / 2) * this.zoom - this.viewport.clientWidth / 2;
        this.viewport.scrollTop = (focus.y + CARD.height / 2) * this.zoom - this.viewport.clientHeight / 2;
    }

    bindControls() {
        this.panel.querySelectorAll('[data-expand]').forEach(button => {
            button.addEventListener('click', () => {
                const direction = button.dataset.expand;
                this.levels[direction] += 1;
                this.stopPair();
                this.fixed.clear();
                this.draw();
                this.scrollToCenter();
                // Montrer la nouvelle génération au bord supérieur ou inférieur de la zone.
                const top = Math.max(0, this.drawing.getBoundingClientRect().height - this.viewport.clientHeight);
                this.viewport.scrollTop = direction === 'ancestors' ? 0 : top;
            });
        });
        this.panel.querySelectorAll('[data-zoom]').forEach(button => {
            button.addEventListener('click', () => {
                const mode = button.dataset.zoom;
                if (mode === 'reset') {
                    this.stopPair();
                    this.fixed.clear();
                    this.draw();
                    this.fitZoom();
                    this.scrollToCenter();
                    return;
                }
                // Retrouver le centre de la zone en unités SVG pour le conserver pendant le zoom.
                const centerX = (this.viewport.scrollLeft + this.viewport.clientWidth / 2) / this.zoom;
                const centerY = (this.viewport.scrollTop + this.viewport.clientHeight / 2) / this.zoom;
                this.zoom = Math.max(0.45, Math.min(1.75, Math.round((this.zoom + (mode === 'in' ? 0.15 : -0.15)) * 100) / 100));
                this.resize();
                this.viewport.scrollLeft = centerX * this.zoom - this.viewport.clientWidth / 2;
                this.viewport.scrollTop = centerY * this.zoom - this.viewport.clientHeight / 2;
            });
        });

        this.panel.querySelectorAll('[data-print-tree]').forEach(button => {
            button.addEventListener('click', () => {
                button.closest('details').open = false;
                this.preparePrint(button.dataset.printTree);
                document.body.classList.add('printing-tree');
                window.print();
            });
        });
        window.addEventListener('beforeprint', () => {
            // L'impression lancée depuis le navigateur imprime la vue courante sur la page Arbre.
            if (document.body.classList.contains('tree-page') && !this.printSurface.firstChild) {
                this.preparePrint('view');
            }
        });
        window.addEventListener('afterprint', () => {
            document.body.classList.remove('printing-tree');
            this.printSurface.replaceChildren();
        });
    }

    preparePrint(mode) {
        // Les branches complètes sont recalculées sans limite de générations ni déplacements manuels.
        // La vue affichée reprend la disposition courante, indépendamment du zoom et du défilement.
        const ranges = {
            ancestors: [Infinity, 0], descendants: [0, Infinity], both: [Infinity, Infinity],
        };
        const model = ranges[mode] ? this.family.layout(...ranges[mode], new Map(), false) : this.model;
        const title = document.createElement('h2');
        const modes = {view: 'Vue affichée', ancestors: 'Branche ascendante', descendants: 'Branche descendante',
            both: 'Ascendants et descendants'};
        title.textContent = this.family.people.get(this.center).name + ' · ' + modes[mode];
        this.printSurface.replaceChildren(title, drawGraph(this.family, model, false, false));
    }

    bindPointer() {
        // gesture mémorise le pointeur, la carte éventuelle et les coordonnées au début du geste.
        // wasDragged permet ensuite de distinguer le déplacement d'un clic sur cette carte.
        let gesture;
        let wasDragged = false;
        let press;
        const cancelPress = () => {
            if (press) {
                window.clearTimeout(press.timer);
                press = null;
            }
        };
        this.surface.addEventListener('pointerdown', event => {
            if (!event.isPrimary || event.button !== 0) {
                return;
            }
            const card = event.target.closest('[data-person]');
            const strip = event.target.closest('[data-edge]');
            wasDragged = false;
            const target = this.contextMenu.target(event.target);
            if (event.pointerType === 'touch' && target) {
                cancelPress();
                const {pointerId, clientX: x, clientY: y} = event;
                // Un appui immobile ouvre le menu. Le déplacement et le défilement annulent le délai.
                press = {pointerId, x, y, timer: window.setTimeout(() => {
                    press = null;
                    gesture = null;
                    wasDragged = true;
                    this.contextMenu.open(target, x, y, true);
                }, LONG_PRESS_MS)};
            }
            if (strip || (!card && event.pointerType === 'touch')) {
                return;
            }
            const id = card ? Number(card.dataset.person) : null;
            gesture = {
                pointer: event.pointerId, id, card, x: event.clientX, y: event.clientY,
                position: id ? {...this.model.positions.get(id)} : null,
                left: this.viewport.scrollLeft, top: this.viewport.scrollTop,
            };
        });
        this.surface.addEventListener('pointermove', event => {
            if (event.pointerId === press?.pointerId && Math.hypot(event.clientX - press.x, event.clientY - press.y) >= 5) {
                cancelPress();
            }
            if (!gesture || gesture.pointer !== event.pointerId) {
                return;
            }
            const dx = event.clientX - gesture.x;
            const dy = event.clientY - gesture.y;
            if (!wasDragged && Math.hypot(dx, dy) < 5) {
                return;
            }
            // Au-delà du seuil de cinq pixels, continuer à recevoir les événements hors de la carte.
            if (!wasDragged) {
                (gesture.card ?? this.surface).setPointerCapture(event.pointerId);
                wasDragged = true;
                this.surface.classList.add('is-dragging');
            }
            if (gesture.card) {
                // Ajouter le défilement au déplacement en pixels, puis revenir en unités SVG.
                const x = Math.max(5, Math.min(this.model.width - CARD.width - 5,
                    gesture.position.x + (dx + this.viewport.scrollLeft - gesture.left) / this.zoom));
                const y = Math.max(26, Math.min(this.model.height - CARD.height - 5,
                    gesture.position.y + (dy + this.viewport.scrollTop - gesture.top) / this.zoom));
                gesture.card.setAttribute('transform', 'translate(' + x + ' ' + y + ')');
                this.fixed.set(gesture.id, {x, y});
                this.model.positions.set(gesture.id, {...gesture.position, x, y});
                updatePaths(this.drawing, this.model);
            } else {
                this.viewport.scrollLeft = gesture.left - dx;
                this.viewport.scrollTop = gesture.top - dy;
            }
        });
        const finish = event => {
            if (event.pointerId === press?.pointerId) {
                cancelPress();
            }
            if (event.pointerId === gesture?.pointer) {
                this.surface.classList.remove('is-dragging');
                gesture = null;
            }
        };
        window.addEventListener('pointerup', finish);
        window.addEventListener('pointercancel', finish);
        this.surface.addEventListener('contextmenu', event => {
            if (event.pointerType === 'touch' && this.contextMenu.target(event.target)) {
                cancelPress();
                gesture = null;
                wasDragged = true;
            }
        });
        this.surface.addEventListener('dragstart', event => event.preventDefault());
        this.surface.addEventListener('click', event => {
            // Bloquer la navigation après un glisser-déposer tout en préservant le clic simulé au clavier.
            if (wasDragged && event.detail !== 0) {
                event.preventDefault();
                wasDragged = false;
                return;
            }
            const edge = event.target.closest('[data-edge]');
            if (edge && this.editable) {
                this.stopPair();
                this.chooseLink(edge);
                return;
            }
            const node = event.target.closest('[data-person]');
            if (node && this.pair) {
                event.preventDefault();
                this.selectPair(node);
            }
        });
        this.surface.addEventListener('keydown', event => {
            if (!this.editable || !['Enter', ' '].includes(event.key) || !event.target.matches('[data-edge]')) {
                return;
            }
            event.preventDefault();
            this.stopPair();
            this.chooseLink(event.target);
        });
    }

    bindCenter() {
        const dialog = this.panel.querySelector('[data-centre-dialog]');
        const input = dialog.querySelector('[data-center-search]');
        const list = dialog.querySelector('[data-center-results]');
        // Chercher chaque mot dans l'index des noms fourni par le serveur et borner la liste de suggestions.
        const listMatches = () => {
            const terms = normalizeSearch(input.value).split(' ').filter(Boolean);
            const matches = this.people.filter(person => terms.every(term => person.search.includes(term)));
            list.replaceChildren();
            for (const person of matches.slice(0, 30)) {
                const item = document.createElement('li');
                const link = document.createElement('a');
                link.href = treeUrl(person.id);
                link.textContent = person.name;
                const dates = document.createElement('small');
                dates.textContent = person.dates;
                link.append(dates);
                item.append(link);
                list.append(item);
            }
            if (matches.length === 0 || matches.length > 30) {
                const hint = document.createElement('li');
                hint.className = 'center-hint';
                hint.textContent = matches.length ? 'Précisez le nom pour affiner la liste.' : 'Aucune personne trouvée.';
                list.append(hint);
            }
        };
        input.addEventListener('input', listMatches);
        this.panel.querySelector('[data-pick-center]').addEventListener('click', () => {
            input.value = '';
            listMatches();
            dialog.showModal();
            input.focus();
        });
    }

    bindRelations(attempted) {
        this.relationDialog = this.panel.querySelector('[data-relation-dialog]');
        this.linkChooser = this.panel.querySelector('[data-link-chooser]');
        this.relationForm = this.relationDialog.querySelector('[data-relation-form]');
        const fields = this.relationForm.elements;
        // Les deux extrémités d'un lien utilisent la même liste de personnes.
        const options = document.createDocumentFragment();
        const empty = document.createElement('option');
        empty.textContent = 'Choisir une personne';
        empty.value = '';
        options.append(empty);
        for (const person of this.people) {
            const option = document.createElement('option');
            option.textContent = person.name + ' · ' + person.dates;
            option.value = person.id;
            options.append(option);
        }
        fields.a_id.append(options.cloneNode(true));
        fields.b_id.append(options);
        fields.kind.addEventListener('change', () => this.relationLabels());
        this.relationForm.addEventListener('submit', event => {
            if (event.submitter?.value === 'delete-relation' && !window.confirm('Supprimer cette relation ?')) {
                event.preventDefault();
            }
        });
        document.querySelectorAll('[data-new-relation]').forEach(button => {
            button.addEventListener('click', () => this.openRelation());
        });
        document.querySelectorAll('[data-edit-relation]').forEach(button => {
            button.addEventListener('click', () => this.openRelation(this.links.get(Number(button.dataset.editRelation))));
        });
        this.panel.querySelector('[data-pick-pair]').addEventListener('click', () => {
            this.relationDialog.close();
            this.startPair();
        });
        this.panel.querySelector('[data-cancel-pair]').addEventListener('click', () => {
            this.stopPair();
            this.panel.querySelector('[data-new-relation]').focus();
        });
        this.panel.addEventListener('keydown', event => {
            if (event.key === 'Escape' && this.pair) {
                this.stopPair();
                this.panel.querySelector('[data-new-relation]').focus();
            }
        });
        if (attempted) {
            // Après une erreur côté serveur, rouvrir l'éditeur avec les valeurs renvoyées par PHP.
            this.openRelation(attempted);
        }
    }

    chooseLink(segment, onSelect = relation => this.openRelation(relation)) {
        // Un trait commun représente plusieurs liens SQLite. Demander lequel éditer avant d'ouvrir le formulaire.
        const candidates = segment.dataset.links.split(',').map(id => this.links.get(Number(id)));
        if (candidates.length === 1) {
            onSelect(candidates[0]);
            return;
        }
        const list = this.linkChooser.querySelector('[data-link-results]');
        list.replaceChildren();
        for (const relation of candidates) {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'button button-light';
            button.textContent = linkLabel(this.family, relation);
            button.addEventListener('click', () => {
                this.linkChooser.close();
                onSelect(relation);
            });
            item.append(button);
            list.append(item);
        }
        this.linkChooser.showModal();
    }

    startPair() {
        this.stopPair();
        // null désactive la sélection. Un tableau vide attend la première carte.
        this.pair = [];
        const status = this.panel.querySelector('[data-pair-status]');
        status.querySelector('p').textContent = 'Sélectionnez la première personne dans le graphique.';
        status.hidden = false;
        this.scrollToCenter();
        this.drawing.querySelector('.tree-node-centre').focus({preventScroll: true});
    }

    stopPair() {
        if (this.pair) {
            this.pair = null;
            this.panel.querySelector('[data-pair-status]').hidden = true;
            this.drawing.querySelector('.is-selected')?.classList.remove('is-selected');
        }
    }

    selectPair(node) {
        const id = Number(node.dataset.person);
        const status = this.panel.querySelector('[data-pair-status] p');
        if (this.pair.length === 0) {
            this.pair.push(id);
            node.classList.add('is-selected');
            status.textContent = 'Sélectionnez la seconde personne.';
        } else if (id !== this.pair[0]) {
            // Pour une nouvelle filiation, la première carte sélectionnée devient le parent proposé.
            const [first] = this.pair;
            this.stopPair();
            this.openRelation({a_id: first, b_id: id});
        }
    }

    relationLabels() {
        // L'ordre a un sens pour une filiation. L'année de fin concerne uniquement les unions.
        const kind = this.relationForm.elements.kind.value;
        const parent = isParent({kind});
        const union = kind === 'marriage' || kind === 'union';
        this.relationForm.querySelector('[data-a-label]').textContent = parent ? 'Parent' : 'Première personne';
        this.relationForm.querySelector('[data-b-label]').textContent = parent ? 'Enfant' : 'Seconde personne';
        this.relationForm.querySelector('[data-end-label]').hidden = !union;
        this.relationForm.elements.end_year.disabled = !union;
    }

    openRelation(relation = {}) {
        this.stopPair();
        // Sans identifiant de relation, le formulaire crée un lien et propose la personne centrale.
        const fields = this.relationForm.elements;
        fields.relation_id.value = relation.id || '';
        fields.a_id.value = relation.a_id || this.center;
        fields.b_id.value = relation.b_id || '';
        fields.kind.value = relation.kind || 'biological';
        fields.end_year.value = relation.end_year || '';
        this.relationDialog.querySelector('[data-relation-title]').textContent = relation.id ? 'Modifier une relation' : 'Ajouter une relation';
        const message = this.relationDialog.querySelector('[data-relation-error]');
        message.hidden = !relation.error;
        message.textContent = relation.error ?? '';
        this.relationForm.querySelector('[data-remove-link]').hidden = !relation.id;
        this.relationDialog.querySelector('[data-pick-pair]').hidden = Boolean(relation.id) || this.model.positions.size < 2;
        this.relationLabels();
        this.relationDialog.showModal();
    }

    runContextAction(item, target) {
        this.stopPair();
        switch (item.dataset.menuAction) {
            case 'edit-link':
                this.chooseLink(target);
                break;
            case 'delete-link':
                this.chooseLink(target, relation => {
                    this.relationForm.elements.relation_id.value = relation.id;
                    this.relationForm.requestSubmit(this.relationForm.querySelector('[data-remove-link]'));
                });
                break;
            case 'create-relative':
                this.openRelative(this.family.people.get(Number(target.dataset.person)), item);
                break;
            case 'delete-person': {
                const person = this.family.people.get(Number(target.dataset.person));
                if (window.confirm('Supprimer définitivement la fiche de ' + person.name + ', ses liens et ses fichiers ?')) {
                    const form = this.panel.querySelector('[data-graph-delete-person]');
                    form.elements.target_id.value = person.id;
                    form.requestSubmit();
                }
                break;
            }
        }
    }

    openRelative(person, preset, rejected = null) {
        const dialog = this.relativeDialog;
        const fields = dialog.querySelector('form.stacked-form').elements;
        dialog.querySelector('[data-relative-title]').textContent = preset.textContent.trim();
        dialog.querySelector('[data-relative-reference]').textContent = 'Personne de référence · ' + person.name;
        fields.reference_id.value = person.id;
        fields.preset.value = preset.dataset.preset;
        fields.first_name.value = rejected?.firstName ?? '';
        const group = preset.dataset.relativeGroup;
        fields.last_name.value = rejected?.lastName ?? (['children', 'siblings'].includes(group) ? person.lastName : '');
        // Les autres raccourcis fixent le sexe. Seule la création d'un conjoint propose un choix.
        const isPartner = group === 'partners';
        dialog.querySelector('[data-relative-sex]').hidden = !isPartner;
        fields.sex.disabled = !isPartner;
        const suggestedSex = ({male: 'female', female: 'male'})[person.sex] ?? '';
        fields.sex.value = isPartner ? (rejected?.sex ?? suggestedSex) : '';
        const error = dialog.querySelector('[data-relative-error]');
        error.textContent = rejected?.error ?? '';
        error.hidden = !rejected;
        dialog.showModal();
        fields.first_name.focus();
    }
}

document.querySelectorAll('[data-graph]').forEach(panel => new TreeUI(panel));
