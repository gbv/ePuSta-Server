#!/bin/bash

SCRIPT_NAME=$(basename "$0")

print_help() {
    echo "Usage: $SCRIPT_NAME [OPTIONS] FILE..."
    echo ""
    echo "Deletes all Solr documents whose 'source' field matches the filename"
    echo "of the given solrImport JSON file(s)."
    echo "The Solr connection is read from the config file."
    echo ""
    echo "Options:"
    echo "  -h, --help    Show this help message and exit"
    echo ""
    echo "Arguments:"
    echo "  FILE...       One or more solrImport JSON files whose entries should be deleted."
}

# Parse options
OPTS=$(getopt -o h --long help -n "$SCRIPT_NAME" -- "$@")
if [ $? -ne 0 ]; then
    echo "Error: Invalid option" >&2
    print_help
    exit 1
fi

eval set -- "$OPTS"

while true; do
    case "$1" in
        -h | --help)
            print_help
            exit 0
            ;;
        --)
            shift
            break
            ;;
        *)
            echo "Error: Unknown option: $1" >&2
            print_help
            exit 1
            ;;
    esac
done

if [ $# -eq 0 ]; then
    echo "Error: No files specified" >&2
    print_help
    exit 1
fi

# Load config
dir=$(dirname "$0")
if [ -f "$dir/../config/config" ]; then
    source "$dir/../config/config"
else
    echo "Error: Cannot find config file ($dir/../config/config)" >&2
    exit 1
fi

SOLRURL="$solrUrl$solrCore"

delete_by_source() {
    local file="$1"

    if [ ! -f "$file" ]; then
        echo "Error: '$file' is not a file" >&2
        return 1
    fi

    # Extract the source value (epustalog filename) from the first line of the solrImport file
    local source_value
    source_value=$(grep -oP '"source":\K[^,\}]+' "$file" | head -1 | tr -d '"' | tr -d ' ')

    if [ -z "$source_value" ]; then
        echo "Error: Could not extract 'source' value from '$file'" >&2
        return 1
    fi

    echo "Deleting Solr entries with source=\"$source_value\"..."

    local response http_code
    response=$(curl -s -w "\n%{http_code}" "$SOLRURL/update" \
        --data "<delete><query>source:\"$source_value\"</query></delete>" \
        -H 'Content-type:text/xml; charset=utf-8')
    http_code=$(echo "$response" | tail -1)

    if [ "$http_code" -ne 200 ]; then
        echo "Error: Solr delete request failed with HTTP $http_code" >&2
        return 1
    fi

    echo "Done: entries for '$source_value' deleted."
}

# Process all files
for file in "$@"; do
    delete_by_source "$file" || exit 1
done

# Commit changes to Solr
echo "Committing changes to Solr..."
response=$(curl -s -w "\n%{http_code}" "$SOLRURL/update" \
    --data '<commit/>' \
    -H 'Content-type:text/xml; charset=utf-8')
http_code=$(echo "$response" | tail -1)

if [ "$http_code" -ne 200 ]; then
    echo "Error: Solr commit failed with HTTP $http_code" >&2
    exit 1
fi

echo "Done."
