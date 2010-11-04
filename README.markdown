PHPMapper 1.0
==================

This is the list of changes for the 1.0 release series.

PHPMapper 1.0.5
--------------------

* Added PHPMapper_Election class for mapping data like election results.
* Tightened up series support as it's used by the new class.
* Added series options to CSV importer.
* Made method chaining available on more occassions.
* Created vector image file for better map support going forward (more maps coming soon).
* Broke apart the draw() method and modeled PHPMapper to be more abstract so that it can be extended easily.

PHPMapper 1.0.4
--------------------

* Made PNG export size configurable.
* Removed all state images, consolidated into a single, large image.
* Wrote cleanup/alpha methods for image generation.
* Removed "state" coupling in favor of "item".
* Added the ability to load different maps.
* Early series support for the 1.1 release.

PHPMapper 1.0.3
--------------------
* First public release on Github!
* Added all core files and U.S. map images.
* Custom color support.
* Added abstract Import library with its first member: a CSV importer.
