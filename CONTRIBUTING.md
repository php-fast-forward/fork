# Contributing

Include one unique `.changelog/*.md` fragment for each observable change. Use
PHP 8.5+ and [CPX](https://github.com/laravel/cpx) to run the isolated tooling;
the library itself does not gain a runtime dependency:

```sh
cpx --skip-local fast-forward/changelog add "Describe the change." --category=fixed --type=patch --no-interaction
cpx --skip-local fast-forward/changelog check --no-interaction
# Compare committed changes with the contribution base:
cpx --skip-local fast-forward/changelog check --since=origin/main --no-interaction
```

Select the category and semantic impact that describe the change. Keep published
history intact; pending notes belong in fragments rather than an `Unreleased`
section.

Run `composer install` to restore the project skill into
`.agents/skills/fast-forward-changelog` through the development-only `llm/skills`
plugin and the reviewed GitHub source in `skills.json`. Generated skill files
are ignored by Git. Use `composer skills:show` to inspect the source and
`composer skills:update --from=github` for an explicit refresh within its
configured version range. Review `skills.json` before changing that range.

The [workflow](.github/workflows/changelog.yml) follows the maintainer-selected
`v0` release channel and validates PR notes,
maintains PR feedback and prepares one version PR on `changelog/version`.
Review that PR's version, history and consumed fragments before merging.
After that approved version PR is merged into `main`, automatic publication
creates its version tag and GitHub Release. The workflow never merges PRs.

Historical notes were imported from GitHub Releases into
[`CHANGELOG.md`](CHANGELOG.md), preserving the published descriptions and adding
version links. New consolidation also reads GitHub Releases. Empty release bodies
still need editorial review; no PR attribution is inferred from prose alone.
