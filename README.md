# ePuSta-Server

The ePuSta-Server provides usage statistics of electronic publications. It
expects enriched log files in the **epustalogfile** format (produced by
[ePuSta-logfileparser](https://github.com/gbv/ePuSta-logfileparser)), imports
them into a Solr core and serves the aggregated statistics through two
separate HTTP APIs.

## Getting Started

### Prerequisites
* Linux
* Solr 7
* PHP 7.4+
* `curl`, `bash`

### Installation

* Clone this repository:

  ```
  git clone https://github.com/gbv/ePuSta-Server.git
  ```

* Create a core in Solr and copy the files from the `solr/` directory into the
  core's `conf/` directory.
* Copy the config template and adjust the values:

  ```
  cp config/config.template config/config
  ```

  Relevant variables (consumed by the shell scripts in `bin/`):

  | Variable | Purpose |
  |---|---|
  | `solrUrl` | Base URL of the Solr server (e.g. `http://localhost:8983/solr/`) |
  | `solrCore` | Name of the Solr core |
  | `epustaLogs` | Directory containing the `*.epusta.log[.gz]` files |
  | `solrImports` | Directory where Solr import JSON files are written |
  | `epustaServerBin` | Path to this project's `bin/` directory |

## Working with the Core

The `bin/` directory contains everything needed to fill and maintain the Solr
core. Each script supports `-h`/`--help`.

### Typical pipeline

```
epustalogfile (*.epusta.log[.gz])
        │
        │  createSolrImport_all.sh
        ▼
Solr import JSON (*.json in $solrImports)
        │
        │  import_all.sh
        ▼
Solr core
```

### Scripts

| Script | Purpose |
|---|---|
| `createSolrImport.php` | Transforms a single `*.epusta.log` file into a Solr import JSON file. |
| `createSolrImport_all.sh` | Runs `createSolrImport.php` for **all** epustalogfiles below `$epustaLogs`. Supports `.log` and `.log.gz`, skips files where the target is already up to date (`-f/--force` to overwrite). |
| `import.sh` | Legacy one-shot import of `solrImport.json` via `/opt/solr/bin/post`. |
| `import_all.sh` | Batch-imports all Solr import JSON files in `$solrImports`. Uses `listSourcesInCore.sh` to compare the document count per source in Solr against the line count in the file and only reimports when the count differs or the source is new (`-f/--force` to reimport unconditionally). |
| `import_allMissed.php` | Older helper that imports only files not yet present in the core (no count check). |
| `listSourcesInCore.sh` | Lists all `source` values currently in the Solr core with their document counts (`--format text|json`). |
| `deleteSolrImportFromCore.sh` | Deletes all Solr documents whose `source` field matches a given Solr import JSON file. Used internally by `import_all.sh`. |
| `deleteSolrCore.sh` | Wipes the whole Solr core (`<delete><query>*:*</query></delete>`). |

### Creating Solr imports

Example: create the import file `reposas-2019-12-01.json` from a single log
file.

```
bin/createSolrImport.php --file=reposas.2019-12-01.log --level=PROD \
    > reposas.2019-12-01.json
```

`--level`:
- `DEBUG` – transform all log lines
- `PROD` – transform only log lines with a publication identifier

Run the same for the complete configured log directory:

```
bin/createSolrImport_all.sh
```

### Importing into Solr

Manual import of a single file:

```
/opt/solr/bin/post -c $solrCore reposas.2019-12-01.json
```

Full batch import of everything below `$solrImports`, only reimporting what is
missing or has a count mismatch:

```
bin/import_all.sh
```

### Operating the core

```
bin/listSourcesInCore.sh                 # list sources + counts
bin/listSourcesInCore.sh --format json   # same, JSON for scripting
bin/deleteSolrImportFromCore.sh file.json    # delete one source
bin/deleteSolrCore.sh                    # wipe the whole core
```

## HTTP APIs

The ePuSta-Server ships **two separate HTTP APIs**. Both read from the same
Solr core but address different use cases and have different code bases and
contracts. They can be deployed side by side.

### OAS-compatible API (`oas-api/`)

The original, lightweight endpoint that returns
[OpenAccess-Statistik (OAS)](https://wiki.dnb.de/) compatible reports. It is
optimised for drop-in replacement of an OAS provider.

* Single entry point: `oas-api/index.php`
* Query-parameter driven: `do`, `from`, `until`, `granularity`,
  `content` (`counter`, `counter_abstract`, `robots`, `robots_abstract`),
  `identifier`, `summarized`, `addemptyrecords`, `jsonheader`,
  `informational`, `format`.
* Output: JSON (OAS report structure).
* Dependencies: plain PHP, no external framework required.
* Configuration: `oas-api/config.template.php` → `oas-api/config.php`.

Used by existing consumers that already speak OAS.

### New REST API (`rest-api/`)

A newer, OpenAPI-first REST API built on top of the Slim framework. It
replaces the ad-hoc parameter interface of the OAS API with a
documented, versioned contract and is the API that
[ePuSta-Elements](https://github.com/PaulBorchert/ePuSta_elements) targets.

* Entry point: `rest-api/index.php`
* OpenAPI description: `rest-api/Epusta-1.0.x.openapi.yaml.template`
  (rendered with the current base path via `rest-api/getOpenapiYaml.php`).
* Resource handlers live in `src/`:
  `documents.php`, `statistics.php`, `tags.php`, `loglines.php`,
  `swagger-ui.php`, `solr.php`.
* Request/response validation against the OpenAPI spec via
  `League\OpenAPIValidation`.
* Interactive documentation through a mounted Swagger-UI.
* Configuration: `config/config.template` / `config/config.php` (shared with
  the rest of the project), plus `restApiDomain` and `restApiBasePath` for
  the public URL rendered into the OpenAPI document.

New integrations and the upcoming web frontend target this API.
