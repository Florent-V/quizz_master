# Commande de nettoyage des sessions annulées

## Description

La commande `app:clean-cancelled-sessions` permet de supprimer les sessions de quiz avec le statut `CANCELLED` ainsi que toutes leurs réponses associées.

## Utilisation

### Syntaxe de base

```bash
php bin/console app:clean-cancelled-sessions [options]
```

## Options disponibles

### `--dry-run`
Affiche les sessions qui seraient supprimées sans effectuer la suppression réellement.

**Utilisation :**
```bash
php bin/console app:clean-cancelled-sessions --dry-run
```

**Résultat :**
- Affiche le nombre de sessions et de réponses qui seraient supprimées
- Aucune donnée n'est supprimée de la base de données

---

### `-f, --force`
Force la suppression sans demander de confirmation interactive.

**Utilisation :**
```bash
php bin/console app:clean-cancelled-sessions --force
```

**Important :** À utiliser avec précaution, notamment dans les scripts automatisés.

---

### `--older-than=X`
Supprime uniquement les sessions annulées datant de plus de X jours.

**Utilisation :**
```bash
# Supprimer les sessions annulées de plus de 30 jours
php bin/console app:clean-cancelled-sessions --older-than=30

# Supprimer les sessions annulées de plus de 7 jours
php bin/console app:clean-cancelled-sessions --older-than=7
```

**Note :** Le filtre se base sur la date de `updatedAt` de la session.

---

### `-v, --verbose`
Affiche un tableau détaillé avec les informations de chaque session qui sera supprimée.

**Utilisation :**
```bash
php bin/console app:clean-cancelled-sessions --dry-run -v
```

**Affichage :**
| ID | Pseudo | Mode | Réponses | Dernière mise à jour |
|----|--------|------|----------|---------------------|
| ... | ... | ... | ... | ... |

## Exemples d'utilisation

### 1. Vérifier ce qui serait supprimé
```bash
php bin/console app:clean-cancelled-sessions --dry-run -v
```

### 2. Supprimer toutes les sessions annulées avec confirmation
```bash
php bin/console app:clean-cancelled-sessions
```

### 3. Supprimer les sessions annulées de plus de 30 jours sans confirmation
```bash
php bin/console app:clean-cancelled-sessions --older-than=30 --force
```

### 4. Vérifier les sessions de plus de 7 jours qui seraient supprimées
```bash
php bin/console app:clean-cancelled-sessions --older-than=7 --dry-run -v
```

## Automatisation

Pour automatiser le nettoyage, vous pouvez créer une tâche cron :

```bash
# Supprimer les sessions annulées de plus de 30 jours tous les jours à 2h du matin
0 2 * * * cd /path/to/project && php bin/console app:clean-cancelled-sessions --older-than=30 --force
```

## Informations techniques

### Comportement de la suppression

- **Cascade** : Les réponses associées (`QuizSessionAnswer`) sont automatiquement supprimées grâce à l'option `orphanRemoval: true` dans la relation.
- **Soft Delete** : Si le trait `SoftDeleteableEntity` est actif, la suppression sera logique (soft delete) et non physique.
- **Transactions** : Chaque session est supprimée dans une transaction distincte pour éviter de perdre toutes les données en cas d'erreur sur une session.

### Statut concerné

Seules les sessions avec le statut `QuizSessionStatus::Cancelled` sont concernées par cette commande.

Les autres statuts ne sont pas affectés :
- `IN_PROGRESS` - En cours
- `FINISHED` - Terminé
- `FAILED` - Échec

## Sécurité

- ⚠️ **Attention** : Cette commande supprime définitivement les données (ou les marque comme supprimées en soft delete).
- ✅ **Recommandation** : Toujours effectuer un backup de la base de données avant d'exécuter cette commande en production.
- ✅ **Bonne pratique** : Utiliser `--dry-run` en premier pour vérifier ce qui sera supprimé.

## Logs

La commande affiche :
- Le nombre total de sessions trouvées
- Le nombre total de réponses associées
- Le résultat de la suppression (sessions et réponses supprimées)
- Les erreurs éventuelles lors de la suppression

## Support

En cas de problème, vérifier :
1. Que les permissions sur la base de données sont correctes
2. Que le statut `CANCELLED` existe bien dans l'énumération `QuizSessionStatus`
3. Les logs Symfony pour plus de détails sur les erreurs

