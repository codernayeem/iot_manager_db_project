# IoT Manager Database Project - Schema Update Summary

## Overview
Complete restructuring of the database schema and PL/SQL implementation according to exact specifications.

---

## 📊 EXACT SCHEMA IMPLEMENTATION

### Tables (7 Total)

1. **users** (user_id, f_name, l_name, email, password, created_at, updated_at)
   - Primary Key: user_id (AUTO_INCREMENT)
   - Unique: email
   - Timestamps: Auto-managed by trigger

2. **device_types** (t_id, t_name, desc)
   - Primary Key: t_id (AUTO_INCREMENT)
   - Unique: t_name
   - New field: `desc` (TEXT) - device type description

3. **devices** (d_id, d_name, t_id, user_id, serial_number, status, purchase_date, created_at, updated_at)
   - Primary Key: d_id (AUTO_INCREMENT)
   - Status: ENUM('error', 'warning', 'info') - **CHANGED from previous**
   - Unique: serial_number
   - Foreign Keys: t_id → device_types, user_id → users
   - Timestamps: Auto-managed by trigger

4. **locations** (loc_id, loc_name, address, latitude, longitude, created_at, updated_at)
   - Primary Key: loc_id (AUTO_INCREMENT)
   - Coordinates: DECIMAL for precise geolocation
   - Timestamps: Auto-managed by trigger

5. **deployments** (d_id, loc_id, deployed_at)
   - **Composite Primary Key: (d_id, loc_id)** - **CHANGED from previous**
   - Foreign Keys: d_id → devices, loc_id → locations
   - Simplified junction table (removed deployment_id, deployed_by, notes, is_active)

6. **device_logs** (log_id, d_id, log_time, log_type, message, severity_level, resolved_by, resolved_at, resolution_notes)
   - Primary Key: log_id (AUTO_INCREMENT)
   - log_type: ENUM('error', 'warning', 'info', 'debug')
   - severity_level: INT (1-10 scale)
   - Foreign Keys: d_id → devices, resolved_by → users
   - Removed: created_at field

7. **alerts** (log_id, alert_time, message, status)
   - **NEW TABLE**
   - Primary Key: log_id (also Foreign Key → device_logs)
   - Status: ENUM('active', 'resolved')
   - Auto-created by trigger for high severity errors

---

## 🔧 PL/SQL FEATURES IMPLEMENTATION

### Views (2 Views - Both Meaningful & Complex)

#### 1. v_device_deployment_summary
**Purpose**: Comprehensive device overview with deployment and alert information
**Features Used**:
- Multiple INNER JOINs (4 tables)
- LEFT JOINs (for optional relationships)
- Subquery (unresolved log count)
- GROUP BY with multiple columns
- Aggregation (COUNT DISTINCT)

**Use Case**: Dashboard overview, device monitoring, quick status checks

#### 2. v_unresolved_critical_logs
**Purpose**: Critical error logs requiring immediate attention
**Features Used**:
- Multiple INNER JOINs (devices, device_types, users)
- LEFT JOINs (alerts, deployments, locations)
- WHERE clause filtering (unresolved + high severity)
- CASE statement (severity categorization)
- DATEDIFF calculation (days unresolved)
- ORDER BY (priority-based sorting)

**Use Case**: Alert dashboard, issue tracking, maintenance prioritization

---

### Stored Procedures (2 Procedures - Advanced PL/SQL)

#### 1. sp_generate_device_report(IN device_status VARCHAR)
**Purpose**: Generate comprehensive health report for devices
**PL/SQL Features**:
- ✅ **CURSOR** - Iterate through devices
- ✅ **LOOP (read_loop)** - Process each device
- ✅ **Variables** - Multiple DECLARE statements (10+ variables)
- ✅ **IF/ELSE** - Health score bounds checking
- ✅ **Temporary Table** - Store and return results
- ✅ **CASE statement** - Health status categorization
- ✅ **Complex calculations** - Multi-factor health scoring

**Use Case**: Device health monitoring, performance analysis, reporting

