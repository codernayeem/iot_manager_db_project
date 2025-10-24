-- Master Installation Script
-- This script sets up the complete database with all objects and demo data
-- Execute this file to create the entire IoT Device Manager database
-- Note: {DB_NAME} will be replaced with actual database name from configuration

-- 1. Create Database
SOURCE sql/create_database.sql;
USE {DB_NAME};

-- 2. Create Tables (in dependency order)
SOURCE sql/tables/users.sql;
SOURCE sql/tables/device_types.sql;
SOURCE sql/tables/locations.sql;
SOURCE sql/tables/devices.sql;
SOURCE sql/tables/deployments.sql;
SOURCE sql/tables/device_logs.sql;

-- 3. Create Views (Simple - Easy to understand)
SOURCE sql/views/v_active_devices.sql;
SOURCE sql/views/v_device_locations.sql;

-- Optional: Complex Views (Uncomment if needed)
-- SOURCE sql/views/v_device_summary.sql;
-- SOURCE sql/views/v_log_analysis.sql;
-- SOURCE sql/views/v_resolver_performance.sql;

-- 4. Create Functions (Simple - Easy to understand)
SOURCE sql/functions/fn_count_user_devices.sql;
SOURCE sql/functions/fn_device_status_text.sql;

-- Optional: Complex Functions (Uncomment if needed)
-- SOURCE sql/functions/fn_calculate_uptime.sql;
-- SOURCE sql/functions/fn_device_risk_score.sql;
-- SOURCE sql/functions/fn_format_duration.sql;

-- 5. Create Stored Procedures (Simple - Easy to understand)
SOURCE sql/procedures/sp_count_devices_by_status.sql;
SOURCE sql/procedures/sp_get_devices_by_type.sql;

-- Optional: Complex Procedures (Uncomment if needed)
-- SOURCE sql/procedures/sp_deploy_device.sql;
-- SOURCE sql/procedures/sp_device_health_check.sql;
-- SOURCE sql/procedures/sp_cleanup_old_logs.sql;
-- SOURCE sql/procedures/sp_resolve_issue.sql;

-- 6. Create Triggers (Simple - Automatic actions)
SOURCE sql/triggers/trg_device_updated_at.sql;
SOURCE sql/triggers/trg_log_new_device.sql;

-- 7. Create Performance Indexes
SOURCE sql/indexes/performance_indexes.sql;

-- 8. Insert Demo Data (in dependency order)
SOURCE sql/demo_data/users_data.sql;
SOURCE sql/demo_data/device_types_data.sql;
SOURCE sql/demo_data/locations_data.sql;
SOURCE sql/demo_data/devices_data.sql;
SOURCE sql/demo_data/deployments_data.sql;
-- Note: Device logs are generated programmatically by the PHP application

-- Installation Complete
SELECT 'IoT Device Manager Database Installation Complete!' as Status;
SELECT 'Database Features: 2 Views, 2 Procedures, 2 Functions, 2 Triggers, Indexes' as Summary;