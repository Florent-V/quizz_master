# Projet Web Symfony + Vue.js

## Stack Technique

### Backend
- **PHP** : 8.3
- **Symfony** : 7.2
- **Template Engine** : Twig
- **ORM** : Doctrine
- **API** : API Platform
- **Admin** : EasyAdminBundle

### Frontend
- **Bundler** : Webpack Encore
- **JavaScript Framework** : Vue.js avec Symfony UX
- **CSS Framework** : Tailwind CSS 4
- **UI Components** : DaisyUI 5
- **Icons** :
  - Symfony UX Icon pour les composants Twig
  - OhVue Icons pour les templates Vue.js

## Qualité de Code

### Linting et Analyse
- **JavaScript** : ESLint + Prettier
- **PHP** : PHPMD + PHPStan

### Principes de Développement
- **Clean Code** : Code propre et lisible
- **Maintenabilité** : Code facile à maintenir et faire évoluer
- **SOLID** : Respect des principes SOLID
- **DRY** : Don't Repeat Yourself
- **KISS** : Keep It Simple, Stupid

## Conventions de Code

### PHP/Symfony
- Suivre les standards PSR-12
- Utiliser les annotations/attributes Symfony
- Services avec injection de dépendances
- Entités avec validation Symfony
- Controllers légers, logique métier dans les services

### JavaScript/Vue.js
- Composition API privilégiée
- Components en Single File Components (.vue)
- Props typées avec TypeScript si possible
- Emit events pour la communication parent/enfant

### CSS/Styling
- Classes utilitaires Tailwind CSS
- Composants DaisyUI pour les éléments complexes
- Responsive design mobile-first
- Variables CSS personnalisées pour le theming

## Structure de Fichiers

```
src/
├── Controller/          # Controllers Symfony
├── Entity/             # Entités Doctrine
├── Form/               # Formulaires Symfony
├── Repository/         # Repositories Doctrine
├── Service/            # Services métier
└── Twig/              # Extensions Twig

templates/
├── base.html.twig      # Template de base
├── components/         # Composants Twig réutilisables
└── pages/             # Templates de pages

assets/
├── app.js             # Point d'entrée JavaScript
├── styles/
│   └── app.css        # Styles Tailwind
└── vue/
    └── components/    # Composants Vue.js

public/
└── build/            # Assets compilés (généré)
```

## Guidelines de Développement

### Backend (Symfony)
- Un controller par ressource
- Services avec interface quand approprié
- Validation côté serveur obligatoire
- Gestion des erreurs avec try/catch
- Tests unitaires pour la logique métier

### Frontend (Vue.js + Twig)
- Composants Vue.js pour les interactions complexes
- Twig pour le rendu initial et SEO
- Progressive enhancement
- Accessibilité (WCAG 2.1 AA)
- Performance (lazy loading, code splitting)

### Base de Données
- Migrations Doctrine pour tous les changements
- Relations explicites entre entités
- Index sur les colonnes de recherche fréquente
- Soft delete si nécessaire

## Configuration Recommandée

### ESLint
```sh
./eslint.config.mjs
```

### PHPStan
```sh
./phpstan.dist.neon
```

### PHPMD
```sh
./phpmd.xml
```

## Notes Spécifiques

- Utiliser `stimulus` pour les micro-interactions
- `Vue.js` pour les composants avec état complexe
- Hydratation côté client pour les composants Vue.js
- Cache Symfony pour les performances
- Symfony UX pour l'intégration frontend/backend