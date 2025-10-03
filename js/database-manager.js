/**
 * Database API Service
 * Handles all API calls for database operations
 */
class DatabaseAPIService {
    constructor() {
        this.baseUrl = 'api/database_operations.php';
    }

    /**
     * Get database status
     */
    async getStatus() {
        try {
            const response = await fetch(`${this.baseUrl}?action=status`);
            return await response.json();
        } catch (error) {
            console.error('Error getting status:', error);
            throw error;
        }
    }

    /**
     * Get SQL content for modals
     */
    async getSQLContent(type, name = '') {
        try {
            const url = `${this.baseUrl}?action=sql_content&type=${type}&name=${name}`;
            const response = await fetch(url);
            return await response.json();
        } catch (error) {
            console.error('Error getting SQL content:', error);
            throw error;
        }
    }

    /**
     * Create database
     */
    async createDatabase() {
        return this.postRequest('create_database');
    }

    /**
     * Create tables
     */
    async createTables() {
        return this.postRequest('create_tables');
    }

    /**
     * Insert sample data
     */
    async insertSampleData() {
        return this.postRequest('insert_sample_data');
    }

    /**
     * Reset database
     */
    async resetDatabase() {
        return this.postRequest('reset_database');
    }

    /**
     * Setup complete database
     */
    async setupAll() {
        return this.postRequest('setup_all');
    }

