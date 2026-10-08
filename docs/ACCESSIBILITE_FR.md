# Accessibilité

Parcours d’assiduité s’appuie sur les composants et conventions Moodle et vise une utilisation complète au clavier et avec les technologies d’assistance.

- Les champs possèdent un libellé explicite, y compris lorsque le libellé est visuellement masqué.
- Les groupes de statuts utilisent des boutons radio dans un `fieldset` avec une légende.
- Les mises à jour de calcul et de progression sont annoncées par des régions `aria-live`.
- Les erreurs de minutes sont reliées au champ fautif avec `aria-describedby` et `aria-invalid`.
- Les tableaux larges peuvent recevoir le focus et défiler horizontalement au clavier.
- Un indicateur de focus visible est conservé sur les liens, boutons et champs.
- La couleur n’est pas le seul moyen utilisé pour transmettre un état : un texte ou un libellé accompagne les badges.

La conformité finale doit également être vérifiée avec le thème Moodle réellement utilisé, car celui-ci peut modifier les couleurs, les espacements et les styles de focus.
