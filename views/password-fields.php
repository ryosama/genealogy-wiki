<label>Mot de passe
    <input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" <?= $passwordRequired ? 'required' : '' ?>>
</label>
<label>Confirmer le mot de passe
    <input type="password" name="confirmation" autocomplete="new-password" minlength="12" maxlength="72" <?= $passwordRequired ? 'required' : '' ?>>
</label>
