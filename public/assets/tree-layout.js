export const CARD = {width: 194, height: 62, gap: 28, rowGap: 112, marginX: 28, marginY: 30};
export const isParent = link => link.kind === 'biological' || link.kind === 'adoptive';

function addIndex(index, id, value) {
    if (!index.has(id)) {
        index.set(id, []);
    }
    index.get(id).push(value);
}

export class FamilyGraph {
    constructor(data, center) {
        this.people = new Map(data.people.map(person => [person.id, person]));
        this.links = data.relations;
        this.center = center;
        this.parents = new Map();
        this.children = new Map();
        this.partners = new Map();
        for (const link of this.links) {
            if (isParent(link)) {
                addIndex(this.parents, link.b_id, link.a_id);
                addIndex(this.children, link.a_id, link.b_id);
            } else {
                addIndex(this.partners, link.a_id, link.b_id);
                addIndex(this.partners, link.b_id, link.a_id);
            }
        }
        this.ancestors = this.trace(this.parents);
        this.descendants = this.trace(this.children);
    }

    trace(index) {
        const distance = new Map([[this.center, 0]]);
        const queue = [this.center];
        for (const id of queue) {
            for (const linked of index.get(id) ?? []) {
                if (!distance.has(linked)) {
                    distance.set(linked, distance.get(id) + 1);
                    queue.push(linked);
                }
            }
        }
        return distance;
    }

    // Calculate ranks in topological order so a shared ancestor stays above every visible child.
    ranks(above, below) {
        const ranks = new Map([[this.center, 0]]);
        for (const [distance, limit, direction] of [[this.ancestors, above, -1], [this.descendants, below, 1]]) {
            for (const [id, depth] of distance) {
                if (depth <= limit) {
                    ranks.set(id, depth * direction);
                }
            }
        }
        const inDegree = new Map([...ranks.keys()].map(id => [id, 0]));
        for (const id of ranks.keys()) {
            for (const child of this.children.get(id) ?? []) {
                if (inDegree.has(child)) {
                    inDegree.set(child, inDegree.get(child) + 1);
                }
            }
        }
        const order = [...ranks.keys()].filter(id => inDegree.get(id) === 0);
        for (const id of order) {
            for (const child of this.children.get(id) ?? []) {
                if (!inDegree.has(child)) {
                    continue;
                }
                const remaining = inDegree.get(child) - 1;
                inDegree.set(child, remaining);
                if (remaining === 0) {
                    order.push(child);
                }
                if (ranks.get(id) >= 0 && ranks.get(child) > 0) {
                    ranks.set(child, Math.max(ranks.get(child), ranks.get(id) + 1));
                }
            }
        }
        for (const child of order.reverse()) {
            for (const parent of this.parents.get(child) ?? []) {
                if (ranks.get(parent) < 0 && ranks.get(child) <= 0) {
                    ranks.set(parent, Math.min(ranks.get(parent), ranks.get(child) - 1));
                }
            }
        }

        // Place partners and co-parents alongside the nearest direct member of the branch.
        const direct = [...ranks.keys()].sort((a, b) => Math.abs(ranks.get(a)) - Math.abs(ranks.get(b)));
        for (const id of direct) {
            const rank = ranks.get(id);
            for (const partner of this.partners.get(id) ?? []) {
                if (!ranks.has(partner)) {
                    ranks.set(partner, rank);
                }
            }
            for (const child of this.children.get(id) ?? []) {
                if (!ranks.has(child) || ranks.get(child) <= rank) {
                    continue;
                }
                for (const parent of this.parents.get(child) ?? []) {
                    if (!ranks.has(parent)) {
                        ranks.set(parent, rank);
                    }
                }
            }
        }
        return ranks;
    }

