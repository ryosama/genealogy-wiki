-- Ces textes restent facultatifs. Retirer les anciennes tâches, y compris celles déjà ignorées.
DELETE FROM person_tasks WHERE field IN ('biography', 'notable_facts');

PRAGMA user_version = 7;
