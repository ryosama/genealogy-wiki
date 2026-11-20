<?php
declare(strict_types=1);

// Durée de validité du calcul et nombre maximal d'onglets conservés par session.
const REGISTRATION_CAPTCHA_LIFETIME = 600;
const REGISTRATION_CAPTCHA_LIMIT = 4;

function registration_challenge(): array
{
    $now = time();
    $previous = array_filter($_SESSION['registration_captchas'] ?? [],
        static fn (array $challenge): bool => $challenge['expires'] > $now);
    $challenges = array_slice($previous, -(REGISTRATION_CAPTCHA_LIMIT - 1), null, true);
    $id = bin2hex(random_bytes(16));
    $left = random_int(2, 19);
    $right = random_int(2, 19);
    $challenges[$id] = ['answer' => (string) ($left + $right), 'expires' => $now + REGISTRATION_CAPTCHA_LIFETIME];
    $_SESSION['registration_captchas'] = $challenges;
    return ['id' => $id, 'left' => $left, 'right' => $right];
}

function verify_registration_challenge(array $form): void
{
    $id = input($form, 'captcha_id');
    $challenge = $_SESSION['registration_captchas'][$id] ?? null;
    // Le calcul est consommé même si la réponse est fausse.
    unset($_SESSION['registration_captchas'][$id]);
    if (!$challenge || $challenge['expires'] <= time() || input($form, 'website') !== ''
        || !hash_equals($challenge['answer'], input($form, 'captcha'))) {
        throw new InputException('Le contrôle anti-spam a échoué. Réponds au nouveau calcul.');
    }
}
