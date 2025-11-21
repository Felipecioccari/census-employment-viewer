# Census Employment Viewer

React front end + Laravel API that visualize U.S. employment counts by state and quarter using the Census QWI dataset. Follow the steps below to clone and run the project quickly.

## Run Locally

- Prereqs: Node 18+ (tested with 20), npm, PHP 8.2+, Composer. The app only reads the Census API and does not require a database.
- Backend (API, port 8000):
  ```bash
  cd census-employment-viewer-backend
  cp .env.example .env   # CENSUS_QWI_BASE_URL defaults to the public API
  composer install
  php artisan serve --port=8000
  ```
- Frontend (Vite/React Router, port 5173):
  ```bash
  cd census-employment-viewer-frontend
  npm install
  npm run dev -- --host --port=5173
  ```
- Open the UI at `http://localhost:5173`. The front end calls the API at `http://localhost:8000/api`.

### Docker option

From the repo root you can also run both services together. You still need to complete the backend setup above (copy `.env`, install Composer dependencies) because the `docker-compose.yml` uses Sail:

- If you want to install PHP and Composer locally, follow the official docs: https://laravel.com/docs/12.x/installation
- If you prefer not to install PHP and Composer locally, run:
```bash
cd census-employment-viewer-backend
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs
```

Then from the repo root:
```bash
docker compose up --build
```

After the initial build you can use:
```bash
docker compose up
```
This binds the API to `http://localhost:80` and the front end to `http://localhost:5173`.

## Architecture Notes

- Backend: Laravel 12 exposes two routes (`/api/states`, `/api/employments`). `EmploymentController` fans out requests to the Census API via `CensusEmploymentService` using `Http::pool` for per-state (and per-sex) calls, aggregates totals, and returns partial-error details when some states fail. State metadata is served from `config/states.php`.
- Frontend: React Router 7 + Vite. The main screen lives in `app/routes/home.tsx` and uses `fetchStates`/`fetchEmployments` to populate filters and the results table; `AppShell` provides layout. Quarters are generated client-side from env bounds (`VITE_FIRST_YEAR_QUARTERS`, `VITE_LAST_YEAR_QUARTERS`).
- Data flow: user selects states/quarter → front end builds a query → hits the Laravel endpoints → API proxies the Census QWI service and aggregates results → front end renders totals with optional male/female breakdown and surfaces any partial errors.
- Trade-offs: No caching; every search hits the Census API so slow or rate-limited responses will bubble up. Errors are reported per-state/per-sex but the request succeeds when some data is returned. No persistence layer is included because the dataset is sourced on-demand.
