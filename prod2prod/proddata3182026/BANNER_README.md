# 🎯 BANNER.SQL - Safe Data Migration File

## ✅ What is banner.sql?

This file contains **ONLY INSERT IGNORE statements** from the old database.

**It does NOT:**
- ❌ Create any tables (NO CREATE TABLE)
- ❌ Drop any tables (NO DROP TABLE)
- ❌ Alter any table structures (NO ALTER TABLE)

**It does:**
- ✅ Insert data into EXISTING tables
- ✅ Skip duplicates (INSERT IGNORE)
- ✅ Preserve existing structure in `127_0_0_1new.sql`

---

## 📦 File Information

**File:** `banner.sql`
**Size:** 102 KB
**Location:** `C:\wamp64\www\Projects\loka2\prod2prod\proddata3182026\banner.sql`

---

## 📊 Data to Import

| Table | Rows from Old DB | Notes |
|-------|-----------------|-------|
| users | 104 | Will skip if user exists |
| departments | 6 | Will skip if department exists |
| vehicles | 11 | Will skip if vehicle exists |
| drivers | 20 | Will skip if driver exists |
| requests | 93 | Will skip if request exists |
| request_passengers | 88 | Will skip if passenger exists |
| assignment_history | 76 | Will skip if history exists |
| approvals | 172 | Will skip if approval exists |
| approval_workflow | 93 | Will skip if workflow exists |
| **TOTAL** | **663 rows** | All safe to import |

---

## 🚀 How to Use

### Prerequisite

Your database (`fleetdb`) must already have the tables from `127_0_0_1new.sql` imported.

### Import Steps

1. **Upload banner.sql to your VPS:**
   ```bash
   scp C:\wamp64\www\Projects\loka2\prod2prod\proddata3182026\banner.sql user@your-vps:/tmp/
   ```

2. **SSH into your VPS:**
   ```bash
   ssh user@your-vps
   ```

3. **Backup your database (optional but recommended):**
   ```bash
   mysqldump -u your_db_user -p fleetdb > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

4. **Import banner.sql:**
   ```bash
   mysql -u your_db_user -p fleetdb < /tmp/banner.sql
   ```

---

## ✅ What Happens During Import

1. **Foreign key checks disabled** (for safety)
2. **Data is inserted** into existing tables
3. **Duplicates are skipped** (INSERT IGNORE)
4. **Foreign key checks re-enabled**
5. **Structure remains unchanged** (tables, columns, indexes all stay the same)

---

## 📋 After Import Verification

Run these queries to verify:

```sql
-- Check if all tables exist (should already exist)
SHOW TABLES;

-- Count rows in each table
SELECT 
    'users' as table_name, COUNT(*) as count FROM users
UNION ALL
SELECT 'departments', COUNT(*) FROM departments
UNION ALL
SELECT 'vehicles', COUNT(*) FROM vehicles
UNION ALL
SELECT 'drivers', COUNT(*) FROM drivers
UNION ALL
SELECT 'requests', COUNT(*) FROM requests
UNION ALL
SELECT 'request_passengers', COUNT(*) FROM request_passengers
UNION ALL
SELECT 'assignment_history', COUNT(*) FROM assignment_history
UNION ALL
SELECT 'approvals', COUNT(*) FROM approvals
UNION ALL
SELECT 'approval_workflow', COUNT(*) FROM approval_workflow;

-- Check if old requests were imported (IDs 48-93)
SELECT id, user_id, destination, status, created_at
FROM requests
WHERE id BETWEEN 48 AND 60
ORDER BY id;

-- Check a specific user
SELECT id, name, email, role, status
FROM users
WHERE id = 1;
```

---

## ⚠️ Important Notes

1. **Structure Preservation:** Your existing table structure from `127_0_0_1new.sql` will NOT be modified
2. **INSERT IGNORE:** Duplicate records (same ID) will be silently skipped
3. **Safe Import:** Foreign key checks are disabled during import
4. **No Data Loss:** Existing data in `127_0_0_1new.sql` is preserved

---

## 🔙 Rollback (if needed)

If something goes wrong:

```bash
# Restore from backup
mysql -u your_db_user -p fleetdb < backup_YYYYMMDD_HHMMSS.sql
```

Or manually delete imported data (NOT recommended):
```sql
DELETE FROM requests WHERE id < 61;
-- (Be very careful with this!)
```

---

## 🐛 Troubleshooting

**Error: "Table doesn't exist"**
- **Solution:** First import `127_0_0_1new.sql` to create table structures
- Then import `banner.sql` to add data

**Error: "Access denied"**
- **Solution:** Check MySQL credentials and permissions

**Warning: "Duplicate entry"**
- **Normal:** INSERT IGNORE skips duplicates safely

---

## 📄 File Comparison

| File | Contains | Purpose |
|------|-----------|---------|
| `127_0_0_1new.sql` | Table structures + new data | Import first for structure |
| `banner.sql` | ONLY old data (INSERT IGNORE) | Import second for data |

**Recommended Order:**
1. Import `127_0_0_1new.sql` → Creates tables with new structure
2. Import `banner.sql` → Adds old data into existing tables

---

## 🎯 Summary

✅ **What banner.sql does:**
- Inserts 663 rows of data from old database
- Uses INSERT IGNORE to avoid conflicts
- Preserves existing table structure
- Safe to import multiple times

❌ **What banner.sql does NOT do:**
- Create tables
- Drop tables
- Alter table structures
- Modify existing data

---

**You're ready to migrate!** 🚀

**Steps:**
1. Import `127_0_0_1new.sql` (table structure + new data)
2. Import `banner.sql` (old data only)
3. Verify with the queries above
