> **Transition de nom :** cette version 1.1.0 / 2026100806 utilise `mod_attendancejourneys`. Ce ZIP ne constitue pas une mise à niveau ordinaire d’une installation `mod_attendanceplus`. Ne désinstallez pas le prédécesseur et ne remplacez pas simplement son dossier. Les laboratoires existants nécessitent une transition séparée, sauvegardée et validée. Les sauvegardes RC11 originales restent intactes : restaurez-les dans un environnement de récupération RC11 correspondant avant transition, puis créez une nouvelle sauvegarde. La restauration automatique native des anciennes sauvegardes sous le nouveau composant n’est pas revendiquée.

# Installation et mise à niveau

La version 1.1.0 (2026100806) adopte Parcours d’assiduité et vise Moodle 4.5 à 5.3. Les fonctions pédagogiques sont conservées; les laboratoires existants nécessitent une transition du composant validée séparément. La publication sur GitHub ne constitue pas une approbation Moodle Marketplace. Utilisez les versions PHP et de base de données prises en charge par votre Moodle. Depuis Moodle 5.1, l’installation manuelle utilise `public/mod/attendancejourneys`; le dossier racine du ZIP est `attendancejourneys`.

Le ZIP ne contient désormais que les chaînes anglaises, conformément aux consignes de soumission Moodle. Les traductions françaises et espagnoles sont conservées séparément pour AMOS après approbation. Avant de mettre à niveau un laboratoire utilisant les anciennes traductions incluses, les préserver sous forme de personnalisations locales dans Moodledata (`lang/fr_local/attendancejourneys.php` et `lang/es_local/attendancejourneys.php`). Fusionner les personnalisations existantes plutôt que les écraser, puis purger les caches de langue. Les packs locaux priment sur les futures traductions officielles et devront être revus lors de leur disponibilité dans AMOS. Le plugin fonctionne en anglais sans ces packs facultatifs.

## Activités historiques et changement de notation

La mise à niveau de la base ne convertit pas les activités existantes : leur mode de calcul, leurs notes et leurs clôtures sont conservés. Les activités nouvellement créées utilisent un parcours principal obligatoire et des notes distinctes par parcours, publiées à la clôture explicite.

Une personne disposant des droits de modification des activités et de gestion des parcours peut ouvrir **Examiner le mode de notation**, dans l’activité historique, même en interface légère. En groupes séparés, l’accès à tous les groupes est également requis. L’examen est en lecture seule : séances, fiches, clôtures, absences justifiées, notes existantes et protections du carnet.

La conversion directe est limitée à une activité sans résultat final, sans note numérique (zéro compris), sans verrou/modification manuelle, sans décision d’achèvement existante et sans politique historique incompatible. Elle conserve le premier élément de note, les fiches et leurs minutes, ainsi que le parcours et les affectations lorsqu’ils existent. Sans parcours, elle crée un parcours principal et lui rattache les séances. Le seuil et le public proposés sont affichés avant confirmation.

Les notes restent absentes jusqu’à clôture. L’achèvement automatique requiert ensuite la clôture et la réussite de tous les parcours obligatoires attribués; les anciennes règles fondées sur les fiches sont remplacées. Le changement ne s’annule pas dans le formulaire des paramètres. Si les données ont changé depuis l’examen, la confirmation est refusée et un nouvel examen est demandé.

Plusieurs parcours historiques, des séances mélangées dans/hors parcours, des équivalences, des résultats finalisés ou des absences justifiées anciennement créditées/exclues nécessitent un plan de conservation distinct. L’examen explique ces limites; l’activité reste utilisable dans son mode historique. Ne pas supprimer des notes ou des historiques pour contourner ce contrôle.

## Avant l’installation

1. Utiliser d’abord un Moodle de test de même version que la production représentatif du site de production.
2. Sauvegarder la base de données et le répertoire Moodledata.
3. Vérifier que le ZIP contient directement le dossier `attendancejourneys`.
4. Confirmer que Moodle identifie le paquet comme `mod_attendancejourneys`; ne pas renommer son composant ou son répertoire.

## Installation ou mise à niveau

1. Ouvrir Administration du site → Plugins → Installer des plugins.
2. Déposer le nouveau ZIP.
3. Vérifier que Moodle annonce `mod_attendancejourneys`.
4. Continuer la mise à niveau de la base de données.
5. Purger les caches Moodle si un ancien libellé demeure affiché.

## Vérifications minimales après mise à niveau

1. Ouvrir une activité existante.
2. Ouvrir Sessions, Parcours, Prise de présence, Rapports et Administration.
3. Créer une session temporaire et enregistrer une présence.
4. Vérifier la fiche individuelle et le journal d’audit.
5. Confirmer la note et l’achèvement avec un compte de test.
6. Effectuer une sauvegarde et une restauration dans un cours de test.

## Retour arrière

Ne jamais installer un ancien ZIP par-dessus une base ayant déjà exécuté une mise à niveau plus récente. Pour revenir en arrière, restaurer ensemble le code, la base de données et Moodledata depuis la même sauvegarde préalable.


La version 2026100710 ajoute l’état d’annulation et son historique. Les anciennes séances restent planifiées; aucune note finale n’est recalculée. L’annulation exige le nouveau mode de notes par parcours, la réouverture des clôtures actives et la résolution des équivalences en attente ou approuvées. Sans données utilisateurs, la sauvegarde conserve l’état mais omet les auteurs et justifications.


## Décisions individuelles et tentatives personnelles explicites

La version 2026100711 ajoute les périodes individuelles, dispenses de parcours entier et leur historique de décision. Les participants existants conservent leur obligation par défaut, sans exclusion ni dispense déduite. La version 2026100712 ajoute un lien racine explicite pour les reprises et les décisions personnelles d’ouverture. Tous les anciens parcours restent indépendants : aucune reprise n’est déduite d’un nom, d’un numéro d’affectation ou d’une ancienne date. Présences, clôtures et notes protégées existantes sont conservées.

Une reprise utilise de nouvelles séances dans la même obligation logique, avec un seul élément de note et le seuil racine. Son ouverture explicite retire la note définitive précédente et réévalue l’achèvement automatique; son dernier résultat clôturé remplace le précédent même s’il est inférieur. Les historiques restent conservés. Ouverture, réouverture et correction respectent les verrouillages et notes imposées de Moodle.

Les sauvegardes avec utilisateurs remappent les identifiants de racine et de reprise ainsi que les décisions personnelles. Sans utilisateurs, les liens structurels restent conservés, mais les ouvertures personnelles et historiques de décision sont omis. Le décalage des dates du cours déplace les périodes pédagogiques, pas les dates d’audit. Effacement des données personnelles et anonymisation des acteurs couvrent ces nouvelles données.

Après mise à niveau, vérifiez une première tentative réussie, ouvrez une reprise justifiée et confirmez le retrait de l’ancienne note. Planifiez de nouvelles séances, clôturez la reprise et vérifiez l’élément de note unique, les statuts courant/historique des rapports, l’achèvement, les exports et la sauvegarde/restauration. Testez séparément théorie et laboratoire avec des seuils différents. Aucune activité existante n’est convertie automatiquement par cette mise à niveau.