    /**
     * Generic POST request
     */
    async postRequest(action, data = {}) {
        try {
            const response = await fetch(`${this.baseUrl}?action=${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            return await response.json();
        } catch (error) {
            console.error(`Error with ${action}:`, error);
            throw error;
        }
    }
}

/**
 * Database Management UI Handler
 */
class DatabaseManager {
    constructor() {
        this.api = new DatabaseAPIService();
        this.statusInterval = null;
        this.init();
    }

    async init() {
        this.bindEvents();
        
        // Always load status from API (no PHP dependency)
        await this.refreshStatus();
        this.startStatusPolling();
    }

    bindEvents() {
        // Database operation buttons
        document.querySelectorAll('[data-action]').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const action = button.getAttribute('data-action');
                this.executeAction(action, button);
            });
        });
        
        // Manual refresh button
        document.addEventListener('click', (e) => {
            if (e.target.matches('[onclick*="refreshStatus"]') || e.target.closest('[onclick*="refreshStatus"]')) {
                e.preventDefault();
                this.manualRefresh();
            }
        });

        // SQL modal triggers (re-bind after status updates)
        this.bindSQLEvents();

        // Table modal triggers (re-bind after status updates)  
        this.bindTableEvents();

        // Modal close events
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.closeModals();
            }
        });

        document.querySelectorAll('.modal-close').forEach(button => {
            button.addEventListener('click', () => this.closeModals());
        });

        // Escape key to close modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeModals();
            }
        });
    }

    async manualRefresh() {
        this.showLoadingOverlay(true);
        try {
            await this.refreshStatus();
            this.showSuccess('Status refreshed successfully');
        } catch (error) {
            this.showError('Failed to refresh status: ' + error.message);
        } finally {
            this.showLoadingOverlay(false);
        }
    }

    bindSQLEvents() {
        // Re-bind SQL modal triggers (they change after status updates)
        document.querySelectorAll('[data-sql-type]').forEach(element => {
            // Remove existing listeners to prevent duplicates
            element.replaceWith(element.cloneNode(true));
        });
        
        document.querySelectorAll('[data-sql-type]').forEach(element => {
            element.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const type = element.getAttribute('data-sql-type');
                const name = element.getAttribute('data-sql-name') || '';
                this.showSQLModal(type, name);
            });
        });
    }

    async executeAction(action, button) {
        const originalText = button.innerHTML;
        const originalDisabled = button.disabled;
        
        // Show loading state
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        button.disabled = true;
        
        // Show loading overlay
        this.showLoadingOverlay(true);

        try {
            let result;
            
            // Add confirmation for reset
            if (action === 'reset_database') {
                if (!confirm('This will delete ALL data. Are you sure?')) {
                    return;
                }
            }

            switch (action) {
                case 'create_database':
                    result = await this.api.createDatabase();
                    break;
                case 'create_tables':
                    result = await this.api.createTables();
                    break;
                case 'insert_sample_data':
                    result = await this.api.insertSampleData();
                    break;
                case 'reset_database':
                    result = await this.api.resetDatabase();
                    break;
                case 'setup_all':
                    result = await this.api.setupAll();
                    // For setup_all, wait longer for all database objects to be created
                    if (result.success) {
                        setTimeout(async () => {
                            await this.refreshStatus();
                        }, 3000); // Longer delay for complex setup
                        return result;
                    }
                    break;
                default:
                    throw new Error('Unknown action: ' + action);
            }

            // Show result
            this.showResult(result);
            
            // ALWAYS refresh status after any operation
            setTimeout(async () => {
                await this.refreshStatus();
                
                // For setup_all, do an additional refresh after a longer delay
                if (action === 'setup_all' && result && result.success) {
                    setTimeout(async () => {
                        await this.refreshStatus();
                    }, 2000);
                }
            }, 500); // Small delay to ensure database changes are committed

        } catch (error) {
            this.showError('Operation failed: ' + error.message);
        } finally {
            // Hide loading overlay
            this.showLoadingOverlay(false);
            
            // Restore button state
            button.innerHTML = originalText;
            button.disabled = originalDisabled;
        }
    }

    async refreshStatus() {
        try {
            const response = await this.api.getStatus();
            if (response.success) {
                this.updateStatusDisplay(response.data);
            } else {
                // If API fails, show disconnected status
                const disconnectedStatus = {
                    connected: false,
                    database_exists: false,
                    tables: [],
                    views: [],
                    procedures: [],
                    functions: [],
                    table_row_counts: {}
                };
                this.updateStatusDisplay(disconnectedStatus);
                console.error('API status error:', response.message);
            }
        } catch (error) {
            // Network error - show disconnected status
            const disconnectedStatus = {
                connected: false,
                database_exists: false,
                tables: [],
                views: [],
                procedures: [],
                functions: [],
                table_row_counts: {}
            };
            this.updateStatusDisplay(disconnectedStatus);
            console.error('Network error refreshing status:', error);
        }
    }

    updateStatusDisplay(status) {
        // Update connection status
        const connectionIndicator = document.getElementById('connection-status');
        if (connectionIndicator) {
            const isConnected = status.connected || status.connection;
            connectionIndicator.className = `connection-status status-${isConnected ? 'good' : 'missing'}`;
            connectionIndicator.innerHTML = `<i class="fas fa-${isConnected ? 'check' : 'times'}"></i>`;
        }

        // Update database status
        const dbIndicator = document.getElementById('database-status');
        if (dbIndicator) {
            const dbExists = status.database_exists || status.database;
            dbIndicator.className = `database-status status-${dbExists ? 'good' : 'missing'} cursor-pointer`;
            dbIndicator.innerHTML = `<i class="fas fa-${dbExists ? 'check' : 'times'}"></i>`;
            // Maintain data attributes
            dbIndicator.setAttribute('data-sql-type', 'create_database');
            dbIndicator.setAttribute('title', 'Click to see SQL commands');
        }

        // Update table counts and status
        this.updateTableStatus(status.tables, status.table_row_counts);
        this.updateViewStatus(status.views);
        this.updateProcedureStatus(status.procedures, status.functions);

        // Update table overview
        this.updateTableOverview(status);

        // Update button states
        this.updateButtonStates(status);
        
        // Re-bind events after status update (important!)
        this.bindSQLEvents();
        this.bindTableEvents();
    }

    updateTableStatus(tables, rowCounts) {
        const requiredTables = ['users', 'device_types', 'devices', 'locations', 'deployments', 'device_logs'];
        
        requiredTables.forEach(table => {
            const indicator = document.querySelector(`[data-table-status="${table}"]`);
            if (indicator) {
                const exists = tables.includes(table);
                indicator.className = `${exists ? 'status-good' : 'status-missing'} cursor-pointer`;
                indicator.innerHTML = `<i class="fas fa-${exists ? 'check' : 'times'}"></i>`;
                
                // Maintain data attributes
                indicator.setAttribute('data-table-status', table);
                indicator.setAttribute('data-sql-type', 'table');
                indicator.setAttribute('data-sql-name', table);
                indicator.setAttribute('title', 'Click to see CREATE TABLE SQL');
            }

            // Update row counts display - find the span that shows row counts
            const rowCountElement = document.querySelector(`[data-table-rows="${table}"]`);
            if (rowCountElement && rowCounts[table] !== undefined) {
                rowCountElement.textContent = (rowCounts[table] || 0).toLocaleString() + ' rows';
            }
        });

        // Update table count display
        const tableCountElement = document.getElementById('tables-status');
        if (tableCountElement) {
            const tableCount = tables ? tables.length : 0;
            tableCountElement.textContent = `(${tableCount}/6)`;
            tableCountElement.className = `table-count status-${tableCount > 0 ? 'good' : 'missing'}`;
        }
    }

    updateViewStatus(views) {
        const requiredViews = ['v_device_summary', 'v_log_analysis', 'v_resolver_performance'];
        
        requiredViews.forEach(view => {
            const indicator = document.querySelector(`[data-view-status="${view}"]`);
            if (indicator) {
                const exists = views.includes(view);
                indicator.className = exists ? 'status-good' : 'status-missing';
                indicator.innerHTML = `<i class="fas fa-${exists ? 'check' : 'times'}"></i>`;
            }
        });

        // Update view count display
        const viewCountElement = document.querySelector('.view-count');
        if (viewCountElement) {
            viewCountElement.textContent = `(${views.length}/3)`;
        }
    }

    updateProcedureStatus(procedures, functions) {
        const procCountElement = document.getElementById('procedure-count');
        if (procCountElement) {
            const procCount = procedures ? procedures.length : 0;
            procCountElement.textContent = `${procCount}/4`;
            procCountElement.className = `procedure-count status-${procCount >= 4 ? 'good' : 'missing'}`;
        }

        const funcCountElement = document.getElementById('function-count');
        if (funcCountElement) {
            const funcCount = functions ? functions.length : 0;
            funcCountElement.textContent = `${funcCount}/3`;
            funcCountElement.className = `function-count status-${funcCount >= 3 ? 'good' : 'missing'}`;
        }
    }

    updateButtonStates(status) {
        // Create database button
        const createDbBtn = document.querySelector('[data-action="create_database"]');
        if (createDbBtn) {
            createDbBtn.disabled = status.database_exists;
            createDbBtn.classList.toggle('opacity-50', status.database_exists);
            createDbBtn.classList.toggle('cursor-not-allowed', status.database_exists);
        }

        // Create tables button
        const createTablesBtn = document.querySelector('[data-action="create_tables"]');
        if (createTablesBtn) {
            createTablesBtn.disabled = !status.database_exists;
            createTablesBtn.classList.toggle('opacity-50', !status.database_exists);
            createTablesBtn.classList.toggle('cursor-not-allowed', !status.database_exists);
        }

        // Insert sample data button
        const insertDataBtn = document.querySelector('[data-action="insert_sample_data"]');
        if (insertDataBtn) {
            const canInsert = status.tables.length >= 6;
            insertDataBtn.disabled = !canInsert;
            insertDataBtn.classList.toggle('opacity-50', !canInsert);
            insertDataBtn.classList.toggle('cursor-not-allowed', !canInsert);
        }
    }

    updateTableOverview(status) {
        const tableOverview = document.getElementById('tableOverview');
        const tableGrid = document.getElementById('tableGrid');
        
        if (!tableOverview || !tableGrid) return;

        // Show/hide table overview based on database existence and tables
        const showOverview = status.database_exists && status.tables.length > 0;
        tableOverview.style.display = showOverview ? 'block' : 'none';

        if (!showOverview) return;

        // Generate table cards
        const tableDescriptions = {
            'users': 'System users and administrators',
            'device_types': 'Categories of IoT devices',
            'devices': 'Registered IoT devices',
            'locations': 'Device deployment locations',
            'deployments': 'Device-location assignments',
            'device_logs': 'Device activity and error logs'
        };

        const tableCards = status.tables.map(table => {
            const rowCount = status.table_row_counts[table] || 0;
            const description = tableDescriptions[table] || 'Database table';
            
            return `
                <div class="bg-gray-50 rounded-lg p-4 hover:bg-gray-100 transition cursor-pointer"
                     data-table="${table}">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="font-semibold text-gray-800 text-sm">
                            <i class="fas fa-table mr-1 text-blue-600"></i>
                            ${table}
                        </h3>
                        <span class="text-xs text-gray-500 bg-white px-2 py-1 rounded">
                            ${rowCount.toLocaleString()} rows
                        </span>
                    </div>
                    
                    <div class="text-xs text-gray-600 space-y-1">
                        ${description}
                    </div>
                    
                    <div class="mt-3 flex justify-between items-center">
                        <span class="text-xs text-blue-600 hover:text-blue-800">
                            <i class="fas fa-info-circle mr-1"></i>View Details
                        </span>
                        <span class="text-xs text-green-600 hover:text-green-800"
                              data-sql-type="table"
                              data-sql-name="${table}">
                            <i class="fas fa-code mr-1"></i>View SQL
                        </span>
                    </div>
                </div>
            `;
        }).join('');

        tableGrid.innerHTML = tableCards;

        // Re-bind events for the new table cards
        this.bindTableEvents();
    }

    bindTableEvents() {
        // Re-bind table modal events
        document.querySelectorAll('[data-table]').forEach(element => {
            element.addEventListener('click', (e) => {
                e.preventDefault();
                const tableName = element.getAttribute('data-table');
                this.showTableModal(tableName);
            });
        });

        // Re-bind SQL modal events for View SQL buttons
        document.querySelectorAll('[data-sql-type="table"]').forEach(element => {
            element.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation(); // Prevent table modal from opening
                const name = element.getAttribute('data-sql-name') || '';
                this.showSQLModal('table', name);
            });
        });
    }

    async showSQLModal(type, name) {
        try {
            const response = await this.api.getSQLContent(type, name);
            if (response.success) {
                const modal = document.getElementById('sqlModal');
                const title = document.getElementById('sqlModalTitle');
                const content = document.getElementById('sqlModalContent');

                title.textContent = this.getSQLModalTitle(type, name);
                content.innerHTML = this.formatSQLContent(response.data.content, type, name);
                
                modal.classList.remove('hidden');
            } else {
                this.showError('Failed to load SQL content: ' + response.message);
            }
        } catch (error) {
            this.showError('Error loading SQL content: ' + error.message);
        }
    }

    getSQLModalTitle(type, name) {
        switch (type) {
            case 'create_database':
                return 'Create Database SQL';
            case 'table':
                return `CREATE TABLE: ${name}`;
            case 'view':
                return `CREATE VIEW: ${name}`;
            case 'procedure':
                return `PROCEDURE: ${name}`;
            case 'function':
                return `FUNCTION: ${name}`;
            case 'procedures':
                return 'Stored Procedures SQL';
            case 'functions':
                return 'User-Defined Functions SQL';
            default:
                return 'SQL Commands';
        }
    }

    formatSQLContent(content, type, name) {
        let description = '';
        
        switch (type) {
            case 'create_database':
                description = 'Creates the main database with UTF-8 character set for international support.';
                break;
            case 'table':
                description = 'Complete table definition with constraints, indexes, and foreign keys.';
                break;
            case 'view':
                description = 'Complex view with JOINs, aggregation, and advanced SQL features.';
                break;
            case 'procedure':
                description = 'Advanced procedure with error handling, conditional logic, and output parameters.';
                break;
            case 'function':
                description = 'User-defined function with mathematical calculations and conditional logic.';
                break;
            case 'procedures':
                description = 'All stored procedures for the IoT Device Manager system.';
                break;
            case 'functions':
                description = 'All user-defined functions for calculations and data processing.';
                break;
        }

        return `
            <div class="mb-4">
                <h4 class="font-bold mb-2">${this.getSQLModalTitle(type, name)}</h4>
                <pre class="bg-gray-800 text-green-400 p-4 rounded text-sm overflow-x-auto"><code>${this.escapeHtml(content)}</code></pre>
                <p class="mt-2 text-sm text-gray-600">${description}</p>
            </div>
        `;
    }

    showTableModal(tableName) {
        const modal = document.getElementById('tableModal');
        const title = document.getElementById('tableModalTitle');
        const content = document.getElementById('tableModalContent');

        title.textContent = 'Table: ' + tableName;
        content.innerHTML = this.getTableStructure(tableName);
        
        modal.classList.remove('hidden');
    }

    getTableStructure(tableName) {
        // This would ideally come from the API as well, but for now we'll keep the existing structure
        // You can extend the API to return table structure information
        return `
            <div class="text-center py-8">
                <i class="fas fa-table text-4xl text-gray-400 mb-4"></i>
                <p class="text-gray-600">Table structure information for <strong>${tableName}</strong></p>
                <p class="text-sm text-gray-500 mt-2">This feature can be enhanced to show detailed table structure from the database.</p>
                <button data-sql-type="table" data-sql-name="${tableName}" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    <i class="fas fa-code mr-2"></i>View CREATE TABLE SQL
                </button>
            </div>
        `;
    }

    closeModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.add('hidden');
        });
    }

    showResult(result) {
        if (result.success) {
            this.showSuccess(result.message, result.data?.logs);
        } else {
            this.showError(result.message);
        }
    }

    showSuccess(message, logs = []) {
        this.showNotification('success', message, logs);
    }

    showError(message) {
        this.showNotification('error', message);
    }

    showNotification(type, message, logs = []) {
        // Remove existing notifications
        document.querySelectorAll('.notification').forEach(n => n.remove());
        
        const className = type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
        const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
        
        let logsHtml = '';
        if (logs && logs.length > 0) {
            logsHtml = `
                <div class="mt-2 space-y-1">
                    ${logs.map(log => `<div class="text-xs font-mono bg-gray-50 p-2 rounded">${this.escapeHtml(log)}</div>`).join('')}
                </div>
            `;
        }
        
        const notification = document.createElement('div');
        notification.className = `notification border px-4 py-3 rounded mb-4 ${className}`;
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${icon} mr-2"></i>
                <span>${this.escapeHtml(message)}</span>
                <button class="ml-auto text-lg" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
            ${logsHtml}
        `;
        
        const container = document.querySelector('.container');
        const header = container.querySelector('h1').parentElement;
        header.insertAdjacentElement('afterend', notification);
        
        // Auto-remove after 10 seconds for success messages
        if (type === 'success') {
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 10000);
        }
    }

    startStatusPolling() {
        // Refresh status every 10 seconds for better responsiveness
        this.statusInterval = setInterval(() => {
            this.refreshStatus();
        }, 10000);
    }

    stopStatusPolling() {
        if (this.statusInterval) {
            clearInterval(this.statusInterval);
            this.statusInterval = null;
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showLoadingOverlay(show) {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            if (show) {
                overlay.classList.remove('hidden');
            } else {
                overlay.classList.add('hidden');
            }
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Make dbManager globally accessible for refresh button and other uses
    window.dbManager = new DatabaseManager();
});