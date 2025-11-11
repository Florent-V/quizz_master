# Commande de nettoyage des sessions annulées - Résumé

## 📋 Fichiers créés

### 1. `src/Command/CleanCancelledSessionsCommand.php`
Commande Symfony complète pour nettoyer les sessions avec le statut `CANCELLED`.

### 2. `docs/commands/clean-cancelled-sessions.md`
Documentation complète de la commande avec exemples d'utilisation.

## ✨ Fonctionnalités

### Options disponibles

| Option | Description |
|--------|-------------|
| `--dry-run` | Mode simulation - affiche ce qui serait supprimé sans rien supprimer |
| `-f, --force` | Supprime sans demander de confirmation |
| `--older-than=X` | Ne supprime que les sessions de plus de X jours |
| `-v, --verbose` | Affiche un tableau détaillé des sessions |

### Comportement

1. **Recherche** : Trouve toutes les sessions avec le statut `CANCELLED`
2. **Filtre optionnel** : Par date avec `--older-than`
3. **Affichage** : Nombre de sessions et réponses concernées
4. **Confirmation** : Demande confirmation sauf si `--force`
5. **Suppression** : 
   - Supprime les sessions
   - Les réponses sont supprimées automatiquement (cascade `orphanRemoval: true`)
6. **Résultat** : Affiche le nombre d'éléments supprimés

### Sécurité

- ✅ Mode `--dry-run` pour tester avant suppression
- ✅ Demande de confirmation par défaut
- ✅ Gestion des erreurs avec try-catch
- ✅ Barre de progression pour le suivi
- ✅ Compteurs précis des suppressions

## 🚀 Exemples d'utilisation

### Voir ce qui serait supprimé
```bash
php bin/console app:clean-cancelled-sessions --dry-run -v
```

### Supprimer toutes les sessions annulées
```bash
php bin/console app:clean-cancelled-sessions
```

### Supprimer uniquement les anciennes sessions (30+ jours)
```bash
php bin/console app:clean-cancelled-sessions --older-than=30 --force
```

## 🔧 Automatisation

Ajout possible dans cron pour nettoyage automatique :
```bash
# Chaque jour à 2h du matin
0 2 * * * cd /path/to/project && php bin/console app:clean-cancelled-sessions --older-than=30 --force
```

## 📊 Résultat des tests

- ✅ Commande enregistrée correctement
- ✅ Mode dry-run fonctionne
- ✅ Affichage verbose OK
- ✅ Filtre par date OK
- ✅ Aucune erreur PHPStan niveau 8
- ✅ Test sur base réelle : 8 sessions + 33 réponses identifiées

## 🎯 Points techniques

### Relation cascade
Les `QuizSessionAnswer` sont supprimées automatiquement grâce à :
```php
#[ORM\OneToMany(
    targetEntity: QuizSessionAnswer::class,
    mappedBy: 'quizSession',
    cascade: ['persist'],
    orphanRemoval: true  // ← Suppression automatique
)]
```

### Soft Delete
Si le trait `SoftDeleteableEntity` est actif, la suppression est logique (pas physique).

### Statut enum
La commande utilise `QuizSessionStatus::Cancelled` de l'enum pour filtrer.

## 📝 Notes

- La commande respecte les bonnes pratiques Symfony
- Code documenté avec PHPDoc
- Gestion propre des erreurs
- Interface utilisateur claire avec SymfonyStyle
- Compatible avec les workflows automatisés

