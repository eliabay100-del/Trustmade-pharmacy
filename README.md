# Pharmacy Management Accounting System (PMAS)

A comprehensive pharmacy management system built with Laravel + MySQL + Blade, featuring inventory management with FEFO batch allocation, double-entry accounting, and role-based access control.

## Requirements

- PHP 8.2+
- Composer
- Node.js & NPM
- MySQL 8.0+ or SQLite (for testing)

## Installation Steps

### 1. Clone and Install Dependencies

```bash
cd /workspace
composer install
npm install && npm run build
```

### 2. Environment Configuration

Copy the example environment file and configure your database:

```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` with your database credentials:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pmas
DB_USERNAME=root
DB_PASSWORD=your_password
```

For testing, the system uses SQLite in-memory database (configured in `phpunit.xml`).

### 3. Database Migration and Seeding

```bash
php artisan migrate --seed
```

### ⚠️ CRITICAL: AccountSeeder Must Run First

**IMPORTANT**: Before any sale or purchase transactions can be processed, you MUST run the `AccountSeeder`. The `AccountingService` uses `Account::where('code', ...)->firstOrFail()` to find accounts, and will throw an exception if the required accounts don't exist.

The seeder creates the following chart of accounts:
- `1000` - Cash
- `1100` - Bank
- `1200` - Inventory
- `2000` - Accounts Payable
- `3000` - Equity
- `4000` - Revenue
- `5000` - Cost of Goods Sold (COGS)
- `5100` - Inventory Shrinkage

If you encounter errors like "Account with code XXXX not found", re-run:
```bash
php artisan db:seed --class=AccountSeeder
```

### 4. Start Development Server

```bash
php artisan serve
```

Visit `http://localhost:8000` in your browser.

## Default Login Credentials

After seeding, use these credentials to log in:

| Role | Email | Password |
|------|-------|----------|
| Owner/Admin | admin@pmas.com | password |

## Running Tests

```bash
php artisan test
```

## Role-Based Access Control

The system implements three-tier RBAC via `role:` middleware:

- **Tier 1** (`owner_admin`, `branch_manager`, `pharmacist_clerk`): Day-to-day operations (dispensing, purchases, returns, adjustments)
- **Tier 2** (`owner_admin`, `branch_manager`): Stock transfers, branch management, financial reports
- **Tier 3** (`owner_admin` only): User/staff management

## Core Features

- **Inventory Management**: FEFO (First Expiry, First Out) batch allocation with concurrency-locked stock
- **Double-Entry Accounting**: Automatic journal entries for all transactions
- **Multi-branch Support**: Branch-level stock tracking and reporting
- **Expiry Alerts**: Configurable notifications for near-expiry and low-stock items
- **Customer Returns**: Full return processing with stock restoration
- **Supplier Returns**: Return to supplier with AP reversal
- **Stock Adjustments**: Write-off workflow with accounting integration
- **Till Closing**: Cashier reconciliation and shift closing

## License

Proprietary software - All rights reserved.
