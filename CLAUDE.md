# ePuSta-Server — Claude Context

Solr-backed server for ePuSta usage statistics. See [README.md](README.md)
for scope, scripts, config and the two HTTP APIs.

## Role in the ePuSta ecosystem

- This project owns the **Solr core** and the **HTTP APIs** on top of it.
- Everything in `bin/` is single-file / single-source tooling.
- Mass processing (directory walks, cron, bulk reimport) belongs in
  [`ePuSta_tools`](https://github.com/gbv/ePuSta_tools/).
- Parsing / enriching / filtering a single log file belongs in
  [`ePuSta-logfileparser`](https://github.com/gbv/ePuSta-logfileparser/).

## Planned move of the batch scripts

`bin/createSolrImport_all.sh`, `bin/import_all.sh` and
`bin/import_allMissed.php` are mass-processing helpers and will be
**moved into `ePuSta_tools`**. Until the move is done they still live
here; afterwards `bin/` will only contain single-file / single-source
tooling.

When adding new functionality, decide by scope:

- operates on exactly one file / one source → stays here
- operates on a whole directory or across repositories → belongs in
  `ePuSta_tools`, not here

## Two HTTP APIs

Be explicit about which API you touch — they are independent code bases:

- **`oas-api/`** — legacy OAS-compatible endpoint. Plain PHP, query-param
  driven (`do`, `from`, `until`, `granularity`, `content`, …). JSON
  output in OAS report structure. Kept for drop-in OAS consumers.
- **`rest-api/`** — newer Slim/OpenAPI REST API. OpenAPI description in
  `rest-api/Epusta-1.0.x.openapi.yaml`, Swagger-UI mounted, this is the
  API that [ePuSta-Elements](https://github.com/PaulBorchert/ePuSta_elements)
  targets and that new integrations should use.

Resource handlers for the REST API live in `src/` (`documents.php`,
`statistics.php`, `tags.php`, `loglines.php`, `swagger-ui.php`, `solr.php`).

## Git workflow

Commits, branches and PR titles are prefixed with the Jira ticket ID, e.g.
`REP-1081 add prefix filter to documentIdentifier facet`.
