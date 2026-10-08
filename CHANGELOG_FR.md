# Historique des versions

## 1.1.0-rc12 — 2026-10-08

Adoption de Parcours d’assiduité / mod_attendancejourneys, version technique 2026100804. Calculs et parcours pédagogiques conservés. Validation du composant renommé sur sept configurations natives Moodle 4.5–5.3; transitions sauvegardées et retours éprouvés séparément sur copies privées complètes. Actualisation des 24 captures fictives et des guides multilingues; chaînes anglaises dans le plugin, traductions préparées séparément. Ce ZIP ne met pas directement à niveau le composant prédécesseur. Les entrées antérieures décrivent ce prédécesseur non publié; publication en attente.

## 1.1.0-rc11 — 2026-10-08

Version technique : `2026100803`. Séparation des politiques principales, de la terminologie et des réglages historiques; guide administrateur accessible sans activité. Validation commune du calcul et de la transmission de note, avec interdiction de verrouiller le calcul requis en position désactivée. Politique facultative des seuils propres aux nouveaux parcours, sans modification des seuils ni résultats existants. Permissions distinctes de clôture, réouverture, reprise et approbation d’équivalence, héritées des permissions existantes à la mise à niveau. Aides et guides harmonisés en anglais, français et espagnol. Aucune conversion des données pédagogiques. Publication en attente.

## 1.1.0-rc10 — 2026-10-08

Version technique : `2026100802`. Guides anglais, français et espagnol actualisés : premier parcours de bout en bout, captures fictives actuelles, clôture, nouvelles tentatives, états des feuilles, listes d’attente et équivalences. Les obligations indépendantes affichent une information au lieu de l’avertissement de conflit historique; les publics sélectionnés et automatiques sont correctement nommés. Aucun changement de schéma, de calcul ni de résultat final. Les traductions AMOS et la documentation publique sont préparées séparément; rien n’est publié. Les liens entre guides respectent les droits du rôle; la mise en page tient sur 320 pixels CSS. Les tests de documentation sont désormais indépendants des autres classes de tests.

## 1.1.0-rc9 — 2026-10-08

Version technique : `2026100801`. Les séances sans participant actuel sont affichées de façon neutre et exclues des compteurs et filtres de saisie à faire. Les séances annulées gardent leur statut d’annulation. Les présences et résultats définitifs restent inchangés.

## 1.1.0-rc8 (2026100800) — 2026-10-08 (en préparation; recette finale en attente)

- Afficher un repère neutre lorsque la date d’audit manque dans le rapport individuel ou la saisie; préserver dates stockées, minutes, notes et historique.
- Conserver les archives RC6 et RC7. Ce point de version ne transforme ni schéma ni données.

## 1.1.0-rc7 — 2026-10-07 (candidate locale; recette finale en attente)

- Ajouter les périodes individuelles, dispenses entières et reprises personnelles avec aperçu, confirmation, historique et refus des confirmations obsolètes.
- Conserver les seuils indépendants théorie/laboratoire et une seule note par obligation. Une reprise hérite du seuil racine et utilise de nouvelles séances; son ouverture retire la note précédente jusqu’à la clôture, puis son dernier résultat la remplace même s’il est inférieur.
- Distinguer les résultats courants publiés des anciennes tentatives dans les rapports et exports; conserver présences et clôtures historiques.
- Couvrir les décisions personnelles par Privacy, sauvegarde/restauration avec ou sans utilisateurs, migration conservatrice et protections du carnet de notes et de l’achèvement.
- Permettre une équivalence explicite plafonnée sur les présences enregistrées d’un parcours source dispensé, sans double crédit.
- Conserver les valeurs Excel numériques, les dates d’audit absentes vides et les titres PDF longs sur plusieurs lignes; mettre à jour la documentation EN/FR/ES et séparer les traductions.
- La version 2026100713 ajoute un point de version sans modifier le schéma ni les données de développement 2026100712. RC6 reste conservée.
- Recette Chrome et vérification des règles Marketplace actuelles en attente. Aucune publication GitHub, Marketplace ou AMOS.

## 1.1.0-dev11 — 2026-10-07 (développement non publié)

- Permettre la clôture et la réouverture individuelles autorisées dans les nouvelles activités Light pour publier la note du parcours principal; conserver les restrictions historiques et la protection des opérations avancées. Corriger les règles, matrices et scénarios de recette dans les trois langues.
- Conserver un seul conteneur défilant nommé et accessible au clavier autour des tableaux lorsque Moodle ajoute un conteneur responsive; préserver les options du tableau appelant et les anciennes API Moodle.
- Afficher un résultat provisoire neutre dans le sommaire individuel tant qu’un parcours en nouveau mode reste ouvert; conserver le pourcentage provisoire et le comportement des activités historiques.
- Ajouter l’annulation et le rétablissement des séances avec justification, contrôle des droits/groupes et refus des confirmations périmées. Conserver les présences et résultats historiques; exclure le temps annulé du calcul, de la saisie, du calendrier et des exports.
- Sauvegarder et restaurer l’état d’annulation; inclure l’historique personnel uniquement avec les données utilisateurs et couvrir son effacement/anonymisation avec Moodle Privacy.
- Appliquer les droits sur tout le public aux éditions, effacements et sources des opérations en lot; vérifier les groupes et participants fixes de destination avant ajout/déplacement.
- Exiger un parcours pour les séances du nouveau mode, conserver les publics indépendants en mode historique, protéger les destinations clôturées et maintenir des révisions de séances croissantes.
- Utiliser le calcul partagé par parcours dans les exports collectifs pour les annulations et équivalences approuvées; préserver le mode historique et les résultats finaux figés.
- Expliquer les notes indépendantes, l'achèvement des parcours obligatoires et la distinction absence justifiée/dispense de séance dans les guides anglais, français et espagnol; identifier les anciennes captures comme illustrations historiques.
- Aligner les aperçus initiaux et interactifs de la feuille sur le calcul enregistré : absence justifiée, dispense de séance et politiques historiques. Traduire le calcul et masquer les champs et étiquettes des minutes inutiles.
- Autoriser les libellés du calcul à revenir à la ligne sur les petits écrans, y compris l'explication de la dispense de séance.
- Traduire les noms de fichiers exportés et titres des rapports PDF avec les chaînes Moodle existantes.
- Exporter les minutes et pourcentages Excel en cellules numériques avec l'API Excel native de Moodle; conserver les identifiants et remarques comme texte sans les interpréter comme formules.


