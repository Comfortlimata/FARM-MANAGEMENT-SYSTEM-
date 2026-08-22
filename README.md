# Farm Management System

## Module Ownership

| Module       | Owner | Section |
|--------------|-------|---------|
| Inventory    |       |         |
| Equipment    |       |         |
| Labour       |       |         |
| Pest/Disease |       |         |
| Weather      |       |         |
| Harvest      |       |         |

## Tech Stack
- Backend: PHP (no framework)
- Database: MySQL
- Frontend: HTML, CSS, JavaScript

## Setup
1. Clone the repo
2. Copy `includes/config.example.php` to `includes/config.php` and fill in your DB credentials
3. Import `database/schema.sql` into MySQL
4. Create a feature branch for your module (see Git Workflow below)

## Git Workflow
- No direct pushes to `main` — all changes via Pull Requests
- Each teammate works on their own feature branch: `feature/<module-name>`
- PR must be reviewed by at least one teammate before merging
- Commit format: `[module] short description` — e.g. `[inventory] add stock table`
