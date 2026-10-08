# Exécuter les tests automatisés

## Validation du composant renommé RC12 — 8 octobre 2026

Le composant `mod_attendancejourneys` réussit 225 tests / 1 675 assertions sur chacune des sept configurations natives : Moodle 4.5, 5.0, 5.1, 5.2 et 5.3 sous PHP 8.3/MySQL, puis Moodle 5.3 sous PHP 8.4/MySQL et PHP 8.3/PostgreSQL 17. Les contrôles officiels phplint, savepoints, validate, phpcs et phpdoc passent; cinq modules AMD sont reconstruits par Grunt natif.

Les transitions sur copies complètes conservent les enregistrements de 510 tables sous 4.5 et 515 tables sous 5.3 après normalisation des références techniques prévues. Le retour au prédécesseur est éprouvé sur les deux copies. La nouvelle sauvegarde limitée au préfixe et sa restauration conservent également les 510 tables de la copie 4.5, sans supprimer de base. Les collisions cibles et la conservation des personnalisations linguistiques sont contrôlées séparément. Ces procédures concernent les laboratoires avant publication; le ZIP ne met pas directement à niveau un autre composant.

Les 24 captures des guides proviennent de l’activité renommée, avec participants fictifs, Chrome natif et Boost : huit par langue. Le rendu des guides intégrés anglais, français et espagnol est revu. Seize tables du plugin et quatorze tables de notes sont inchangées après les captures. VoiceOver reste différé. Les résultats RC11 ci-dessous décrivent la validation historique du prédécesseur, sans nouvelle certification de ce composant.

## Validation de l’administration RC11 — 8 octobre 2026

La suite fonctionnelle RC11 a réussi **225 tests et 1 675 assertions** sur les sept configurations du tableau ci-dessous. Les nouveaux cas couvrent les politiques de calcul, l’enregistrement administratif natif, les valeurs imposées, les seuils conservés et les permissions distinctes. La migration privée RC10 vers RC11 a conservé les données pédagogiques et copié les permissions existantes, y compris les interdictions locales. Huit routes réelles ont refusé les demandes privées de la permission requise. Les pages administratives ont été rendues en anglais, français et espagnol. Il s’agit de validations locales, pas d’une certification Marketplace. La revue visuelle de l’administration reste comprise dans la revue avant publication; VoiceOver est différé.

## Référence validée RC9 — 8 octobre 2026

La suite RC9 conservée a réussi **216 tests et 1 614 assertions** sur chacune des configurations ci-dessous. La RC10 modifie la documentation et les messages des parcours; elle conserve cette référence et utilise des contrôles ciblés distincts pour ces changements. Ces résultats ne certifient pas toutes les versions PHP/base de données ni l’admission Moodle Marketplace.

| Moodle | PHP | Base de données |
|---|---|---|
| 4.5.13 | 8.3.30 | MySQL 8.0.44 |
| 5.0.9, 5.1.6, 5.2.2, 5.3 | 8.3.30 | MySQL 8.4.11 |
| 5.3 | 8.4.17 | MySQL 8.4.11 |
| 5.3 | 8.3.30 | PostgreSQL 17.11 |

La suite couvre les calculs, permissions, confidentialité, sauvegarde/restauration, notes, achèvement, calendrier, historique, nouvelles tentatives et nettoyage à la désinstallation. Conservez les preuves et notez l’archive exacte et l’environnement de chaque nouvelle exécution.

## Environnement de développement isolé

Utiliser une copie de développement de la branche Moodle à tester avec ses dépendances Composer de développement. Réserver une base ou un préfixe de tables distinct et un dossier de données distinct pour PHPUnit. Ne jamais utiliser les données ordinaires du site pour PHPUnit. Configurer PHP selon les exigences de la branche, notamment `max_input_vars=5000` au minimum.

Exemple de configuration de développement :

```php
$CFG->phpunit_dbname = 'attendancejourneys_phpunit';
$CFG->phpunit_prefix = 'phpu_';
$CFG->phpunit_dataroot = '/chemin/separe/attendancejourneys-phpunitdata';
```

Placer l’exécutable PHP voulu en tête de PATH. Depuis la racine du dépôt Moodle, initialiser avec la commande correspondant à la branche :

```bash
# Moodle 4.5 et 5.0.
php admin/tool/phpunit/cli/init.php

# Moodle 5.1 et ultérieur.
php public/admin/tool/phpunit/cli/init.php
```

Puis lancer uniquement la suite du plugin :

```bash
php -d max_input_vars=5000 vendor/bin/phpunit --testsuite mod_attendancejourneys_testsuite
```

L’initialisation réinitialise l’environnement PHPUnit réservé. Ne pas exécuter PHPDoc Checker en même temps que PHPUnit sur le même noyau : son plugin temporaire peut perturber la découverte des composants.

## Contrôles complémentaires

Utiliser Moodle Plugin CI pour la syntaxe PHP, les points de mise à niveau, la structure du plugin, le standard de code Moodle et PHPDoc; utiliser Moodle Grunt pour les builds AMD, ESLint et les styles CSS. Le paquet ne contient pas de modèle Mustache. Conserver les sources de traduction françaises et espagnoles hors du plugin installable pour AMOS, en contrôlant leurs clés et paramètres par rapport à l’anglais.

Vérifiez aussi installation et mise à niveau natives, sauvegarde/restauration, parcours navigateur enseignant/étudiant, téléchargements, clavier et affichage adaptatif. Les preuves RC9 comprennent des téléchargements Excel/PDF finalisés et des parcours représentatifs Chrome à 320 pixels CSS; elles restent distinctes des contrôles documentaires et des libellés RC10. Les captures du guide utilisent des données fictives et ne certifient pas tous les écrans, thèmes ou navigateurs. VoiceOver reste différé. Reprenez les vérifications appropriées lorsque le comportement ou les environnements pris en charge changent. Ces contrôles locaux ne valent pas admission Marketplace ni publication de traductions AMOS.
