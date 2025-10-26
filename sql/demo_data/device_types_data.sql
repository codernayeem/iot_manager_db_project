-- Demo Data for Device Types Table
-- Features: INSERT IGNORE, Multiple Value Inserts

INSERT IGNORE INTO device_types (t_name, `desc`) VALUES 
('Temperature Sensor', 'Monitors ambient temperature in real-time'),
('Humidity Sensor', 'Tracks humidity levels and moisture content'),
('Motion Detector', 'Detects movement and presence in designated areas'),
('Smart Camera', 'Video surveillance with AI-powered analytics'),
('Door Controller', 'Electronic access control and monitoring system'),
('Air Quality Monitor', 'Measures air quality index and pollutant levels'),
('Smart Thermostat', 'Intelligent climate control and energy management'),
('Pressure Sensor', 'Monitors pressure levels in industrial systems'),
('Light Sensor', 'Detects ambient light levels for automation'),
('Vibration Monitor', 'Tracks vibration patterns for equipment health');
