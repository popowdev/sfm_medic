# Récapitulatif de nettoyage - Site SAMR

Ce projet a été nettoyé avant transfert afin de retirer/remplacer les informations sensibles demandées.

## Fichiers modifiés

### `config.php`
À renseigner par le prochain développeur :
- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DISCORD_CLIENT_ID`
- `DISCORD_CLIENT_SECRET`
- `DISCORD_BOT_TOKEN`
- `DISCORD_REDIRECT_URI`
- `DISCORD_GUILD_ID`
- `DISCORD_ROLE_SAMR_ID`

Les valeurs réelles ont été remplacées par des placeholders `CHANGE_ME_...`.

### `irs/config/config.php`
À renseigner par le prochain développeur :
- `db_host`
- `db_name`
- `db_user`
- `db_pass`

Les valeurs réelles ont été remplacées par des placeholders `CHANGE_ME_...`.

### `ppa2/config.php`
À renseigner par le prochain développeur :
- `discord_bot_token`

La valeur réelle a été remplacée par `CHANGE_ME_DISCORD_BOT_TOKEN`.

Note : les webhooks Discord présents dans ce fichier n'ont pas été supprimés, car ils n'étaient pas inclus dans la liste des éléments à remplacer.

### `discord_roles.php`
À renseigner par le prochain développeur :
- tous les IDs des rôles Discord dans `DISCORD_ROLES`

Les anciens IDs ont été remplacés par des placeholders du type :
- `CHANGE_ME_DISCORD_ROLE_ID_CHIRURGIEN`
- `CHANGE_ME_DISCORD_ROLE_ID_CARDIOLOGUE`
- etc.

### `ppa.php`
À renseigner par le prochain développeur :
- l'ID du rôle Discord utilisé pour les permissions PPA

L'ancien ID a été remplacé par :
- `CHANGE_ME_DISCORD_ROLE_ID_PPA`

### `ppa2/bootstrap.php`
À renseigner par le prochain développeur :
- l'ID du rôle Discord utilisé pour les permissions PPA

L'ancien ID a été remplacé par :
- `CHANGE_ME_DISCORD_ROLE_ID_PPA`

## Contrôles effectués

- Vérification de syntaxe PHP effectuée sur les fichiers modifiés.
- Recherche de tokens Discord de type bot token après remplacement : aucun token Discord bot trouvé.
- Recherche des anciens identifiants de base de données après remplacement : aucun identifiant BDD réel trouvé.
- Recherche des anciens IDs Discord numériques isolés après remplacement : aucun ID numérique Discord isolé trouvé dans les fichiers PHP.

## Points non modifiés volontairement

Les éléments suivants n'ont pas été modifiés, car ils n'étaient pas dans la liste demandée :
- Webhooks Discord.
- URLs de production.
- Fichiers uploadés, signatures, images ou documents utilisateurs.
- Données SQL éventuelles ou contenus de base de données non inclus dans les fichiers de configuration.

