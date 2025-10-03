-- Demo Data for Device Types Table
-- Features: INSERT IGNORE, Multiple Value Inserts

INSERT IGNORE INTO device_types (t_name, description, icon) VALUES 
('Temperature Sensor', 'Monitors ambient temperature and thermal conditions', 'fas fa-thermometer-half'),
('Humidity Sensor', 'Tracks humidity levels and moisture detection', 'fas fa-tint'),
('Motion Detector', 'Detects movement, activity, and intrusion', 'fas fa-running'),
('Smart Camera', 'Video surveillance, monitoring, and analytics', 'fas fa-video'),
('Door Controller', 'Access control, security, and entry management', 'fas fa-door-open'),
('Air Quality Monitor', 'Measures pollutants, CO2, and air quality index', 'fas fa-wind'),
('Smart Thermostat', 'Climate control and energy management', 'fas fa-temperature-high'),
('Pressure Sensor', 'Monitors atmospheric and system pressure', 'fas fa-gauge-high'),
('Light Sensor', 'Ambient light detection and control', 'fas fa-lightbulb'),
('Vibration Monitor', 'Equipment health and structural monitoring', 'fas fa-wave-square');