## 1.1.0-dev10 — 2026-10-07 (développement non publié)

- Protéger les suppressions de séances et parcours référencés par des équivalences ou des résultats historiques; appliquer les droits de groupes aux suppressions de séances.
- Conserver chaque approbation, refus et révocation d’équivalence avec son auteur, sa justification et sa date, dans la même transaction que la décision courante. Refuser les confirmations périmées.
- Sauvegarder et restaurer l’historique avec les participants, auteurs et équivalences correctement associés. Couvrir sa découverte, son export, son effacement et l’anonymisation des auteurs avec Moodle Privacy.
- Reprendre uniquement la dernière décision historique connue, clairement identifiée comme un historique partiel; les décisions déjà remplacées ne peuvent pas être reconstituées. Ne pas recalculer les notes finales.
- Sources alpha non empaquetées et non publiées; compatibilité et validation navigateur finales encore à terminer.

## 1.1.0-dev9 — 2026-10-07 (développement non publié)

- Ajouter des éléments de note stables par parcours, des seuils distincts et l’achèvement de tous les parcours obligatoires; publier les notes à la clôture explicite et rouvrir un parcours sans retirer le résultat d’un autre.
- Créer un parcours principal obligatoire pour les nouvelles activités; distinguer le public inscrit des affectations explicites, y compris une liste explicitement vide.
- Distinguer absence justifiée et dispense accordée par le personnel dans le nouveau mode; conserver les paramètres historiques et les résultats figés.
- Garder les contrôles de clôture sur les séances futures/non renseignées et les approbations en attente; respecter la décision exacte au seuil malgré les arrondis du carnet.
- Signaler les notes verrouillées ou remplacées manuellement au personnel et appliquer les groupes séparés aux pages de gestion des parcours.
- Ajouter un examen en lecture seule de la conversion historique et une conversion explicite des activités non terminées compatibles, avec conservation des fiches et de l’élément de note initial. Préserver les historiques incompatibles ou finalisés; revérifier les droits, la clé de session et l’état examiné.
- Ajouter des champs de schéma conservés par sauvegarde/restauration. Ces sources sont alpha, sans archive ni publication; l’audit final de compatibilité et navigateur reste à réaliser.

## 1.1.0-rc6 — 2026-10-07

- Coordonner les modifications des locaux avec leur affectation aux sessions via le verrou d’activité Moodle. Une suppression concurrente revérifie l’utilisation du local après la fin de l’enregistrement de la session.
- Réserver l’export de la fiche individuelle et le lien vers le rapport collectif aux utilisateurs autorisés à consulter les rapports. Les étudiants conservent leur fiche et un retour à l’accueil de l’activité; les droits serveur restent inchangés.
- Retirer les références comparatives inutiles des guides d’installation et actualiser les résultats navigateur. Conserver les mentions de droit d’auteur et de licence.
- Vérifier la concurrence des locaux et les commandes selon le rôle via les vrais contrôleurs. Contrôler dans Chrome la saisie enseignant sous Boost et les rapports étudiant sous New Learning réparé; les preuves Excel/PDF et à 320 pixels restent applicables au code inchangé.
- Aucun changement de schéma. VoiceOver reste différé. La version demeure candidate; aucun dépôt public ni dossier Moodle n’a été soumis.

## 1.1.0-rc5 — 2026-10-07

- Présenter les PDF sous forme de tableaux de champs par enregistrement avec la bibliothèque PDF de Moodle et des en-têtes répétés entre les pages. Conserver les champs vides, les zéros et les remarques comme texte échappé.
- Couvrir les rapports individuel, collectif, de parcours, détaillé par parcours et d’audit. Les autres formats conservent leur export natif.
- Valider 85 tests et 537 assertions sur sept configurations Moodle 4.5–5.3, ainsi que les exports et les refus d’accès dans les contrôleurs. Aligner les 868 chaînes anglaises, françaises et espagnoles.
- Aucun changement de schéma de base de données. La finalisation des téléchargements dans le navigateur et le parcours complet à 320 pixels stables restent à vérifier. Version candidate.


## 1.1.0-rc4 — 2026-10-07

- Permettre le retour à la ligne du nom et du courriel dans les cartes étroites et limiter le sélecteur natif d’export à son conteneur.
- Actualiser les guides de tests anglais, français et espagnol : matrice de 81 tests, préparation PHPUnit selon la branche et limites navigateur connues. Inclure Moodle 5.3 dans l’installation manuelle française.
- Aucun changement de comportement PHP ni de schéma. Moodle Stylelint valide le CSS modifié. Cette livraison reste une candidate.

## 1.1.0-rc3 — 2026-10-07

