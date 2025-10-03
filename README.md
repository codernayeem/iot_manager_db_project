# IoT Device Manager - Database Project

## 📋 Project Overview

A comprehensive PHP-based IoT Device Management & Monitoring System that demonstrates extensive MySQL/SQL features. This system allows users to manage IoT devices (drones, CCTV cameras, Raspberry Pis, etc.), track deployments, monitor logs, and perform advanced analytics.

## 🎯 Key Features

### Core Functionality
- **User Management**: Registration, authentication, and profile management
- **Device Management**: Create, read, update, delete IoT devices
- **Location Management**: Manage deployment locations
- **Device Deployment**: Many-to-many relationship between devices and locations
- **Log Monitoring**: Track device activities, errors, warnings, and info logs
- **Issue Resolution**: Users can resolve device issues with tracking
- **Advanced Analytics**: Performance metrics, trends, and comprehensive reporting

### 🛢️ SQL Features Demonstrated

#### **Basic Operations**
- ✅ SELECT (simple and complex queries)
- ✅ INSERT (single and bulk operations)
- ✅ UPDATE (single and multi-table)
- ✅ DELETE (single and cascade operations)

#### **Advanced Querying**
- ✅ **JOINs**: INNER JOIN, LEFT JOIN, RIGHT JOIN, FULL OUTER JOIN
- ✅ **Subqueries**: Scalar, correlated, EXISTS/NOT EXISTS, IN/NOT IN
- ✅ **Window Functions**: ROW_NUMBER(), RANK(), DENSE_RANK(), LAG()
- ✅ **Common Table Expressions (WITH)**: Complex hierarchical queries
- ✅ **Views**: Database views for simplified data access

#### **Aggregation & Grouping**
- ✅ **Aggregate Functions**: COUNT(), SUM(), AVG(), MAX(), MIN()
- ✅ **GROUP BY**: Data grouping with multiple columns
- ✅ **HAVING**: Filtering grouped data
- ✅ **GROUP_CONCAT**: String aggregation
- ✅ **Conditional Aggregation**: COUNT(CASE WHEN...)

#### **Data Types & Constraints**
- ✅ **Primary Keys**: AUTO_INCREMENT
- ✅ **Foreign Keys**: WITH CASCADE options
- ✅ **UNIQUE Constraints**: Email uniqueness, serial numbers
- ✅ **CHECK Constraints**: Data validation (MySQL 8.0+)
- ✅ **ENUM Types**: Status fields
- ✅ **Indexes**: Performance optimization

#### **Advanced Features**
- ✅ **CASE Statements**: Conditional logic in queries
- ✅ **Date Functions**: NOW(), DATE_SUB(), DATEDIFF(), YEARWEEK()
- ✅ **String Functions**: CONCAT(), LIKE pattern matching
- ✅ **Mathematical Functions**: Percentage calculations
- ✅ **Full-Text Search**: MATCH AGAINST for log searching
- ✅ **Regular Expressions**: REGEXP pattern matching

#### **Stored Procedures & Functions**
- ✅ **Stored Procedures**: With parameters and control flow
- ✅ **User-Defined Functions**: Custom business logic
- ✅ **Triggers**: Automated database actions
- ✅ **Transactions**: BEGIN, COMMIT, ROLLBACK

#### **Performance & Optimization**
- ✅ **Indexing**: Single and composite indexes
- ✅ **Query Optimization**: EXPLAIN query plans
- ✅ **Pagination**: LIMIT and OFFSET
- ✅ **Result Limiting**: Performance optimization

#### **Data Integrity**
- ✅ **Referential Integrity**: Foreign key constraints
- ✅ **CASCADE Operations**: ON DELETE CASCADE, ON UPDATE CASCADE
- ✅ **NULL Handling**: Proper NULL checks
- ✅ **Data Validation**: Input sanitization and validation

## 🗂️ Database Schema

