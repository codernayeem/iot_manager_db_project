-- Demo Data for Devices Table
-- Features: INSERT IGNORE, Foreign Key References, ENUM Values, Date Handling

INSERT IGNORE INTO devices (t_id, d_name, serial_number, status, purchase_date, warranty_expiry, user_id) VALUES 
-- Headquarters devices
(1, 'TEMP-HQ-001', 'TH001HQ', 'active', '2024-01-15', '2026-01-15', 1),
(2, 'HUM-HQ-001', 'HM001HQ', 'active', '2024-02-01', '2026-02-01', 1),
(3, 'MOT-HQ-001', 'MT001HQ', 'active', '2024-01-20', '2026-01-20', 1),
(4, 'CAM-HQ-001', 'CM001HQ', 'active', '2024-03-01', '2027-03-01', 1),
(6, 'AIR-HQ-001', 'AQ001HQ', 'maintenance', '2024-02-15', '2026-02-15', 1),

-- Warehouse devices
(1, 'TEMP-WH-001', 'TH001WH', 'active', '2024-01-10', '2026-01-10', 1),
(8, 'PRES-WH-001', 'PR001WH', 'active', '2024-03-15', '2026-03-15', 1),
(10, 'VIB-WH-001', 'VB001WH', 'error', '2024-02-20', '2026-02-20', 1),
(3, 'MOT-WH-001', 'MT001WH', 'active', '2024-01-25', '2026-01-25', 1),

-- Branch Office devices
(7, 'THERM-BO-001', 'TM001BO', 'active', '2024-03-10', '2026-03-10', 1),
(9, 'LIGHT-BO-001', 'LT001BO', 'active', '2024-02-25', '2026-02-25', 1),
(5, 'DOOR-BO-001', 'DR001BO', 'active', '2024-01-30', '2026-01-30', 1),

-- Manufacturing Plant devices
(1, 'TEMP-MP-001', 'TH001MP', 'active', '2024-02-05', '2026-02-05', 1),
(10, 'VIB-MP-001', 'VB001MP', 'maintenance', '2024-03-20', '2026-03-20', 1),
(8, 'PRES-MP-001', 'PR001MP', 'error', '2024-01-12', '2026-01-12', 1),

-- Data Center devices
(2, 'HUM-DC-001', 'HM001DC', 'active', '2024-02-10', '2026-02-10', 1),
(1, 'TEMP-DC-001', 'TH001DC', 'active', '2024-01-05', '2026-01-05', 1),
(6, 'AIR-DC-001', 'AQ001DC', 'active', '2024-03-25', '2026-03-25', 1);