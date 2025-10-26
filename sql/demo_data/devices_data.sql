-- Demo Data for Devices Table
-- Features: INSERT IGNORE, Foreign Key References, ENUM Values, Date Handling
-- Status values: 'error', 'warning', 'info'

INSERT IGNORE INTO devices (d_id, t_id, d_name, serial_number, status, purchase_date, user_id) VALUES 
-- Headquarters devices
(1, 1, 'TEMP-HQ-001', 'TH001HQ', 'info', '2024-01-15', 1),
(2, 2, 'HUM-HQ-001', 'HM001HQ', 'info', '2024-02-01', 1),
(3, 3, 'MOT-HQ-001', 'MT001HQ', 'warning', '2024-01-20', 1),
(4, 4, 'CAM-HQ-001', 'CM001HQ', 'info', '2024-03-01', 1),
(5, 6, 'AIR-HQ-001', 'AQ001HQ', 'warning', '2024-02-15', 1),

-- Warehouse devices
(6, 1, 'TEMP-WH-001', 'TH001WH', 'info', '2024-01-10', 2),
(7, 8, 'PRES-WH-001', 'PR001WH', 'info', '2024-03-15', 2),
(8, 10, 'VIB-WH-001', 'VB001WH', 'error', '2024-02-20', 2),
(9, 3, 'MOT-WH-001', 'MT001WH', 'info', '2024-01-25', 2),

-- Branch Office devices
(10, 7, 'THERM-BO-001', 'TM001BO', 'info', '2024-03-10', 3),
(11, 9, 'LIGHT-BO-001', 'LT001BO', 'info', '2024-02-25', 3),
(12, 5, 'DOOR-BO-001', 'DR001BO', 'warning', '2024-01-30', 3),

-- Manufacturing Plant devices
(13, 1, 'TEMP-MP-001', 'TH001MP', 'info', '2024-02-05', 4),
(14, 10, 'VIB-MP-001', 'VB001MP', 'error', '2024-03-20', 4),
(15, 8, 'PRES-MP-001', 'PR001MP', 'error', '2024-01-12', 4),

-- Data Center devices
(16, 2, 'HUM-DC-001', 'HM001DC', 'info', '2024-02-10', 5),
(17, 1, 'TEMP-DC-001', 'TH001DC', 'info', '2024-01-05', 5),
(18, 6, 'AIR-DC-001', 'AQ001DC', 'info', '2024-03-25', 5);
