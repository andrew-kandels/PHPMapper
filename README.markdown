PHPMapper 1.1
==================

This is the list of changes for the 1.1 release series.

PHPMapper 1.1.0
--------------------
* Renamed project to PHPMapper from PHPStateMapper.
* Added world map and set as the default map.
* Broke the map's data areas and image processing into separate libraries in PHPMapper/Map.
* Added PHPUnit tests to cover the core libraries.
* Added PDO importer to load data from databases.
* Added GeoIP support for loading data and assigning to a region.
* Moved away from state to country/region for map areas.
* Moved to a fluent interface for all objects to allow method chaining.
* Touched up maps for better display.
* Made libraries more extendable for overloading.