    layout(above, below, fixed = new Map()) {
        const ranks = this.ranks(above, below);
        const firstRank = Math.min(0, ...ranks.values());
        const lastRank = Math.max(0, ...ranks.values());
        const rows = Array.from({length: lastRank - firstRank + 1}, () => []);
        for (const [id, rank] of ranks) {
            rows[rank - firstRank].push(id);
        }
        const links = this.links.filter(link => ranks.has(link.a_id) && ranks.has(link.b_id));
        const neighborIndex = new Map();
        for (const link of links) {
            addIndex(neighborIndex, link.a_id, link.b_id);
            addIndex(neighborIndex, link.b_id, link.a_id);
        }
        for (const row of rows) {
            row.sort((a, b) => (this.people.get(a).year ?? Infinity) - (this.people.get(b).year ?? Infinity) || a - b);
        }
        this.orderRows(rows, ranks, firstRank, links, neighborIndex);

        const horizontal = this.placeRows(rows, ranks, firstRank);
        const left = [...horizontal.values()].reduce((min, x) => Math.min(min, x), Infinity);
        const right = [...horizontal.values()].reduce((max, x) => Math.max(max, x), -Infinity);
        const innerWidth = right - left + CARD.width;
        const positions = new Map();
        for (const [index, row] of rows.entries()) {
            row.forEach(id => {
                const coordinates = {
                    x: horizontal.get(id) - left + CARD.marginX,
                    y: CARD.marginY + index * (CARD.height + CARD.rowGap),
                    rank: ranks.get(id),
                };
                positions.set(id, {...coordinates, ...fixed.get(id)});
            });
        }
        const outerRoutes = links.filter(link => isParent(link) && ranks.get(link.b_id) > ranks.get(link.a_id) + 1).length;
        return {
            positions,
            links,
            width: Math.max(innerWidth + CARD.marginX * 2 + (outerRoutes ? 15 + outerRoutes * 7 : 0), 320),
            height: CARD.marginY * 2 + rows.length * CARD.height + (rows.length - 1) * CARD.rowGap,
        };
    }

    placeRows(rows, ranks, firstRank) {
        const step = CARD.width + CARD.gap;
        const centerIndex = -firstRank;
        const middle = rows[centerIndex];
        const centerColumn = middle.indexOf(this.center);
        const locations = new Map(middle.map((id, column) => [id, (column - centerColumn) * step]));

        // Align each group with its relatives in the row closer to the focus.
        for (const [direction, relatives] of [[-1, this.children], [1, this.parents]]) {
            for (let index = centerIndex + direction; index >= 0 && index < rows.length; index += direction) {
                const row = rows[index];
                const rank = index + firstRank;
                const anchors = new Map();
                const columns = new Map(row.map((id, column) => [id, column]));
                for (const id of row) {
                    const near = (relatives.get(id) ?? []).filter(relative => ranks.get(relative) === rank - direction);
                    if (near.length) {
                        anchors.set(id, near.reduce((sum, relative) => sum + locations.get(relative), 0) / near.length);
                    }
                }
                const preferences = row.map((id, column) => {
                    if (anchors.has(id)) {
                        return {x: anchors.get(id), weight: 3};
                    }
                    const partner = (this.partners.get(id) ?? []).find(other => anchors.has(other));
                    if (partner !== undefined) {
                        return {x: anchors.get(partner) + Math.sign(column - columns.get(partner)) * step, weight: 2};
                    }
                    return {x: (column - (row.length - 1) / 2) * step, weight: 1};
                });
                separateColumns(preferences, step).forEach((x, column) => locations.set(row[column], x));
            }
        }
        return locations;
    }

