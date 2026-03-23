# Trip Data Migration Guide

This directory contains tools to migrate trip-related data from the old LOKA database to the new database.

## Files Available

### Source Files
- `127_0_0_1old.sql` - Old database dump (source)
- `127_0_0_1new.sql` - New database dump (target)

### Migration Tools
- `extract_trip_data.php` - **RECOMMENDED** - Extracts trip data from old SQL file and creates migration SQL
- `migrate_from_sql_files.php` - Alternative PHP extraction script
- `migrate_trip_data.php` - Direct database-to-database migration script
- `extract_trip_data.sh` - Bash extraction script

### Generated Files
- `trip_data_migration.sql` - Generated migration SQL (output from extraction scripts)

## Quick Start (Recommended)

### Step 1: Extract trip data from old SQL file

```bash
php extract_trip_data.php
```

This will generate `trip_data_migration.sql` with all trip-related data from the old database.

### Step 2: Import into new database

```bash
mysql -u root -p loka_new < trip_data_migration.sql
```

Or from MySQL prompt:
```sql
USE loka_new;
SOURCE trip_data_migration.sql;
```

## What Data Is Migrated?

The following trip-related tables are migrated:

| Table | Description | Rows in Old DB |
|-------|-------------|----------------|
| `users` | User accounts | 104 |
| `departments` | Department info | 6 |
| `vehicles` | Vehicle information | 11 |
| `drivers` | Driver records | 20 |
| `requests` | Trip requests | 93 |
| `request_passengers` | Passenger lists | 88 |
| `assignment_history` | Vehicle/driver assignments | 76 |
| `approvals` | Approval records | 172 |
| `approval_workflow` | Workflow history | 93 |
| `fuel_records` | Fuel records | 0 (no data) |
| `maintenance` | Maintenance records | 0 (no data) |
| `maintenance_requests` | Maintenance requests | 0 (no data) |

**Total: 663 rows**

## Important Notes

### INSERT IGNORE
The migration uses `INSERT IGNORE` statements, which means:
- New data from old database will be inserted
- Existing data with same primary keys will be skipped (not overwritten)
- No duplicate key errors will occur

### Foreign Key Constraints
- Foreign key checks are disabled during migration
- They are re-enabled after migration completes
- This ensures referential integrity is maintained

### Trip Tickets
The new database has a `trip_tickets` table that doesn't exist in the old database. This table is separate from the trip requests and contains additional trip completion data. The old trip request data will be available, but trip tickets would need to be created based on the requests if needed.

## Alternative Methods

### Method 2: Direct Database Migration

If you have both databases running:

1. Edit `migrate_trip_data.php` and update database credentials:
```php
$oldDbConfig = [
    'host' => '127.0.0.1',
    'dbname' => 'loka_old',  // Change to your old DB name
    'username' => 'root',
    'password' => '',        // Add your password
];

$newDbConfig = [
    'host' => '127.0.0.1',
    'dbname' => 'loka_new',  // Change to your new DB name
    'username' => 'root',
    'password' => '',        // Add your password
];
```

2. Run the migration:
```bash
php migrate_trip_data.php
```

### Method 3: Using Bash Script

```bash
bash extract_trip_data.sh
```

Then import the generated file:
```bash
mysql -u root -p loka_new < trip_data_extracted.sql
```

## Verifying Migration

After migration, verify the data:

```sql
-- Check requests count
SELECT COUNT(*) FROM requests;

-- Check completed requests
SELECT COUNT(*) FROM requests WHERE status = 'completed';

-- Check recent requests
SELECT id, user_id, destination, status, created_at
FROM requests
ORDER BY created_at DESC
LIMIT 10;

-- Check vehicles
SELECT id, make, model, plate_number, status
FROM vehicles;

-- Check drivers
SELECT id, name, license_number, status
FROM drivers d
JOIN users u ON d.user_id = u.id;
```

## Troubleshooting

### Error: "Unknown database 'loka_new'"

Make sure the new database exists:
```sql
CREATE DATABASE loka_new CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Error: "Access denied for user"

Check your MySQL credentials and permissions.

### Duplicate Key Errors

The migration uses `INSERT IGNORE`, so duplicate keys should be silently skipped. If you get errors, check that:
- Primary key values don't conflict
- Foreign key references exist in parent tables

### Missing Data After Migration

If some data is missing:
1. Check the original SQL file has the data
2. Verify the table structure matches between databases
3. Check for any errors in the extraction output

## Backup Recommendations

Before importing:

1. Backup the new database:
```bash
mysqldump -u root -p loka_new > backup_before_migration.sql
```

2. After migration, verify and then create another backup:
```bash
mysqldump -u root -p loka_new > backup_after_migration.sql
```

## Customization

### Adding More Tables

To include additional tables, edit the `$tables` array in the extraction script:

```php
$tables = [
    'users',
    'departments',
    'vehicles',
    'drivers',
    'requests',
    // Add your custom tables here
    'custom_table1',
    'custom_table2',
];
```

### Filtering Data

To filter specific data (e.g., only completed trips), modify the SQL extraction pattern in the script.

## Support

For issues or questions:
1. Check the AGENTS.md file in the project root
2. Review the database schema in docs/DATABASE.md
3. Check the migration script output for specific error messages

## Summary

The migration extracts **663 rows** of trip-related data from the old database across 12 tables. The process uses `INSERT IGNORE` to safely merge data without overwriting existing records.

**Migration Status:**
- ✓ Users: 104 rows
- ✓ Departments: 6 rows
- ✓ Vehicles: 11 rows
- ✓ Drivers: 20 rows
- ✓ Requests: 93 rows
- ✓ Passengers: 88 rows
- ✓ Assignment History: 76 rows
- ✓ Approvals: 172 rows
- ✓ Approval Workflow: 93 rows

**Total: 663 rows ready for migration!**
