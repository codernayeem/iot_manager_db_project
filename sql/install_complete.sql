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
SOURCE sql/tables/alerts.sql;

-- 3. Create Triggers (auto-update timestamps and alert management)
SOURCE sql/triggers/trg_users_updated_at.sql;
SOURCE sql/triggers/trg_locations_updated_at.sql;
SOURCE sql/triggers/trg_devices_updated_at.sql;
SOURCE sql/triggers/trg_create_alert_from_log.sql;
SOURCE sql/triggers/trg_update_alert_status.sql;

-- 4. Create Views
SOURCE sql/views/v_active_devices.sql;
SOURCE sql/views/v_device_locations.sql;

-- 5. Create Functions
SOURCE sql/functions/fn_count_user_devices.sql;
SOURCE sql/functions/fn_device_status_text.sql;

-- 6. Create Stored Procedures
SOURCE sql/procedures/sp_count_devices_by_status.sql;
SOURCE sql/procedures/sp_get_devices_by_type.sql;

-- 7. Create Performance Indexes
SOURCE sql/indexes/performance_indexes.sql;

-- 8. Insert Demo Data (in dependency order)
-- Note: Triggers will automatically handle created_at/updated_at timestamps
SOURCE sql/demo_data/users_data.sql;
SOURCE sql/demo_data/device_types_data.sql;
SOURCE sql/demo_data/locations_data.sql;
SOURCE sql/demo_data/devices_data.sql;
SOURCE sql/demo_data/deployments_data.sql;

-- 9. Insert Device Logs (triggers will auto-create alerts for high severity errors)
SOURCE sql/demo_data/device_logs_data.sql;

-- Installation Complete
SELECT 'IoT Device Manager Database Installation Complete!' as Status;
SELECT 'Database Features: 2 Views, 2 Procedures, 2 Functions, 5 Triggers, Indexes' as Summary;
SELECT 'Schema: users, device_types, devices, locations, deployments, device_logs, alerts' as Tables;
