#!/bin/bash

SCRIPT_NAME=$(basename "$0")
FORMAT="text"

print_help() {
    echo "Usage: $SCRIPT_NAME [OPTIONS]"
    echo ""
    echo "Lists all source values in the Solr index with their document counts."
    echo "The Solr connection is read from the config file."
    echo ""
    echo "Options:"
    echo "  --format FORMAT   Output format: text (default) or json"
    echo "  -h, --help        Show this help message and exit"
}

# Parse options
OPTS=$(getopt -o h --long help,format: -n "$SCRIPT_NAME" -- "$@")
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
        --format)
            FORMAT="$2"
            shift 2
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

if [ "$FORMAT" != "text" ] && [ "$FORMAT" != "json" ]; then
    echo "Error: Invalid format '$FORMAT'. Valid values: text, json" >&2
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

# Query Solr facets for source field
response=$(curl -s -w "\n%{http_code}" \
    "$SOLRURL/select?q=*:*&rows=0&facet=true&facet.field=source&facet.limit=-1&wt=json")
http_code=$(echo "$response" | tail -1)
body=$(echo "$response" | head -n -1)

if [ "$http_code" -ne 200 ]; then
    echo "Error: Solr request failed with HTTP $http_code" >&2
    exit 1
fi

# facet_counts.facet_fields.source is an alternating array: [name, count, name, count, ...]
if [ "$FORMAT" = "json" ]; then
    echo "$body" | grep -oP '"source"\s*:\s*\[\K[^\]]+' \
        | grep -oP '"[^"]+"\s*,\s*\d+' \
        | awk -F',' 'BEGIN{print "["} {
            gsub(/^ +| +$/, "", $1);
            count=$2+0;
            if (NR>1) printf ",\n";
            printf "  {\"source\": %s, \"count\": %d}", $1, count
          } END{print "\n]"}'
else
    echo "$body" | grep -oP '"source"\s*:\s*\[\K[^\]]+' \
        | grep -oP '"[^"]+"\s*,\s*\d+' \
        | awk -F',' '{
            gsub(/^ +| +$/, "", $1);
            gsub(/"/, "", $1);
            count=$2+0;
            printf "%-60s %d\n", $1, count
          }'
fi