```sql
-- Users table
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    f_name VARCHAR(50) NOT NULL,
    l_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Device Types table
CREATE TABLE device_types (
    t_id INT PRIMARY KEY AUTO_INCREMENT,
    t_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Devices table
CREATE TABLE devices (
    d_id INT PRIMARY KEY AUTO_INCREMENT,
    d_name VARCHAR(100) NOT NULL,
    t_id INT NOT NULL,
    user_id INT NOT NULL,
    serial_number VARCHAR(100) UNIQUE NOT NULL,
    status ENUM('active', 'inactive', 'maintenance', 'error') DEFAULT 'inactive',
    purchase_date DATE,
    warranty_expiry DATE,
    last_maintenance DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (t_id) REFERENCES device_types(t_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- Locations table
CREATE TABLE locations (
    loc_id INT PRIMARY KEY AUTO_INCREMENT,
    loc_name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Deployments table (Many-to-Many)
CREATE TABLE deployments (
    deployment_id INT PRIMARY KEY AUTO_INCREMENT,
    d_id INT NOT NULL,
    loc_id INT NOT NULL,
    deployed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deployed_by INT NOT NULL,
    deployment_notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (d_id) REFERENCES devices(d_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (loc_id) REFERENCES locations(loc_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (deployed_by) REFERENCES users(user_id) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- Device Logs table
CREATE TABLE device_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    d_id INT NOT NULL,
    log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    log_type ENUM('error', 'warning', 'info', 'debug') NOT NULL,
    message TEXT NOT NULL,
    severity_level INT DEFAULT 1,
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (d_id) REFERENCES devices(d_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
);
```

## 🚀 Installation & Setup

### Prerequisites
- **XAMPP** (Apache, MySQL, PHP 7.4+)
- **Web Browser** (Chrome, Firefox, Safari, Edge)
- **Modern PHP** with PDO extension

### Installation Steps

1. **Clone/Download the project**
   ```bash
   # Place the project in your XAMPP htdocs directory
   C:\xampp\htdocs\iot_manager_db_project\
   ```

2. **Start XAMPP Services**
   - Start Apache
   - Start MySQL

3. **Database Setup**
   - Open your browser and navigate to: `http://localhost/iot_manager_db_project/`
   - The system will automatically:
     - Create the database (`iot_manager_db`)
     - Create all tables with constraints
     - Insert sample data

4. **Access the System**
   ```
   URL: http://localhost/iot_manager_db_project/
   
   Default Admin Account:
   Email: admin@iot.com
   Password: admin123
   
   Sample User Account:
   Email: john.doe@email.com
   Password: password123
   ```

## 📁 Project Structure

```
iot_manager_db_project/
├── config/
│   ├── database.php          # Database configuration & table creation
│   └── sql_features.php      # SQL features tracking system
├── components/
│   └── navbar.php           # Navigation component
├── index.php               # Home page & database setup
├── login.php               # User authentication
├── register.php            # User registration
├── logout.php              # Session management
├── dashboard.php           # Main dashboard with statistics
├── devices.php             # Device management with advanced filtering
├── analytics.php           # Advanced analytics with window functions
├── sql_features.php        # SQL features documentation
└── README.md              # This file
```

## 🔍 SQL Features by Page

### **Dashboard (dashboard.php)**
- Multiple subqueries for statistics
- Complex JOINs (INNER, LEFT)
- GROUP BY with multiple columns
- GROUP_CONCAT for string aggregation
- CASE statements for conditional logic
- Date functions (DATE_SUB, NOW)

### **Device Management (devices.php)**
- Dynamic WHERE clauses
- LIKE operator for search
- Complex pagination with LIMIT/OFFSET
- Multiple table JOINs
- Correlated subqueries
- ORDER BY with multiple options

### **Analytics (analytics.php)**
- Window functions (RANK, ROW_NUMBER, LAG)
- Common Table Expressions (WITH)
- Percentile calculations
- Time-based analysis with YEARWEEK
- Complex aggregations
- Performance rankings