    orderRows(rows, ranks, firstRank, links, neighborIndex) {
        const adjacent = new Map();
        for (const link of links) {
            const a = ranks.get(link.a_id) - firstRank;
            const b = ranks.get(link.b_id) - firstRank;
            if (isParent(link) && Math.abs(a - b) === 1) {
                addIndex(adjacent, Math.min(a, b), a < b ? [link.a_id, link.b_id] : [link.b_id, link.a_id]);
            }
        }
        const locations = new Map();
        const indexRow = row => row.forEach((id, position) => locations.set(id, position - (row.length - 1) / 2));
        rows.forEach(indexRow);

        // Alternate between parents and children when ordering each generation.
        let best = rows.map(row => [...row]);
        let bestScore = Infinity;
        for (let sweep = 0; sweep < 8; sweep += 1) {
            const forward = sweep % 2 === 0;
            for (let index = 0; index < rows.length; index += 1) {
                const rowIndex = forward ? index : rows.length - index - 1;
                const row = rows[rowIndex];
                const rank = rowIndex + firstRank;
                const weights = new Map();
                for (const id of row) {
                    const neighbors = (neighborIndex.get(id) ?? []).filter(other =>
                        forward ? ranks.get(other) < rank : ranks.get(other) > rank);
                    const opposite = (neighborIndex.get(id) ?? []).filter(other => ranks.get(other) !== rank);
                    const candidates = neighbors.length ? neighbors : opposite;
                    const anchor = candidates.length
                        ? candidates.reduce((sum, other) => sum + locations.get(other), 0) / candidates.length
                        : locations.get(id);
                    weights.set(id, anchor);
                }
                row.sort((a, b) => weights.get(a) - weights.get(b) || locations.get(a) - locations.get(b));
                indexRow(row);
            }
            // Adjacent swaps undo crossings when the average position only approximates the best order.
            for (const [index, row] of rows.entries()) {
                if (row.length > 80) {
                    continue;
                }
                let score = this.rowScore(index, adjacent, locations, row, ranks);
                for (let offset = 0; offset < row.length - 1; offset += 1) {
                    [row[offset], row[offset + 1]] = [row[offset + 1], row[offset]];
                    indexRow(row);
                    const candidate = this.rowScore(index, adjacent, locations, row, ranks);
                    if (candidate < score) {
                        score = candidate;
                    } else {
                        [row[offset], row[offset + 1]] = [row[offset + 1], row[offset]];
                        indexRow(row);
                    }
                }
            }
            const score = rows.reduce((sum, row, index) => sum + this.rowScore(index, adjacent, locations, row, ranks), 0);
            if (score < bestScore) {
                best = rows.map(row => [...row]);
                bestScore = score;
            }
        }
        rows.splice(0, rows.length, ...best);
    }

    rowScore(index, adjacent, positions, row, ranks) {
        let score = crossings(adjacent.get(index - 1) ?? [], positions) + crossings(adjacent.get(index) ?? [], positions);
        for (const id of row) {
            for (const partner of this.partners.get(id) ?? []) {
                if (partner > id && ranks.get(partner) === ranks.get(id)) {
                    score += Math.abs(positions.get(id) - positions.get(partner)) * 0.35;
                }
            }
        }
        return score;
    }
}

// Pool neighboring positions that would overlap, preserving the minimum spacing.
function separateColumns(preferences, spacing) {
    const blocks = [];
    for (const [index, {x, weight}] of preferences.entries()) {
        blocks.push({start: index, end: index + 1, weight, x: x - index * spacing});
        while (blocks.length >= 2) {
            const last = blocks.at(-1);
            const previous = blocks.at(-2);
            if (previous.x <= last.x) {
                break;
            }
            const total = previous.weight + last.weight;
            previous.x = (previous.x * previous.weight + last.x * last.weight) / total;
            previous.end = last.end;
            previous.weight = total;
            blocks.pop();
        }
    }
    const positions = [];
    for (const {start, end, x} of blocks) {
        for (let column = start; column < end; column += 1) {
            positions.push(x + column * spacing);
        }
    }
    return positions;
}

// Count inversions with a Fenwick tree so broad generations do not need pairwise comparisons.
function crossings(edges, positions) {
    const targets = [...new Set(edges.map(([, b]) => positions.get(b)))].sort((a, b) => a - b);
    const index = new Map(targets.map((position, offset) => [position, offset + 1]));
    const sorted = edges.map(([a, b]) => [positions.get(a), index.get(positions.get(b))])
        .sort((a, b) => a[0] - b[0] || a[1] - b[1]);
    const counts = new Int32Array(targets.length + 1);
    let seen = 0;
    let count = 0;
    for (const [, position] of sorted) {
        let atOrBefore = 0;
        for (let i = position; i > 0; i -= i & -i) {
            atOrBefore += counts[i];
        }
        count += seen - atOrBefore;
        for (let i = position; i < counts.length; i += i & -i) {
            counts[i] += 1;
        }
        seen += 1;
    }
    return count;
}

