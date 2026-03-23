# Ultra SQL Import Instructions

## 📦 File Ready for Production VPS

**File:** `ultra.sql`
**Size:** 102 KB
**Location:** `C:\wamp64\www\Projects\loka2\prod2prod\proddata3182026\ultra.sql`

---

## 📊 Data Included

| Table | Records |
|-------|---------|
| users | 104 |
| departments | 6 |
| vehicles | 11 |
| drivers | 20 |
| **requests** | **93** |
| request_passengers | 88 |
| assignment_history | 76 |
| approvals | 172 |
| approval_workflow | 93 |
| **TOTAL** | **663 rows** |

---

## 🚀 How to Import to Production VPS

### Step 1: Upload the file to your VPS

Using SCP:
```bash
scp C:\wamp64\www\Projects\loka2\prod2prod\proddata3182026\ultra.sql user@your-vps-ip:/tmp/
```

Or use FileZilla/SFTP to upload `ultra.sql` to `/tmp/` on your VPS.

### Step 2: Backup your production database (IMPORTANT!)

```bash
mysqldump -u your_db_user -p your_production_db > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Step 3: Import the data

```bash
mysql -u your_db_user -p your_production_db < /tmp/ultra.sql
```

Or from MySQL prompt:
```sql
USE your_production_db;
SOURCE /tmp/ultra.sql;
```

---

## ✅ Verify Import

After import, run these queries to verify:

```sql
-- Check total users
SELECT COUNT(*) FROM users;

-- Check total requests
SELECT COUNT(*) FROM requests;

-- Check some old requests (IDs 48-93 are from old database)
SELECT id, user_id, destination, status, created_at
FROM requests
WHERE id < 61
ORDER BY id DESC
LIMIT 10;

-- Check departments
SELECT id, name FROM departments;
```

---

## ⚠️ Important Notes

1. **INSERT IGNORE is used** - This means:
   - New data will be inserted
   - Existing records with same IDs will be preserved (not overwritten)
   - No duplicate key errors

2. **Foreign key checks are disabled** during import and re-enabled after

3. **Passwords are preserved** - Users can login with existing credentials

4. **Test first** - If you have a staging environment, test the import there first

---

## 🔙 Rollback (if needed)

If you need to undo the import:

```bash
# Restore from backup
mysql -u your_db_user -p your_production_db < backup_YYYYMMDD_HHMMSS.sql
```

---

## 🐛 Troubleshooting

**"Access denied"**
- Check MySQL credentials and permissions

**"Unknown database"**
- Create database first or verify database name

**"Table doesn't exist"**
- Ensure database schema exists before importing data

**"Duplicate entry" warnings**
- Normal with INSERT IGNORE - duplicates are skipped

---

## 📞 Support

For issues, check:
- `AGENTS.md` in project root
- `docs/DATABASE.md` for schema information
- MySQL error logs on your VPS

---

**Last Updated:** March 18, 2026
