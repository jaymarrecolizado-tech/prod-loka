#!/bin/bash

###############################################################################
# Trip Data Extraction Script
# Extracts all trip-related INSERT statements from old SQL dump
###############################################################################

# Input file
OLD_SQL="127_0_0_1old.sql"
OUTPUT_SQL="trip_data_extracted.sql"

# Trip-related tables to extract
TABLES=(
    "users"
    "departments"
    "vehicles"
    "drivers"
    "requests"
    "request_passengers"
    "assignment_history"
    "approvals"
    "approval_workflow"
    "fuel_records"
    "maintenance"
    "maintenance_requests"
)

echo "============================================================"
echo "TRIP DATA EXTRACTION TOOL"
echo "============================================================"
echo "Input: $OLD_SQL"
echo "Output: $OUTPUT_SQL"
echo "============================================================"

# Check if input file exists
if [ ! -f "$OLD_SQL" ]; then
    echo "✗ Error: Input file not found: $OLD_SQL"
    exit 1
fi

# Start output file
cat > "$OUTPUT_SQL" << 'EOF'
--
-- Trip Data Migration Script
-- Extracted from: 127_0_0_1old.sql
-- Generated: $(date)
--
-- Instructions:
-- 1. Import into new database: mysql -u root -p loka_new < trip_data_extracted.sql
-- 2. Or run within MySQL: SOURCE trip_data_extracted.sql;
--

-- Disable foreign key checks
SET FOREIGN_KEY_CHECKS = 0;
SET AUTOCOMMIT = 0;

EOF

# Process each table
TOTAL_STATEMENTS=0
for table in "${TABLES[@]}"; do
    echo ""
    echo "Processing: $table"

    # Extract INSERT statements for this table
    # Use sed to extract lines between table creation and next table
    awk '/INSERT INTO `'"$table"'`/,/;$/' "$OLD_SQL" | grep "INSERT INTO \`$table\`" >> "$OUTPUT_SQL"

    # Count statements
    count=$(grep -c "INSERT INTO \`$table\`" "$OUTPUT_SQL" || echo 0)
    echo "  Extracted $count statement(s)"
    TOTAL_STATEMENTS=$((TOTAL_STATEMENTS + count))
done

# Complete output file
cat >> "$OUTPUT_SQL" << 'EOF'

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

--
-- End of migration
--
EOF

echo ""
echo "============================================================"
echo "EXTRACTION SUMMARY"
echo "============================================================"
echo "Output file: $OUTPUT_SQL"
echo "Total statements: $TOTAL_STATEMENTS"
echo "File size: $(wc -c < "$OUTPUT_SQL") bytes"
echo "============================================================"

if [ $TOTAL_STATEMENTS -gt 0 ]; then
    echo ""
    echo "✓ Extraction completed successfully!"
    echo ""
    echo "To import into new database:"
    echo "  mysql -u root -p loka_new < $OUTPUT_SQL"
    echo ""
    echo "Or from MySQL prompt:"
    echo "  USE loka_new;"
    echo "  SOURCE $OUTPUT_SQL;"
else
    echo ""
    echo "⚠ No statements extracted. Check input file."
fi

echo ""