#### 2. sp_bulk_resolve_alerts(IN device_id, IN resolver_id, IN notes, OUT alerts_resolved)
**Purpose**: Batch resolve all active alerts for a device
**PL/SQL Features**:
- ✅ **WHILE LOOP** - Process alerts sequentially
- ✅ **IF/ELSE** - Multiple conditional branches
- ✅ **Variables** - Counter, totals, severity tracking
- ✅ **Error Handling** - SIGNAL SQLSTATE for validation
- ✅ **Temporary Table** - Store log IDs for processing
- ✅ **OUT Parameter** - Return count of resolved alerts
- ✅ **UPDATE operations** - Batch log resolution

**Use Case**: Maintenance workflows, bulk issue resolution, automation

---

### Functions (2 Functions - Calculation & Logic)

#### 1. fn_get_device_health_score(device_id) RETURNS DECIMAL(5,2)
**Purpose**: Calculate 0-100 health score for a device
**PL/SQL Features**:
- ✅ **Variables** - Multiple calculations
- ✅ **IF/ELSE** - Input validation, bounds checking
- ✅ **Subqueries** - CASE aggregation in SELECT
- ✅ **Complex calculations** - Multi-factor scoring algorithm
- ✅ **COALESCE** - NULL handling

**Scoring Algorithm**:
- Base score: 100
- Error penalty: -5 points each
- Warning penalty: -2 points each
- Unresolved penalty: -3 points each
- Active alert penalty: -10 points each
- Average severity penalty: -2 × avg_severity

**Use Case**: Device ranking, health dashboards, predictive maintenance

#### 2. fn_get_alert_summary(device_id) RETURNS VARCHAR(255)
**Purpose**: Get formatted alert statistics summary
**PL/SQL Features**:
- ✅ **Variables** - Status tracking
- ✅ **IF/ELSE** - Multi-level conditional logic
- ✅ **String manipulation** - CONCAT operations
- ✅ **CASE aggregation** - Count by condition
- ✅ **COALESCE** - NULL handling

**Output Examples**:
- "CRITICAL - Active: 3, Resolved: 5, Total: 8 (2 critical)"
- "ALL RESOLVED - Active: 0, Resolved: 12, Total: 12"
- "No alerts recorded"

**Use Case**: Alert widgets, notifications, status displays

---

### Triggers (5 Triggers - Automation)

#### 1. trg_users_updated_at
**Type**: BEFORE UPDATE on users
**Purpose**: Auto-update updated_at timestamp

#### 2. trg_locations_updated_at
**Type**: BEFORE UPDATE on locations
**Purpose**: Auto-update updated_at timestamp

#### 3. trg_devices_updated_at
**Type**: BEFORE UPDATE on devices
**Purpose**: Auto-update updated_at timestamp

#### 4. trg_create_alert_from_log
**Type**: AFTER INSERT on device_logs
**Purpose**: Auto-create alert for high severity errors
**Logic**: IF log_type = 'error' AND severity_level > 5 THEN create alert
**Features**: IF condition, INSERT operation

#### 5. trg_update_alert_status
**Type**: AFTER UPDATE on device_logs
**Purpose**: Auto-resolve alert when log is resolved
**Logic**: IF resolved_by changed from NULL to value THEN update alert status
**Features**: IF condition, UPDATE operation

---

## 📈 PL/SQL FEATURES DISTRIBUTION

| Feature | Location | Usage |
|---------|----------|-------|
| CURSOR | sp_generate_device_report | Device iteration |
| LOOP (read_loop) | sp_generate_device_report | Process cursor results |
| WHILE LOOP | sp_bulk_resolve_alerts | Alert processing |
| IF/ELSE | All procedures, functions, triggers | Conditional logic |
| Variables (DECLARE) | All procedures & functions | Data storage |
| Temporary Tables | sp_generate_device_report, sp_bulk_resolve_alerts | Result storage |
| Error Handling (SIGNAL) | sp_bulk_resolve_alerts | Validation errors |
| Subqueries | Views, functions | Nested calculations |
| CASE statements | Views, procedures | Categorization |
| Aggregation (COUNT, SUM, AVG) | Views, procedures, functions | Statistics |

**All features are appropriately distributed - no redundancy, all practical use cases**

---

## 📝 DEMO DATA UPDATES

