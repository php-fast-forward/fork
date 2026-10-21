# fast-forward-fork maintenance

This repository owns `fast-forward/fork`. Read Composer metadata, source and
tests before changing public integration. Preserve unrelated work. Run
`composer install`, `composer validate --strict` and `composer dev-tools`.
Keep merge, release and deployment decisions with the maintainer. Ordinary
source/resources share this root contract; consumer procedures live in SKILL.md.

## Bundled consumer skill

Read [the consumer skill](resources/skills/fast-forward-fork/SKILL.md) when changing application integration
examples, consumer guides or the skill. Keep its version contract, references
and executable examples aligned with the supported public API. The canonical
distributable source is `resources/skills`; it needs no installed copy or host
alias. Repository maintenance continues to follow this AGENTS.md.
