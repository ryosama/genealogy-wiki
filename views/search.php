<form action="./" method="get" class="search-form <?= ($heroSearch ?? false) ? 'search-hero' : '' ?>" role="search">
    <input type="hidden" name="view" value="personnes">
    <label class="search-field search-name">
        <span>Nom ou prénom</span>
        <span class="input-with-icon"><?= icon('search', 19) ?>
            <input type="search" name="name" maxlength="150" placeholder="Ex. Camille Varenne" value="<?= e($filters['name'] ?? '') ?>">
        </span>
    </label>
    <label class="search-field search-year">
        <span>Année</span>
        <span class="input-with-icon"><?= icon('calendar', 18) ?>
            <input type="text" name="year" inputmode="numeric" pattern="[0-9]{1,4}" maxlength="4"
                placeholder="1880" value="<?= e($filters['year'] ?? '') ?>">
        </span>
    </label>
    <label class="search-field search-when">
        <span>Événement</span>
        <select name="when">
            <option value="">Tous</option>
            <option value="birth" <?= ($filters['when'] ?? '') === 'birth' ? 'selected' : '' ?>>Naissance</option>
            <option value="death" <?= ($filters['when'] ?? '') === 'death' ? 'selected' : '' ?>>Décès</option>
        </select>
    </label>
    <button class="button button-primary search-submit"><?= icon('search', 18) ?> Rechercher</button>
</form>
