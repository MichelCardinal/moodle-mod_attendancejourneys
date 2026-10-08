# Guide administrateur — Parcours d’assiduité

## Notation nouvelle et historique

Les nouvelles activités créent un parcours principal obligatoire et utilisent des notes indépendantes par parcours. La note est publiée à la clôture explicite; l'achèvement automatique exige la clôture et la réussite de tous les parcours obligatoires affectés. Une absence justifiée reste une absence; une dispense de séance autorisée retire cette obligation. Le mode professionnel permet des parcours simultanés distincts avec leurs propres seuils. Le mode simple conserve le parcours principal.

Une mise à niveau ne convertit pas les activités existantes et ne recalcule pas leurs résultats historiques. Utilisez la revue du mode de notation et le [guide de mise à niveau](MISE_A_NIVEAU_FR.md) pour la conversion explicite et conservatrice. Les anciennes conditions d'achèvement dès une présence et politiques de temps excusé ne décrivent pas les règles des nouvelles activités.

Le [guide utilisateur](GUIDE_UTILISATEUR_FR.md) décrit les règles actuelles par parcours avec leurs illustrations.

## Obligations individuelles de parcours

Dans le nouveau mode de notation par parcours, ouvrez **Rapports → participant → Obligation individuelle de parcours**. Le personnel autorisé à modifier l’activité peut dispenser l’obligation entière ou définir une période individuelle explicite. Toute décision, y compris un rétablissement, exige une justification. Un résultat final doit d’abord être rouvert; les verrouillages et notes imposées dans le carnet Moodle restent protégés.

Laissez les deux dates désactivées pour exiger toutes les séances applicables. Le début est inclusif et la fin exclusive. Une séance entièrement hors période est exclue; une séance qui la chevauche compte en entier. Les dates techniques d’inscription et d’affectation ne déterminent jamais ces bornes. Par exemple, exclure une absence antérieure de 100 minutes et conserver une séance de 100 minutes avec 20 minutes absentes fait passer le calcul provisoire de 80/200 à 80/100. Les présences enregistrées restent dans l’historique.

Cliquez sur **Prévisualiser la décision** pour comparer minutes et séances avant/après, avec exclusions et réintégrations. **Modifier** revient à la proposition sans enregistrer. **Confirmer cette décision** conserve la justification et l’historique; cette action ne clôture pas le parcours et ne publie aucune note. Si les présences, obligations ou droits ont changé depuis l’aperçu, obtenez un nouvel aperçu. **Annuler** conserve l’obligation actuelle.

La dispense entière ignore les dates, retire cette obligation de l’achèvement automatique requis et ne crée aucune note ni réussite artificielle. Les autres parcours obligatoires doivent encore être clôturés et réussis. Si toutes les obligations sont dispensées, l’achèvement automatique reste faux; une personne autorisée peut utiliser la décision manuelle native de Moodle. Les synthèses identifient la dispense; les rapports détaillés conservent les minutes historiques et signalent les séances hors obligation individuelle. Des équivalences existantes peuvent empêcher de retirer une séance concernée par une décision en attente ou approuvée.

Le participant peut lire son historique de décisions. Les autres participants ne peuvent pas le consulter. Une sauvegarde avec utilisateurs comprend les décisions et leur historique; une sauvegarde de structure les omet. Lors d’une restauration avec décalage du cours, les dates des séances et bornes pédagogiques se déplacent ensemble, tandis que les dates d’audit des décisions et présences conservent leur valeur historique.


## Terminologie institutionnelle et locale au cours

L’administration du site peut définir séparément, en français, en anglais et en espagnol, les termes au singulier et au pluriel pour quatre notions métier : **parcours, session, participant et local**. Chaque notion possède son propre verrou institutionnel. Un champ vide conserve le libellé traduit standard dans la langue concernée. Sans verrou, les enseignants peuvent personnaliser chaque langue dans les paramètres de l’activité; une valeur locale vide hérite du réglage institutionnel de cette langue. Seul l’affichage change : les identifiants de base de données, les permissions, les sauvegardes et les intégrations demeurent stables.

## Pages d’administration du site

La page principale propose des liens vers **Terminologie**, **Réglages historiques** et le **Guide administrateur**. Ce guide se consulte directement depuis l’administration du site, sans créer d’activité, même lorsque l’aide des activités est désactivée. Son accès exige la capacité Moodle de configuration du site.

