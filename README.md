docker cp ./sql/init-data.sql symfony-db-local:/tmp/
SOURCE /tmp/init-data.sql;


## Quiz Master Application

Quiz Master is a Symfony and Vue.js application for managing and taking quizzes.

### Prerequisites

*   PHP 8.3 or higher
*   Symfony CLI
*   Composer
*   Node.js & npm/yarn (for frontend assets)
*   Docker (for database or full environment setup)

### Installation

1.  **Clone the repository:**
    ```bash
    git clone <repository-url>
    cd quiz-master 
    ```

2.  **Install PHP dependencies:**
    ```bash
    composer install
    ```

3.  **Install frontend dependencies:**
    ```bash
    npm install 
    # or
    yarn install
    ```

4.  **Setup environment variables:**
    Create a `.env.local` file and configure your database connection and other necessary variables (e.g., mailer DSN, OAuth credentials).
    ```env
    DATABASE_URL="mysql://user:password@127.0.0.1:3306/quiz_db?serverVersion=8.0.32&charset=utf8mb4"
    MAILER_DSN=smtp://localhost:1025
    # ... other variables
    ```

5.  **Database Setup:**
    *   Ensure your database server is running.
    *   Create the database if it doesn't exist:
        ```bash
        php bin/console doctrine:database:create
        ```
    *   Run migrations:
        ```bash
        php bin/console doctrine:migrations:migrate
        ```

6.  **Build frontend assets:**
    ```bash
    npm run dev 
    # or
    yarn dev
    # For production: npm run build / yarn build
    ```

7.  **Run the application:**
    ```bash
    symfony server:start
    ```

### Features

#### Quiz JSON Import

This application includes a feature to import quizzes from a JSON file, typically following the OpenQuizzDB format. This allows for bulk creation of categories, sub-categories, questions, and their translations.

**How to use the JSON Import:**

1.  **Access the Import Page:**
    *   Navigate to `/admin/quiz/import` in your browser.
    *   *(Note: This route might be protected and require administrator privileges.)*

2.  **Prepare your JSON File:**
    *   The JSON file should follow the structure provided in the initial specification. It includes:
        *   `fournisseur`, `licence`, `rédacteur`, `difficulté` (base difficulty).
        *   `catégorie-nom-slogan`: Contains translations for category name, sub-category name, and their slogans.
        *   `quizz`: Contains questions, proposals, answers, and anecdotes, organized by language (`fr`, `en`, etc.) and then by level (`débutant`, `confirmé`, `expert`).
    *   Example structure:
        ```json
        {
          "fournisseur": "OpenQuizzDB",
          "licence": "CC BY-SA",
          "rédacteur": "User",
          "difficulté": "2 / 5", // Base difficulty for the quiz
          "catégorie-nom-slogan": {
            "fr": {
              "catégorie": "Culture Générale",
              "nom": "Histoire",
              "slogan": "De l'antiquité à nos jours"
            },
            "en": {
              "catégorie": "General Knowledge",
              "nom": "History",
              "slogan": "From ancient times to the present day"
            }
          },
          "quizz": {
            "fr": {
              "débutant": [ 
                // Array of question objects
              ],
              "confirmé": [
                // Array of question objects
              ],
              "expert": [
                // Array of question objects
              ]
            },
            "en": {
              // ... similar structure for English questions
            }
          }
        }
        ```

3.  **Upload the File:**
    *   On the import page, click the "Choose File" (or similar) button.
    *   Select your prepared JSON file.
    *   Click the "Import Quiz" button.

4.  **Review the Summary:**
    *   After processing, a summary will be displayed showing:
        *   Number of categories, sub-categories, questions, and proposals created or updated.
        *   Any errors encountered during the import. Detailed error messages from the import process will also be shown as flash messages.
    *   Check the logs for more detailed error information if needed.

**Notes on Import Logic:**

*   **Categories & Sub-Categories:** Created or updated based on their names (slugified) in the default language (French). Translations for names and slogans are saved for all provided languages.
*   **Difficulty:** The base difficulty from the JSON (`"difficulté": "X / 5"`) is used. Questions under:
    *   `débutant`: Base difficulty - 1 (minimum 1).
    *   `confirmé`: Base difficulty.
    *   `expert`: Base difficulty + 1 (maximum 5).
        New `Difficulty` entities are created if they don't exist for a given level (1-5).
*   **Questions & Proposals:** Questions and their proposals are created. Translations for question content, explanations (anecdotes), and proposal content are saved.
*   **Idempotency:** The import service attempts to find existing categories and sub-categories by their slug (derived from the French name) to avoid duplicates. Questions are currently always created as new entities on import.
*   **Error Handling:** Validation errors or structural issues in the JSON will be reported in the summary and logs. The import process uses a database transaction, which is rolled back if a major error occurs during the main processing.

### Running Tests

```bash
php bin/phpunit
```

### Coding Standards

This project uses PHP CS Fixer for PHP code style and ESLint/Prettier for JavaScript/Vue.
```bash
vendor/bin/php-cs-fixer fix
npm run lint
npm run format
```
(Adjust commands based on your `composer.json` and `package.json` scripts)


docker cp ./sql/init-data.sql symfony-db-local:/tmp/
SOURCE /tmp/init-data.sql;

