#!/bin/bash

SCRIPT_NAME=$(basename "$0")
FORCE=false

print_help() {
    echo "Usage: $SCRIPT_NAME [OPTIONS] [FILE...]"
    echo ""
    echo "Updates the Solr core with solrImport JSON files."
    echo "Uses listSourcesInCore.sh to check if a source already exists in the index"
    echo "and whether the document count matches. Only reimports when necessary."
    echo "The Solr connection and directories are read from the config file."
    echo ""
    echo "Options:"
    echo "  -f, --force   Always delete and reimport, regardless of current state"
    echo "  -h, --help    Show this help message and exit"
    echo ""
    echo "Arguments:"
    echo "  FILE...       solrImport JSON files or directories to process."
    echo "                If omitted, the directory from config is used."
}

# Parse options
OPTS=$(getopt -o hf --long help,force -n "$SCRIPT_NAME" -- "$@")
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
        -f | --force)
            FORCE=true
            shift
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

# Load config
dir=$(dirname "$0")
if [ -f "$dir/../config/config" ]; then
    source "$dir/../config/config"
else
    echo "Error: Cannot find config file ($dir/../config/config)" >&2
    exit 1
fi

# Fetch all sources with counts from Solr once
SOURCES_JSON=$("$dir/listSourcesInCore.sh" --format json)
if [ $? -ne 0 ]; then
    echo "Error: Could not retrieve sources from Solr" >&2
    exit 1
fi

# Get the document count for a specific source from the cached JSON
get_solr_count() {
    local source="$1"
    echo "$SOURCES_JSON" | grep "\"source\": \"$source\"" | grep -oP '"count":\s*\K[0-9]+'
}

import_file() {
    local file="$1"

    if [ ! -f "$file" ]; then
        echo "Error: '$file' is not a file" >&2
        return 1
    fi

    # Extract source value (epustalog filename) from the solrImport file
    local source_value
    source_value=$(grep -oP '"source":\K[^,\}]+' "$file" | head -1 | tr -d '"' | tr -d ' ')

    if [ -z "$source_value" ]; then
        echo "Warning: Could not extract source from '$file', skipping" >&2
        return 0
    fi

    # Count lines in solrImport file (= expected document count)
    local expected_count
    expected_count=$(wc -l < "$file")

    # Get current count in Solr for this source (empty = not in index)
    local solr_count
    solr_count=$(get_solr_count "$source_value")

    # Decide whether to import
    local reason=""
    if [ "$FORCE" = true ]; then
        reason="force"
    elif [ -z "$solr_count" ]; then
        reason="new source"
    elif [ "$solr_count" -ne "$expected_count" ]; then
        reason="count mismatch: Solr=$solr_count, file=$expected_count"
    else
        echo "Skipping: $file (up to date, count=$solr_count)"
        return 0
    fi

    echo "Processing: $file ($reason)"

    # Delete existing Solr entries if source is already in the index
    if [ -n "$solr_count" ]; then
        "$dir/deleteSolrImportFromCore.sh" "$file" || return 1
    fi

    # Import into Solr
    /opt/solr/bin/post -c "$solrCore" "$file"
    if [ $? -ne 0 ]; then
        echo "Error: Import failed for '$file'" >&2
        return 1
    fi
}

# Determine input paths: from arguments or from config
if [ $# -gt 0 ]; then
    INPUT_PATHS=("$@")
else
    INPUT_PATHS=("$solrImports")
fi

# Process all input paths
for path in "${INPUT_PATHS[@]}"; do
    if [ -d "$path" ]; then
        for file in "$path"/*.json; do
            [ -f "$file" ] || continue
            import_file "$file"
        done
    elif [ -f "$path" ]; then
        import_file "$path"
    else
        echo "Error: '$path' is not a file or directory" >&2
        exit 1
    fi
done