### Updated Files:
1. **device_types_data.sql** - Added `desc` field with descriptions
2. **devices_data.sql** - Changed status values to 'error', 'warning', 'info'; added explicit IDs
3. **deployments_data.sql** - Removed extra fields, kept only (d_id, loc_id, deployed_at)
4. **device_logs_data.sql** - **NEW FILE** with manual log entries
   - High severity errors (severity > 5) - Will auto-create alerts via trigger
   - Medium severity errors
   - Low severity errors (won't create alerts)
   - Warning logs
   - Info logs
   - One resolved log example

### Data Flow:
1. Users → Inserted first (no dependencies)
2. Device Types → With descriptions
3. Locations → Geographic data
4. Devices → References users & device_types, uses new status ENUM
5. Deployments → Simple junction table
6. Device Logs → **Triggers auto-create alerts for severity > 5**

---

## 🗑️ REMOVED/CLEANED UP

### Deleted Files:
- `sql/triggers/trg_device_updated_at.sql` (replaced by trg_devices_updated_at.sql)
- `sql/triggers/trg_log_new_device.sql` (no longer needed)

### Updated Files (Complete Rewrites):
- All table definitions
- Both views
- Both procedures
- Both functions
- Installation script

---

## 🚀 INSTALLATION ORDER

```sql
SOURCE sql/install_complete.sql;
```

**Execution sequence**:
1. Create database
2. Create tables (dependency order: users → device_types → locations → devices → deployments → device_logs → alerts)
3. Create triggers (timestamp + alert automation)
4. Create views
5. Create functions
6. Create procedures
7. Create indexes
8. Insert demo data (users → types → locations → devices → deployments → logs)
9. **Triggers automatically create alerts during log insertion**

---

## ✅ VERIFICATION CHECKLIST

- [x] Exact schema match: users, device_types, devices, locations, deployments, device_logs, alerts
- [x] device_types has `desc` field
- [x] devices.status = ENUM('error', 'warning', 'info')
- [x] deployments has composite PK (d_id, loc_id)
- [x] alerts table created with proper FKs
- [x] 2 Views created (meaningful, complex queries)
- [x] 2 Procedures created (cursor + loop, while loop + if/else)
- [x] 2 Functions created (health score, alert summary)
- [x] 5 Triggers created (timestamps + alert automation)
- [x] PL/SQL features distributed (cursor, loops, if/else, variables, temp tables)
- [x] Demo data updated for new schema
- [x] Triggers handle alert creation automatically
- [x] All old/redundant code removed
- [x] Installation script updated

---

## 📖 USAGE EXAMPLES

### Test Procedures:
```sql
-- Generate full device health report
CALL sp_generate_device_report(NULL);

-- Generate report for error devices only
CALL sp_generate_device_report('error');

-- Bulk resolve alerts for device 8
CALL sp_bulk_resolve_alerts(8, 1, 'Fixed sensor calibration', @resolved);
SELECT @resolved AS alerts_resolved;
```

### Test Functions:
```sql
-- Get health scores for all devices
SELECT d_id, d_name, fn_get_device_health_score(d_id) AS health_score
FROM devices
ORDER BY health_score ASC;

-- Get alert summaries
SELECT d_id, d_name, fn_get_alert_summary(d_id) AS alert_info
FROM devices;
```

### Test Views:
```sql
-- View all device deployments with alert counts
SELECT * FROM v_device_deployment_summary;

-- View critical unresolved issues
SELECT * FROM v_unresolved_critical_logs
WHERE days_unresolved > 2;
```

### Test Triggers:
```sql
-- Test alert auto-creation
INSERT INTO device_logs (d_id, log_type, message, severity_level)
VALUES (1, 'error', 'Critical temperature spike detected', 9);

-- Verify alert was created
SELECT * FROM alerts WHERE log_id = LAST_INSERT_ID();

-- Test alert auto-resolution
UPDATE device_logs 
SET resolved_by = 1, resolved_at = NOW(), resolution_notes = 'Replaced sensor'
WHERE log_id = LAST_INSERT_ID();

-- Verify alert was resolved
SELECT * FROM alerts WHERE log_id = LAST_INSERT_ID();
```

---

## 🎯 NEXT STEPS (UI Updates)

After this internal restructuring, the following UI updates will be needed:
1. Update PHP files to use new schema
2. Update status dropdowns (error/warning/info)
3. Add alerts management interface
4. Update dashboard to show health scores
5. Add views for procedure/function outputs
6. Update forms to match new field structure

---

**Database restructuring complete! All requirements met with no redundancy.**
