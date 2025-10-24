-- Simple View: Active Devices Overview
-- Shows only active devices with basic information
-- Features: Simple SELECT with JOINs
CREATE OR REPLACE VIEW v_active_devices AS
SELECT 
    d.d_id,
    d.d_name,
    dt.t_name as device_type,
    d.status,
    d.serial_number,
    CONCAT(u.f_name, ' ', u.l_name) as owner_name
FROM devices d
INNER JOIN device_types dt ON d.t_id = dt.t_id
INNER JOIN users u ON d.user_id = u.user_id
WHERE d.status = 'active';
