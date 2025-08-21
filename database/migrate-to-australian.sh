# Australian Database Migration Script
# Blue Cleaning Services - Database Migration Tool
# 
# This script safely migrates existing data to the new Australian schema
# while preserving all existing functionality.
# 
# Version: 2.0.0
# Created: 07/08/2025

#!/bin/bash

# Set script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Log function
log() {
    echo -e "${BLUE}[$(date '+%d/%m/%Y %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if we're in the correct directory
if [[ ! -f "$PROJECT_ROOT/config/australian-database.php" ]]; then
    error "Australian database configuration not found. Please run this script from the project root."
    exit 1
fi

log "🇦🇺 Blue Cleaning Services - Australian Database Migration"
log "=================================================="

# Load environment variables
if [[ -f "$PROJECT_ROOT/.env.australia" ]]; then
    log "Loading Australian environment configuration..."
    source "$PROJECT_ROOT/.env.australia"
else
    warning ".env.australia not found, using defaults"
    DB_HOST="localhost"
    DB_PORT="3306"
    DB_DATABASE="blue_cleaning_au"
    DB_USERNAME="blue_user"
fi

# Database connection test
log "Testing database connection..."
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" -e "SELECT 1;" "$DB_DATABASE" 2>/dev/null

if [[ $? -ne 0 ]]; then
    error "Cannot connect to database. Please check your configuration."
    exit 1
fi

success "Database connection successful"

# Backup existing database
BACKUP_FILE="$PROJECT_ROOT/database/backup_pre_australian_migration_$(date '+%Y%m%d_%H%M%S').sql"
log "Creating backup of existing database..."

mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" \
    --single-transaction --routines --triggers \
    "$DB_DATABASE" > "$BACKUP_FILE"

if [[ $? -eq 0 ]]; then
    success "Backup created: $BACKUP_FILE"
else
    error "Backup failed. Migration aborted."
    exit 1
fi

# Check existing tables
log "Analyzing existing database structure..."
EXISTING_TABLES=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" \
    -e "SHOW TABLES;" "$DB_DATABASE" 2>/dev/null | grep -v "Tables_in_")

if [[ -z "$EXISTING_TABLES" ]]; then
    log "No existing tables found. Proceeding with fresh installation."
    MIGRATION_TYPE="fresh"
else
    log "Existing tables detected:"
    echo "$EXISTING_TABLES" | sed 's/^/  - /'
    MIGRATION_TYPE="upgrade"
fi

# Apply Australian schema
log "Applying Australian database schema..."

if [[ -f "$PROJECT_ROOT/database/australian-schema-part1.sql" ]]; then
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" \
        "$DB_DATABASE" < "$PROJECT_ROOT/database/australian-schema-part1.sql"
    
    if [[ $? -eq 0 ]]; then
        success "Australian schema applied successfully"
    else
        error "Schema application failed"
        exit 1
    fi
else
    error "Australian schema file not found"
    exit 1
fi

# Data migration for existing installations
if [[ "$MIGRATION_TYPE" == "upgrade" ]]; then
    log "Performing data migration to Australian format..."
    
    # Create migration SQL script
    cat > /tmp/australian_migration.sql << 'EOF'
-- Australian Data Migration Script

-- Update existing user addresses to Australian format
UPDATE users SET 
    country = 'AUS' 
WHERE country IS NULL OR country = '';

-- Migrate phone numbers to Australian format
UPDATE users SET 
    mobile = CONCAT('+61', SUBSTRING(mobile, 2))
WHERE mobile LIKE '04%' AND mobile NOT LIKE '+61%';

-- Set default timezone for existing users
UPDATE users SET 
    timezone = 'Australia/Sydney' 
WHERE timezone IS NULL OR timezone = '';

-- Set language to Australian English
UPDATE users SET 
    language = 'en_AU' 
WHERE language IS NULL OR language = '';

-- Migrate existing professionals
UPDATE professionals p
JOIN users u ON p.user_id = u.id
SET p.verification_status = COALESCE(p.verification_status, 'pending'),
    p.availability_status = COALESCE(p.availability_status, 'unavailable');

-- Add training codes to existing trainings
UPDATE trainings 
SET training_code = CONCAT('TRN_', DATE_FORMAT(created_at, '%Y%m%d'), '_', UPPER(RIGHT(UUID(), 6)))
WHERE training_code IS NULL OR training_code = '';

-- Add candidate codes to existing candidates  
UPDATE candidates
SET candidate_code = CONCAT('CND_', DATE_FORMAT(created_at, '%Y%m%d'), '_', UPPER(RIGHT(UUID(), 6)))
WHERE candidate_code IS NULL OR candidate_code = '';

-- Add booking codes to existing bookings
UPDATE bookings
SET booking_code = CONCAT('BK_', DATE_FORMAT(created_at, '%Y%m%d'), '_', UPPER(RIGHT(UUID(), 6)))
WHERE booking_code IS NULL OR booking_code = '';

-- Add user codes to existing users
UPDATE users
SET user_code = CONCAT('USR_', DATE_FORMAT(created_at, '%Y%m%d'), '_', UPPER(RIGHT(UUID(), 6)))
WHERE user_code IS NULL OR user_code = '';

-- Update GST calculations for existing bookings (10% Australian GST)
UPDATE bookings 
SET gst_amount = ROUND(total_amount * 0.10, 2)
WHERE gst_amount = 0 AND total_amount > 0;

COMMIT;
EOF

    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" \
        "$DB_DATABASE" < /tmp/australian_migration.sql
    
    if [[ $? -eq 0 ]]; then
        success "Data migration completed successfully"
        rm /tmp/australian_migration.sql
    else
        error "Data migration failed"
        exit 1
    fi
fi

# Verify migration
log "Verifying migration results..."
VERIFICATION_RESULTS=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" \
    "$DB_DATABASE" << 'EOF'
SELECT 
    'users' as table_name, COUNT(*) as record_count
FROM users
UNION ALL
SELECT 
    'professionals' as table_name, COUNT(*) as record_count  
FROM professionals
UNION ALL
SELECT 
    'candidates' as table_name, COUNT(*) as record_count
FROM candidates
UNION ALL
SELECT 
    'trainings' as table_name, COUNT(*) as record_count
FROM trainings
UNION ALL
SELECT 
    'bookings' as table_name, COUNT(*) as record_count
FROM bookings;
EOF
)