- Supprimer les instances avec l’API complète de Moodle avant de désinstaller le plugin, afin de ne pas laisser de lignes d’achèvement orphelines.
- Utiliser les actions de format de cours actuelles sur Moodle 5.2+ et l’API historique prise en charge sur les branches antérieures.
- Tester le nettoyage de l’achèvement, des fichiers, notes et contextes, la préservation des autres activités et un second appel sans instance.
- Aucun changement de schéma. Le cycle natif de désinstallation/réinstallation est vérifié dans un laboratoire isolé.

## 1.1.0-rc2 — 2026-10-07

- Présenter les détails de présence dans la langue de consultation pour le rapport individuel, l’audit administratif et son export; préserver les instantanés originaux et les notes du personnel.
- Corriger le libellé du filtre individuel pour parler des sessions. Synchroniser les chaînes anglaises, françaises et espagnoles.
- Ajouter des régressions pour tous les statuts, la conservation de l’historique, les remarques multilignes, les formats inconnus et l’échappement HTML.
- Aucun changement de schéma. Version candidate à évaluer avant toute utilisation en production.

## 1.1.0-rc1 — 2026-10-07

- Étendre la compatibilité à Moodle 5.3. Valider 79 tests et 498 assertions sur les cinq branches de Moodle 4.5 à 5.3, dont Moodle 5.3 avec PHP 8.4 et PostgreSQL 17.
- Appliquer les standards de code et de documentation Moodle; reconstruire les modules AMD avec Moodle Grunt et utiliser le dialogue de confirmation Moodle pour les modifications collectives de présences.
- Fournir uniquement les chaînes anglaises dans le plugin installable. Conserver séparément les traductions françaises et espagnoles pour AMOS et les personnalisations linguistiques des laboratoires.
- Exporter les actions attribuées au personnel via l’API Privacy; effacer l’attribution des parcours lors d’une suppression collective, traiter les historiques orphelins, refuser les contextes autres que les modules et coordonner les suppressions avec les saisies de présences.
- Aucun changement de schéma. Version candidate : vérifications d’installation, de mise à niveau et de thème encore en cours.

## 1.0.4 — 2026-09-07

- Corriger l’ouverture normale des formulaires et confirmations administratives sans clé de session; conserver la vérification de clé pour les soumissions.
- Limiter l’historique individuel des révisions à l’activité consultée et conserver les noms des sessions lorsque le rapport est filtré.
- Vérifier la migration directe depuis 1.0.0, la sauvegarde/restauration native entre laboratoires avec utilisateurs, notes et achèvement, et les parcours navigateur enseignant/étudiant. Ajouter les régressions de navigation et d’isolation des historiques entre cours.
- Aucun changement de schéma de base de données.

## 1.0.3 — 2026-09-07

- Saisie par pages de 100 participants, écritures et approbations limitées à la page, coordination des opérations administratives avec les saisies, focus clavier renforcé et guides de démarrage. Validation de Moodle 5.0/5.1, de l’isolation des pages, de la concurrence administrative et de la restauration de démonstration.
- Aucun changement de schéma.

## 1.0.2 — 2026-09-07

- Verrou Moodle partagé par activité pour les saisies des enseignants et les autodéclarations individuelles ou groupées. Les versions et les présences sont relues sous verrou : protection contre les formulaires périmés et les insertions concurrentes de notes.
- Clé de session valide exigée à la soumission et version explicite de la feuille pour les enseignants.
- Rapport collectif allégé : moins de requêtes d’équivalences et de champs chargés pour les grandes classes.
- Suppression d’un repère principal imbriqué dans l’accueil pour les technologies d’assistance.
- Tests supplémentaires des verrous, de l’export de données personnelles et de l’effacement sélectif. Campagne locale avec 30, 300 et 1 000 étudiants fictifs, saisies simultanées et contrôles d’accès.
- Aucun changement de structure de base de données.

## 1.0.1 — 2026-09-07

- Extension de la compatibilité déclarée à Moodle 5.2, avec validation sous Moodle 5.2.2 et non-régression sous Moodle 4.5.13.
- Conservation des espacements, sélecteurs, badges de statut et libellés accessibles avec les thèmes Bootstrap 4 et 5.
- Ajout des attributs de groupe PHPUnit 11 en conservant les annotations PHPUnit 9 utilisées sous Moodle 4.5.
- Réussite de 75 tests et 426 assertions sous Moodle 4.5/PHP 8.3 et Moodle 5.2.2/PHP 8.3 et 8.4.
- Aucun changement du schéma de données; conservation des présences lors de la mise à niveau.

## 1.0.0 — 2026-08-15

- Publication de la première version stable après la campagne complète de validation des versions candidates.
- Confirmation des modes professionnel et Light, de la terminologie multilingue, du centre d’aide illustré et des intégrations natives Moodle.
- Validation des principales pages du plugin sous Moodle 4.5 et Moodle 5.0.
- Réussite de 75 tests automatisés et de 426 assertions sous Moodle 4.5.

## 0.99.57-rc85 — 2026-08-15

- Synchronisation des présentations française, anglaise et espagnole du paquet avec les fonctions auditées et les métadonnées actuelles.
- Documentation du mode Light, de la terminologie métier multilingue, des locaux, des groupes Moodle, des séries avancées et du centre d’aide illustré facultatif.
- Finalisation de l’audit technique, linguistique, structurel et visuel préfinal sous Moodle 4.5 et Moodle 5.0.
- Réussite de 75 tests automatisés et de 426 assertions sous Moodle 4.5.
- Cette livraison demeure une candidate et n’est pas la version définitive.

## 0.99.56-rc84 — 2026-08-15

