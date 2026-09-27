# SAMR

Intranet du service médical d'un serveur de jeu de rôle FiveM.
Application PHP 8.3 / PDO / MariaDB, sans gestionnaire de dépendances.
Authentification Discord OAuth2, droits adossés aux rôles Discord.

Modules : dispatch, dossiers patients (trauma, gynéco, laboratoire),
rendez-vous, calendrier, facturation, pharmacie, personnel et salaires,
formations, blacklist, documents, et les tests PPA (`ppa2/`).

## Attribution obligatoire

L'usage de ce logiciel est conditionné au maintien d'une mention visible
« Développé par Surf Smart » avec un lien vers https://surfsmart.fr sur chaque
page publiquement accessible. La mention est déjà en place dans `layout.php`.
Son retrait résilie la licence. Voir `LICENSE` et `NOTICE`.

## Prérequis

PHP 8.3 avec PDO MySQL, MariaDB ou MySQL, un serveur web servant la racine du
dépôt. Aucun composer, aucun build.

## Installation

```bash
mysql -u root -e "CREATE DATABASE samr CHARACTER SET utf8mb4;"
mysql -u root samr < base-samr_AAAAMMJJ.sql
cp .env.example .env
```

Renseigner ensuite :

| Fichier | Valeurs |
| --- | --- |
| `config.php` | `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` |
| `.env` | les identifiants Discord OAuth et le token du bot |
| `ppa2/config.php` | les webhooks Discord des résultats PPA (vidés) |

Toutes les valeurs livrées sont des marqueurs `REMPLACER_*` ou des chaînes
vides.

## Points d'attention

**Le bot Discord doit être membre du serveur.** Le callback lit les rôles du
membre via l'API REST avec le token du bot. Sans cela, personne ne peut se
connecter.

**L'accès est conditionné à un rôle Discord.** `DISCORD_ROLE_SAMR_ID` donne
l'accès standard, `DISCORD_ROLE_STAFF_ID` donne un accès administrateur
complet. Ces identifiants sont ceux de l'ancien serveur : remplacez-les.

**Les fichiers déposés ne sont pas dans le dépôt.** `docs/` et `uploads/`
(480 Mo de documents générés et de pièces jointes médicales) sont exclus. La
base y fait référence : sans eux, les enregistrements pointent dans le vide.

**Le sous-module `irs/` n'est pas livré.** Il avait été dépublié côté serveur
web et remplacé par un outil distinct.

**Fuseau horaire et mode SQL sont forcés dans `db.php`** (`Europe/Paris` et
`NO_ENGINE_SUBSTITUTION`). Le second reproduit la permissivité de l'ancien
hébergeur : le retirer fait apparaître des erreurs sur du code hérité.

**Les sessions utilisent un répertoire dédié.** La configuration est dupliquée
dans `auth.php` et `ppa2/bootstrap.php` et doit rester identique dans les deux,
sinon les tests PPA renvoient vers l'accueil.
