# Plugin Planning Distributeur

Planification hebdomadaire des doses d'un distributeur pour animaux, avec un widget graphique qu'on modifie directement depuis le Dashboard ou le mobile.

- Grille de 7 jours × 24 heures : chaque case contient une dose (0 = rien)
- On choisit une dose puis on « peint » les cases au doigt ou à la souris
- Sur écran tactile (mobile, Jeedom Connect), la grille est verrouillée par défaut pour pouvoir faire défiler la page sans la modifier : touchez le cadenas pour la déverrouiller (reverrouillage automatique après 30 s)
- Copie d'un jour sur la semaine (lun→ven ou 7 jours), vidage d'un jour
- Total du jour (distribué par le planning / prévu, doses manuelles indiquées à part), prochaine et dernière distribution
- Un équipement par distributeur

## Configuration

1. **Plugins → Organisation → Planning Distributeur → Ajouter**.
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

### Distributions manuelles

Une dose donnée hors planning avec la commande *Distribuer* ou *Distribuer (curseur)* passe par le plugin. Elle est envoyée au distributeur, puis comptée :

- dans *Distribué aujourd'hui* (total de la journée, planning + manuel) ;
- dans *Manuel aujourd'hui* (doses manuelles seules) ;
- dans *Dernière dose* et *Dernière distribution*.

Elle ne modifie pas le planning et n'empêche pas la distribution planifiée suivante. Une dose hors limites est ramenée entre la dose min et la dose max ; 0 ou une valeur non numérique est refusée.

Une dose envoyée directement à la commande du distributeur, sans passer par le plugin, n'est pas comptée.

### Total du jour dans le widget

Le widget affiche **les doses du planning distribuées / les doses prévues**, par exemple `10 / 19`. Les doses manuelles sont indiquées à part sous le total : « aujourd'hui (+1 manuelle) ». Le total ne dépasse donc plus le prévu quand on distribue à la main. Les deux compteurs repassent à 0 à minuit.

## Distribuer depuis un virtuel

Pour garder un curseur de distribution manuelle sur le Dashboard (par exemple dans un virtuel qui regroupe les infos du distributeur) **et** que les doses soient comptées :

1. Dans le virtuel, créez (ou modifiez) une commande **action**, sous-type **Curseur**, avec min et max égaux à la dose min et à la dose max de l'équipement (ex. 1 et 12).
2. Dans son premier champ, choisissez la commande **Distribuer (curseur)** de l'équipement Planning Distributeur : `#[Objet][Distributeur RDC][Distribuer (curseur)]#`.
3. Le second champ (valeur affichée par le curseur) peut rester sur l'info d'état du distributeur.
4. Sauvegardez.

À ne pas faire :

- **Cibler la commande du distributeur physique directement** : la dose n'est pas comptée par le plugin. Et si le virtuel appelle à la fois le distributeur et le plugin, la dose est distribuée deux fois.
- **Cibler *Distribuer* (type message) depuis un curseur** : à la sauvegarde, Jeedom repasse la commande du virtuel en sous-type Message, et le curseur disparaît. Le sélecteur de commande du virtuel ne propose d'ailleurs que des commandes du même sous-type.
- **Choisir la commande du virtuel comme *Commande de distribution* de l'équipement** : le plugin et le virtuel s'appelleraient en boucle. La commande de distribution doit toujours être celle du distributeur physique.

Depuis un scénario, on peut utiliser l'une ou l'autre commande : *Distribuer* avec la dose dans le champ *Message*, ou *Distribuer (curseur)* avec la dose comme valeur.

## Commandes

| Commande | Type | Usage |
|---|---|---|
| Planning | info | Planning (porte le widget) |
| Set planning | action message | Remplace le planning (format ci-dessous) |
| Prévu aujourd'hui | info numérique | Somme des doses prévues aujourd'hui |
| Distribué aujourd'hui | info numérique | Somme des doses distribuées aujourd'hui, planning + manuel (remise à zéro à minuit) |
| Manuel aujourd'hui | info numérique | Somme des doses distribuées manuellement aujourd'hui avec *Distribuer* ou *Distribuer (curseur)* (remise à zéro à minuit). Doses du planning = Distribué − Manuel |
| Prochaine distribution | info | Heure et dose (`18:00 - 3`, `lun. 08:00 - 2`) |
| Dernière dose | info numérique | |
| Dernière distribution | info | Heure de la dernière distribution (`12:00`, `lun. 18:00`) |
| Distribuer | action message | Distribue tout de suite la dose indiquée (ex. `2`), hors planning ; comptée dans *Distribué aujourd'hui* et *Manuel aujourd'hui* |
| Distribuer (curseur) | action curseur | Identique à *Distribuer*, la dose étant la valeur du curseur (bornes = dose min / max, mises à jour à la sauvegarde de l'équipement). À utiliser depuis un virtuel (voir plus haut) |

## Format de l'info Planning

```
168 doses séparées par des virgules
```

Index d'une case : `jour × 24 + heure`, avec jour 0 = lundi. Chaque valeur vaut 0 ou une dose comprise entre la dose min et la dose max.

## Historique des versions

- **0.1.8** : nouvelle commande *Distribuer (curseur)*, ciblable par une action curseur de virtuel.
- **0.1.7** : *Distribuer* accepte aussi la dose transmise par un curseur ou une liste.
- **0.1.6** : nouvelle info *Manuel aujourd'hui* (doses distribuées manuellement dans la journée). Le widget n'affiche plus que les doses du planning dans « X / Y aujourd'hui », les doses manuelles à part.
- **0.1.5** : verrou d'édition sur écran tactile (cadenas, reverrouillage automatique après 30 s).
- **0.1.4** : catégorie Organisation.
- **0.1.3** : bouton « Distribuer maintenant » retiré du widget ; la commande *Distribuer* est conservée.
- **0.1.2** : commande *Distribuer* et bouton « Distribuer maintenant » dans le widget.
- **0.1.1** : widget renommé pour ne plus être confondu avec celui du plugin Planning Thermostat ; migration automatique des commandes existantes.
- **0.1.0** : première version.