- Ajout d’un réglage administratif Moodle permettant de masquer institutionnellement l’onglet Aide dans toutes les activités.
- Redirection sécuritaire des anciennes adresses du centre d’aide vers l’accueil de l’activité lorsqu’il est désactivé.
- Conservation des aides contextuelles natives de Moodle et de tous les documents intégrés, immédiatement récupérables.
- Mise à jour des guides administrateur et de la couverture automatisée en français, anglais et espagnol.
- Cette livraison demeure une candidate et n’est pas la version définitive.

## 0.99.55-rc83 — 2026-08-15

- Correction de l’interprétation du singulier et du pluriel lorsqu’un terme français possède la même forme aux deux nombres, notamment `parcours`.
- Ajout de l’élision et des contractions françaises pour les termes personnalisés commençant par une voyelle, afin d’afficher notamment `un horaire`, `l’horaire`, `de l’horaire` et `les horaires`.
- Ajout d’une couverture automatisée et validation visuelle avec une terminologie locale temporaire, ensuite retirée.
- Réussite de 74 tests automatisés et de 423 assertions sous Moodle 4.5.
- Cette livraison demeure une candidate et n’est pas la version définitive.

## 0.99.54-rc82 — 2026-08-15

- Finalisation des guides illustrés français, anglais et espagnols avec 54 captures de démonstration éthiques réalisées sous Moodle Boost.
- Correction de l’accès de l’étudiant à sa propre fiche individuelle tout en conservant la barrière de permissions Moodle qui interdit les fiches des autres participants.
- Protection des adresses techniques des images et des liens contre les remplacements de terminologie métier personnalisée.
- Audit visuel de toutes les pages principales sous Moodle 4.5 et Moodle 5.0, sans débordement horizontal, chaîne manquante ni erreur de page.
- Réussite de 73 tests automatisés et de 421 assertions sous Moodle 4.5.
- Cette livraison demeure une candidate et n’est pas la version définitive.

## 0.99.51-rc79 — 2026-08-15

- Ajout d’un onglet Aide intégré sélectionnant automatiquement les documents français, anglais ou espagnols.
- Restriction des documents administratifs, de tests et de mise à niveau selon les permissions Moodle et le statut d’administrateur du site.
- Application de la terminologie métier personnalisée de l’activité à la documentation affichée.
- Ajout des titres visibles Singulier et Pluriel au-dessus des champs compacts.
- Ajout d’une couverture automatisée des 24 documents inclus.
- Cette livraison demeure une candidate et n’est pas la version définitive.

## 0.99.50-rc78 — 2026-08-15

- Déplacement de la terminologie vers la fin des réglages propres à l’activité et fermeture de la section par défaut.
- Remplacement de la longue liste par une ligne compacte singulier/pluriel pour chaque notion métier.
- Affichage prioritaire de la langue du cours et classement des langues supplémentaires dans les champs avancés natifs de Moodle.
- Conservation de toutes les valeurs multilingues, règles d’héritage et verrous institutionnels indépendants.
- Cette livraison demeure une candidate et n’est pas la version définitive.

## 0.99.49-rc77 — 2026-08-15

- Extension de la terminologie contextuelle aux parcours, sessions, participants et locaux.
- Ajout de valeurs singulier/pluriel indépendantes en français, anglais et espagnol, au niveau du site et de l’activité.
- Ajout d’un verrou institutionnel indépendant pour chaque notion métier.
- Conservation de toutes les notions dans les sauvegardes/restaurations Moodle et des termes Parcours de la RC76 lors de la mise à niveau.
- Extension des tests automatisés et de la documentation trilingue.
- Cette livraison demeure une candidate et n’est pas la version définitive.

## 0.99.48-rc76 — 2026-08-15

- Ajout d’une terminologie indépendante en français, en anglais et en espagnol, au niveau du site et de l’activité.
- Migration des anciens termes monolingues vers la langue appropriée sans perte de données.
- Conservation des termes multilingues dans la sauvegarde, la restauration et la suppression Moodle.
- Mise à jour des guides trilingues et des tests automatisés de terminologie.
- Cette livraison demeure une candidate et n’est pas la version définitive.

## 0.99.47-rc75 — 2026-08-15

- Correction de la résolution du contexte afin que la terminologie enregistrée dans l’activité soit utilisée sur toutes les pages du plugin.
- Extension du traitement à tous les textes visibles du plugin dans les trois langues, y compris les libellés dynamiques.
- Ajout d’un test de régression couvrant la détection automatique de l’activité courante.
- Cette livraison demeure une candidate et n’est pas la version définitive.

## 0.99.46-rc74 — 2026-08-15

- Ajout d’une terminologie institutionnelle et propre à l’activité, au singulier et au pluriel, pour les parcours.
- Ajout d’un réglage Moodle natif permettant à l’administration de verrouiller cette terminologie pour toutes les activités.
- Application contextuelle des termes aux espaces de travail, formulaires, rapports et exports sans modifier les identifiants internes ni les API.
- Conservation des termes dans les sauvegardes/restaurations Moodle, mise à jour de la documentation trilingue et ajout de tests automatisés.
- Cette livraison demeure une candidate et n’est pas la version définitive.

## 0.99.45-rc73 — 2026-08-15

- Ajout du paquet linguistique espagnol complet comprenant les 809 chaînes du plugin.
- Ajout en espagnol des guides utilisateur et administrateur, des matrices des modes et des rôles, ainsi que des guides de mise à niveau, de recette, d’accessibilité et de tests.
- Conservation de toutes les variables Moodle et validation de la parité avec les paquets anglais et français.
- Cette livraison demeure une candidate et n’est pas la version définitive.

## 0.99.44-rc72 — 2026-08-15

