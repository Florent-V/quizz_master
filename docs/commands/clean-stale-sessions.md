# Commande de nettoyage des sessions abandonnées

## Description

La commande `app:clean-stale-sessions` permet de supprimer les sessions de quiz avec le statut `IN_PROGRESS` qui sont restées inactives depuis plus de 24 heures (ou un nombre d'heures personnalisé). Ces sessions sont considérées comme abandonnées car l'utilisateur n'a pas terminé ou annulé sa partie.

## Utilisation

### Syntaxe de base

```bash
php bin/console app:clean-stale-sessions [options]
```

## Options disponibles

### `--dry-run`
Affiche les sessions qui seraient supprimées sans effectuer la suppression réellement.

**Utilisation :**
```bash
php bin/console app:clean-stale-sessions --dry-run
```

**Résultat :**
- Affiche le nombre de sessions et de réponses qui seraient supprimées
- Aucune donnée n'est supprimée de la base de données

---

### `-f, --force`
Force la suppression sans demander de confirmation interactive.

**Utilisation :**
```bash
php bin/console app:clean-stale-sessions --force
```

**Important :** À utiliser avec précaution, notamment dans les scripts automatisés.

---

### `--hours=X`
Définit le seuil d'inactivité en heures (par défaut : 24 heures).

**Utilisation :**
```bash
# Supprimer les sessions inactives depuis plus de 24h (par défaut)
php bin/console app:clean-stale-sessions

# Supprimer les sessions inactives depuis plus de 48h
php bin/console app:clean-stale-sessions --hours=48

# Supprimer les sessions inactives depuis plus de 12h
php bin/console app:clean-stale-sessions --hours=12

# Supprimer les sessions inactives depuis plus de 1h
php bin/console app:clean-stale-sessions --hours=1
```

**Note :** Le filtre se base sur la date de `updatedAt` de la session.

---

### `-v, --verbose`
Affiche un tableau détaillé avec les informations de chaque session qui sera supprimée, incluant le temps d'inactivité.

**Utilisation :**
```bash
php bin/console app:clean-stale-sessions --dry-run -v
```

**Affichage :**
| ID | Pseudo | Mode | Réponses | Dernière activité | Inactivité |
|----|--------|------|----------|-------------------|------------|
| ... | ... | ... | ... | 2025-11-09 08:23:42 | 28h |

## Exemples d'utilisation

### 1. Vérifier ce qui serait supprimé (sessions de plus de 24h)
```bash
php bin/console app:clean-stale-sessions --dry-run -v
```

### 2. Supprimer les sessions abandonnées depuis plus de 24h avec confirmation
```bash
php bin/console app:clean-stale-sessions
```

### 3. Supprimer les sessions abandonnées depuis plus de 48h sans confirmation
```bash
php bin/console app:clean-stale-sessions --hours=48 --force
```

### 4. Vérifier les sessions abandonnées depuis plus de 6h
```bash
php bin/console app:clean-stale-sessions --hours=6 --dry-run -v
```

### 5. Nettoyage agressif : sessions de plus de 2h
```bash
php bin/console app:clean-stale-sessions --hours=2 --force
```

## Automatisation

Pour automatiser le nettoyage, vous pouvez créer une tâche cron :

### Exemples de configurations cron

```bash
# Supprimer les sessions abandonnées de plus de 24h tous les jours à 3h du matin
0 3 * * * cd /path/to/project && php bin/console app:clean-stale-sessions --force

# Supprimer les sessions abandonnées de plus de 48h tous les lundis à 2h
0 2 * * 1 cd /path/to/project && php bin/console app:clean-stale-sessions --hours=48 --force

# Supprimer les sessions abandonnées de plus de 12h toutes les 6 heures
0 */6 * * * cd /path/to/project && php bin/console app:clean-stale-sessions --hours=12 --force
```

## Différence avec `app:clean-cancelled-sessions`

| Critère | clean-stale-sessions | clean-cancelled-sessions |
|---------|---------------------|--------------------------|
| **Statut ciblé** | `IN_PROGRESS` | `CANCELLED` |
| **Critère** | Inactivité (heures) | Date de mise à jour (jours) |
| **Seuil par défaut** | 24 heures | Tous |
| **Option de temps** | `--hours` | `--older-than` |
| **Cas d'usage** | Sessions abandonnées | Sessions explicitement annulées |

## Informations techniques

### Comportement de la suppression

- **Cascade** : Les réponses associées (`QuizSessionAnswer`) sont automatiquement supprimées grâce à l'option `orphanRemoval: true` dans la relation.
- **Soft Delete** : Si le trait `SoftDeleteableEntity` est actif, la suppression sera logique (soft delete) et non physique.
- **Transactions** : Chaque session est supprimée dans une transaction distincte pour éviter de perdre toutes les données en cas d'erreur sur une session.

### Statut concerné

Seules les sessions avec le statut `QuizSessionStatus::InProgress` sont concernées par cette commande.

Les autres statuts ne sont pas affectés :
- `FINISHED` - Terminé
- `CANCELLED` - Annulé
- `FAILED` - Échec

### Calcul d'inactivité

L'inactivité est calculée depuis la dernière mise à jour (`updatedAt`) de la session :
- Si une session est créée et jamais modifiée : inactivité = temps depuis `createdAt`
- Si une session a des réponses : inactivité = temps depuis la dernière réponse

### Colonne affichée en mode verbose

En mode verbose (`-v`), la colonne "Inactivité" affiche le nombre d'heures écoulées depuis la dernière activité, ce qui permet de visualiser rapidement les sessions les plus anciennes.

## Cas d'usage typiques

### 1. Maintenance quotidienne standard
```bash
# Cron quotidien pour nettoyer les sessions de plus de 24h
0 3 * * * php bin/console app:clean-stale-sessions --force
```
**Objectif** : Éviter l'accumulation de sessions abandonnées.

### 2. Nettoyage hebdomadaire conservateur
```bash
# Cron hebdomadaire pour les sessions de plus de 7 jours
0 2 * * 0 php bin/console app:clean-stale-sessions --hours=168 --force
```
**Objectif** : Laisser une marge pour les joueurs qui reviennent.

### 3. Nettoyage avant maintenance
```bash
# Avant une opération de maintenance
php bin/console app:clean-stale-sessions --hours=1 --dry-run -v
php bin/console app:clean-stale-sessions --hours=1 --force
```
**Objectif** : Libérer de l'espace avant une grosse opération.

## Sécurité

- ⚠️ **Attention** : Cette commande supprime définitivement les données (ou les marque comme supprimées en soft delete).
- ⚠️ **Seuil minimum** : Le nombre d'heures doit être >= 1.
- ✅ **Recommandation** : Toujours effectuer un backup de la base de données avant d'exécuter cette commande en production.
- ✅ **Bonne pratique** : Utiliser `--dry-run` en premier pour vérifier ce qui sera supprimé.
- ✅ **Conseil** : Pour un usage quotidien, privilégier un seuil de 24h minimum pour éviter de supprimer des sessions de joueurs en pause.

## Logs

La commande affiche :
- Le seuil d'inactivité utilisé
- La date limite calculée
- Le nombre total de sessions trouvées
- Le nombre total de réponses associées
- En mode verbose : tableau détaillé avec temps d'inactivité
- Le résultat de la suppression (sessions et réponses supprimées)
- Les erreurs éventuelles lors de la suppression

## Combinaison des deux commandes

Pour un nettoyage complet, vous pouvez combiner les deux commandes :

```bash
#!/bin/bash
# Script de nettoyage complet

# 1. Nettoyer les sessions abandonnées (>24h)
php bin/console app:clean-stale-sessions --force

# 2. Nettoyer les sessions annulées (>30 jours)
php bin/console app:clean-cancelled-sessions --older-than=30 --force
```

Ou dans le cron :
```bash
# Tous les jours à 3h : nettoyage des sessions abandonnées
0 3 * * * cd /path/to/project && php bin/console app:clean-stale-sessions --force

# Tous les jours à 4h : nettoyage des sessions annulées
0 4 * * * cd /path/to/project && php bin/console app:clean-cancelled-sessions --older-than=30 --force
```

## Support

En cas de problème, vérifier :
1. Que les permissions sur la base de données sont correctes
2. Que le statut `IN_PROGRESS` existe bien dans l'énumération `QuizSessionStatus`
3. Que le seuil d'heures est >= 1
4. Les logs Symfony pour plus de détails sur les erreurs

