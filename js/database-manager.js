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
            const url = `${this.baseUrl}?action=sql_content&type=${type}${name ? `&name=${name}` : ''}`;
            const response = await fetch(url);
            return await response.json();
        } catch (error) {
            console.error('Error getting SQL content:', error);
            throw error;
        }
    }

    /**
     * Get table structure
     */
    async getTableStructure(tableName) {
        try {
            const response = await fetch(`${this.baseUrl}?action=table_structure&table=${tableName}`);
            return await response.json();
        } catch (error) {
            console.error('Error getting table structure:', error);
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
     * Setup all (complete installation)
     */
    async setupAll() {
        return this.postRequest('setup_all');
    }

    /**
     * Generic POST request
     */
    async postRequest(action, data = {}) {
        try {
            const response = await fetch(this.baseUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action, ...data })
            });
            return await response.json();
        } catch (error) {
            console.error(`Error with ${action}:`, error);
            throw error;
        }
    }
}

/**
 * Database Manager
 * Handles UI interactions and updates
 */
class DatabaseManager {
    constructor() {
        this.api = new DatabaseAPIService();
        this.statusInterval = null;
        this.init();
    }

    init() {
        this.bindEvents();
        this.startStatusPolling();
    }

    bindEvents() {
        // Database operation buttons
        document.querySelectorAll('[data-action]').forEach(button => {
            button.addEventListener('click', (e) => {
                const action = button.getAttribute('data-action');
                this.executeAction(action, button);
            });
        });

        // SQL modal buttons
        document.querySelectorAll('[data-sql-type]').forEach(element => {
            element.addEventListener('click', (e) => {
                e.preventDefault();
                const type = element.getAttribute('data-sql-type');
                const name = element.getAttribute('data-sql-name') || '';
                this.showSQLModal(type, name);
            });
        });

        // Modal close functionality
        document.querySelectorAll('.modal-close').forEach(element => {
            element.addEventListener('click', () => this.closeModals());
        });

        // Close modals on backdrop click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeModals();
                }
            });
        });

        // Initial table events
        this.bindTableEvents();
    }

    bindTableEvents() {
        // Re-bind table modal events (they change after status updates)
        document.querySelectorAll('[data-table]').forEach(element => {
            // Remove existing listeners to prevent duplicates
            element.replaceWith(element.cloneNode(true));
        });
        
        document.querySelectorAll('[data-table]').forEach(element => {
            element.addEventListener('click', (e) => {
                e.preventDefault();
                const tableName = element.getAttribute('data-table');
                this.showTableModal(tableName);
            });
        });

        // Re-bind SQL modal events for View SQL buttons in table overview
        document.querySelectorAll('[data-sql-type="table"]').forEach(element => {
            element.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation(); // Prevent table modal from opening
                const name = element.getAttribute('data-sql-name') || '';
                this.showSQLModal('table', name);
            });
        });
    }

    async executeAction(action, button) {
        const originalText = button.innerHTML;
        const originalDisabled = button.disabled;
        
        // Show loading state
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        button.disabled = true;
        
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
                        break;
                    }
                    break;
                default:
                    throw new Error('Unknown action: ' + action);
            }

            this.showResult(result);
            await this.refreshStatus(); // Refresh status after operation

        } catch (error) {
            this.showError('Operation failed: ' + error.message);
        } finally {
            // Restore button state
            button.innerHTML = originalText;
            button.disabled = originalDisabled;
        }
    }

    async refreshStatus() {
        try {
            const status = await this.api.getStatus();
            if (status.success) {
                this.updateStatusDisplay(status.data);
                this.updateTableOverview(status.data);
                this.bindTableEvents(); // Re-bind events after status update
            }
        } catch (error) {
            console.error('Failed to refresh status:', error);
        }
    }

    startStatusPolling() {
        // Initial load
        this.refreshStatus();
        
        // Poll every 30 seconds
        this.statusInterval = setInterval(() => {
            this.refreshStatus();
        }, 30000);
    }

    stopStatusPolling() {
        if (this.statusInterval) {
            clearInterval(this.statusInterval);
            this.statusInterval = null;
        }
    }

    updateStatusDisplay(status) {
        // Update connection status
        const connectionElement = document.getElementById('connection-status');
        if (connectionElement) {
            connectionElement.className = `px-3 py-1 rounded-full text-sm font-medium ${
                status.connection ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
            }`;
            connectionElement.textContent = status.connection ? 'Connected' : 'Disconnected';
        }

        // Update database status
        const databaseElement = document.getElementById('database-status');
        if (databaseElement) {
            databaseElement.className = `px-3 py-1 rounded-full text-sm font-medium ${
                status.database_exists ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
            }`;
            databaseElement.textContent = status.database_exists ? 'Exists' : 'Not Created';
        }

        // Update tables count
        const tablesElement = document.getElementById('tables-status');
        if (tablesElement) {
            const tableCount = status.tables ? status.tables.length : 0;
            tablesElement.textContent = `(${tableCount}/6)`;
            tablesElement.className = tableCount > 0 ? 'status-good' : 'status-missing';
        }

        // Update individual table status
        ['users', 'device_types', 'devices', 'locations', 'deployments', 'device_logs'].forEach(table => {
            const element = document.querySelector(`[data-table-status="${table}"]`);
            if (element) {
                const exists = status.tables && status.tables.includes(table);
                element.innerHTML = exists ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>';
                element.className = exists ? 'status-good cursor-pointer' : 'status-missing cursor-pointer';
            }
        });

        // Update views count
        const viewsElement = document.getElementById('views-status');
        if (viewsElement) {
            const viewCount = status.views ? status.views.length : 0;
            viewsElement.textContent = `(${viewCount}/2)`;
            viewsElement.className = viewCount > 0 ? 'status-good' : 'status-missing';
        }

        // Update views status
        ['v_active_devices', 'v_device_locations'].forEach(view => {
            const element = document.querySelector(`[data-view-status="${view}"]`);
            if (element) {
                // Case-insensitive comparison to handle any case differences
                const exists = status.views && status.views.some(v => v.toLowerCase() === view.toLowerCase());
                element.innerHTML = exists ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>';
                element.className = exists ? 'status-good' : 'status-missing';
            }
        });

        // Update procedures & functions count
        this.updateProcedureStatus(status);
    }

    updateProcedureStatus(status) {
        const procedureElement = document.getElementById('procedure-count');
        const functionElement = document.getElementById('function-count');
        const triggerElement = document.getElementById('trigger-count');
        
        if (procedureElement) {
            const procedureCount = status.procedures ? status.procedures.length : 0;
            procedureElement.textContent = `${procedureCount}/2`;
            procedureElement.className = procedureCount > 0 ? 'status-good' : 'status-missing';
        }
        
        if (functionElement) {
            const functionCount = status.functions ? status.functions.length : 0;
            functionElement.textContent = `${functionCount}/2`;
            functionElement.className = functionCount > 0 ? 'status-good' : 'status-missing';
        }
        
        if (triggerElement) {
            const triggerCount = status.triggers ? status.triggers.length : 0;
            triggerElement.textContent = `${triggerCount}/2`;
            triggerElement.className = triggerCount > 0 ? 'status-good' : 'status-missing';
        }
    }

    updateTableOverview(status) {
        const tableOverview = document.getElementById('tableOverview');
        const tableGrid = document.getElementById('tables-overview');
        
        if (!tableOverview || !tableGrid) return;

        // Show/hide table overview based on database existence and tables
        const showOverview = status.database_exists && status.tables && status.tables.length > 0;
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

    async showTableModal(tableName) {
        const modal = document.getElementById('tableModal');
        const title = document.getElementById('tableModalTitle');
        const content = document.getElementById('tableModalContent');

        // Show loading state
        title.textContent = 'Table: ' + tableName;
        content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i><p class="mt-2 text-gray-600">Loading table structure...</p></div>';
        modal.classList.remove('hidden');

        try {
            const response = await this.api.getTableStructure(tableName);
            if (response.success) {
                content.innerHTML = this.renderTableStructure(response.data);
            } else {
                content.innerHTML = `<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-triangle text-2xl mb-2"></i><p>${response.message}</p></div>`;
            }
        } catch (error) {
            content.innerHTML = `<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-triangle text-2xl mb-2"></i><p>Failed to load table structure: ${error.message}</p></div>`;
        }
    }

    renderTableStructure(tableData) {
        const { table_name, columns, foreign_keys, indexes, row_count } = tableData;
        
        // Build columns table
        let columnsHtml = `
            <div class="mb-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-3">
                    <i class="fas fa-columns mr-2 text-blue-600"></i>
                    Columns (${columns.length})
                </h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Null</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Key</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Default</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Extra</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
        `;

        columns.forEach(column => {
            const typeDisplay = column.CHARACTER_MAXIMUM_LENGTH 
                ? `${column.DATA_TYPE}(${column.CHARACTER_MAXIMUM_LENGTH})`
                : column.DATA_TYPE;
            
            const keyIcon = column.COLUMN_KEY === 'PRI' ? '<i class="fas fa-key text-yellow-600" title="Primary Key"></i>' :
                           column.COLUMN_KEY === 'MUL' ? '<i class="fas fa-link text-blue-600" title="Foreign Key"></i>' :
                           column.COLUMN_KEY === 'UNI' ? '<i class="fas fa-asterisk text-green-600" title="Unique"></i>' : '';

            columnsHtml += `
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm font-medium text-gray-900">${column.COLUMN_NAME}</td>
                    <td class="px-4 py-2 text-sm text-gray-700">${typeDisplay}</td>
                    <td class="px-4 py-2 text-sm text-center">
                        ${column.IS_NULLABLE === 'YES' ? 
                            '<i class="fas fa-check text-green-600"></i>' : 
                            '<i class="fas fa-times text-red-600"></i>'}
                    </td>
                    <td class="px-4 py-2 text-sm text-center">${keyIcon}</td>
                    <td class="px-4 py-2 text-sm text-gray-600">${column.COLUMN_DEFAULT || '-'}</td>
                    <td class="px-4 py-2 text-sm text-gray-600">${column.EXTRA || '-'}</td>
                </tr>
            `;
        });

        columnsHtml += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        // Build foreign keys section
        let foreignKeysHtml = '';
        if (foreign_keys.length > 0) {
            foreignKeysHtml = `
                <div class="mb-6">
                    <h4 class="text-lg font-semibold text-gray-800 mb-3">
                        <i class="fas fa-link mr-2 text-blue-600"></i>
                        Foreign Keys (${foreign_keys.length})
                    </h4>
                    <div class="space-y-2">
            `;
            
            foreign_keys.forEach(fk => {
                foreignKeysHtml += `
                    <div class="bg-blue-50 border border-blue-200 rounded p-3">
                        <div class="text-sm">
                            <strong>${fk.COLUMN_NAME}</strong> → <strong>${fk.REFERENCED_TABLE_NAME}.${fk.REFERENCED_COLUMN_NAME}</strong>
                        </div>
                        <div class="text-xs text-gray-600 mt-1">
                            ON UPDATE: ${fk.UPDATE_RULE} | ON DELETE: ${fk.DELETE_RULE}
                        </div>
                    </div>
                `;
            });
            
            foreignKeysHtml += `
                    </div>
                </div>
            `;
        }

        // Build indexes section
        let indexesHtml = '';
        if (indexes.length > 0) {
            indexesHtml = `
                <div class="mb-6">
                    <h4 class="text-lg font-semibold text-gray-800 mb-3">
                        <i class="fas fa-search mr-2 text-green-600"></i>
                        Indexes (${indexes.length})
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            `;
            
            const groupedIndexes = {};
            indexes.forEach(index => {
                if (!groupedIndexes[index.Key_name]) {
                    groupedIndexes[index.Key_name] = [];
                }
                groupedIndexes[index.Key_name].push(index);
            });

            Object.entries(groupedIndexes).forEach(([indexName, indexColumns]) => {
                const isPrimary = indexName === 'PRIMARY';
                const isUnique = indexColumns[0].Non_unique === '0';
                const columns = indexColumns.map(col => col.Column_name).join(', ');
                
                indexesHtml += `
                    <div class="bg-gray-50 border rounded p-3">
                        <div class="font-medium text-sm flex items-center">
                            ${isPrimary ? '<i class="fas fa-key text-yellow-600 mr-1"></i>' :
                              isUnique ? '<i class="fas fa-asterisk text-green-600 mr-1"></i>' :
                              '<i class="fas fa-search text-gray-600 mr-1"></i>'}
                            ${indexName}
                        </div>
                        <div class="text-xs text-gray-600 mt-1">${columns}</div>
                    </div>
                `;
            });
            
            indexesHtml += `
                    </div>
                </div>
            `;
        }

        // Build summary section
        const summaryHtml = `
            <div class="mb-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-3">
                    <i class="fas fa-info-circle mr-2 text-purple-600"></i>
                    Table Summary
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-blue-50 border border-blue-200 rounded p-3 text-center">
                        <div class="text-2xl font-bold text-blue-600">${row_count.toLocaleString()}</div>
                        <div class="text-xs text-gray-600">Total Rows</div>
                    </div>
                    <div class="bg-green-50 border border-green-200 rounded p-3 text-center">
                        <div class="text-2xl font-bold text-green-600">${columns.length}</div>
                        <div class="text-xs text-gray-600">Columns</div>
                    </div>
                    <div class="bg-yellow-50 border border-yellow-200 rounded p-3 text-center">
                        <div class="text-2xl font-bold text-yellow-600">${foreign_keys.length}</div>
                        <div class="text-xs text-gray-600">Foreign Keys</div>
                    </div>
                    <div class="bg-purple-50 border border-purple-200 rounded p-3 text-center">
                        <div class="text-2xl font-bold text-purple-600">${Object.keys(indexes.reduce((acc, idx) => ({...acc, [idx.Key_name]: true}), {})).length}</div>
                        <div class="text-xs text-gray-600">Indexes</div>
                    </div>
                </div>
            </div>
        `;

        return summaryHtml + columnsHtml + foreignKeysHtml + indexesHtml;
    }

    async showSQLModal(type, name = '') {
        const modal = document.getElementById('sqlModal');
        const content = document.getElementById('sqlModalContent');
        const title = document.getElementById('sqlModalTitle');

        if (!modal || !content || !title) return;

        // Show loading state
        title.textContent = 'Loading...';
        content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i><p class="mt-2 text-gray-600">Loading SQL content...</p></div>';
        modal.classList.remove('hidden');

        try {
            const response = await this.api.getSQLContent(type, name);
            
            if (response.success) {
                title.textContent = this.getSQLModalTitle(type, name);
                content.innerHTML = `
                    <div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm overflow-x-auto">
                        <pre>${response.data.content || 'No content available'}</pre>
                    </div>
                `;
            } else {
                title.textContent = 'Error';
                content.innerHTML = `<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-triangle text-2xl mb-2"></i><p>${response.message}</p></div>`;
            }
        } catch (error) {
            title.textContent = 'Error';
            content.innerHTML = `<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-triangle text-2xl mb-2"></i><p>Failed to load SQL content: ${error.message}</p></div>`;
        }
    }

    getSQLModalTitle(type, name) {
        switch (type) {
            case 'create_database':
                return 'Create Database SQL';
            case 'table':
                return `CREATE TABLE ${name}`;
            case 'view':
                return `CREATE VIEW ${name}`;
            case 'procedures':
                return 'Stored Procedures';
            case 'functions':
                return 'Functions';
            case 'triggers':
                return 'Triggers';
            default:
                return 'SQL Content';
        }
    }

    closeModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.add('hidden');
        });
    }

    showResult(result) {
        if (result.success) {
            this.showNotification('success', result.message);
        } else {
            this.showNotification('error', result.message);
        }
    }

    showError(message) {
        this.showNotification('error', message);
    }

    showNotification(type, message) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-300 ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        notification.innerHTML = `
            <div class="flex items-center space-x-2">
                <i class="fas ${
                    type === 'success' ? 'fa-check-circle' :
                    type === 'error' ? 'fa-exclamation-circle' :
                    'fa-info-circle'
                }"></i>
                <span>${message}</span>
                <button class="ml-2 text-white hover:text-gray-200" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    destroy() {
        this.stopStatusPolling();
    }
}

// Initialize when DOM is loaded
let databaseManager;
document.addEventListener('DOMContentLoaded', () => {
    databaseManager = new DatabaseManager();
});

// Clean up on page unload
window.addEventListener('beforeunload', () => {
    if (databaseManager) {
        databaseManager.destroy();
    }
});

// Global function for refresh button
window.dbManager = {
    refreshStatus: () => databaseManager?.refreshStatus()
};