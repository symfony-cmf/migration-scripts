Migration Scripts
=================

Repository of migration scripts related to the Symfony CMF.
The migration scripts are stored in the ``src`` directory.

Please copy and adapt any of the migration scripts into your
own project and register them in a Bundle as a service. See
here for documentation:
http://symfony.com/doc/master/cmf/bundles/phpcr_odm.html#migration-loading

List of available scripts
~~~~~~~~~~~~~~~~~~~~~~~~~

* media_bundle_mixin_types.php
  * related to https://github.com/symfony-cmf/MediaBundle/pull/63
  * adds custom mixin types for PHPCR models in the MediaBundle