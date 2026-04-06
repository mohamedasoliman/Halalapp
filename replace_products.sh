#!/bin/bash

# Replace all products in the database with a new CSV file
# Usage: ./replace_products.sh /path/to/file.csv

if [ -z "$1" ]; then
    echo "Usage: ./replace_products.sh /path/to/file.csv"
    exit 1
fi

CSV_FILE="$1"

if [ ! -f "$CSV_FILE" ]; then
    echo "Error: File not found: $CSV_FILE"
    exit 1
fi

SERVER="halalapp@108.167.141.140"
REMOTE_DIR="/home5/halalapp/halalapp"
REMOTE_CSV="$REMOTE_DIR/import.csv"

echo "Uploading CSV to server..."
scp "$CSV_FILE" "$SERVER:$REMOTE_CSV"

echo "Running product replace..."
ssh "$SERVER" "cd $REMOTE_DIR && php artisan products:replace $REMOTE_CSV && rm $REMOTE_CSV"

echo "Done!"