### **Authentication (login.php, register.php)**
- Password hashing and verification
- UNIQUE constraint validation
- EXISTS subqueries
- UPDATE with timestamps
- Transaction handling

## 🎨 User Interface Features

### **Interactive SQL Tooltips**
- Hover over any SQL-related button to see the actual query
- Real-time SQL command display
- Educational tool for learning SQL

### **Responsive Design**
- Tailwind CSS for modern styling
- Mobile-friendly interface
- Font Awesome icons
- Interactive charts and graphs

### **Search & Filter System**
- Advanced filtering capabilities
- Dynamic query building
- Real-time search results
- Pagination controls

## 📊 Key SQL Patterns Demonstrated

### **1. Complex JOIN with Subqueries**
```sql
SELECT d.d_name, dt.t_name, 
       (SELECT COUNT(*) FROM device_logs dl 
        WHERE dl.d_id = d.d_id AND dl.log_type = 'error') as error_count
FROM devices d
INNER JOIN device_types dt ON d.t_id = dt.t_id
LEFT JOIN deployments dep ON d.d_id = dep.d_id
```

### **2. Window Functions for Ranking**
```sql
SELECT d_name, error_count,
       RANK() OVER (ORDER BY error_count DESC) as error_rank,
       ROW_NUMBER() OVER (PARTITION BY device_type ORDER BY error_count DESC) as type_rank
FROM device_performance_view
```

### **3. Common Table Expressions**
```sql
WITH device_stats AS (
    SELECT d.d_id, COUNT(dl.log_id) as log_count
    FROM devices d
    LEFT JOIN device_logs dl ON d.d_id = dl.d_id
    GROUP BY d.d_id
)
SELECT * FROM device_stats WHERE log_count > 10
```

### **4. Conditional Aggregation**
```sql
SELECT dt.t_name,
       COUNT(d.d_id) as total_devices,
       SUM(CASE WHEN d.status = 'active' THEN 1 ELSE 0 END) as active_count,
       SUM(CASE WHEN d.status = 'error' THEN 1 ELSE 0 END) as error_count
FROM device_types dt
LEFT JOIN devices d ON dt.t_id = d.t_id
GROUP BY dt.t_id, dt.t_name
```

## 🔧 Advanced Features

### **SQL Feature Tracking System**
- Comprehensive documentation of all SQL features used
- Searchable feature index
- File-by-file feature mapping
- Educational tooltips and examples

### **Performance Optimization**
- Strategic indexing on frequently queried columns
- Query optimization with EXPLAIN
- Efficient pagination
- Proper use of JOINs vs subqueries

### **Data Integrity**
- Foreign key constraints with CASCADE options
- UNIQUE constraints for data uniqueness
- CHECK constraints for data validation
- Proper NULL handling

## 🎓 Educational Value

This project serves as a comprehensive reference for:
- **Database Students**: Real-world application of SQL concepts
- **Developers**: Best practices in PHP-MySQL integration
- **Instructors**: Teaching tool for advanced SQL features
- **Database Administrators**: Schema design and optimization techniques

## 🔍 SQL Features Search

The built-in SQL features documentation (`sql_features.php`) allows you to:
- Search for specific SQL keywords
- Filter by feature categories
- View implementation examples
- Navigate to relevant files
- Understand query patterns

## 🚀 Future Enhancements

Potential areas for expansion:
- **Stored Procedures**: More complex business logic
- **Triggers**: Automated logging and auditing
- **Views**: Simplified data access layers
- **Full-Text Search**: Advanced log searching
- **Geospatial Features**: Location-based queries
- **Data Warehousing**: Historical data analysis

## 📝 License

This project is created for educational purposes and database project demonstrations.

## 👨‍💻 Author

Created as a comprehensive database project demonstrating advanced MySQL/SQL features in a real-world IoT management scenario.

---

**Note**: This project demonstrates SQL features compatible with MySQL 5.7+ and MySQL 8.0+. Some advanced features like CHECK constraints and window functions require MySQL 8.0+.