-- Simple View: Device Location View
-- Shows devices with their current locations
-- Features: Simple SELECT with LEFT JOIN
CREATE OR REPLACE VIEW v_device_locations AS
SELECT 
    d.d_id,
    d.d_name,
    dt.t_name as device_type,
    l.loc_name,
    l.address,
    dep.deployed_at
FROM devices d
INNER JOIN device_types dt ON d.t_id = dt.t_id
LEFT JOIN deployments dep ON d.d_id = dep.d_id AND dep.is_active = 1
LEFT JOIN locations l ON dep.loc_id = l.loc_id;
