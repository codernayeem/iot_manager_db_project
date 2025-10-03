-- Demo Data for Deployments Table
-- Features: INSERT IGNORE, Junction Table Data, Boolean Values

INSERT IGNORE INTO deployments (d_id, loc_id, deployed_by, deployment_notes, is_active) VALUES 
-- Headquarters deployments (loc_id: 1)
(1, 1, 1, 'Initial deployment - main lobby temperature monitoring', 1),
(2, 1, 1, 'Initial deployment - server room humidity control', 1),
(3, 1, 1, 'Initial deployment - main entrance security', 1),
(4, 1, 1, 'Initial deployment - reception area surveillance', 1),
(5, 1, 1, 'Initial deployment - office air quality monitoring', 1),

-- Warehouse A deployments (loc_id: 2)
(6, 2, 1, 'Initial deployment - warehouse climate control', 1),
(7, 2, 1, 'Initial deployment - hydraulic system monitoring', 1),
(8, 2, 1, 'Initial deployment - conveyor system health monitoring', 1),
(9, 2, 1, 'Initial deployment - loading dock security', 1),

-- Branch Office deployments (loc_id: 3)
(10, 3, 1, 'Initial deployment - conference room climate control', 1),
(11, 3, 1, 'Initial deployment - automatic lighting system', 1),
(12, 3, 1, 'Initial deployment - office access control', 1),

-- Manufacturing Plant deployments (loc_id: 4)
(13, 4, 1, 'Initial deployment - production line temperature monitoring', 1),
(14, 4, 1, 'Initial deployment - machine health monitoring', 1),
(15, 4, 1, 'Initial deployment - steam system pressure monitoring', 1),

-- Data Center deployments (loc_id: 5)
(16, 5, 1, 'Initial deployment - critical humidity monitoring', 1),
(17, 5, 1, 'Initial deployment - cooling system temperature monitoring', 1),
(18, 5, 1, 'Initial deployment - air quality monitoring system', 1);