- Suppression des références nominatives à un environnement client dans l’historique distribué.
- Vérification de la neutralité du code, des langues, des styles, des tests, de la documentation et des noms de fichiers.
- Confirmation qu’aucune logique ne dépend d’IOMAD, d’un thème commercial, d’un domaine ou d’un client particulier.
- Cette livraison demeure une candidate et n’est pas la version définitive.

## 0.99.43-rc71 — 2026-08-15

- Recette réelle de RC70 sur Moodle 4.5 en mode Light et Moodle 5.0 en mode Professionnel.
- Validation des pages principales, des espaces professionnels, des redirections Light et des formulaires sans libellé manquant.
- Correction de l’espacement Markdown des descriptions institutionnelles afin d’éviter l’affichage littéral de `\\n\\n`.
- Cette livraison demeure une candidate et n’est pas la version définitive.

## 0.99.42-rc70 — 2026-08-14

- Audit préfinal complet des modes Light et Professionnel, des permissions, des mutations protégées, des langues, des aides, de la confidentialité, des sauvegardes et des intégrations Moodle.
- Ajout d’une explication propre à chaque valeur institutionnelle dans la page d’administration du site.
- Ajout d’un guide administrateur et d’une matrice Light/Professionnel bilingues au ZIP.
- Conservation du périmètre stable : aucune intégration CRM ni règle d’affaires spéculative n’a été introduite dans cette candidate.

## 0.99.41-rc69 — 2026-08-14

- Déplacement de chaque explication de verrouillage institutionnel directement sous le paramètre concerné dans le formulaire Moodle.
- Conservation de l’application côté serveur déjà validée et restauration du réglage du site de test après validation.

## 0.99.40-rc68 — 2026-08-14

- Ajout des paramètres administratifs Moodle natifs pour les valeurs institutionnelles par défaut.
- Ajout de verrouillages facultatifs côté serveur pour le mode d’expérience, les calculs, le seuil, le carnet de notes, les absences excusées, l’auto-saisie étudiante et le calendrier.
- Remplacement de toutes les suppressions directes d’événements du calendrier dans le code actif et les anciennes migrations par l’API calendrier Moodle.
- Migration des derniers comportements JavaScript intégrés vers des modules AMD réutilisables.
- Ajout de la documentation bilingue et de tests automatisés pour les politiques institutionnelles et le nettoyage du calendrier.

## 0.99.30-rc58 — 2026-08-14

- Intégration des équivalences approuvées au moteur central d’assiduité, aux rapports, à l’achèvement et au carnet de notes.
- La durée de la session attendue demeure le temps possible; seules les minutes réellement présentes sont transférées et plafonnées à cette durée.
- Priorité aux présences normales, prévention du double comptage d’une session source et identification claire de l’équivalence dans la fiche individuelle.
- Protection des résultats fermés et tests unitaires des différences de durée et du plafonnement.

## 0.99.29-rc57 — 2026-08-14

- Ajout des décisions d’approbation, de refus et de révocation des équivalences dans la fiche individuelle.
- Ajout d’une note de décision, de l’auteur et de la date afin de conserver un historique institutionnel vérifiable.
- Nouvelle validation de l’admissibilité au moment de l’approbation et blocage de la fermeture d’un résultat contenant une demande en attente.
- Les équivalences approuvées demeurent encore sans effet sur le calcul jusqu’à l’étape d’intégration suivante.

## 0.99.28-rc56 — 2026-08-14

- Ajout de la création d’une demande d’équivalence depuis la fiche individuelle d’un participant.
- La session de remplacement doit appartenir à un autre parcours et posséder une présence déjà consignée.
- Justification obligatoire, protection contre les demandes actives en double et affichage de l’état dans la fiche.
- Les demandes demeurent sans effet sur les calculs avant l’étape d’approbation.

## 0.99.27-rc55 — 2026-08-14

- Ajout de la fondation de données pour les équivalences de sessions entre parcours.
- Le modèle distingue la session attendue, la session réellement suivie, le participant, la justification et la décision vérifiable.
- Aucun résultat d’assiduité n’est encore modifié par ces données tant que le processus d’approbation n’est pas terminé.

## 0.99.26-rc54 — 2026-08-14

- Ajout d’un historique administratif visible des promotions et retraits de la liste d’attente.
- Affichage du participant, de la décision, de sa date et de la personne qui l’a prise.
- Conservation du fonctionnement bilingue et des données d’audit déjà sauvegardées par Moodle.

## 0.99.25-rc53 — 2026-08-14

- Ajout d’une liste d’attente facultative et ordonnée pour chaque parcours d’assiduité.
- Ajout d’actions volontaires de promotion et de retrait réservées au personnel, avec conservation d’un historique vérifiable.
- Ajout d’un avertissement avant toute promotion dépassant volontairement la capacité, sans imposer un blocage rigide.
- Intégration des listes d’attente à la sauvegarde, la restauration, la confidentialité et aux remises à zéro Moodle.
- Validation de 56 tests et 317 assertions sous Moodle 4.5.13.

## 0.99.24-rc52 — 2026-08-14

- Ajout d’une capacité facultative pour chaque parcours, indépendante de celle des locaux.
- Ajout des indicateurs de places disponibles, de capacité atteinte et de dépassement dans la gestion et la fiche des parcours.
- Confirmation autorisée explicite exigée lors de la création, de la réduction de capacité ou de l’ajout d’un participant au-delà de la limite, sans blocage rigide.
- Conservation de la capacité des parcours dans les sauvegardes et restaurations Moodle.
- Validation de 56 tests et 311 assertions sur Moodle 4.5.13.

## 0.99.23-rc51 — 2026-08-14

