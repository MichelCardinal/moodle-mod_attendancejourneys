> **Transition de nom :** cette candidate de travail 1.1.0-rc12 / 2026100804 utilise `mod_attendancejourneys`. Ce ZIP ne constitue pas une mise à niveau ordinaire d’une installation `mod_attendanceplus`. Ne désinstallez pas le prédécesseur et ne remplacez pas simplement son dossier. Les laboratoires existants nécessitent une transition séparée, sauvegardée et validée. Les sauvegardes RC11 originales restent intactes : restaurez-les dans un environnement de récupération RC11 correspondant avant transition, puis créez une nouvelle sauvegarde. La restauration automatique native des anciennes sauvegardes sous le nouveau composant n’est pas revendiquée.

# Installer et essayer Parcours d’assiduité

Ces instructions accompagnent la candidate `1.1.0-rc10` (`2026100802`) pour Moodle 4.5 à 5.3. Utilisez l’archive correspondante et validez d’abord dans un laboratoire. Rien n’est publié.

## Installation

Utilisez le ZIP AttendanceJourneys correspondant à la livraison. Dans Moodle : Administration du site → Plugins → Installer des plugins. Téléversez le ZIP et suivez la vérification puis la mise à niveau. Le composant doit être `mod_attendancejourneys`.

Pour une installation manuelle, placez le dossier `attendancejourneys` dans `mod/` sous Moodle 4.5 ou 5.0; sous Moodle 5.1 à 5.3, dans `public/mod/`. Ouvrez ensuite les notifications d’administration. Aucun lancement de Composer n’est nécessaire pour installer le plugin.

Avant une mise à niveau d’un site utilisé, sauvegardez sa base, moodledata et son code. Essayez la mise à niveau sur une copie avant de l’appliquer au site. Un retour à une ancienne version exige la restauration d’une sauvegarde cohérente; ne remplacez pas seulement le dossier du plugin par un ancien ZIP.

## Premier essai

1. Créez un cours d’essai et inscrivez un enseignant ainsi que trois étudiants fictifs.
2. Ajoutez une activité Parcours d’assiduité. Le mode Léger crée un parcours principal simple avec prise de présence et clôture individuelle; utilisez le mode Professionnel pour plusieurs parcours distincts et les opérations avancées.
3. Créez une séance de 60 minutes passée ou en cours.
4. Avec le rôle enseignant, saisissez une présence complète, une absence et une présence partielle de 15 minutes d’absence. Enregistrez et vérifiez le rapport.
5. Pour essayer l’autodéclaration, activez-la dans les paramètres, créez une autre séance admissible et connectez-vous avec un étudiant fictif. La déclaration doit attendre l’approbation enseignante.

La présence complète représente 100 % du temps, l'absence zéro et la présence partielle la durée moins les minutes absentes. Le pourcentage reste provisoire sans note finale tant que le parcours individuel n'est pas clôturé. Attendez la fin de toutes les séances exigées et résolvez les présences et approbations manquantes avant cette clôture.

Les rôles Moodle contrôlent les droits. Un étudiant ne doit pas consulter le rapport d’un autre étudiant ni les opérations d’administration. Vérifiez vos rôles personnalisés si vous en utilisez.

## Exemple illustré

Suivez le [guide utilisateur](GUIDE_UTILISATEUR_FR.md) pour la théorie à 60 %, le laboratoire à 80 % et la clôture explicite. Une ancienne sauvegarde de démonstration conserve sa politique historique; sa restauration ne convertit pas automatiquement la notation en obligations indépendantes.

## Points de configuration

- Les feuilles de saisie utilisent des pages de 100 participants. Enregistrez avant de changer de page.
- Les paramètres de notes et d’achèvement doivent correspondre à votre usage pédagogique. Une déclaration en attente ne vaut pas une présence approuvée.
- Les intégrations calendrier, notes et achèvement dépendent des options activées.
- Les opérations d’effacement et de réinitialisation retirent des données; lisez leur aperçu et leur confirmation.
- Utilisez les outils de confidentialité de Moodle pour les demandes d’export ou d’effacement. Définissez votre politique de conservation selon votre contexte.

Le plugin fonctionne localement sans service externe. Une validation sur un thème ou une extension tierce reste nécessaire si votre installation en dépend.

Les personnalisations locales français/espagnol et les futures traductions AMOS restent distinctes du ZIP installable en anglais.
