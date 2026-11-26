#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
version=$(sed -n 's/^ \* Version: *//p' plugin/empedia-ai-connector.php | head -1)
stable=$(sed -n 's/^Stable tag: *//p' plugin/readme.txt | head -1)
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ && "$version" == "$stable" ]] || { echo 'Plugin version and stable tag must match.' >&2; exit 1; }
if [[ -n "${RELEASE_TAG:-}" ]]; then
  [[ "$RELEASE_TAG" == "v$version" ]] || { echo 'Release tag does not match plugin version.' >&2; exit 1; }
fi
mkdir -p build/empedia-ai-connector
cp plugin/empedia-ai-connector.php plugin/readme.txt plugin/LICENSE build/empedia-ai-connector/
cp -R plugin/includes build/empedia-ai-connector/
find build/empedia-ai-connector -name '*.php' -exec php -l {} \;
(cd build && zip -qr empedia-ai-connector.zip empedia-ai-connector)