- Ajout d’un espace de gestion des groupes Moodle natifs directement dans Parcours d’assiduité.
- Les personnes autorisées peuvent créer un groupe, modifier son identité et gérer les membres inscrits au cours sans dupliquer les données de groupe.
- Les groupes deviennent immédiatement disponibles dans les parcours et les sessions; la suppression et les groupements avancés demeurent dans l’administration Moodle.
- Application de la permission Moodle native `moodle/course:managegroups` et validation de la séparation entre enseignants éditeurs et non éditeurs.
- Validation de 55 tests et 307 assertions sur Moodle 4.5.13.

## 0.99.22-rc50 — 2026-08-14

- Ajout de locaux réutilisables propres à l’activité avec code, capacité, indications, notes et état actif ou inactif.
- Intégration des locaux enregistrés aux sessions en classe et hybrides, tout en conservant les emplacements saisis manuellement.
- Conservation d’une copie lisible du lieu et de l’association au local dans les sauvegardes et restaurations Moodle.
- Blocage de la suppression des locaux déjà utilisés par des sessions.
- Validation de 55 tests et 304 assertions sur Moodle 4.5.13.

## 0.99.21-rc49 — 2026-08-14

- Ajout du choix du public directement dans la duplication groupée des sessions.
- Les copies peuvent conserver chaque affectation originale ou être affectées ensemble à tous, à un groupe Moodle ou à un parcours actif.
- Enrichissement de la prévisualisation avec les dates, publics et parcours originaux et proposés.
- Recalcul des avertissements de chevauchement selon le public proposé et nouvelle validation des cibles lors de la confirmation.
- Validation de 55 tests et 299 assertions sur Moodle 4.5.13.

## 0.99.20-rc48 — 2026-08-14

- Ajout de la duplication groupée des sessions sélectionnées avec un décalage en jours civils ou en minutes.
- Ajout de la prévisualisation avant/après, de la détection détaillée des chevauchements et de la confirmation des conflits intentionnels.
- Les copies conservent l’horaire et le public, mais ne recopient jamais les présences ni les résultats définitifs.
- Exclusion des périmètres protégés par des résultats définitifs actifs et ajout de la protection concurrente.
- Validation de 55 tests et 296 assertions sur Moodle 4.5.13.

## 0.99.19-rc47 — 2026-08-14

- Ajout du renommage groupé prévisualisé des sessions existantes, avec un nom commun ou une numérotation chronologique stable.
- Ajout du remplacement ou de l’effacement groupé des descriptions sans modifier le public, les calculs de présence ni les résultats définitifs.
- Extension de la protection concurrente aux noms et descriptions des sessions.
- Ajout des libellés et aides Moodle bilingues pour ces nouvelles modifications administratives.
- Validation de 54 tests et 291 assertions sur Moodle 4.5.13.

## 0.99.18-rc46 — 2026-08-14

- Ajout de l’affectation groupée prévisualisée des sessions vides à tous, à un groupe Moodle ou à un parcours actif.
- Application automatique du groupe configuré dans le parcours et détection des conflits pour le nouveau public.
- Exclusion de toute session contenant des présences ou contribuant à un résultat définitif.
- Ajout de la protection concurrente, de la mise à jour du calendrier et des nouveaux calculs après confirmation.
- Validation de 53 tests et 288 assertions sur Moodle 4.5.13.

## 0.99.17-rc45 — 2026-08-14

- Ajout des modifications groupées prévisualisées pour la durée, la modalité, le lieu et le lien de classe virtuelle.
- Ajout des choix Moodle de modalité et de la possibilité d’effacer un ancien lieu ou lien.
- Protection des durées contribuant à un résultat définitif actif, tout en permettant les corrections sûres de diffusion.
- Nouveau contrôle des conflits après un changement de durée et maintien de la confirmation et de la protection concurrente.
- Validation de 52 tests et 283 assertions sur Moodle 4.5.13.

## 0.99.16-rc44 — 2026-08-14

- Ajout du déplacement groupé des sessions existantes sélectionnées dans le catalogue.
- Ajout du déplacement par jours civils ou minutes avec prévisualisation avant/après.
- Détection des conflits pour un même public et confirmation explicite des superpositions intentionnelles.
- Exclusion des sessions protégées par un résultat définitif actif et protection contre les modifications concurrentes.
- Mise à jour du calendrier Moodle, de l’achèvement et des notes après confirmation.
- Validation de 51 tests et 279 assertions sur Moodle 4.5.13.

## 0.99.15-rc43 — 2026-08-14

- Vérification du cycle complet de suppression sécurisée des parcours et de leurs sessions.
- Ajout de la preuve automatisée que la suppression d’un parcours sans présence retire ses événements du calendrier Moodle.
- Confirmation que les sessions indépendantes et leurs événements demeurent intacts.
- Conservation du blocage existant lorsqu’un parcours contient des présences ou des résultats définitifs.
- Validation de 49 tests et 274 assertions sur Moodle 4.5.13.

## 0.99.14-rc42 — 2026-08-14

- Réalisation d’un contrôle visuel réel avec un brouillon de huit sessions dans une mise en page Moodle avec blocs latéraux.
- Validation des fiches de longue série, du résumé et du rapport détaillé des chevauchements dans le thème installé.
- Passage des commandes de sélection et de tri au niveau secondaire Moodle afin de réserver l’accent aux actions principales.
- Création d’un simple brouillon privé temporaire pendant le contrôle; aucune session de test n’a été enregistrée.

## 0.99.13-rc41 — 2026-08-14

- Transformation des longues prévisualisations en fiches de session clairement séparées.
- Présentation des actions groupées dans un panneau de planification distinct et meilleure adaptation des boutons.
- Amélioration des dates, champs et actions dans les colonnes Moodle étroites et sur mobile.
- Conservation du formulaire Moodle standard et de toutes les données de la série.

