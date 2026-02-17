# Pro Bros Providore CRM - Backend API

Laravel 12 RESTful API backend for the Pro Bros Providore CRM application.

## Tech Stack

- **Framework**: Laravel 12
- **Database**: MySQL
- **Authentication**: Laravel Sanctum (token-based)
- **Permissions**: Spatie Laravel-Permission

## Quick Start

### 1. Install Dependencies

```bash
cd crm-backend
composer install
```

### 2. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and configure your MySQL connection:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=probros_crm
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 3. Create Database

```bash
mysql -u root -p -e "CREATE DATABASE probros_crm;"
```

### 4. Run Migrations & Seed

```bash
php artisan migrate
php artisan db:seed
```

### 5. Start Development Server

```bash
php artisan serve
```

API available at: `http://localhost:8000/api`

## Default Users

| Role  | Email              | Password |
|-------|--------------------|----------|
| Admin | admin@probros.com  | password |
| Sales | rana@probros.com   | password |
| Sales | budi@probros.com   | password |
| Sales | sari@probros.com   | password |

## API Endpoints

### Authentication

| Method | Endpoint     | Description          |
|--------|--------------|----------------------|
| POST   | /api/login   | Login & get token    |
| POST   | /api/register| Register new user    |
| POST   | /api/logout  | Logout (revoke token)|
| GET    | /api/user    | Get current user     |

### Dashboard

| Method | Endpoint            | Description     |
|--------|---------------------|-----------------|
| GET    | /api/dashboard-stats| Get stats       |

### Companies

| Method | Endpoint              | Description            |
|--------|-----------------------|------------------------|
| GET    | /api/companies        | List (filter: area_id) |
| POST   | /api/companies        | Create                 |
| GET    | /api/companies/{id}   | Show                   |
| PUT    | /api/companies/{id}   | Update                 |
| DELETE | /api/companies/{id}   | Delete (soft)          |

### Contacts

| Method | Endpoint             | Description               |
|--------|----------------------|---------------------------|
| GET    | /api/contacts        | List (filter: company_id) |
| POST   | /api/contacts        | Create                    |
| GET    | /api/contacts/{id}   | Show                      |
| PUT    | /api/contacts/{id}   | Update                    |
| DELETE | /api/contacts/{id}   | Delete                    |

### Deals

| Method | Endpoint              | Description                    |
|--------|-----------------------|--------------------------------|
| GET    | /api/deals            | List (Sales: own, Admin: all)  |
| POST   | /api/deals            | Create                         |
| GET    | /api/deals/{id}       | Show                           |
| PUT    | /api/deals/{id}       | Update (triggers commission)   |
| DELETE | /api/deals/{id}       | Delete (soft)                  |
| GET    | /api/deals/statuses   | Get available statuses         |

### Deal Activities (Timeline)

| Method | Endpoint                    | Description    |
|--------|-----------------------------|----------------|
| GET    | /api/deals/{id}/activities  | Get timeline   |
| POST   | /api/deals/{id}/activities  | Add activity   |

### Commissions

| Method | Endpoint                      | Description    |
|--------|-------------------------------|----------------|
| GET    | /api/commissions              | List           |
| GET    | /api/commissions/summary      | Get summary    |
| PATCH  | /api/commissions/{id}/pay     | Mark as paid   |

### Areas (Admin Only)

| Method | Endpoint          | Description |
|--------|-------------------|-------------|
| GET    | /api/areas        | List        |
| POST   | /api/areas        | Create      |
| GET    | /api/areas/{id}   | Show        |
| PUT    | /api/areas/{id}   | Update      |
| DELETE | /api/areas/{id}   | Delete      |

## Deal Status Lifecycle

```
Lead → Contacted → Qualified → Quotes Sent → Trial Order → Active Customer → Retained/Growing
                                                                           ↘ Lost Customer
```

**Commission Auto-Calculation**: When a deal status changes to `active_customer`, a 5% commission is automatically calculated and recorded.

## Connecting to React Frontend

1. Start the Laravel backend: `php artisan serve`
2. In React frontend, configure API base URL: `http://localhost:8000/api`
3. Use Axios or fetch with credentials for authenticated requests

Example React API call:

```javascript
const response = await fetch('http://localhost:8000/api/deals', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
  },
});
const deals = await response.json();
```

## Testing

```bash
php artisan test
```
