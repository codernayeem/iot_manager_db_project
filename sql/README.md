# SQL Files Organization

This directory contains organized SQL definitions for the IoT Device Manager database. All static SQL code has been separated from the PHP application logic for better maintainability and modularity.

## Directory Structure

```
sql/
├── create_database.sql          # Database creation script
├── install_complete.sql         # Master installation script
├── tables/                      # Table definitions
│   ├── users.sql
│   ├── device_types.sql
│   ├── locations.sql
│   ├── devices.sql
│   ├── deployments.sql
│   └── device_logs.sql
├── views/                       # View definitions
│   ├── v_device_summary.sql
│   ├── v_log_analysis.sql
│   └── v_resolver_performance.sql
├── procedures/                  # Stored procedure definitions
│   ├── sp_device_health_check.sql
│   ├── sp_cleanup_old_logs.sql
│   ├── sp_deploy_device.sql
│   └── sp_resolve_issue.sql
├── functions/                   # User-defined function definitions
│   ├── fn_calculate_uptime.sql
│   ├── fn_device_risk_score.sql
│   └── fn_format_duration.sql
├── indexes/                     # Index definitions
│   └── performance_indexes.sql
└── demo_data/                   # Sample data for each table
    ├── users_data.sql
    ├── device_types_data.sql
    ├── locations_data.sql
    ├── devices_data.sql
    └── deployments_data.sql
    
Note: Device logs are generated programmatically by the PHP application using realistic algorithms, not from static SQL files.
```

## SQL Features Demonstrated

### Tables (`tables/`)
- **Primary Keys & Auto Increment**: All tables use auto-incrementing primary keys
- **Foreign Key Constraints**: Proper referential integrity with CASCADE/RESTRICT options
- **UNIQUE Constraints**: Email uniqueness, serial number uniqueness
- **CHECK Constraints**: ENUM types for status validation
- **Indexes**: Performance indexes on frequently queried columns
- **Timestamps**: Automatic created_at/updated_at tracking
- **Full-text Search**: FULLTEXT index on device log messages

### Views (`views/`)
- **Complex JOINs**: Multi-table relationships with LEFT/INNER JOINs
- **Aggregation Functions**: COUNT, AVG, MAX with GROUP BY
- **Date Functions**: DATE_SUB, DATEDIFF for time-based analysis
- **CASE Statements**: Conditional logic for data categorization
- **Window Functions Concept**: Advanced analytical queries

### Stored Procedures (`procedures/`)
- **Input/Output Parameters**: IN, OUT, INOUT parameter types
- **Control Flow**: IF/ELSE, loops, conditional logic
- **Transaction Control**: BEGIN, COMMIT, ROLLBACK
- **Error Handling**: DECLARE EXIT HANDLER for robust error management
- **Variable Declarations**: Local variables and calculations
- **Business Logic**: Complex multi-step operations

### Functions (`functions/`)
- **User-Defined Functions**: Custom reusable calculations
- **Mathematical Operations**: Complex scoring algorithms
- **String Manipulation**: CONCAT, formatting functions
- **Date Calculations**: DATEDIFF, date arithmetic
- **DETERMINISTIC Functions**: Performance-optimized functions

### Indexes (`indexes/`)
- **Composite Indexes**: Multi-column indexes for complex queries
- **Performance Optimization**: Query acceleration strategies
- **Covering Indexes**: Indexes that cover entire queries

### Demo Data (`demo_data/`)
- **Realistic Data Sets**: Sample data for core tables (users, device types, locations, devices, deployments)
- **Foreign Key Relationships**: Properly linked reference data
- **Geographic Data**: Latitude/longitude coordinates
- **Programmatic Logs**: Device logs are generated dynamically using realistic algorithms rather than static SQL

Note: The `device_logs` table is populated programmatically by the PHP application's `generateRealisticLogs()` method, which creates time-series data with realistic patterns, resolution tracking, and device-specific behaviors.

## Database Configuration System

The database configuration is now completely dynamic and user-configurable through a JSON-based configuration system.

### Configuration Files

- **`config/db_config.json`**: Main configuration file (JSON format)
- **`config/ConfigManager.php`**: Configuration management class
- **`config/database.php`**: Database class (updated to use ConfigManager)
- **`db_config.php`**: Web interface for configuration management

### Default Configuration

```json
{
    "host": "localhost",
    "db_name": "iot_device_manager", 
    "username": "root",
    "password": "",
    "charset": "utf8",
    "port": 3306,
    "options": {
        "PDO::ATTR_ERRMODE": "PDO::ERRMODE_EXCEPTION",
        "PDO::ATTR_DEFAULT_FETCH_MODE": "PDO::FETCH_ASSOC",
        "PDO::ATTR_EMULATE_PREPARES": false
    }
}
```

### Changing Configuration

#### Through Web Interface
1. Go to `db_config.php` 
2. Update connection settings
3. Test connection before saving
4. Save configuration

#### Programmatically
```php
$database = new Database();
$database->updateConfig([
    'host' => 'your_host',
    'db_name' => 'your_database',
    'username' => 'your_username',
    'password' => 'your_password',
    'port' => 3306
]);
```

#### Direct File Edit
Edit `config/db_config.json` with your preferred settings.

### Features

- **✅ Dynamic Loading**: Configuration loaded on each request
- **✅ Validation**: Built-in validation for all parameters
- **✅ Connection Testing**: Test connections before saving
- **✅ Web Interface**: User-friendly configuration management
- **✅ Secure**: Sensitive data stored separately from code
- **✅ Environment Friendly**: Easy to customize per environment

## Database Name Configuration

The SQL files use a placeholder `{DB_NAME}` which is automatically replaced with the actual database name configured in the `Database` class. This makes the system flexible for different environments and configurations.

### Changing Database Name

1. Edit `config/database.php`:
```php
class Database {
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