## 0.99.12-rc40 — 2026-08-14

- Ajout d’un tri chronologique stable après les changements manuels ou groupés.
- Ajout d’un tableau de bord indiquant inclusions, exclusions, durée, période, modalités et conflits.
- Calcul des totaux à partir des rencontres incluses seulement et conservation de toutes les informations pendant le tri.
- Amélioration de la révision des longues séries sans modifier le fonctionnement standard du formulaire Moodle.
- Élargissement de la suite validée à 49 tests et 270 assertions.

## 0.99.11-rc39 — 2026-08-14

- Ajout du déplacement groupé par jours pour tout sous-ensemble de rencontres proposées.
- Ajout du décalage positif ou négatif en minutes tout en conservant les durées.
- Conservation de l’heure locale lors des changements d’heure saisonniers pour les déplacements en jours.
- Nouveau calcul du rapport détaillé de chevauchements après chaque changement d’horaire.
- Élargissement de la suite validée à 48 tests et 263 assertions.

## 0.99.10-rc38 — 2026-08-14

- Remplacement du simple compteur par un rapport de conflits détaillé et exploitable.
- Affichage des noms, horaires complets, durée du chevauchement, public et origine du conflit.
- Ajout d’un lien ouvrant la session existante dans un nouvel onglet sans perdre le brouillon.
- Maintien des chevauchements intentionnels après confirmation explicite.
- Validation de 47 tests et 257 assertions sur Moodle 4.5.13.

## 0.99.9-rc37 — 2026-08-14

- Ajout de la sélection en un clic de toutes les rencontres incluses et de l’effacement de la sélection groupée.
- Conservation de toutes les modifications de l’aperçu pendant les commandes de sélection.
- Détection des chevauchements dans la nouvelle série et avec les sessions existantes du même public.
- Confirmation explicite obligatoire avant de créer des rencontres volontairement superposées.
- Élargissement de la suite validée à 47 tests et 252 assertions.

## 0.99.8-rc36 — 2026-08-14

- Ajout de la modalité, du lieu, du lien en ligne et de la description propres à chaque rencontre de l’aperçu.
- Ajout des modifications groupées de modalité, de lieu et de lien pour les rencontres sélectionnées.
- Validation indépendante de chaque rencontre incluse avant le début de la transaction.
- Conservation d’un public, groupe et parcours communs afin d’éviter les changements d’affectation accidentels.
- Validation de 45 tests et 248 assertions sur Moodle 4.5.13.

## 0.99.7-rc35 — 2026-08-14

- Ajout de la numérotation automatique facultative lors de la génération d’une série.
- Ajout d’une sélection indépendante pour les modifications groupées, sans modifier les choix d’inclusion.
- Ajout du nommage séquentiel, du nom commun et de la durée commune pour tout sous-ensemble sélectionné.
- Conservation des heures locales des rencontres lors des changements d’heure saisonniers.
- Élargissement de la suite validée à 45 tests et 245 assertions.

## 0.99.6-rc34 — 2026-08-14

- Ajout d’une étape Moodle native de révision des séries, tout en conservant la création immédiate.
- Possibilité de personnaliser le nom, la date et les heures de chaque rencontre ou de l’exclure avant l’enregistrement.
- Aperçus privés à l’utilisateur et à l’activité, avec expiration automatique après deux heures.
- Nouvelle validation du public et du parcours actif à la confirmation, puis création transactionnelle des rencontres retenues.
- Élargissement de la suite validée à 42 tests et 230 assertions.

## 0.99.5-rc33 — 2026-08-13

- Correction des exports collectifs afin qu’une déclaration étudiante en attente ne contribue jamais aux résultats officiels.
- Vérification des calculs officiels, des filtres combinés du journal et des six formats Moodle avec du contenu français accentué.
- Ajout de tests de validation des dates, groupes, modalités et liens en ligne des sessions et parcours.
- Contrôle final des langues, de XMLDB, des permissions, des points de mutation et de l’interface adaptative.
- Élargissement de la suite validée à 40 tests et 225 assertions.

## 0.99.4-rc32 — 2026-08-13

- Centralisation de la remise à zéro d’une feuille de présence dans une fonction transactionnelle.
- Vérification que la session est conservée, que les présences et révisions sont supprimées et que sa version de concurrence progresse.
- Vérification qu’un résultat final actif empêche la remise à zéro jusqu’à sa réouverture.
- Ajout de tests distinguant les modifications de contenu des changements structurels et de public.
- Élargissement de la suite validée à 32 tests et 187 assertions.

## 0.99.3-rc31 — 2026-08-13

- Ajout d’une validation défensive de l’appartenance avant toute suppression de parcours.
- Vérification de la suppression sûre des parcours sans présence et de leurs sessions, sans toucher aux sessions indépendantes.
- Vérification qu’un parcours contenant des présences ne peut pas être supprimé.
- Vérification que la remise à zéro retire uniquement les présences, révisions, affectations et résultats du participant ciblé.
- Vérification que le compte Moodle, l’inscription et les données des autres participants demeurent intacts.
- Élargissement de la suite validée à 29 tests et 167 assertions.

## 0.99.2-rc30 — 2026-08-13

- Ajout de tests d’intégration pour les sessions communes et le ciblage par groupes Moodle.
- Vérification que le public figé d’un parcours exclut les participants qui n’y sont pas affectés.
- Vérification qu’une inscription suspendue disparaît des nouvelles feuilles sans supprimer l’historique.
- Vérification qu’une réinscription dans un nouveau parcours reçoit un nouveau numéro de tentative sans réactiver l’auto-saisie de l’ancienne tentative.
- Élargissement de la suite validée à 25 tests et 143 assertions.

