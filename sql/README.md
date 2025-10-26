# SQL Files Organization# SQL Files Organization



This directory contains organized SQL definitions for the IoT Device Manager database implementing exact schema specification with comprehensive PL/SQL features.This directory contains organized SQL definitions for the IoT Device Manager database. All static SQL code has been separated from the PHP application logic for better maintainability and modularity.



## Database Schema (Exact Specification)## Directory Structure



### Tables```

sql/

1. **users** (user_id, f_name, l_name, email, password, created_at, updated_at)├── create_database.sql          # Database creation script

2. **device_types** (t_id, t_name, desc)├── install_complete.sql         # Master installation script

3. **devices** (d_id, d_name, t_id, user_id, serial_number, status ENUM('error', 'warning', 'info'), purchase_date, created_at, updated_at)├── tables/                      # Table definitions

4. **locations** (loc_id, loc_name, address, latitude, longitude, created_at, updated_at)│   ├── users.sql

5. **deployments** (d_id, loc_id, deployed_at) - Composite PK: (d_id, loc_id)│   ├── device_types.sql

6. **device_logs** (log_id, d_id, log_time, log_type, message, severity_level, resolved_by, resolved_at, resolution_notes)│   ├── locations.sql

7. **alerts** (log_id (pk), alert_time, message, status ENUM('active', 'resolved'))│   ├── devices.sql

│   ├── deployments.sql

## PL/SQL Features Demonstrated│   └── device_logs.sql

├── views/                       # View definitions

### Views (2 Views)│   ├── v_device_summary.sql

1. **v_device_deployment_summary**: Comprehensive view showing devices with deployment locations and alert counts│   ├── v_log_analysis.sql

   - Features: Multiple JOINs, LEFT JOINs, Subqueries, GROUP BY, Aggregation│   └── v_resolver_performance.sql

   ├── procedures/                  # Stored procedure definitions

2. **v_unresolved_critical_logs**: Critical logs needing attention with device and user information│   ├── sp_device_health_check.sql

   - Features: Multiple JOINs, WHERE filtering, CASE statement, DATEDIFF│   ├── sp_cleanup_old_logs.sql

│   ├── sp_deploy_device.sql

### Stored Procedures (2 Procedures)│   └── sp_resolve_issue.sql

├── functions/                   # User-defined function definitions

1. **sp_generate_device_report(IN device_status)**│   ├── fn_calculate_uptime.sql

   - **PL/SQL Features Used**: CURSOR, LOOP, Variables, IF/ELSE, Temporary Tables, CASE statement│   ├── fn_device_risk_score.sql

   - Iterates through devices using cursor and calculates comprehensive health statistics│   └── fn_format_duration.sql

   - Demonstrates advanced cursor-based processing with multiple calculations├── indexes/                     # Index definitions

   │   └── performance_indexes.sql

2. **sp_bulk_resolve_alerts(IN device_id, IN resolver_id, IN notes, OUT alerts_resolved)**└── demo_data/                   # Sample data for each table

   - **PL/SQL Features Used**: WHILE LOOP, IF/ELSE, Variables, Error Handling (SIGNAL)    ├── users_data.sql

   - Batch processes multiple alerts with custom resolution notes based on severity    ├── device_types_data.sql

   - Demonstrates loop-based batch operations with conditional logic    ├── locations_data.sql

    ├── devices_data.sql

### Functions (2 Functions)    └── deployments_data.sql

    