function rankConnections(links, positions, endpoint, other) {
    const groups = new Map();
    for (const link of links) {
        addIndex(groups, link[endpoint], link);
    }
    const offsets = new Map();
    for (const list of groups.values()) {
        list.sort((a, b) => positions.get(a[other]).x - positions.get(b[other]).x || a.id - b.id);
        list.forEach((link, index) => {
            const distance = Math.min(11, (CARD.width - 34) / Math.max(1, list.length - 1));
            offsets.set(link.id, (index - (list.length - 1) / 2) * distance);
        });
    }
    return offsets;
}

function familySegments(model) {
    const byChild = new Map();
    for (const link of model.links.filter(isParent)) {
        addIndex(byChild, link.b_id, link);
    }
    const direct = [];
    const shared = new Map();
    for (const [child, links] of byChild) {
        const generation = model.positions.get(child).rank;
        const parents = [...new Set(links.map(link => link.a_id))].sort((a, b) => a - b);
        const sameGeneration = parents.every(id => model.positions.get(id).rank === generation - 1);
        if (!sameGeneration || parents.length < 2) {
            direct.push(...links);
            continue;
        }
        const key = parents.join('-');
        if (!shared.has(key)) {
            shared.set(key, {key, generation, stems: new Map(), tips: new Map()});
        }
        const unit = shared.get(key);
        unit.tips.set(child, links);
        for (const parent of parents) {
            if (!unit.stems.has(parent)) {
                unit.stems.set(parent, []);
            }
        }
        for (const link of links) {
            unit.stems.get(link.a_id).push(link);
        }
    }
    return {direct, shared};
}

function allocateTracks(groups) {
    const tracksByGap = new Map();
    const fractions = new Map();
    for (const group of groups) {
        addIndex(tracksByGap, group.rank, group);
    }
    for (const items of tracksByGap.values()) {
        items.sort((a, b) => (b.right - b.left) - (a.right - a.left));
        const tracks = [];
        for (const {key, left, right} of items) {
            let lane = tracks.findIndex(track => track.every(([start, end]) => right < start - 2 || left > end + 2));
            if (lane === -1) {
                lane = tracks.length;
                tracks.push([]);
            }
            tracks[lane].push([left, right]);
            fractions.set(key, lane);
        }
        for (const {key} of items) {
            fractions.set(key, 0.29 + 0.37 * (fractions.get(key) + 1) / (tracks.length + 1));
        }
    }
    return fractions;
}

