# EMPEDIA AI Connector for WordPress

Connect WordPress AI Client compatible plugins to EMPEDIA models for text, image input, embeddings and speech. Requires WordPress 7.0+, PHP 7.4+ and an EMPEDIA API key with access to the desired models. See [the plugin readme](plugin/readme.txt) for service, pricing and privacy information. The WordPress AI Client capabilities available depend on the installed WordPress version.

This repository is the public distribution and release repository. The plugin is developed in the private AuditCollector repository under `wordpress/empedia-ai-connector`; changes must be synchronized from that source before releasing.

## Build and checks

Run `bash tools/build.sh` to create `build/empedia-ai-connector.zip`. The ZIP contains only plugin runtime files. GitHub Actions runs WordPress coding standards, PHP 7.4 compatibility, WordPress Plugin Check, SDK contract tests for text, image input and speech against WordPress 7.0 and 7.1, and retains the ZIP as a workflow artifact. `.wordpress-org/` contains directory assets, not plugin runtime files.

## WordPress.org release

1. Submit the plugin ZIP to WordPress.org and wait for acceptance. Confirm the assigned slug is `empedia-ai-connector`.
2. Configure the repository variable `WPORG_APPROVED` to `true`, set the Actions secrets `WPORG_SVN_USERNAME` and `WPORG_SVN_PASSWORD` to the WordPress.org SVN credentials, and configure the `wordpress-org` environment if approvals are desired.
3. Update the plugin header version and `Stable tag` in `plugin/readme.txt` to match, push the reviewed source to `main`, then create a matching Git tag, e.g. `v0.1.0`.
4. The CI workflow builds and checks the plugin. After every check passes, the deploy job publishes the tag and directory assets. It can be manually rerun on the tag if needed. Do not enable it before WordPress.org acceptance.

The deploy job depends on all CI checks and uses the `wordpress-org` environment. No WordPress.org submission or publication is performed when this repository is created.
