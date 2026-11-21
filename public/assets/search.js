'use strict';

const search = document.querySelector('[data-people-search]');

if (search) {
    const input = search.elements.name;
    const list = search.querySelector('.search-suggestions');
    let timer;
    let pending;
    let results = [];
    let selected = -1;

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', list.id);
    input.setAttribute('aria-expanded', 'false');

    function closeList() {
        clearTimeout(timer);
        pending?.abort();
        results = [];
        selected = -1;
        list.hidden = true;
        list.replaceChildren();
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    }

    function showResults(people) {
        results = people;
        for (const [index, person] of people.entries()) {
            const option = document.createElement('li');
            option.id = list.id + '-' + index;
            option.dataset.index = index;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            const name = document.createElement('span');
            name.textContent = person.name;
            const dates = document.createElement('small');
            dates.textContent = person.dates;
            option.append(name, dates);
            list.append(option);
        }
        list.hidden = people.length === 0;
        input.setAttribute('aria-expanded', String(!list.hidden));
    }

    function scheduleSearch() {
        closeList();
        if ([...input.value.trim()].length < 3) return;

        // Attendre la fin d'une frappe rapide. Une requête annulée ne peut pas rouvrir la liste.
        timer = setTimeout(async () => {
            const request = new AbortController();
            pending = request;
            const endpoint = new URL(search.dataset.peopleSearch, document.baseURI);
            for (const field of ['name', 'year', 'when']) {
                endpoint.searchParams.set(field, search.elements[field].value);
            }
            try {
                const response = await fetch(endpoint, {signal: request.signal});
                if (!response.ok) return;
                const people = await response.json();
                if (!request.signal.aborted && document.activeElement === input) showResults(people);
            } catch (error) {
                // En cas d'échec, le formulaire classique reste disponible.
            }
        }, 150);
    }

    input.addEventListener('focus', scheduleSearch);
    input.addEventListener('input', event => {
        if (!event.isComposing) scheduleSearch();
    });
    input.addEventListener('compositionend', scheduleSearch);
    input.addEventListener('blur', closeList);

    input.addEventListener('keydown', event => {
        if (event.isComposing) return;
        if (event.key === 'Escape' && !list.hidden) {
            event.preventDefault();
            closeList();
        } else if (event.key === 'Enter' && selected >= 0) {
            event.preventDefault();
            window.location.assign(results[selected].url);
        } else if (!list.hidden && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault();
            // Conserver le focus dans le champ pour permettre de reprendre la saisie à tout moment.
            list.children[selected]?.setAttribute('aria-selected', 'false');
            const step = event.key === 'ArrowDown' ? 1 : -1;
            selected = selected < 0 && step < 0 ? results.length - 1 : (selected + step + results.length) % results.length;
            const option = list.children[selected];
            option.setAttribute('aria-selected', 'true');
            input.setAttribute('aria-activedescendant', option.id);
            option.scrollIntoView({block: 'nearest'});
        }
    });

    // Un clic doit sélectionner la suggestion avant que la perte de focus ferme la liste.
    list.addEventListener('mousedown', event => event.preventDefault());
    list.addEventListener('click', event => {
        const option = event.target.closest('[data-index]');
        if (option) window.location.assign(results[option.dataset.index].url);
    });
}