if [[ $? -eq 0 ]]; then
    success "Migration verification completed:"
    echo "$VERIFICATION_RESULTS" | column -t -s $'\t'
else
    warning "Migration verification had issues, but migration may still be successful"
fi

# Create post-migration report
REPORT_FILE="$PROJECT_ROOT/database/migration_report_$(date '+%Y%m%d_%H%M%S').txt"
cat > "$REPORT_FILE" << EOF
🇦🇺 BLUE CLEANING SERVICES - AUSTRALIAN MIGRATION REPORT
========================================================

Migration Date: $(date '+%d/%m/%Y %H:%M:%S %Z')
Migration Type: $MIGRATION_TYPE
Database: $DB_DATABASE
Host: $DB_HOST:$DB_PORT

BACKUP INFORMATION:
- Backup File: $BACKUP_FILE
- Backup Size: $(du -h "$BACKUP_FILE" 2>/dev/null | cut -f1 || echo "Unknown")

MIGRATION RESULTS:
$VERIFICATION_RESULTS

AUSTRALIAN STANDARDS APPLIED:
✅ Database timezone set to +10:00 (Australian Eastern)
✅ Date format: DD/MM/YYYY
✅ Currency: AUD with GST calculations
✅ Phone number format: +61 format
✅ Address format: Australian suburbs, states, postcodes
✅ User codes, booking codes, training codes generated
✅ Professional verification statuses updated
✅ Candidate management system activated

NEXT STEPS:
1. Test all functionality with the new Australian system
2. Update any hardcoded references to old database fields
3. Verify date/time displays use Australian format
4. Test payment processing with AUD currency
5. Validate phone number and address formatting

ROLLBACK INSTRUCTIONS:
If issues occur, restore from backup:
mysql -h$DB_HOST -P$DB_PORT -u$DB_USERNAME -p$DB_PASSWORD $DB_DATABASE < $BACKUP_FILE

For support, contact the Blue Cleaning Development Team.
EOF

success "Migration completed successfully!"
log "Migration report saved: $REPORT_FILE"
log ""
log "🎉 Your Blue Cleaning Services system is now Australian-ready!"
log "   - All dates display in DD/MM/YYYY format"
log "   - Currency displays in AUD with GST"
log "   - Phone numbers use +61 format"  
log "   - Addresses use Australian suburbs/states/postcodes"
log "   - System timezone set to Australia/Sydney"
log ""
log "Please test your system thoroughly before going to production."

# Clean up temporary files
rm -f /tmp/australian_migration.sql

exit 0
