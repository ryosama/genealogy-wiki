<?php
declare(strict_types=1);

// Valeurs stockées dans people.sex. NULL représente une fiche encore non renseignée.
const SEX_LABELS = ['male' => 'Homme', 'female' => 'Femme'];

// Retourne le libellé à afficher et l'année numérique utilisée par la recherche.
// Une date inconnue a un libellé vide et aucune année.
function parse_date(string $value): array
{
    if ($value === '' || preg_match('/^(?:inconnue?|\?)$/iu', $value)) {
        return ['', null];
    }
    if (!preg_match('/^(?:(vers|avant|après|apres)\s+)?(.+)$/iu', $value, $parts)) {
        throw new InputException('Saisis une année, une date complète ou une date comme « vers 1880 ».');
    }
    // Séparer la précision historique du jour, du mois ou de l'année à contrôler.
    $prefix = mb_strtolower($parts[1] ?? '');
    $prefix = $prefix === 'apres' ? 'après' : $prefix;
    $date = trim($parts[2]);
    if (preg_match('/^\d{1,4}$/D', $date) && (int) $date > 0) {
        $year = (int) $date;
        return [trim($prefix . ' ' . $year), $year];
    }

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $matches)) {
        [, $year, $month, $day] = $matches;
    } elseif (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{1,4})$~D', $date, $matches)) {
        [, $day, $month, $year] = $matches;
    } else {
        throw new InputException('Saisis une année, une date au format JJ/MM/AAAA ou une date comme « vers 1880 ».');
    }
    if (!checkdate((int) $month, (int) $day, (int) $year)) {
        throw new InputException('Une des dates saisies est invalide.');
    }
    return [trim($prefix . ' ' . sprintf('%02d/%02d/%04d', $day, $month, $year)), (int) $year];
}

// Le libellé vient de parse_date(). Les bornes sont inclusives et null désigne une borne inconnue.
function date_bounds(string $label): array
{
    // Une date « vers » n'indique aucune marge précise pour conclure à une contradiction.
    if ($label === '' || str_starts_with($label, 'vers ')) {
        return [null, null];
    }
    [$prefix, $date] = str_contains($label, ' ') ? explode(' ', $label, 2) : ['', $label];
    if (str_contains($date, '/')) {
        $first = $last = DateTimeImmutable::createFromFormat('!d/m/Y', $date);
    } else {
        $year = sprintf('%04d', (int) $date);
        $first = new DateTimeImmutable($year . '-01-01');
        $last = new DateTimeImmutable($year . '-12-31');
    }

    // « Avant » et « après » excluent le jour ou l'année qui leur sert de repère.
    return match ($prefix) {
        'avant' => [null, $first->modify('-1 day')],
        'après' => [$last->modify('+1 day'), null],
        default => [$first, $last],
    };
}

function assert_lifespan_order(array $person): void
{
    [$earliestBirth] = date_bounds($person['birth_date']);
    [, $latestDeath] = date_bounds($person['death_date']);
    if ($earliestBirth !== null && $latestDeath !== null && $earliestBirth >= $latestDeath) {
        throw new InputException('La naissance doit être antérieure au décès de ' . full_name($person) . '.');
    }
}

function normalized_name(string $value): string
{
    // Appliquer le même traitement à la saisie et au nom indexé. Les espaces séparent les mots.
    $value = mb_strtolower($value, 'UTF-8');
    $value = strtr($value, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a', 'æ' => 'ae',
        'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'œ' => 'oe',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ÿ' => 'y', 'ß' => 'ss',
    ]);
    return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '');
}

function bio_html(string $html): string
{
    // La limite en octets borne le travail du parseur avant le parcours des balises.
    if (strlen($html) > 70000) {
        throw new InputException('Le texte est trop long.');
    }
    if (trim($html) === '') {
        return '';
    }
    $document = new DOMDocument();
    $document->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>',
        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $body = $document->getElementsByTagName('body')->item(0);
    return $body ? trim(clean_nodes($body)) : '';
}

