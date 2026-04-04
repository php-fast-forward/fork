Dependencies
============

Runtime dependencies
--------------------

Direct runtime dependencies are intentionally small:

.. list-table::
   :header-rows: 1

   * - Dependency
     - Category
     - Notes
   * - ``php ^8.3``
     - Platform
     - Required runtime version
   * - ``psr/log ^3.0``
     - Composer package
     - Optional logger integration through ``LoggerInterface``

Runtime capability dependencies
-------------------------------

The package also depends on runtime capabilities rather than Composer packages:

- ``pcntl_async_signals``
- ``pcntl_fork``
- ``pcntl_signal``
- ``pcntl_waitpid``
- ``posix_getpid``
- ``posix_kill``
- ``stream_socket_pair``
- ``stream_select``

Development dependencies
------------------------

Direct development dependency:

.. list-table::
   :header-rows: 1

   * - Dependency
     - Role
   * - ``fast-forward/dev-tools``
     - Aggregates development tooling, CI support, static analysis, formatting, and documentation helpers

Indirect development dependencies
---------------------------------

The current lock file includes the following indirect development packages.
They are installed through development tooling rather than required by the
runtime library itself.

Amp ecosystem
^^^^^^^^^^^^^

- ``amphp/amp``
- ``amphp/byte-stream``
- ``amphp/cache``
- ``amphp/dns``
- ``amphp/parallel``
- ``amphp/parser``
- ``amphp/pipeline``
- ``amphp/process``
- ``amphp/serialization``
- ``amphp/socket``
- ``amphp/sync``

Composer ecosystem
^^^^^^^^^^^^^^^^^^

- ``composer/ca-bundle``
- ``composer/class-map-generator``
- ``composer/composer``
- ``composer/metadata-minifier``
- ``composer/pcre``
- ``composer/semver``
- ``composer/spdx-licenses``
- ``composer/xdebug-handler``

Doctrine and related utilities
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

- ``doctrine/collections``
- ``doctrine/deprecations``
- ``doctrine/instantiator``
- ``myclabs/deep-copy``
- ``webmozart/assert``

Ergebnis and quality tooling
^^^^^^^^^^^^^^^^^^^^^^^^^^^^

- ``ergebnis/composer-normalize``
- ``ergebnis/json``
- ``ergebnis/json-normalizer``
- ``ergebnis/json-pointer``
- ``ergebnis/json-printer``
- ``ergebnis/json-schema-validator``
- ``ergebnis/rector-rules``

Fast Forward ecosystem
^^^^^^^^^^^^^^^^^^^^^^

- ``fast-forward/dev-tools``
- ``fast-forward/phpdoc-bootstrap-template``

PHP documentation and reflection
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

- ``phpdocumentor/reflection-common``
- ``phpdocumentor/reflection-docblock``
- ``phpdocumentor/shim``
- ``phpdocumentor/type-resolver``
- ``phpstan/phpdoc-parser``
- ``saggre/phpdocumentor-markdown``

Testing, static analysis, and refactoring
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

- ``dg/bypass-finals``
- ``esi/phpunit-coverage-check``
- ``friendsofphp/php-cs-fixer``
- ``nikic/php-parser``
- ``phpspec/prophecy``
- ``phpspec/prophecy-phpunit``
- ``phpstan/phpstan``
- ``phpunit/php-code-coverage``
- ``phpunit/php-file-iterator``
- ``phpunit/php-invoker``
- ``phpunit/php-text-template``
- ``phpunit/php-timer``
- ``phpunit/phpunit``
- ``rector/rector``
- ``staabm/side-effects-detector``
- ``symplify/easy-coding-standard``
- ``theseer/tokenizer``

Symfony ecosystem
^^^^^^^^^^^^^^^^^

- ``symfony/cache``
- ``symfony/cache-contracts``
- ``symfony/config``
- ``symfony/console``
- ``symfony/dependency-injection``
- ``symfony/deprecation-contracts``
- ``symfony/dotenv``
- ``symfony/event-dispatcher``
- ``symfony/event-dispatcher-contracts``
- ``symfony/expression-language``
- ``symfony/filesystem``
- ``symfony/finder``
- ``symfony/options-resolver``
- ``symfony/polyfill-ctype``
- ``symfony/polyfill-intl-grapheme``
- ``symfony/polyfill-intl-normalizer``
- ``symfony/polyfill-mbstring``
- ``symfony/polyfill-php73``
- ``symfony/polyfill-php80``
- ``symfony/polyfill-php81``
- ``symfony/polyfill-php84``
- ``symfony/process``
- ``symfony/service-contracts``
- ``symfony/stopwatch``
- ``symfony/string``
- ``symfony/var-dumper``
- ``symfony/var-exporter``
- ``symfony/yaml``

ReactPHP and event-loop related packages
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

- ``clue/ndjson-react``
- ``evenement/evenement``
- ``react/cache``
- ``react/child-process``
- ``react/dns``
- ``react/event-loop``
- ``react/promise``
- ``react/socket``
- ``react/stream``
- ``revolt/event-loop``

Other locked development packages
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

- ``daverandom/libdns``
- ``fakerphp/faker``
- ``fidry/cpu-core-counter``
- ``gitonomy/gitlib``
- ``jolicode/jolinotif``
- ``jolicode/php-os-helper``
- ``justinrainbow/json-schema``
- ``kelunik/certificate``
- ``laravel/serializable-closure``
- ``league/uri``
- ``league/uri-interfaces``
- ``localheinz/diff``
- ``marc-mabe/php-enum``
- ``monolog/monolog``
- ``ondram/ci-detector``
- ``phar-io/composer-distributor``
- ``phar-io/executor``
- ``phar-io/filesystem``
- ``phar-io/gnupg``
- ``phar-io/manifest``
- ``phar-io/version``
- ``phpro/grumphp``
- ``psr/cache``
- ``psr/container``
- ``psr/event-dispatcher``
- ``psr/http-factory``
- ``psr/http-message``
- ``pyrech/composer-changelogs``
- ``seld/jsonlint``
- ``seld/phar-utils``
- ``seld/signal-handler``
- ``sebastian/cli-parser``
- ``sebastian/comparator``
- ``sebastian/complexity``
- ``sebastian/diff``
- ``sebastian/environment``
- ``sebastian/exporter``
- ``sebastian/global-state``
- ``sebastian/lines-of-code``
- ``sebastian/object-enumerator``
- ``sebastian/object-reflector``
- ``sebastian/recursion-context``
- ``sebastian/type``
- ``sebastian/version``
- ``thecodingmachine/safe``

Optional integration notes
--------------------------

- There are no optional Composer runtime dependencies beyond ``psr/log``.
- PSR-11 integration is application-defined rather than bundled.
- Logger implementations such as Monolog are optional and can be added by the host application.