## 0.99.1-rc29 — 2026-08-13

- Centralisation de la règle de verrouillage utilisée par les écrans de déclaration individuelle et par lot.
- Vérification qu’une déclaration en attente ou retournée pour correction reste modifiable par son auteur.
- Vérification qu’une déclaration approuvée ou une présence saisie par le personnel demeure verrouillée pour l’étudiant.
- Ajout de tests sur l’autorité des données, l’historique permanent des révisions et les traces d’audit.
- Élargissement de la suite validée à 21 tests et 126 assertions.

## 0.99.0-rc28 — 2026-08-13

- Ajout de tests d’intégration au calendrier Moodle lorsque l’option est désactivée ou activée.
- Vérification qu’une modification de session met à jour un seul événement sans créer de doublon.
- Vérification que la désactivation retire uniquement les événements de Parcours d’assiduité.
- Vérification que le nettoyage des événements obsolètes ne supprime jamais ceux d’un autre module.
- Élargissement de la suite validée à 17 tests et 107 assertions.

## 0.98.0-rc27 — 2026-08-13

- Ajout de tests du carnet de notes confirmant que seuls les résultats clôturés publient une note définitive.
- Vérification que la réouverture retire la note finale tout en conservant l’historique.
- Ajout de tests pour les règles d’achèvement : présence consignée, toutes les sessions, résultat clôturé et seuil réussi.
- Élargissement de la suite validée à 14 tests et 94 assertions.

## 0.97.0-rc26 — 2026-08-13

- Ajout de véritables tests d’intégration de sauvegarde et restauration de cours Moodle.
- Vérification de la restauration complète des sessions, parcours, présences, révisions, affectations et résultats définitifs.
- Vérification qu’une restauration structurelle exclut les données personnelles et rouvre correctement les parcours copiés.
- Élargissement de la suite validée à 12 tests et 83 assertions.

## 0.96.0-rc25 — 2026-08-13

- Ajout d’un générateur standard de données de test Moodle pour Parcours d’assiduité.
- Ajout de tests automatisés des capacités attribuées aux rôles par défaut.
- Ajout de tests de confidentialité couvrant les participants, responsables de saisie, approbateurs, réviseurs et responsables des parcours.
- Ajout d’un test du cycle de clôture et de réouverture d’un résultat de parcours.
- Élargissement de la suite validée à 10 tests et 67 assertions.

## 0.95.0-rc24 — 2026-08-13

- Mise en place d’un environnement Moodle PHPUnit isolé et réutilisable pour Parcours d’assiduité.
- Correction des valeurs vides non conformes détectées par XMLDB pendant l’installation de test.
- Ajout du chemin de mise à niveau retirant ces valeurs par défaut des installations existantes.

## 0.94.0-rc23 — 2026-08-13

- Audit de la sauvegarde, de la restauration, de la suppression d’activité, de la remise à zéro du cours et du cycle de confidentialité.
- Repérage de confidentialité complété pour les réviseurs et les responsables des résultats de parcours.
- Ajout d’un test de base de données garantissant que la suppression de l’activité retire toutes les données opérationnelles dépendantes.

## 0.93.0-rc22 — 2026-08-13

- Audit de la matrice des capacités Moodle pour les étudiants, enseignants non éditeurs, enseignants, gestionnaires et administrateurs du site.
- Ajout d’une documentation bilingue sur les rôles, les permissions et l’accessibilité.
- Association explicite des erreurs de minutes avec les champs concernés pour les technologies d’assistance.
- Annonce vocale des mises à jour du calcul de présence.
- Uniformisation du focus clavier visible sur les contrôles du plugin.

## 0.92.0-rc21 — 2026-08-13

- Documentation opérationnelle entièrement disponible en français et en anglais.
- Ajout des guides anglais d’utilisation, de mise à niveau et de recette fonctionnelle.
- Harmonisation de la terminologie entre l’interface et les documents.

## 0.91.0-rc20 — 2026-08-13

- Aide contextuelle Moodle complétée dans les paramètres, l’achèvement, les sessions et les parcours.
- Ajout d’explications concises en français et en anglais pour les calculs, les absences excusées et la clôture.
- Ajout de pastilles accessibles pour les dates, les lieux, les liens, les séries et les affectations.

## 0.90.0-rc19 — 2026-08-13

- Ajout de la documentation de préparation à la production.
- Ajout des procédures d’installation, de mise à niveau et de retour arrière.
- Ajout du guide opérationnel et du protocole de recette institutionnelle.

## 0.89.0-rc18 — 2026-08-13

- Campagne de stabilisation Moodle 4.5 terminée.
- Correction de la remise à zéro du cours avec historique d’audit.
- Sauvegarde, restauration et six formats d’export validés.

## 0.88.0-rc17 — 2026-08-13

- Ajout du journal d’audit institutionnel central en lecture seule.
- Ajout des filtres et des exports du journal.
- Ajout des actions permanentes de saisie et de modification des présences.

## 0.87.0-rc16 — 2026-08-13

- Ajout de l’historique permanent des approbations, corrections et nouvelles soumissions.
- Intégration de cet historique aux fiches individuelles, à la confidentialité et aux sauvegardes Moodle.

## Versions candidates antérieures

Les versions antérieures ont introduit les parcours et tentatives, la clôture manuelle, les groupes Moodle, les inscriptions inactives, la séparation des rôles, l’auto-saisie étudiante, les notes, l’achèvement, les rapports, les exports, le calendrier facultatif, les modalités de diffusion, l’administration et l’interface professionnelle harmonisée avec Moodle.
