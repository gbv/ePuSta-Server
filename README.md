# ReposAS-Server

The ReposAS-Server provide AccessStatistics. Required is Logfile in reposas logformat. The statistics curently only accessed by an OAS compatible API. More APIs and a Webinterface comming soon. 

## Getting Started

### Prerequisites
* Linux 
* Solr 7

### Installation

Create a Core in Solr and copy the Files, located in the *solr* directory, in the *conf* directory of the core.

Edit the files deleteSolrCore.sh import.sh to set the SolrURL and core name.

To use the OAS compatible API see.

## Work with the Core

Scripts to work with the core are located in the directory *bin*.

### Import Data

Create the import file.
```
cat reposas.log | ./createSolrImport.php > reposas.import.json
```

Push the date to the solr core.
```
/opt/solr/bin/post -c $SOLRCORE reposas.import.json
```
