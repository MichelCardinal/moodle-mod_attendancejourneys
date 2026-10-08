> **Renaming transition:** This is the working Attendance Journeys component (`mod_attendancejourneys`), candidate 1.1.0-rc12 / 2026100804. It is not a normal upgrade ZIP for an existing `mod_attendanceplus` installation. Do not uninstall the predecessor or replace its folder. Existing pre-publication laboratories require a backed-up, separately validated component transition. Original RC11 backups remain unchanged; restore them in a matching RC11 recovery environment before transition and create a new backup with the renamed component. Native automatic restoration of predecessor-component backups is not claimed.

> Candidate 1.1.0-rc12 has passed the local seven-configuration Moodle 4.5–5.3 test matrix. Full-copy component transitions and restoration have been validated separately. This is a release candidate, not a published or Marketplace-approved release.

# Attendance Journeys / Parcours d’assiduité

Attendance Journeys is a Moodle activity module for professional attendance management. It supports individual sessions, session series, learning journeys within the activity, Moodle groups, partial attendance expressed as minutes absent, configurable attendance thresholds, finalised results, gradebook integration and institutional audit history.

Parcours d’assiduité est un module d’activité Moodle destiné à la gestion professionnelle des présences. Il prend en charge les sessions, les séries, les parcours, les groupes Moodle, la présence partielle exprimée en minutes d’absence, les seuils configurables, les résultats définitifs, le carnet de notes et le journal d’audit institutionnel.

## Release candidate

- Release: `1.1.0-rc12`
- Technical version: `2026100804`
- Target compatibility: Moodle 4.5 through 5.3
- Maturity: release candidate

This local release candidate adopts Attendance Journeys and the independent technical component `mod_attendancejourneys`. It retains the predecessor’s attendance calculations, institutional settings and delegated pedagogical permissions. All predecessor archives remain unchanged. Existing pre-publication installations need the separately tested, backed-up component transition described above. Review the candidate before publication. Nothing is published on GitHub, Moodle Marketplace or AMOS.

New activities use a required main journey by default. Each independent obligation has its own threshold and grade, published only after explicit closure of its selected attempt. Automatic completion requires all assigned required journeys to be closed and passed. Justified absence counts as absence; an explicit staff exemption removes the session duration from required time.

Light mode supports individual closure and reopening of the main journey. Professional mode additionally supports several simultaneous journeys and advanced journey management. Adding a participant to another journey does not automatically transfer attendance credit or remove the original obligation. Authorised staff can preview and confirm individual obligation periods, whole-obligation waivers and new personal attempts. A retake uses separate sessions within the original obligation, inherits its threshold and retains one grade item. Opening it withdraws the previous final grade until closure; the latest closed result replaces the earlier result even if lower. Attendance history is preserved.

Existing activities retain their historical calculation and grading mode. The **Review grading mode** tab gives authorised editors a read-only inventory and an explicit conversion for compatible unfinished activities. It retains the original grade item, attendance records, journey identifiers and assignments. Activities with final snapshots, numeric grades (including zero), locks, manual overrides, existing completion decisions or incompatible historical policies are left unchanged. Several historical journeys, mixed session scopes and equivalences require a separate preservation plan; this workflow does not automatically redistribute their results. A stale confirmation is rejected.

The installable plugin contains English language strings only. French and Spanish translations are preserved separately for AMOS submission when the component is available and can be installed as local language customisations in the laboratory. Illustrated documentation remains multilingual. Historical distributions are preserved unchanged.

## Main features

- Sessions with calculated duration from start and end date/time.
- Journey sessions with enrolled, Moodle-group or explicitly assigned audiences; independent common/group sessions remain available in historical activities.
- Session series with preview, per-session adjustments and overlap warnings.
- Course-local room management and native Moodle group management.
- In-person, online, hybrid or unspecified delivery modes.
- Optional Moodle calendar integration, disabled by default.
- Present, absent, partial, justified absence, staff exemption and unrecorded statuses.
- Partial attendance entered as minutes absent.
- Optional attendance percentage and configurable passing threshold.
- Historical configurable handling of justified absences, preserved in existing activities.
- Student self-recording with staff approval and correction workflow.
- Individual and collective attendance reports.
- Auditable session cancellation and reinstatement with a required reason; cancelled time is excluded without deleting attendance history.
- Manual finalisation of a participant or journey result.
- Moodle gradebook and activity-completion integration.
- CSV, Excel, JSON, ODS, HTML and PDF exports.
- Read-only institutional audit log.
- Moodle privacy, backup, restore and course-reset support.
- Moodle-native institutional defaults with optional server-side locks.
- A streamlined Light mode alongside the complete professional mode.
- Multilingual institutional and activity-level business terminology, with optional locks.
- An integrated illustrated Help centre in French, English and Spanish, which administrators may hide without disabling Moodle contextual help.

## Installation and upgrade

Install the ZIP through **Site administration → Plugins → Install plugins**, or place the `attendancejourneys` directory in `mod/attendancejourneys`.

On Moodle 5.1 and later, a manual installation goes in `public/mod/attendancejourneys`, beneath the web root. The ZIP still contains a single top-level `attendancejourneys` directory.

The internal component must remain `mod_attendancejourneys` and the directory must remain `attendancejourneys`. Uploading a newer ZIP upgrades the existing Attendance Journeys installation. It does not import or replace another activity module.

Back up the Moodle database and Moodle data directory before any production upgrade. Test every release in a staging site first.

## Permissions

