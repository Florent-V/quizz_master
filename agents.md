# Fichier de Configuration pour l'Agent Gemini

Ce fichier sert de guide à l'agent IA Gemini pour l'assister dans le développement de ce projet. Il contient les informations essentielles sur la stack technique, les conventions et les commandes du projet.

## 1. Vue d'ensemble du projet

- **Nom du projet** : Quiz Master
- **Description** : Une application web développée avec Symfony et Vue.js pour la gestion et la participation à des quiz. Elle inclut une fonctionnalité d'import de quiz depuis des fichiers JSON.
- **Auteur** : Florent.V (florent@f5t.fr)

## 2. Stack Technique

### Backend
- **PHP** : ~8.3
- **Framework** : Symfony 7.2
- **ORM** : Doctrine
- **API** : API Platform
- **Admin** : EasyAdminBundle

### Frontend
- **JavaScript**
- **Frameworks JS** : Vue.js 3, Stimulus
- **Bundler** : Webpack Encore
- **CSS** : TailwindCSS, DaisyUI, Sass

### Base de données
- **SGBD** : MySQL 8.0.33

### Environnement
- **Conteneurisation** : Docker

## 3. Installation et Lancement

Le projet utilise un `Makefile` pour simplifier la gestion de l'environnement.

- **Installation initiale complète** :
  ```bash
  make setup
  ```
- **Installer les dépendances** :
  ```bash
  make install
  ```
- **Démarrer l'environnement de développement** (Docker + serveur Symfony) :
  ```bash
  make start
  ```
- **Arrêter l'environnement** :
  ```bash
  make stop
  ```
- **Démarrer uniquement les services Docker** (base de données, etc.) :
  ```bash
  make up
  ```
- **Arrêter les services Docker** :
  ```bash
  make down
  ```

## 4. Qualité du Code et Tests

### Tests
- **Lancer la suite de tests** (PHPUnit) :
  ```bash
  make tests
  ```

### Qualité du code PHP
- **Vérifier le style (dry-run)** :
  ```bash
  vendor/bin/php-cs-fixer fix --dry-run
  ```
- **Corriger le style automatiquement** :
  ```bash
  vendor/bin/php-cs-fixer fix
  ```
- **Analyse statique** (PHPStan) :
  ```bash
  vendor/bin/phpstan analyse
  ```

### Qualité du code Frontend
- **Linter les fichiers JS/Vue** :
  ```bash
  npm run lint
  ```
- **Corriger les fichiers JS/Vue** :
  ```bash
  npm run lint:fix
  ```
- **Formatter le code** (Prettier) :
  ```bash
  npm run format
  ```

### Outils d'automatisation
- **GrumPHP** est utilisé pour automatiser les vérifications avant les commits.
  - **Lancer sur les fichiers modifiés** : `make grumphp-git`
  - **Lancer sur tout le projet** : `make grumphp-run`

## 5. Conventions de Codage

- **PHP** : Le projet suit la convention de style **PSR-12**. `php-cs-fixer` est configuré pour l'appliquer (`@PSR12` et `@Symfony` dans `.php-cs-fixer.dist.php`). L'utilisation de `declare(strict_types=1);` est également forcée.
- **JavaScript/Vue** : Le style est géré par **ESLint** et **Prettier**.

## 6. Structure du Projet et Architecture

Le projet suit la structure standard d'une application Symfony :
- `src/` : Code source PHP (Entités, Services, Contrôleurs, etc.).
- `assets/` : Fichiers source du frontend (JS, Vue, SASS).
- `templates/` : Templates Twig.
- `config/` : Fichiers de configuration de l'application.
- `migrations/` : Migrations de la base de données Doctrine.
- `tests/` : Tests unitaires et fonctionnels (PHPUnit).