Les valeurs par défaut concernent les nouvelles activités. Une valeur verrouillée est réappliquée au prochain enregistrement d’une activité; modifier un défaut ou un verrou ne modifie pas toutes les activités et ne recalcule pas leurs résultats définitifs. La terminologie change immédiatement l’affichage. Les anciennes politiques de temps excusé concernent seulement le mode de calcul historique; la notation par parcours conserve la distinction entre absence justifiée et dispense autorisée.

Les valeurs par défaut du calcul en pourcentage et du carnet sont enregistrées comme un groupe validé : la transmission de note par défaut ne peut pas être activée si le calcul en pourcentage est désactivé. Moodle conserve normalement les changements de configuration. Ce réglage ne publie aucune note provisoire. Le calcul ne peut pas être verrouillé en position désactivée, car la nouvelle notation par parcours l’exige.

**Autoriser les seuils propres aux parcours** est activé par défaut. Le désactiver interdit de créer ou de modifier un seuil propre à un parcours ordinaire, y compris de supprimer une dérogation existante. Les seuils déjà enregistrés, les résultats définitifs et le seuil hérité d’une reprise personnelle sont conservés. Les nouveaux parcours héritent du seuil de l’activité. Réactiver la politique permet les modifications sous réserve des protections habituelles des résultats définitifs. Verrouiller le seuil de l’activité ne suffit pas à interdire les seuils propres aux parcours.

## Délégation des décisions pédagogiques

Les rôles Moodle peuvent autoriser séparément la clôture, la réouverture, l’ouverture d’une reprise personnelle et l’approbation ou la révocation d’équivalences. Chaque décision exige également la capacité existante de gestion des parcours; les restrictions de cours, de groupes et d’accès à l’activité demeurent applicables. La proposition d’équivalence reste distincte de son approbation.

À la mise à niveau, Moodle copie vers chaque nouvelle capacité les permissions existantes de gestion des parcours, y compris les interdictions locales. Le personnel éditeur conserve ses droits jusqu’à leur adaptation explicite par l’administrateur. Vérifiez les deux capacités lors d’une délégation; masquer un bouton ne constitue pas à lui seul le contrôle d’accès.

## Configuration institutionnelle

La page **Administration du site → Plugins → Modules d’activité → Parcours d’assiduité** utilise l’API de configuration native de Moodle. Elle définit les valeurs proposées lors de la création d’une activité : mode Light ou Professionnel, calcul du pourcentage, seuil, carnet de notes, statut Excusé, auto-saisie étudiante et calendrier.

Le réglage institutionnel **Afficher le centre d’aide intégré** permet de masquer l’onglet Aide dans toutes les activités. Une ancienne adresse du centre d’aide redirige alors vers l’accueil de l’activité avec une information claire. Les documents demeurent dans le plugin et réapparaissent sans perte lorsqu’on réactive le réglage. Les aides contextuelles natives de Moodle dans les formulaires restent toujours disponibles.

Chaque valeur possède un verrou facultatif. Sans verrou, l’enseignant éditeur peut adapter la valeur dans son activité. Avec verrou, la valeur institutionnelle est affichée comme non modifiable dans le formulaire et réappliquée côté serveur. Une activité existante adopte une nouvelle valeur verrouillée lorsqu’elle est modifiée.

## Choisir le mode

- **Light** : sessions simples, prise de présence, auto-saisie facultative et rapports essentiels. Les fonctions avancées sont masquées et leurs URL sont protégées côté serveur.
- **Professionnel** : ajoute les parcours simultanés, capacités et listes d’attente, équivalences, locaux, groupes Moodle, opérations groupées, administration exceptionnelle et audit. Les nouvelles activités Light permettent aussi la clôture finale individuelle du parcours principal.

Le mode ne crée pas deux éditions du plugin et ne duplique aucune donnée. Il modifie l’expérience disponible dans chaque activité.

Le formulaire d’activité regroupe les réglages dans les sections Moodle **Expérience utilisateur**, **Calcul de la présence**, **Permissions de saisie**, **Calendrier Moodle** et **Terminologie**.

## Permissions recommandées

- Étudiant : consulter l’activité et sa propre information; auto-saisie seulement si l’option et la capacité sont actives.
- Enseignant non éditeur : prendre les présences et consulter les rapports.
- Enseignant éditeur ou gestionnaire : gérer aussi sessions, parcours et ressources opérationnelles.
- Administrateur du site : remise à zéro exceptionnelle et journal institutionnel.

