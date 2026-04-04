Coverage and Reports
====================

The repository includes dedicated GitHub workflows for testing and reporting.

Primary workflow links
----------------------

- `Tests workflow <https://github.com/php-fast-forward/fork/actions/workflows/tests.yml>`_
- `Reports workflow <https://github.com/php-fast-forward/fork/actions/workflows/reports.yml>`_
- `Wiki workflow <https://github.com/php-fast-forward/fork/actions/workflows/wiki.yml>`_

What these workflows are for
----------------------------

- The tests workflow runs the shared Fast Forward test suite pipeline.
- The reports workflow is intended to publish project reports and generated artifacts.
- The wiki workflow updates documentation-related materials maintained through the Fast Forward tooling chain.

How to use them
---------------

- Use the tests workflow to confirm the current branch health.
- Use the reports workflow when you want the latest generated reporting artifacts for the repository.
- Use local commands from the README when iterating on code before pushing changes.

Reference
---------

The workflow files live in:

- ``.github/workflows/tests.yml``
- ``.github/workflows/reports.yml``
- ``.github/workflows/wiki.yml``