1. **fn_get_device_health_score(device_id) RETURNS DECIMAL**Note: Device logs are generated programmatically by the PHP application using realistic algorithms, not from static SQL files.

   - **PL/SQL Features Used**: Variables, IF/ELSE, Multiple calculations, Subqueries```

   - Calculates device health score (0-100) based on logs, errors, and alerts

   - Uses complex scoring algorithm with multiple penalties## SQL Features Demonstrated

   

2. **fn_get_alert_summary(device_id) RETURNS VARCHAR**### Tables (`tables/`)

   - **PL/SQL Features Used**: IF/ELSE, Variables, String manipulation, CASE aggregation- **Primary Keys & Auto Increment**: All tables use auto-incrementing primary keys

   - Returns formatted string with alert statistics and status- **Foreign Key Constraints**: Proper referential integrity with CASCADE/RESTRICT options

   - Demonstrates conditional string building and categorization- **UNIQUE Constraints**: Email uniqueness, serial number uniqueness

- **CHECK Constraints**: ENUM types for status validation

### Triggers (5 Triggers)- **Indexes**: Performance indexes on frequently queried columns

- **Timestamps**: Automatic created_at/updated_at tracking

1. **trg_users_updated_at**: Auto-update updated_at on users table- **Full-text Search**: FULLTEXT index on device log messages

2. **trg_locations_updated_at**: Auto-update updated_at on locations table

3. **trg_devices_updated_at**: Auto-update updated_at on devices table### Views (`views/`)

4. **trg_create_alert_from_log**: Auto-create alert from high severity error logs (severity > 5)- **Complex JOINs**: Multi-table relationships with LEFT/INNER JOINs

   - Uses IF condition to selectively create alerts- **Aggregation Functions**: COUNT, AVG, MAX with GROUP BY

5. **trg_update_alert_status**: Auto-update alert status when associated log is resolved- **Date Functions**: DATE_SUB, DATEDIFF for time-based analysis

   - Uses IF condition to detect resolution changes- **CASE Statements**: Conditional logic for data categorization

- **Window Functions Concept**: Advanced analytical queries

## PL/SQL Features Summary

### Stored Procedures (`procedures/`)

### Distributed Features:- **Input/Output Parameters**: IN, OUT, INOUT parameter types

- **CURSOR**: Used in sp_generate_device_report- **Control Flow**: IF/ELSE, loops, conditional logic

- **LOOP (read_loop)**: Used in sp_generate_device_report  - **Transaction Control**: BEGIN, COMMIT, ROLLBACK

- **WHILE LOOP**: Used in sp_bulk_resolve_alerts- **Error Handling**: DECLARE EXIT HANDLER for robust error management

- **IF/ELSE**: Used in all procedures, functions, and triggers- **Variable Declarations**: Local variables and calculations

- **Variables (DECLARE)**: Used extensively in all procedures and functions- **Business Logic**: Complex multi-step operations

- **Temporary Tables**: Used in sp_generate_device_report

- **Error Handling (SIGNAL)**: Used in sp_bulk_resolve_alerts### Functions (`functions/`)

- **Subqueries**: Used in views and functions- **User-Defined Functions**: Custom reusable calculations

- **CASE statements**: Used in views and procedures- **Mathematical Operations**: Complex scoring algorithms

- **Aggregation**: Used in views and functions- **String Manipulation**: CONCAT, formatting functions

- **Date Calculations**: DATEDIFF, date arithmetic

## Installation- **DETERMINISTIC Functions**: Performance-optimized functions



### Complete Installation### Indexes (`indexes/`)

Run the master installation script to create everything:- **Composite Indexes**: Multi-column indexes for complex queries

```sql- **Performance Optimization**: Query acceleration strategies

SOURCE sql/install_complete.sql;- **Covering Indexes**: Indexes that cover entire queries

```

### Demo Data (`demo_data/`)

This will:- **Realistic Data Sets**: Sample data for core tables (users, device types, locations, devices, deployments)

1. Create database- **Foreign Key Relationships**: Properly linked reference data

2. Create all tables in dependency order- **Geographic Data**: Latitude/longitude coordinates

3. Create all triggers (for auto-updates and alert management)- **Programmatic Logs**: Device logs are generated dynamically using realistic algorithms rather than static SQL

4. Create views, functions, and procedures

5. Create performance indexesNote: The `device_logs` table is populated programmatically by the PHP application's `generateRealisticLogs()` method, which creates time-series data with realistic patterns, resolution tracking, and device-specific behaviors.

6. Insert demo data (triggers will auto-create alerts for high severity errors)

## Database Configuration System

## Testing PL/SQL Features

The database configuration is now completely dynamic and user-configurable through a JSON-based configuration system.

### Test the Procedures

```sql### Configuration Files

-- Generate device report for all devices

CALL sp_generate_device_report(NULL);- **`config/db_config.json`**: Main configuration file (JSON format)

- **`config/ConfigManager.php`**: Configuration management class

-- Generate report for error status devices only- **`config/database.php`**: Database class (updated to use ConfigManager)

CALL sp_generate_device_report('error');- **`db_config.php`**: Web interface for configuration management



-- Bulk resolve alerts for device 8### Default Configuration

CALL sp_bulk_resolve_alerts(8, 1, 'Replaced faulty component', @count);