Ces comportements reposent sur les capacités Moodle et peuvent être adaptés dans **Définition des rôles** ou par dérogation dans le contexte du cours ou de l’activité. N’accordez `mod/attendancejourneys:resetuserdata` qu’aux personnes autorisées à supprimer des données institutionnelles.

## Données et intégrations Moodle

- Le carnet de notes est alimenté par l’API Gradebook.
- L’achèvement repose sur l’API d’achèvement personnalisé.
- Le calendrier est facultatif et désactivé par défaut; les événements sont gérés par l’API calendrier.
- Les groupes créés dans Parcours d’assiduité sont de véritables groupes du cours Moodle.
- Les rapports utilisent les formats d’export installés dans Moodle.
- La sauvegarde/restauration, la remise à zéro du cours et l’API de confidentialité sont prises en charge.

## Exploitation et maintenance

L’onglet **Administration** permet de rechercher ou filtrer les participants et d’amorcer une remise à zéro exceptionnelle. L’avertissement rappelle explicitement que seules les données Parcours d’assiduité sont supprimées; les comptes, inscriptions, groupes, autres notes et journaux Moodle demeurent intacts.

Le **Journal d’audit** est indépendant des données modifiables et conserve la traçabilité institutionnelle des opérations sensibles.

Testez toute mise à niveau sur un site de préproduction, puis sauvegardez la base de données et Moodledata avant l’installation en production. Après une mise à niveau, purgez les caches, ouvrez une activité Light et une activité Professionnelle, puis exécutez la recette fonctionnelle fournie dans le ZIP.

Les résultats provisoires ne doivent pas déclencher un certificat. Utilisez la clôture pour publier un résultat final. Toute réouverture doit être une décision autorisée et demeure retracée.

## Deux parcours obligatoires : exemple concret

| | Assiduité | Seuil | État | Carnet de notes |
|---|---:|---:|---|---|
| Théorie | 70 % | 60 % | Clôturé, réussi | 70 % |
| Laboratoire | 70 % | 80 % | Ouvert, provisoire | Aucune note |

L’achèvement reste incomplet tant que Laboratoire est ouvert. Sa clôture à 70 % publie sa note, mais son seuil de 80 % n’est pas atteint : l’achèvement demeure incomplet. La réouverture de Laboratoire retire uniquement sa note finale. Après correction autorisée et clôture à 80 %, les deux parcours obligatoires sont réussis. Moodle gère l’agrégation des notes du cours; une réussite en Théorie ne compense pas l’échec d’un Laboratoire obligatoire.


## Nouvelles tentatives pour une même obligation

Dans la fiche individuelle du participant, choisissez **Ouvrir une nouvelle tentative** après la clôture de la tentative courante. Le personnel autorisé fournit un nom et une justification, vérifie l’aperçu des conséquences, puis confirme ou annule. Un verrouillage ou une note imposée manuellement dans Moodle empêche l’ouverture. Une obligation dispensée doit d’abord être rétablie.

La confirmation ouvre une tentative personnelle distincte, avec le seuil de l’obligation d’origine. Planifiez ses nouvelles séances : aucune séance ni présence n’est copiée. La note définitive précédente est retirée et l’achèvement automatique est réévalué jusqu’à la clôture de la nouvelle tentative. Le dernier résultat clôturé remplace le précédent, même s’il est inférieur; un ancien meilleur résultat ne sert jamais de recours. Une réouverture de la tentative courante retire de nouveau son résultat. Corriger une ancienne tentative conserve son historique sans changer la tentative courante.

Une obligation conserve un seul élément de note et une seule exigence d’achèvement pour toutes ses tentatives. Les obligations indépendantes de théorie et de laboratoire gardent leurs seuils, notes et exigences propres. Le numéro d’affectation dans l’activité n’est pas le numéro personnel de tentative d’une obligation.

La fiche individuelle d’une obligation unique ouvre directement sa tentative courante. Avec plusieurs obligations, choisissez leur tentative courante ou un lien d’historique. Les anciennes présences et clôtures restent consultables. Les rapports collectifs et exports Excel/PDF d’un parcours physique conservent les données du parcours demandé; **Résultat final précédent** ou **Tentative précédente** signifie que ce résultat ne détermine pas la note actuelle. Les exports par séance comportent une colonne **Statut du résultat de la tentative**, distincte du statut de présence comme Présent.
