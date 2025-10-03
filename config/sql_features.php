<?php
/**
 * SQL Features Index
 * This file tracks all SQL features used throughout the application
 */

class SQLFeatureTracker {
    public static $features = [
        'Basic Operations' => [
            'SELECT' => [
                'files' => ['dashboard.php', 'devices.php', 'users.php'],
                'description' => 'Basic SELECT statements for data retrieval'
            ],
            'INSERT' => [
                'files' => ['add_device.php', 'register.php', 'add_location.php'],
                'description' => 'INSERT statements for adding new records'
            ],
            'UPDATE' => [
                'files' => ['edit_device.php', 'resolve_log.php', 'profile.php'],
                'description' => 'UPDATE statements for modifying existing records'
            ],
            'DELETE' => [
                'files' => ['delete_device.php', 'admin_panel.php'],
                'description' => 'DELETE statements for removing records'
            ]
        ],
        'Advanced Queries' => [
            'INNER JOIN' => [
                'files' => ['dashboard.php', 'device_details.php'],
                'description' => 'INNER JOIN for combining related tables'
            ],
            'LEFT JOIN' => [
                'files' => ['reports.php', 'device_logs.php'],
                'description' => 'LEFT JOIN for including all records from left table'
            ],
            'RIGHT JOIN' => [
                'files' => ['analytics.php'],
                'description' => 'RIGHT JOIN demonstrations'
            ],
            'FULL OUTER JOIN' => [
                'files' => ['comprehensive_reports.php'],
                'description' => 'FULL OUTER JOIN using UNION'
            ]
        ],
        'Aggregation Functions' => [
            'COUNT' => [
                'files' => ['dashboard.php', 'statistics.php'],
                'description' => 'COUNT function for counting records'
            ],
            'SUM' => [
                'files' => ['analytics.php', 'cost_analysis.php'],
                'description' => 'SUM function for totaling values'
            ],
            'AVG' => [
                'files' => ['performance_metrics.php'],
                'description' => 'AVG function for calculating averages'
            ],
            'MAX/MIN' => [
                'files' => ['reports.php', 'device_analytics.php'],
                'description' => 'MAX and MIN functions for extreme values'
            ],
            'GROUP_CONCAT' => [
                'files' => ['device_locations.php'],
                'description' => 'GROUP_CONCAT for string aggregation'
            ]
        ],
        'Grouping and Filtering' => [
            'GROUP BY' => [
                'files' => ['analytics.php', 'reports.php'],
                'description' => 'GROUP BY for data grouping'
            ],
            'HAVING' => [
                'files' => ['advanced_analytics.php'],
                'description' => 'HAVING clause for filtering grouped data'
            ],
            'ORDER BY' => [
                'files' => ['device_list.php', 'log_viewer.php'],
                'description' => 'ORDER BY for sorting results'
            ],
            'LIMIT' => [
                'files' => ['pagination.php', 'recent_logs.php'],
                'description' => 'LIMIT for result pagination'
            ]
        ],
        'Subqueries' => [
            'Scalar Subqueries' => [
                'files' => ['device_comparison.php'],
                'description' => 'Subqueries returning single values'
            ],
            'Correlated Subqueries' => [
                'files' => ['advanced_reports.php'],
                'description' => 'Subqueries referencing outer query'
            ],
            'EXISTS/NOT EXISTS' => [
                'files' => ['conditional_reports.php'],
                'description' => 'EXISTS for conditional logic'
            ],
            'IN/NOT IN' => [
                'files' => ['filtered_devices.php'],
                'description' => 'IN operator with subqueries'
            ]
        ],
        'Views and CTEs' => [
            'CREATE VIEW' => [
                'files' => ['database_views.php'],
                'description' => 'Creating database views'
            ],
            'Common Table Expressions (WITH)' => [
                'files' => ['recursive_reports.php'],
                'description' => 'WITH clause for CTEs'
            ]
        ],
        'Stored Procedures and Functions' => [
            'Stored Procedures' => [
                'files' => ['stored_procedures.php'],
                'description' => 'Custom stored procedures with parameters'
            ],
            'Functions' => [
                'files' => ['custom_functions.php'],
                'description' => 'User-defined functions'
            ],
            'Triggers' => [
                'files' => ['audit_triggers.php'],
                'description' => 'Database triggers for automation'
            ]
        ],
        'Advanced Features' => [
            'CASE Statements' => [
                'files' => ['conditional_logic.php', 'status_reports.php'],
                'description' => 'CASE for conditional logic in queries'
            ],
            'Window Functions' => [
                'files' => ['ranking_analysis.php'],
                'description' => 'ROW_NUMBER, RANK, DENSE_RANK, etc.'
            ],
            'Regular Expressions' => [
                'files' => ['pattern_search.php'],
                'description' => 'REGEXP for pattern matching'
            ],
            'Full-Text Search' => [
                'files' => ['search_logs.php'],
                'description' => 'MATCH AGAINST for text search'
            ]
        ],
        'Data Manipulation' => [
            'INSERT ... ON DUPLICATE KEY UPDATE' => [
                'files' => ['upsert_operations.php'],
                'description' => 'UPSERT operations'
            ],
            'REPLACE' => [
                'files' => ['data_sync.php'],
                'description' => 'REPLACE for insert or update'
            ],
            'Multi-table UPDATE' => [
                'files' => ['bulk_updates.php'],
                'description' => 'Updating multiple tables'
            ],
            'Multi-table DELETE' => [
                'files' => ['cascade_delete.php'],
                'description' => 'Deleting from multiple tables'
            ]
        ],
        'Constraints and Integrity' => [
            'Foreign Key Constraints' => [
                'files' => ['database.php'],
                'description' => 'FOREIGN KEY with CASCADE options'
            ],
            'CHECK Constraints' => [
                'files' => ['data_validation.php'],
                'description' => 'CHECK constraints for data validation'
            ],
            'UNIQUE Constraints' => [
                'files' => ['unique_validation.php'],
                'description' => 'UNIQUE constraints'
            ]
        ],
        'Indexing and Performance' => [
            'CREATE INDEX' => [
                'files' => ['index_management.php'],
                'description' => 'Creating indexes for performance'
            ],
            'EXPLAIN' => [
                'files' => ['query_optimization.php'],
                'description' => 'Query execution plan analysis'
            ],
            'ANALYZE TABLE' => [
                'files' => ['table_analysis.php'],
                'description' => 'Table statistics update'
            ]
        ]
    ];

    public static function getFeatureUsage($feature) {
        foreach (self::$features as $category => $features) {
            if (isset($features[$feature])) {
                return $features[$feature];
            }
        }
        return null;
    }

    public static function getAllFeatures() {
        return self::$features;
    }

    public static function searchFeatures($keyword) {
        $results = [];
        foreach (self::$features as $category => $features) {
            foreach ($features as $feature => $details) {
                if (stripos($feature, $keyword) !== false || 
                    stripos($details['description'], $keyword) !== false) {
                    $results[$category][$feature] = $details;
                }
            }
        }
        return $results;
    }
}
?>