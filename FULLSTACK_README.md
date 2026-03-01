# LOKA Fleet Management System - Full Stack Setup

This document describes the full-stack development setup for the LOKA Fleet Management System.

## Tech Stack

### Frontend
- **Framework**: Vue 3 with Composition API
- **Build Tool**: Vite 6
- **State Management**: Pinia
- **Routing**: Vue Router 4
- **Styling**: Tailwind CSS 3 + DaisyUI
- **Type Safety**: TypeScript 5
- **Testing**: Vitest + Playwright
- **Linting**: ESLint + Prettier

### Backend
- **Language**: PHP 8.2
- **Server**: Apache (production) / Built-in PHP server (development)
- **Database**: MySQL 8
- **Cache/Queue**: Redis 7
- **PDF Generation**: TCPDF
- **Testing**: PHPUnit 9
- **Code Quality**: PHPStan, PHPCS, Psalm

### DevOps
- **Containerization**: Docker + Docker Compose
- **CI/CD**: GitHub Actions
- **Version Control**: Git

## Project Structure

```
public_html/
├── api/                    # PHP API endpoints
├── assets/
│   ├── css/
│   │   └── app.css        # Tailwind CSS entry point
│   ├── js/
│   │   ├── api/           # API client methods
│   │   ├── composables/   # Vue composables
│   │   ├── components/    # Vue components
│   │   ├── router/        # Vue Router configuration
│   │   ├── stores/        # Pinia stores
│   │   ├── views/         # Vue page components
│   │   ├── test/          # Unit test setup
│   │   ├── e2e/           # E2E test specs
│   │   ├── app.js         # Main JavaScript (legacy)
│   │   ├── admin.js       # Vite entry for admin
│   │   └── main.js        # Vue app entry
│   └── dist/              # Built assets (generated)
├── classes/               # PHP classes
├── config/                # Configuration files
├── includes/              # PHP includes
├── migrations/            # Database migrations
├── pages/                 # PHP pages
├── vendor/                # Composer dependencies
├── .github/               # GitHub Actions workflows
├── .env.example           # Environment template
├── composer.json          # PHP dependencies
├── docker-compose.yml     # Docker services
├── Dockerfile             # Container definition
├── package.json           # Node dependencies
├── vite.config.js         # Vite configuration
├── tailwind.config.js     # Tailwind configuration
└── tsconfig.json          # TypeScript configuration
```

## Getting Started

### Prerequisites

- Node.js 22+
- PHP 8.2+
- MySQL 8+
- Redis 7+ (optional, for caching)
- Composer
- Git

### Installation

1. Clone the repository
```bash
git clone <repository-url>
cd loka2/public_html
```

2. Install PHP dependencies
```bash
composer install
```

3. Install Node dependencies
```bash
npm install
```

4. Configure environment
```bash
cp .env.example .env
# Edit .env with your database credentials
```

5. Set up the database
```bash
php setup_database.php
```

### Development

#### Start the development server

**Option 1: Using PHP built-in server**
```bash
php -S localhost:8000
```

**Option 2: Using Docker**
```bash
docker-compose up -d
```

**Option 3: Using WAMP/XAMPP**
Access via `http://localhost/loka2/public_html`

#### Start Vite dev server (for HMR)
```bash
npm run dev
```

The Vite dev server runs on `http://localhost:5173` and provides:
- Hot Module Replacement
- Fast refresh
- Source maps
- TypeScript checking

### Building for Production

```bash
npm run build
```

This creates optimized assets in `assets/dist/`.

## Available NPM Scripts

| Command | Description |
|---------|-------------|
| `npm run dev` | Start Vite dev server |
| `npm run build` | Build for production |
| `npm run preview` | Preview production build |
| `npm run test` | Run unit tests |
| `npm run test:ui` | Run tests with UI |
| `npm run test:coverage` | Run tests with coverage |
| `npm run test:e2e` | Run E2E tests |
| `npm run lint` | Run ESLint |
| `npm run lint:fix` | Fix ESLint errors |
| `npm run format` | Format with Prettier |
| `npm run format:check` | Check formatting |
| `npm run type-check` | TypeScript type check |
| `npm run validate` | Run all validation checks |

## Available Composer Scripts

| Command | Description |
|---------|-------------|
| `composer test` | Run PHPUnit tests |
| `composer test:coverage` | Run tests with coverage |
| `composer analyze` | Run PHPStan analysis |
| `composer cs-check` | Check code style |
| `composer cs-fix` | Fix code style issues |
| `composer psalm` | Run Psalm analysis |
| `composer metrics` | Generate code metrics |

## Docker Commands

```bash
# Build and start all services
docker-compose up -d

# Start with dev tools (phpMyAdmin, MailHog)
docker-compose --profile tools up -d

# Start with Node dev server
docker-compose --profile dev up -d

# View logs
docker-compose logs -f

# Stop services
docker-compose down

# Rebuild after changes
docker-compose up -d --build
```

## API Client Usage

```javascript
import api from '@/api'

// Auth
await api.auth.login({ email, password })
await api.auth.logout()

// Vehicles
const vehicles = await api.vehicles.list({ status: 'active' })
await api.vehicles.create(vehicleData)
await api.vehicles.update(id, updates)
```

## Vue Store Usage

```javascript
import { useAuthStore, useVehiclesStore } from '@/stores'

const authStore = useAuthStore()
const vehiclesStore = useVehiclesStore()

// Auth
await authStore.login({ email, password })
console.log(authStore.userName)

// Vehicles
await vehiclesStore.fetchVehicles()
console.log(vehiclesStore.vehicles)
```

## Testing

### Unit Tests (Vitest)

```bash
npm run test
```

### E2E Tests (Playwright)

```bash
npm run test:e2e
```

### PHP Tests (PHPUnit)

```bash
composer test
```

## Code Quality

### JavaScript/TypeScript

```bash
# Lint
npm run lint

# Format
npm run format

# Type check
npm run type-check

# All checks
npm run validate
```

### PHP

```bash
# Static analysis
composer analyze

# Code style check
composer cs-check

# Fix code style
composer cs-fix
```

## Deployment

### Using GitHub Actions

Push to `main` branch triggers automatic deployment to staging.

For production deployment:

```bash
git checkout main
git pull
npm run build
git add assets/dist
git commit -m "chore: build assets"
git push origin main
```

Then trigger the `deploy` workflow manually and select `production`.

### Manual Deployment

```bash
# Install dependencies
composer install --no-dev
npm ci

# Build assets
npm run build

# Run migrations
php run-migrations.php

# Clear cache
rm -rf cache/*
```

## Environment Variables

See `.env.example` for all available environment variables.

Key variables:
- `APP_ENV` - Application environment (development/production)
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` - Database credentials
- `VITE_API_BASE` - API base URL for frontend
- `REDIS_HOST`, `REDIS_PORT` - Redis configuration

## Troubleshooting

### Vite HMR not working
- Ensure Vite dev server is running (`npm run dev`)
- Check firewall settings for port 5173
- Clear browser cache and Vite cache (`rm -rf node_modules/.vite`)

### Docker issues
- Rebuild containers: `docker-compose up -d --build`
- Check logs: `docker-compose logs -f`
- Reset volumes: `docker-compose down -v`

### PHP errors
- Check error logs in `logs/` directory
- Ensure all PHP extensions are installed
- Verify `.env` configuration

## Contributing

1. Create a feature branch
2. Make your changes
3. Run `npm run validate` and `composer analyze`
4. Commit with conventional commit messages
5. Push and create a pull request

## License

Proprietary - All rights reserved
