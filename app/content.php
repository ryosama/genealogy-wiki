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
    // Construire les colonnes autorisées avant de préparer l'écriture SQL.
    $fields = [
        'first_name' => required_text($source, 'first_name', 'Le prénom', 100),
        'last_name' => required_text($source, 'last_name', 'Le nom', 100),
        'sex' => $sex === '' ? null : $sex,
        'birth_place' => short_text($source, 'birth_place', 180),
        'death_place' => short_text($source, 'death_place', 180),
        'residence' => short_text($source, 'residence', 180),
        'biography' => bio_html(input($source, 'biography')),
        'notable_facts' => bio_html(input($source, 'notable_facts')),
    ];
    $fields['search_name'] = normalized_name(full_name($fields));
    [$fields['birth_date'], $fields['birth_year']] = parse_date(input($source, 'birth_date'));
    [$fields['death_date'], $fields['death_year']] = parse_date(input($source, 'death_date'));
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
    return match (true) {
        !$death => 'Né·e en ' . $birthLabel,
        !$birth => 'Décès en ' . $deathLabel,
        default => $birthLabel . ' – ' . $deathLabel,
    };
}

function initials(array $person): string
{
    return mb_strtoupper(mb_substr($person['first_name'], 0, 1) . mb_substr($person['last_name'], 0, 1));
}
