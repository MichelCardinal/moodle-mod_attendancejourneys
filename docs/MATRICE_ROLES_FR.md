# Matrice des rôles et permissions

Cette matrice décrit les autorisations par défaut de Parcours d’assiduité. Moodle permet aux administrateurs d’adapter ces capacités dans les rôles personnalisés.

| Action | Étudiant | Enseignant non éditeur | Enseignant | Gestionnaire | Administrateur du site |
|---|---:|---:|---:|---:|---:|
| Consulter l’activité et sa propre situation | Oui | Oui | Oui | Oui | Oui |
| Déclarer sa propre présence, si l’option est activée | Oui | Non | Non | Non | Oui, par capacité |
| Prendre ou approuver les présences | Non | Oui | Oui | Oui | Oui |
| Consulter les rapports des participants autorisés | Non | Oui | Oui | Oui | Oui |
| Créer et gérer les sessions | Non | Non | Oui | Oui | Oui |
| Créer et gérer les parcours | Non | Non | Oui | Oui | Oui |
| Clôturer et publier les résultats définitifs | Non | Non | Oui | Oui | Oui |
| Rouvrir les résultats définitifs | Non | Non | Oui | Oui | Oui |
| Ouvrir une reprise personnelle | Non | Non | Oui | Oui | Oui |
| Approuver, refuser ou révoquer les équivalences | Non | Non | Oui | Oui | Oui |
| Modifier les paramètres de l’activité | Non | Non | Oui | Oui | Oui |
| Remettre à zéro les données d’un participant | Non | Non | Non | Non | Oui par défaut |

## Capacités Moodle

- `mod/attendancejourneys:view` : consulter l’activité.
- `mod/attendancejourneys:canbelisted` : faire partie du public étudiant admissible.
- `mod/attendancejourneys:selfrecord` : déclarer sa propre présence.
- `mod/attendancejourneys:takeattendance` : prendre et approuver les présences.
- `mod/attendancejourneys:viewreports` : consulter les rapports permis par le contexte et les groupes.
- `mod/attendancejourneys:managesessions` : gérer les sessions.
- `mod/attendancejourneys:managejourneys` : gérer les parcours; les décisions spécifiques exigent aussi leur capacité distincte.
- `mod/attendancejourneys:closejourneys`: clôturer les parcours et publier les résultats définitifs.
- `mod/attendancejourneys:reopenjourneys`: rouvrir les résultats définitifs.
- `mod/attendancejourneys:manageattempts`: ouvrir une reprise personnelle.
- `mod/attendancejourneys:approveequivalences`: approuver, refuser ou révoquer les équivalences.
- `mod/attendancejourneys:manage` : modifier les paramètres propres à l’activité.
- `mod/attendancejourneys:resetuserdata` : utiliser les fonctions administratives destructives.

Les restrictions de groupes séparés et la capacité Moodle `moodle/site:accessallgroups` sont respectées. Un étudiant n’obtient jamais l’accès au rapport individuel d’un autre participant.
