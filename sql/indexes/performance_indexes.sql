-- Performance Indexes for Common Queries
-- Features: Composite Indexes, Performance Optimization, Query Acceleration

-- Device logs time and type index for temporal queries
CREATE INDEX IF NOT EXISTS idx_device_logs_time_type ON device_logs(log_time, log_type);

-- Device logs by device and time (descending) for recent logs
CREATE INDEX IF NOT EXISTS idx_device_logs_device_time ON device_logs(d_id, log_time DESC);

-- Resolution tracking index
CREATE INDEX IF NOT EXISTS idx_device_logs_resolved ON device_logs(resolved_by, resolved_at);

-- Severity and type index for filtering
CREATE INDEX IF NOT EXISTS idx_device_logs_severity ON device_logs(severity_level, log_type);

-- Active deployments index
CREATE INDEX IF NOT EXISTS idx_deployments_active ON deployments(is_active, d_id, loc_id);

-- Device status and type index
CREATE INDEX IF NOT EXISTS idx_devices_status_type ON devices(status, t_id);

-- Maintenance tracking index
CREATE INDEX IF NOT EXISTS idx_devices_maintenance ON devices(last_maintenance, status);

-- Composite index for complex device log queries
CREATE INDEX IF NOT EXISTS idx_device_logs_composite ON device_logs(d_id, log_type, log_time, resolved_by);