function clean_nodes(DOMNode $parent): string
{
    // Seules ces balises de mise en forme sont reproduites. Les autres sont déballées,
    // sauf les contenus actifs ou techniques, ignorés avec tous leurs descendants.
    $allowed = ['p', 'div', 'br', 'b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li', 'blockquote', 'h2', 'h3', 'a'];
    $result = '';
    foreach ($parent->childNodes as $node) {
        if ($node instanceof DOMText) {
            $result .= e($node->nodeValue);
            continue;
        }
        if (!$node instanceof DOMElement || in_array($node->tagName, ['script', 'style', 'template', 'iframe', 'svg', 'math'], true)) {
            continue;
        }
        $contents = clean_nodes($node);
        $tag = $node->tagName;
        if (!in_array($tag, $allowed, true)) {
            $result .= $contents;
            continue;
        }
        if ($tag === 'br') {
            $result .= '<br>';
        } elseif ($tag === 'a') {
            // href est le seul attribut de la saisie conservé. Les protocoles sont filtrés.
            $href = trim($node->getAttribute('href'));
            $result .= preg_match('~^(https?://|mailto:)[^\x00-\x20\x7f]+$~iD', $href)
                ? '<a href="' . e($href) . '" rel="nofollow noreferrer">' . $contents . '</a>'
                : $contents;
        } else {
            $result .= "<$tag>$contents</$tag>";
        }
    }
    return $result;
}

function person_fields(array $source): array
{
    $sex = input($source, 'sex');
    if ($sex !== '' && !isset(SEX_LABELS[$sex])) {
        throw new InputException('Choisis un sexe valide ou laisse ce champ non renseigné.');
    }
    // Le formulaire envoie explicitement 0 lorsque la case est décochée. Les anciennes saisies
    // et la démonstration peuvent encore indiquer le décès uniquement avec une date ou un lieu.
    $deceased = input($source, 'deceased');
    [$deathDate, $deathYear] = $deceased === '0' ? ['', null] : parse_date(input($source, 'death_date'));
    $deathPlace = $deceased === '0' ? '' : short_text($source, 'death_place', 180);
    // Construire les colonnes autorisées avant de préparer l'écriture SQL.
    $fields = [
        'first_name' => short_text($source, 'first_name', 100),
        'last_name' => family_name(short_text($source, 'last_name', 100)),
        'sex' => $sex === '' ? null : $sex,
        'deceased' => (int) ($deceased === '1' || $deathDate !== '' || $deathPlace !== ''),
        'birth_place' => short_text($source, 'birth_place', 180),
        'death_place' => $deathPlace,
        'death_date' => $deathDate,
        'death_year' => $deathYear,
        'residence' => short_text($source, 'residence', 180),
        'biography' => bio_html(input($source, 'biography')),
        'notable_facts' => bio_html(input($source, 'notable_facts')),
    ];
    $fields['search_name'] = normalized_name($fields['first_name'] . ' ' . $fields['last_name']);
    [$fields['birth_date'], $fields['birth_year']] = parse_date(input($source, 'birth_date'));
    return $fields;
}

function lifespan(array $person): string
{
    // Le libellé court des cartes marque « vers », « avant » et « après » avec le signe ≈.
    $birth = $person['birth_year'];
    $death = $person['death_year'];
    $birthApproximate = $birth && preg_match('/^\p{L}/u', $person['birth_date']);
    $deathApproximate = $death && preg_match('/^\p{L}/u', $person['death_date']);
    if (!$birth && !$death) {
        return 'Dates inconnues';
    }
    $birthLabel = ($birthApproximate ? '≈ ' : '') . $birth;
    $deathLabel = ($deathApproximate ? '≈ ' : '') . $death;
    $birthPrefix = match ($person['sex'] ?? null) {
        'male' => 'Né en ',
        'female' => 'Née en ',
        default => 'Naissance en ',
    };
    return match (true) {
        !$death => $birthPrefix . $birthLabel,
        !$birth => 'Décès en ' . $deathLabel,
        default => $birthLabel . ' – ' . $deathLabel,
    };
}

function initials(array $person): string
{
    $letters = mb_substr($person['first_name'], 0, 1) . mb_substr($person['last_name'], 0, 1);
    return $letters === '' ? '?' : mb_strtoupper($letters);
}
