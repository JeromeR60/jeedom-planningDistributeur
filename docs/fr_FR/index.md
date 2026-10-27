# Plugin Planning Distributeur

Planification hebdomadaire des doses d'un distributeur pour animaux, avec un widget graphique qu'on modifie directement depuis le Dashboard ou le mobile.

- Grille de 7 jours × 24 heures : chaque case contient une dose (0 = rien)
- On choisit une dose puis on « peint » les cases au doigt ou à la souris
- Copie d'un jour sur la semaine (lun→ven ou 7 jours), vidage d'un jour
- Total du jour (distribué / prévu), prochaine et dernière distribution
- Un équipement par distributeur

## Configuration

1. **Plugins → Programmation → Planning Distributeur → Ajouter**.
2. Choisissez l'**objet parent** et la **commande de distribution** : la commande action du distributeur qui reçoit la dose (curseur, message ou liste).
3. Renseignez la **dose min** et la **dose max** (par défaut 1 à 12, maximum 50).
4. Sauvegardez. Le widget apparaît sur le Dashboard avec un planning vide, à remplir directement.

### Champs

| Champ | Rôle |
|---|---|
| Commande de distribution | Commande action qui reçoit la dose |
| Dose min / Dose max | Valeurs proposées dans le widget (0 = rien est toujours disponible) |
| Couleur du widget | Couleur des cases : plus la dose est forte, plus la case est foncée |
| Largeur du widget | 360 px par défaut |

## Fonctionnement

Chaque minute, le plugin regarde la dose prévue pour l'heure en cours. Elle est envoyée à l'heure pile, **une seule fois par heure**.

Si Jeedom était arrêté à l'heure pile, la distribution est rattrapée pendant les 10 premières minutes de l'heure. Au-delà, elle est abandonnée, pour ne pas nourrir l'animal en décalé. Une distribution n'est jamais faite deux fois dans la même heure, même après un redémarrage.

Modifier la case de l'heure en cours dans les 10 premières minutes déclenche donc sa distribution.

Si la dose min ou max change, le planning existant est ramené dans les nouvelles limites à la sauvegarde.

## Commandes

| Commande | Type | Usage |
|---|---|---|
| Planning | info | Planning (porte le widget) |
| Set planning | action message | Remplace le planning (format ci-dessous) |
| Prévu aujourd'hui | info numérique | Somme des doses prévues aujourd'hui |
| Distribué aujourd'hui | info numérique | Somme des doses distribuées aujourd'hui (remise à zéro à minuit) |
| Prochaine distribution | info | Heure et dose (`18:00 - 3`, `lun. 08:00 - 2`) |
| Dernière dose | info numérique | |
| Dernière distribution | info | Heure de la dernière distribution (`12:00`, `lun. 18:00`) |
| Distribuer | action message | Distribue tout de suite la dose indiquée (ex. `2`), hors planning ; comptée dans le total du jour |

## Format de l'info Planning

```
168 doses séparées par des virgules
```

Index d'une case : `jour × 24 + heure`, avec jour 0 = lundi. Chaque valeur vaut 0 ou une dose comprise entre la dose min et la dose max.
