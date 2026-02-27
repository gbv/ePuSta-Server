#!/bin/bash

SCRIPT_NAME=$(basename "$0")

print_help() {
    echo "Usage: $SCRIPT_NAME [OPTIONS] [FILE...]"
    echo ""
    echo "Uses createSolrImport.php for mass creation of Solr JSON import files."
    echo "Source and target directories are read from the config file."
    echo "By default, only files where the source is newer than the target are processed."
    echo ""
    echo "Options:"
    echo "  -f, --force   Overwrite target files even if they are not outdated"
    echo "  -h, --help    Show this help message and exit"
    echo ""
    echo "Arguments:"
    echo "  FILE...       Log files or directories to process."
    echo "                If omitted, the directory from config is used."
}

# Parse options (supports both short and long options)
FORCE=false
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

# Process a single log file
process_file() {
    local src="$1"
    local is_gz=false
    local base_name

    if [[ "$src" == *.log.gz ]]; then
        is_gz=true
        base_name=$(basename "${src%.log.gz}")
    elif [[ "$src" == *.log ]]; then
        base_name=$(basename "${src%.log}")
    else
        echo "Warning: Skipping '$src' - unsupported file extension" >&2
        return
    fi

    local destfile="$solrImports/$base_name.json"

    # Skip if target exists and is up to date (unless --force)
    if [ -f "$destfile" ] && [ "$FORCE" = false ]; then
        if [ ! "$src" -nt "$destfile" ]; then
            echo "Skipping: $src (up to date)"
            return
        fi
    fi

    echo "Processing: $src -> $destfile"

    # Decompress if needed
    local src_for_php
    if [ "$is_gz" = true ]; then
        gzip -d "$src"
        src_for_php="${src%.gz}"
    else
        src_for_php="$src"
    fi

    # Run createSolrImport.php
    "$epustaServerBin/createSolrImport.php" --file="$src_for_php" --level=PROD > "$destfile"
    local exit_code=$?

    # Recompress if source was gzip
    if [ "$is_gz" = true ]; then
        gzip "$src_for_php"
    fi

    if [ $exit_code -ne 0 ]; then
        echo "Error: createSolrImport.php failed for '$src'" >&2
    fi
}

# Check if a path is within the epustaLogs directory
check_epustaLogs_path() {
    local path="$1"
    local real_path real_epustaLogs
    real_path=$(realpath -m "$path")
    real_epustaLogs=$(realpath -m "$epustaLogs")
    if [[ "$real_path" != "$real_epustaLogs" && "$real_path" != "$real_epustaLogs/"* ]]; then
        echo "Error: '$path' is not within the epustaLogs directory ($epustaLogs)" >&2
        return 1
    fi
}

# Determine input paths: from arguments or from config
EXPLICIT_PATHS=false
if [ $# -gt 0 ]; then
    INPUT_PATHS=("$@")
    EXPLICIT_PATHS=true
else
    INPUT_PATHS=("$epustaLogs")
fi

# Process all input paths
for path in "${INPUT_PATHS[@]}"; do
    if [ -d "$path" ]; then
        if [ "$EXPLICIT_PATHS" = true ]; then
            check_epustaLogs_path "$path" || exit 1
        fi
        for file in "$path"/*; do
            [ -f "$file" ] || continue
            process_file "$file"
        done
    elif [ -f "$path" ]; then
        if [ "$EXPLICIT_PATHS" = true ]; then
            check_epustaLogs_path "$path" || exit 1
        fi
        process_file "$path"
    else
        echo "Error: '$path' is not a file or directory" >&2
        exit 1
    fi
done
