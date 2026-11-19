<?php
declare(strict_types=1);

function demo_portrait(string $crop): string
{
    global $config;
    $tool = $config['image_tool'];
    $photo = PROJECT_ROOT . '/public/assets/archives-fictives.webp';
    $process = proc_open([$tool, $photo, '-crop', $crop, '+repage', '-strip', 'webp:-'],
        [['file', '/dev/null', 'r'], ['pipe', 'w'], ['file', '/dev/null', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Impossible de créer les portraits de démonstration avec ImageMagick.');
    }
    $bytes = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    if (proc_close($process) !== 0 || !$bytes) {
        throw new RuntimeException('Impossible de créer les portraits de démonstration avec ImageMagick.');
    }
    return $bytes;
}

function demo_document(): string
{
    // Ce PDF de démonstration contient uniquement un texte inventé, sans métadonnées d'auteur.
    $content = "BT /F1 24 Tf 70 717 Td (Document de demonstration) Tj 0 -38 Td /F1 12 Tf "
        . "(Camille Varenne - Archive fictive) Tj 0 -32 Td "
        . "(Lorem ipsum dolor sit amet, consectetur adipiscing elit.) Tj ET\n";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream',
    ];
    $file = "%PDF-1.4\n";
    // Le lecteur PDF utilise cette table pour retrouver la position de chaque objet dans le fichier.
    $offsets = "0000000000 65535 f \n";
    foreach ($objects as $index => $object) {
        $offsets .= sprintf("%010d 00000 n \n", strlen($file));
        $file .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($file);
    return $file . "xref\n0 6\n" . $offsets . "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
}

// Identités et sexes déclarés pour les seules fiches fictives du mode démo.
function demo_people(): array
{
    return [
        ['Jean', 'Varenne', 'vers 1782', '1848', 'male'],
        ['Claire', 'Lavaux', '1787', '1856', 'female'],
        ['Étienne', 'Varenne', '1808', '1872', 'male'],
        ['Agathe', 'Rolin', '1811', '1878', 'female'],
        ['Jules', 'Varenne', '12/04/1836', '1903', 'male'],
        ['Élise', 'Aubrac', '1841', '1916', 'female'],
        ['Henri', 'Bellier', '1838', '1902', 'male'],
        ['Adèle', 'Morel', '1845', '1919', 'female'],
        ['Camille', 'Varenne', '15/06/1869', '09/01/1947', 'female'],
        ['Lucien', 'Bellier', '1867', '1936', 'male'],
        ['Jeanne', 'Varenne', '1874', '1951', 'female'],
        ['Marguerite', 'Bellier', '12/08/1890', '1972', 'female'],
        ['Gabriel', 'Bellier', '1892', '1959', 'male'],
        ['Émile', 'Bellier', '1895', '1968', 'male'],
        ['Alice', 'Bellier', 'vers 1899', '1982', 'female'],
        ['Paul', 'Rolin', '1868', '1930', 'male'],
        ['Albert', 'Lenoir', '1889', '1961', 'male'],
        ['Rose', 'Lenoir', '1917', '1998', 'female'],
        ['Louis', 'Lenoir', '1921', '2001', 'male'],
        ['Victor', 'Delaunay', '1915', '1993', 'male'],
        ['Madeleine', 'Delaunay', '1942', '2018', 'female'],
        ['André', 'Delaunay', '1946', '', 'male'],
        ['Cécile', 'Renaud', '1891', '1979', 'female'],
        ['Louise', 'Bellier', '1918', '2003', 'female'],
        ['Anna', 'Breuil', 'vers 1896', '1973', 'female'],
        ['Denise', 'Bellier', '1923', '2009', 'female'],
    ];
}

function seed_demo(PDO $db): void
{
    if ($db->query('SELECT 1 FROM people LIMIT 1')->fetchColumn()) {
        throw new InputException('La base contient déjà des fiches.');
    }
    $people = demo_people();
    // Les relations et les recadrages utilisent des numéros de démonstration, convertis ensuite en véritables ID SQLite.
    $unions = [
        [1, 2], [3, 4], [5, 6], [7, 8], [9, 10], [9, 16, 'union', 1888],
        [12, 17], [18, 20], [13, 23], [14, 25, 'union'],
    ];
    $families = [
        [[1, 2], [3]], [[3, 4], [5]], [[5, 6], [9, 11]], [[7, 8], [10]],
        [[9, 10], [12, 13, 14]], [[9, 10], [15], 'adoptive'], [[12, 17], [18, 19]],
        [[18, 20], [21, 22]], [[13, 23], [24]], [[14, 25], [26]],
    ];
    $photos = [
        [5, 'Portrait de Jules', '280x320+985+170'],
        [6, 'Portrait d’Élise', '270x305+552+223'],
        [9, 'Portrait de Camille', '290x330+637+56'],
        [10, 'Portrait de Lucien', '280x320+804+34'],
        [12, 'Portrait de Marguerite', '270x305+768+282'],
        [14, 'Portrait d’Émile', '275x310+1095+154'],
    ];
    // Préparer les recadrages avant de verrouiller la base.
    $images = [];
    foreach ($photos as [$id, $title, $crop]) {
        $images[] = [$id, $title, demo_portrait($crop)];
    }
    $biography = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer sit amet ipsum id erat '
        . 'fermentum laoreet. Aenean consequat sapien sed augue cursus, non feugiat urna dictum.</p>'
        . '<p>Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, '
        . 'quis nostrud exercitation ullamco laboris.</p>';
    $facts = '<ul><li>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</li>'
        . '<li>Ut enim ad minim veniam, quis nostrud exercitation.</li></ul>';

    write_transaction($db, static function (PDO $db) use ($people, $unions, $families, $images, $biography, $facts): void {
        // La base a pu recevoir une fiche pendant la préparation des images.
        if ($db->query('SELECT 1 FROM people LIMIT 1')->fetchColumn()) {
            throw new InputException('La base contient déjà des fiches.');
        }
        $ids = [];
        foreach ($people as $index => [$first, $last, $birth, $death, $sex]) {
            $source = [
                'first_name' => $first, 'last_name' => $last, 'sex' => $sex, 'birth_date' => $birth, 'death_date' => $death,
                'residence' => 'Val-des-Ormes', 'birth_place' => $index % 3 === 0 ? 'Val-des-Ormes' : '',
                'death_place' => $index % 5 === 0 && $death ? 'Val-des-Ormes' : '',
                'biography' => $biography, 'notable_facts' => $facts,
            ];
            $ids[$index + 1] = insert_person($db, person_fields($source), 'Nora Exemple');
        }

        foreach ($unions as $union) {
            [$a, $b] = $union;
            insert_relation($db, relation_fields($ids[$a], $ids[$b], $union[2] ?? 'marriage', $union[3] ?? null));
        }
        foreach ($families as $family) {
            [$parents, $children] = $family;
            foreach ($parents as $parent) {
                foreach ($children as $child) {
                    insert_relation($db, relation_fields($ids[$parent], $ids[$child], $family[2] ?? 'biological', null));
                }
            }
        }
        foreach ($images as [$id, $title, $bytes]) {
            insert_media($db, $ids[$id], 'photo', $title . ' · archive fictive', 'image/webp', $bytes);
        }
        insert_media($db, $ids[9], 'document', 'Archive fictive', 'application/pdf', demo_document());
        // Les entrées de démonstration illustrent l'activité de plusieurs éditeurs sur l'accueil.
        foreach ([5, 14, 15, 12, 10, 9] as $personId) {
            $person = find_person($db, $ids[$personId]);
            record_change($db, $person, $personId % 2 ? 'Nora Exemple' : 'Hugo Démo', 'edited');
        }
    });
}
