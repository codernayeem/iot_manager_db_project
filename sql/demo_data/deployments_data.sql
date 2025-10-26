-- Demo Data for Deployments Table
-- Features: Composite Primary Key (d_id, loc_id)

INSERT IGNORE INTO deployments (d_id, loc_id, deployed_at) VALUES 
-- Headquarters deployments (loc_id: 1)
(1, 1, '2024-01-16 09:00:00'),
(2, 1, '2024-02-02 10:30:00'),
(3, 1, '2024-01-21 14:15:00'),
(4, 1, '2024-03-02 08:45:00'),
(5, 1, '2024-02-16 11:20:00'),

-- Warehouse A deployments (loc_id: 2)
(6, 2, '2024-01-11 07:30:00'),
(7, 2, '2024-03-16 13:00:00'),
(8, 2, '2024-02-21 09:45:00'),
(9, 2, '2024-01-26 15:30:00'),

-- Branch Office deployments (loc_id: 3)
(10, 3, '2024-03-11 10:00:00'),
(11, 3, '2024-02-26 14:45:00'),
(12, 3, '2024-01-31 12:15:00'),

-- Manufacturing Plant deployments (loc_id: 4)
(13, 4, '2024-02-06 08:00:00'),
(14, 4, '2024-03-21 11:30:00'),
(15, 4, '2024-01-13 09:15:00'),

-- Data Center deployments (loc_id: 5)
(16, 5, '2024-02-11 07:00:00'),
(17, 5, '2024-01-06 08:30:00'),
(18, 5, '2024-03-26 10:45:00');
