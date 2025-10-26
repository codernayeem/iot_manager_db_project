-- Demo Data for Device Logs Table
-- Features: Manual insert of logs (triggers will handle alert creation automatically)
-- Note: Alerts will be auto-created for error logs with severity_level > 5

-- High severity error logs (will trigger alert creation)
INSERT INTO device_logs (d_id, log_time, log_type, message, severity_level) VALUES 
(8, '2024-10-20 14:30:00', 'error', 'Critical vibration detected - Bearing failure imminent', 9),
(14, '2024-10-21 09:15:00', 'error', 'Excessive vibration levels - Machine malfunction detected', 8),
(15, '2024-10-22 11:45:00', 'error', 'Pressure threshold exceeded - System shutdown required', 10),
(8, '2024-10-23 16:20:00', 'error', 'Abnormal vibration pattern detected', 7),
(15, '2024-10-24 08:00:00', 'error', 'Hydraulic pressure spike detected', 8);

-- Medium severity error logs (will trigger alert creation)
INSERT INTO device_logs (d_id, log_time, log_type, message, severity_level) VALUES 
(3, '2024-10-19 10:00:00', 'error', 'Motion sensor calibration error', 6),
(12, '2024-10-20 15:30:00', 'error', 'Door controller communication timeout', 6),
(14, '2024-10-25 13:10:00', 'error', 'Vibration sensor reading anomaly', 7);

-- Low severity error logs (will NOT trigger alert creation)
INSERT INTO device_logs (d_id, log_time, log_type, message, severity_level) VALUES 
(3, '2024-10-15 08:30:00', 'error', 'Sensor calibration drift detected', 4),
(5, '2024-10-16 14:00:00', 'error', 'Air quality reading fluctuation', 3),
(12, '2024-10-18 11:20:00', 'error', 'Door sensor misalignment', 5);

-- Warning logs
INSERT INTO device_logs (d_id, log_time, log_type, message, severity_level) VALUES 
(3, '2024-10-22 09:00:00', 'warning', 'Motion detector battery low', 3),
(5, '2024-10-23 10:30:00', 'warning', 'Air quality sensor cleaning required', 2),
(12, '2024-10-24 14:15:00', 'warning', 'Door controller firmware update available', 2),
(1, '2024-10-25 07:45:00', 'warning', 'Temperature fluctuation detected', 3),
(6, '2024-10-25 16:00:00', 'warning', 'Temperature sensor recalibration recommended', 2);

-- Info logs
INSERT INTO device_logs (d_id, log_time, log_type, message, severity_level) VALUES 
(1, '2024-10-20 08:00:00', 'info', 'Temperature sensor operating normally', 1),
(2, '2024-10-20 08:05:00', 'info', 'Humidity levels within normal range', 1),
(4, '2024-10-20 08:10:00', 'info', 'Camera system online and recording', 1),
(6, '2024-10-21 07:30:00', 'info', 'Daily temperature check completed', 1),
(7, '2024-10-21 09:00:00', 'info', 'Pressure readings stable', 1),
(9, '2024-10-22 12:00:00', 'info', 'Motion detector self-test passed', 1),
(10, '2024-10-23 08:30:00', 'info', 'Thermostat schedule updated', 1),
(11, '2024-10-24 10:00:00', 'info', 'Light sensor calibrated successfully', 1),
(13, '2024-10-25 07:00:00', 'info', 'Temperature monitoring active', 1),
(16, '2024-10-25 08:00:00', 'info', 'Humidity control system operational', 1),
(17, '2024-10-25 09:00:00', 'info', 'Data center temperature optimal', 1),
(18, '2024-10-25 10:00:00', 'info', 'Air quality monitoring active', 1);

-- Resolved error log (with resolution)
INSERT INTO device_logs (d_id, log_time, log_type, message, severity_level, resolved_by, resolved_at, resolution_notes) VALUES 
(8, '2024-10-15 10:00:00', 'error', 'Vibration sensor connectivity issue', 8, 2, '2024-10-16 14:30:00', 'Replaced network cable and rebooted sensor. Operating normally.');