- Student: views the activity and their own information; may self-record only when enabled.
- Non-editing teacher: takes attendance and views reports.
- Editing teacher: additionally manages sessions and journeys.
- Manager: same operational capabilities as an editing teacher by default.
- Site administrator: additionally accesses exceptional participant reset and the institutional audit log.

Capabilities remain configurable through Moodle role overrides. Reviewing and converting historical grading requires both course activity editing and journey management. In separate-group mode it additionally requires access to all groups.

## Documentation

- Français : `docs/GUIDE_UTILISATEUR_FR.md`, `docs/GUIDE_ADMINISTRATEUR_FR.md`, `docs/MATRICE_MODES_FR.md`, `docs/MISE_A_NIVEAU_FR.md`, `docs/RECETTE_FONCTIONNELLE_FR.md`, `docs/MATRICE_ROLES_FR.md`, `docs/ACCESSIBILITE_FR.md` et `docs/TESTS_FR.md`.
- English: `docs/USER_GUIDE_EN.md`, `docs/ADMINISTRATOR_GUIDE_EN.md`, `docs/MODE_MATRIX_EN.md`, `docs/UPGRADE_GUIDE_EN.md`, `docs/ACCEPTANCE_TEST_EN.md`, `docs/ROLE_MATRIX_EN.md`, `docs/ACCESSIBILITY_EN.md` and `docs/TESTING_EN.md`.
- Español: `docs/GUIA_USUARIO_ES.md`, `docs/GUIA_ADMINISTRADOR_ES.md`, `docs/MATRIZ_MODOS_ES.md`, `docs/GUIA_ACTUALIZACION_ES.md`, `docs/PRUEBA_ACEPTACION_ES.md`, `docs/MATRIZ_ROLES_ES.md`, `docs/ACCESIBILIDAD_ES.md` y `docs/PRUEBAS_ES.md`.
- `CHANGELOG.md`, `CHANGELOG_FR.md` and `CHANGELOG_ES.md`: release history in all three languages.

## Licence / License

GNU General Public License v3 or later.

---

## Résumé français

Candidate locale `1.1.0-rc12`, numéro technique `2026100804`, compatibilité visée Moodle 4.5 à 5.3. Nom public et composant harmonisés; 24 captures fictives et guides illustrés actualisés en anglais, français et espagnol. La matrice native du nouveau composant et les transitions sur copies complètes sont vérifiées. Le ZIP ne met pas directement à niveau une installation de l’ancien composant. Une revue préalable à la diffusion reste nécessaire. Aucune publication GitHub, Moodle Marketplace ou AMOS.

Parcours d’assiduité offre les séances de parcours avec audience inscrite, de groupe ou explicitement affectée, les séries avec aperçu et ajustements individuels, les locaux du cours, les groupes Moodle, le calcul automatique des durées, les modalités en classe, en ligne ou hybrides, la présence partielle en minutes d’absence, les seuils configurables, l’auto-saisie étudiante avec approbation, les résultats définitifs, les notes, l’achèvement, les rapports, les exports et le journal d’audit. Les séances communes et de groupe indépendantes restent disponibles dans les activités historiques. Dans les nouvelles activités, une absence justifiée conserve le temps exigé; une dispense de séance autorisée le retire du calcul.

Le mode Light permet la clôture et la réouverture individuelles du parcours principal. Le mode Professionnel permet aussi plusieurs parcours simultanés et leur gestion avancée. Une affectation à un autre parcours ne transfère pas automatiquement les présences et ne retire pas l’obligation initiale. Le personnel autorisé peut prévisualiser et confirmer une période individuelle, une dispense entière et une nouvelle tentative personnelle. Une reprise utilise de nouvelles séances dans la même obligation, hérite de son seuil et conserve un seul élément de note. Son ouverture retire la note précédente jusqu’à la clôture; le dernier résultat remplace le précédent même s’il est inférieur. Les présences historiques restent conservées. Le plugin comprend également une terminologie métier multilingue personnalisable et verrouillable, ainsi qu’un centre d’aide illustré français, anglais et espagnol que l’administration peut masquer sans retirer les aides contextuelles Moodle.

Pour installer ou mettre à niveau le plugin, utilisez **Administration du site → Plugins → Installer des plugins**. Moodle doit annoncer le composant `mod_attendancejourneys`. Sauvegardez toujours la base de données et Moodledata et validez d’abord la version sur un site de préproduction.

Par défaut, l’étudiant consulte ses propres informations; l’enseignant non éditeur prend les présences et consulte les rapports; l’enseignant éditeur et le gestionnaire administrent aussi les sessions et les parcours; l’administrateur du site accède aux remises à zéro exceptionnelles et au journal d’audit. Toutes les capacités demeurent configurables dans Moodle.

La documentation française se trouve dans les fichiers `GUIDE_UTILISATEUR_FR.md`, `MISE_A_NIVEAU_FR.md`, `RECETTE_FONCTIONNELLE_FR.md` et `CHANGELOG_FR.md`. Les équivalents anglais sont fournis dans le même ZIP.

## Quick start and support

French quick guides: [installation](docs/INSTALLATION_RAPIDE_FR.md) and [teacher workflow](docs/DEMARRAGE_ENSEIGNANT_FR.md). Detailed multilingual guides remain in `docs/`. The separate demonstration backup contains no user data.

Attendance entry is limited to 100 participants per page. Search and filtering operate within the current page; save before moving to another page. The approval button approves declarations on the current page.