SELECT @count;```json

```{

    "host": "localhost",

### Test the Functions    "db_name": "iot_device_manager", 

```sql    "username": "root",

-- Get health score for device 8    "password": "",

SELECT fn_get_device_health_score(8) as health_score;    "charset": "utf8",

    "port": 3306,

-- Get alert summary for device 14    "options": {

SELECT fn_get_alert_summary(14) as alert_summary;        "PDO::ATTR_ERRMODE": "PDO::ERRMODE_EXCEPTION",

        "PDO::ATTR_DEFAULT_FETCH_MODE": "PDO::FETCH_ASSOC",

-- Use in query        "PDO::ATTR_EMULATE_PREPARES": false

SELECT d_id, d_name, fn_get_device_health_score(d_id) as health    }

FROM devices}

ORDER BY health DESC;```

```

### Changing Configuration

### Test the Views

```sql#### Through Web Interface

-- View deployment summary1. Go to `db_config.php` 

SELECT * FROM v_device_deployment_summary;2. Update connection settings

3. Test connection before saving

-- View critical unresolved logs4. Save configuration

SELECT * FROM v_unresolved_critical_logs;

```#### Programmatically

```php

### Test the Triggers$database = new Database();

```sql$database->updateConfig([

-- Test timestamp trigger    'host' => 'your_host',

UPDATE users SET f_name = 'John' WHERE user_id = 1;    'db_name' => 'your_database',

SELECT updated_at FROM users WHERE user_id = 1;    'username' => 'your_username',

    'password' => 'your_password',

-- Test alert creation trigger (insert high severity error)    'port' => 3306

INSERT INTO device_logs (d_id, log_type, message, severity_level)]);

VALUES (1, 'error', 'Test critical error', 9);```



-- Check if alert was created#### Direct File Edit

SELECT * FROM alerts WHERE log_id = LAST_INSERT_ID();Edit `config/db_config.json` with your preferred settings.



-- Test alert resolution trigger### Features

UPDATE device_logs SET resolved_by = 1, resolved_at = NOW() 

WHERE log_id = LAST_INSERT_ID();- **✅ Dynamic Loading**: Configuration loaded on each request

- **✅ Validation**: Built-in validation for all parameters

-- Check if alert was marked resolved- **✅ Connection Testing**: Test connections before saving

SELECT * FROM alerts WHERE log_id = LAST_INSERT_ID();- **✅ Web Interface**: User-friendly configuration management

```- **✅ Secure**: Sensitive data stored separately from code

- **✅ Environment Friendly**: Easy to customize per environment

## Automatic Behaviors via Triggers

## Database Name Configuration

### Timestamp Management

- `created_at` and `updated_at` automatically maintained for users, locations, and devicesThe SQL files use a placeholder `{DB_NAME}` which is automatically replaced with the actual database name configured in the `Database` class. This makes the system flexible for different environments and configurations.

- No manual timestamp management needed

### Changing Database Name

### Alert Management

1. **Auto-creation**: Alerts automatically created when error logs with severity > 5 are inserted1. Edit `config/database.php`:

2. **Auto-resolution**: Alerts automatically marked as 'resolved' when associated log is resolved```php

3. **Manual insertion**: Demo data manually inserts logs; triggers handle alert creationclass Database {

    private $db_name = "your_custom_database_name";
    // ... rest of configuration
}
```

2. All SQL files will automatically use the new database name when executed
3. No manual updates to SQL files required

### How It Works

- SQL files contain `{DB_NAME}` placeholder
- The `executeSQLFile()` method replaces placeholders with actual values
- The `getSQLDefinition()` method also handles replacement for display purposes

## Installation

### Complete Installation
Run the master installation script to create everything:
```sql
SOURCE sql/install_complete.sql;
```

### Individual Component Installation
You can also install components individually:

1. **Database & Tables**:
```sql
SOURCE sql/create_database.sql;
USE iot_device_manager;
SOURCE sql/tables/users.sql;
SOURCE sql/tables/device_types.sql;
-- ... continue with other tables
```

2. **Views**:
```sql
SOURCE sql/views/v_device_summary.sql;
SOURCE sql/views/v_log_analysis.sql;
SOURCE sql/views/v_resolver_performance.sql;
```

3. **Functions & Procedures**:
```sql
SOURCE sql/functions/fn_calculate_uptime.sql;
SOURCE sql/procedures/sp_device_health_check.sql;
-- ... continue with others
```

4. **Demo Data**:
```sql
SOURCE sql/demo_data/users_data.sql;
SOURCE sql/demo_data/device_types_data.sql;
-- ... continue with other data files
-- Note: Device logs are generated programmatically, not from SQL files
```

## Usage in PHP Application

The PHP application now dynamically loads these SQL files:

```php
// Database class automatically loads SQL from files
$database = new Database();
$database->createDatabase();
$database->createTables();        // Loads from sql/tables/
$database->insertSampleData();    // Loads from sql/demo_data/

// Get SQL definitions for frontend display
$tableSQL = $database->getSQLDefinition('table', 'devices');
$viewSQL = $database->getSQLDefinition('view', 'v_device_summary');
$procSQL = $database->getSQLDefinition('procedure', 'sp_device_health_check');
```

## Benefits of This Organization

1. **Modularity**: Each SQL object is in its own file
2. **Maintainability**: Easy to find and modify specific definitions
3. **Version Control**: Clear change tracking for individual components
4. **Documentation**: Each file is self-documenting with comments
5. **Reusability**: SQL files can be used independently
6. **Frontend Integration**: Easy to display SQL definitions in web interface
7. **Testing**: Individual components can be tested separately
8. **Deployment**: Selective deployment of specific changes

## Frontend Integration

The web application can now display the actual SQL definitions from these files, allowing users to:
- View table structures and constraints
- Examine view logic and joins
- Study stored procedure implementations
- Understand function algorithms
- See index strategies
- Review sample data structure

This provides an educational platform for learning SQL concepts while using a real-world application.