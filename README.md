# ePuSta-Server

The ePuSta-Server provides usage statistics of electronic publications. It
expects enriched log files in the **epustalogfile** format (produced by
[ePuSta-logfileparser](https://github.com/gbv/ePuSta-logfileparser)), imports
them into a Solr core and serves the aggregated statistics through two
separate HTTP APIs.

## Role in the ePuSta ecosystem

This project's scope is the **Solr core and the HTTP APIs** on top of it.
The shell helpers in `bin/` are deliberately **single-file** helpers: each
one operates on one log / one Solr import file / one source in the core.

Mass processing (iterating over directories, cron integration, bulk
reimport) belongs in [ePuSta_tools](https://github.com/gbv/ePuSta_tools/).

| Concern | Home project |
|---|---|
| Single-file parse / enrich / filter | [ePuSta-logfileparser](https://github.com/gbv/ePuSta-logfileparser/) |
| Single-file import / single-source operations + HTTP APIs | this project |
| Mass / batch processing, cron, orchestration | [ePuSta_tools](https://github.com/gbv/ePuSta_tools/) |

## Getting Started

### Prerequisites
* Linux
* Solr 8+
* PHP 7.4+ with `curl` extension
* `bash`

### Installation

**1. Clone this repository**

```bash
git clone https://github.com/gbv/ePuSta-Server.git
```

**2. Deploy the Solr configset** *(once per Solr server — skip if `configset_v1` is already present)*

The directory `solr/configset_v1/` contains the schema and configuration for
the Solr core. Copy it into Solr's configsets directory. The typical path is
`/var/solr/data/configsets/`:

```bash
mkdir -p /var/solr/data/configsets/configset_v1/conf
cp solr/configset_v1/* /var/solr/data/configsets/configset_v1/conf/
```

**3. Create the config file**

```bash
cp config/config.template config/config
```

Edit `config/config` and fill in the values:

| Variable | Purpose |
|---|---|
| `solrUrl` | Base URL of the Solr server (e.g. `http://localhost:8983/solr/`) |
| `solrCore` | Name of the Solr core to create |
| `epustaLogs` | Directory containing the `*.epusta.log[.gz]` files |
| `solrImports` | Directory where Solr import JSON files are written |
| `epustaServerBin` | Path to this project's `bin/` directory |

**4. Create the Solr core**

```bash
bin/createSolrCore.php
```

The script checks whether the core already exists and creates it using the
`configset_v1` configset. Schema and Solr configuration are applied
automatically from the configset.

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

Example: create the import file `access-2019-12-01.json` from a single log
file.

```
bin/createSolrImport.php --file=access-2019-12-01.epusta.log --level=PROD \
    > access.2019-12-01.json
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
/opt/solr/bin/post -c $solrCore access.2019-12-01.json
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
OpenAccess-Statistik (OAS) compatible reports. It is
optimised for drop-in replacement of an OAS provider.

* Single entry point: `oas-api/index.php`
* Query-parameter driven: `do`, `from`, `until`, `granularity`,
  `content` (`counter`, `counter_abstract`, `robots`, `robots_abstract`),
  `identifier`, `summarized`, `addemptyrecords`, `jsonheader`,
  `informational`, `format`.
* Output: JSON (OAS report structure).
* Configuration: `oas-api/config.template.php` → `oas-api/config.php`.

### ePuSta REST API (`rest-api/`)

A newer, OpenAPI-first REST API built on top of the Slim framework. It
replaces the ad-hoc parameter interface of the OAS API with a
documented, versioned contract and is the API that
[ePuSta-Elements](https://github.com/PaulBorchert/ePuSta_elements) targets.

* Entry point: `rest-api/index.php`
* OpenAPI description: `rest-api/Epusta-1.0.x.openapi.yaml`
* Interactive documentation through a mounted Swagger-UI.
* Configuration: `config/config.template` / `config/config.php` (shared with
  the rest of the project), plus `restApiDomain` and `restApiBasePath` for
  the public URL rendered into the OpenAPI document.

New integrations and the upcoming web frontend target this API.
