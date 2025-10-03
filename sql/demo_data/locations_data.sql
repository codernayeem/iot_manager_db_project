-- Demo Data for Locations Table
-- Features: INSERT IGNORE, Geographic Data (Latitude/Longitude)

INSERT IGNORE INTO locations (loc_name, address, latitude, longitude) VALUES 
('Headquarters', '123 Main St, Downtown Business District', 40.7128, -74.0060),
('Warehouse A', '456 Industrial Blvd, Port District', 40.6892, -74.0445),
('Branch Office', '789 Business Ave, Midtown Plaza', 40.7589, -73.9851),
('Manufacturing Plant', '321 Factory Road, Industrial Zone', 40.6782, -74.1745),
('Data Center', '654 Tech Drive, Innovation Campus', 40.7831, -73.9712),
('Retail Store', '987 Shopping Center, Commercial District', 40.7282, -73.9942);