export function linkShapes(model) {
    const {positions, links, width} = model;
    const {direct, shared} = familySegments(model);
    // Give each distinct branch its own attachment point on a card.
    const stems = [...shared.values()].flatMap(unit => [...unit.stems.values()].map(([link]) => ({
        ...link, id: -link.id,
    })));
    const exits = rankConnections([...direct, ...stems], positions, 'a_id', 'b_id');
    const entries = rankConnections(direct, positions, 'b_id', 'a_id');
    const centerX = position => position.x + CARD.width / 2;
    const straight = direct.map(link => {
        const a = positions.get(link.a_id);
        const b = positions.get(link.b_id);
        const x1 = centerX(a) + exits.get(link.id);
        const x2 = centerX(b) + entries.get(link.id);
        return {
            key: 'relation-' + link.id, link, x1, x2, start: a.y + CARD.height, end: b.y - 2,
            a, b, rank: a.rank, left: Math.min(x1, x2), right: Math.max(x1, x2),
        };
    });
    const grouped = [...shared.values()].map(unit => {
        const origins = [...unit.stems.entries()].map(([id, edges]) => ({
            id, edges, x: centerX(positions.get(id)) + exits.get(-edges[0].id), y: positions.get(id).y + CARD.height,
        }));
        const targets = [...unit.tips.entries()].map(([id, edges]) => ({
            id, edges, x: centerX(positions.get(id)), y: positions.get(id).y - 2,
        }));
        const xs = [...origins, ...targets].map(point => point.x);
        const start = Math.max(...origins.map(point => point.y));
        const end = Math.min(...targets.map(point => point.y));
        return {key: 'parents-' + unit.key, rank: unit.generation - 1, left: Math.min(...xs), right: Math.max(...xs),
            origins, targets, start, end};
    });
    const fractions = allocateTracks([...grouped, ...straight.filter(item => item.b.rank === item.a.rank + 1)]);
    const shapes = [];
    let outer = 0;
    for (const item of straight) {
        const {key, a, b, x1, x2, start, end, link} = item;
        let d;
        if (b.rank > a.rank + 1) {
            const outerX = width - 19 - outer * 7;
            outer += 1;
            d = 'M' + x1 + ' ' + start + ' V' + (start + 27) + ' H' + outerX + ' V' + (end - 31) + ' H' + x2 + ' V' + end;
        } else if (end > start + 14 && fractions.has(key)) {
            const bend = start + (end - start) * fractions.get(key);
            d = 'M' + x1 + ' ' + start + ' V' + bend + ' H' + x2 + ' V' + end;
        } else {
            const beside = Math.max(a.x, b.x) + CARD.width + 9;
            d = 'M' + x1 + ' ' + start + ' V' + (start + 14) + ' H' + beside + ' V' + (end - 22) + ' H' + x2 + ' V' + end;
        }
        shapes.push({key, d, kind: link.kind, arrow: true, links: [link]});
    }
    const kindOf = edges => edges.every(link => link.kind === 'adoptive') ? 'adoptive' : 'biological';
    for (const {key, left, right, origins, targets, start, end} of grouped) {
        const y = end > start + 14 ? start + (end - start) * fractions.get(key) : start + 14;
        const sharedLinks = targets.flatMap(point => point.edges);
        shapes.push({key: key + '-join', d: 'M' + left + ' ' + y + ' H' + right, kind: kindOf(sharedLinks), links: sharedLinks});
        for (const {id, edges, x, y: from} of origins) {
            shapes.push({key: key + '-from-' + id, d: 'M' + x + ' ' + from + ' V' + y, kind: kindOf(edges), links: edges});
        }
        for (const {id, edges, x, y: to} of targets) {
            const d = y < to ? 'M' + x + ' ' + y + ' V' + to
                : 'M' + x + ' ' + y + ' h15 V' + (to - 14) + ' H' + x + ' V' + to;
            shapes.push({key: key + '-to-' + id, d, kind: kindOf(edges), arrow: true, links: edges});
        }
    }
    const unions = links.filter(link => !isParent(link));
    const unionPorts = rankConnections(unions.flatMap(link => [
        link, {...link, id: -link.id, a_id: link.b_id, b_id: link.a_id},
    ]), positions, 'a_id', 'b_id');
    for (const link of unions) {
        const a = positions.get(link.a_id);
        const b = positions.get(link.b_id);
        let d;
        const distance = Math.abs(a.x - b.x);
        if (a.y === b.y && distance >= CARD.width && distance <= CARD.width + CARD.gap + 1) {
            const y = a.y + CARD.height / 2 + (link.kind === 'marriage' ? -2 : 2);
            d = 'M' + (Math.min(a.x, b.x) + CARD.width) + ' ' + y + ' H' + Math.max(a.x, b.x);
        } else {
            const x1 = centerX(a) + unionPorts.get(link.id);
            const x2 = centerX(b) + unionPorts.get(-link.id);
            const bend = Math.min(a.y, b.y) - 13 - link.id % 4 * 2.5;
            d = 'M' + x1 + ' ' + a.y + ' V' + bend + ' H' + x2 + ' V' + b.y;
        }
        shapes.push({key: 'relation-' + link.id, d, kind: link.kind, links: [link], ended: Boolean(link.end_year)});
    }
    return shapes;
}
