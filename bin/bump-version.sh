#!/bin/bash
#
# Bump the plugin version across all locations that must stay in sync.
#
# Usage: bash bin/bump-version.sh 1.2.0
#
# Locations updated:
#   samplehq-request-form.php   — plugin header (* Version:) and SHQF_VERSION define
#   readme.txt                  — Stable tag:
#   package.json                — "version"
#   composer.json               — "version"
#   phpstan-constants.php       — SHQF_VERSION define
#   tests/bootstrap.php         — SHQF_VERSION define
#   assets/src/js/blocks/form-block/block.json — "version"
#   tests/Unit/PluginTest.php   — version assertion
#

set -euo pipefail

VERSION="${1:-}"

if [ -z "$VERSION" ]; then
	echo "Usage: bash bin/bump-version.sh <version>"
	echo "Example: bash bin/bump-version.sh 1.2.0"
	exit 1
fi

if ! [[ "$VERSION" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]; then
	echo "Error: Version must be in semver format (e.g. 1.2.0), got: $VERSION"
	exit 1
fi

PLUGIN_FILE="samplehq-request-form.php"
README_FILE="readme.txt"
PACKAGE_FILE="package.json"
COMPOSER_FILE="composer.json"
PHPSTAN_FILE="phpstan-constants.php"
BOOTSTRAP_FILE="tests/bootstrap.php"
BLOCK_JSON="assets/src/js/blocks/form-block/block.json"
PLUGIN_TEST="tests/Unit/PluginTest.php"

for file in "$PLUGIN_FILE" "$README_FILE" "$PACKAGE_FILE" "$COMPOSER_FILE" \
            "$PHPSTAN_FILE" "$BOOTSTRAP_FILE" "$BLOCK_JSON" "$PLUGIN_TEST"; do
	if [ ! -f "$file" ]; then
		echo "Error: $file not found. Run this script from the plugin root."
		exit 1
	fi
done

# Plugin header: * Version:           X.Y.Z
sed -i.bak "s/^\( \* Version:[[:space:]]*\)[0-9].*/\1${VERSION}/" "$PLUGIN_FILE"

# SHQF_VERSION define in main plugin file.
sed -i.bak "s/define( 'SHQF_VERSION', '[^']*' )/define( 'SHQF_VERSION', '${VERSION}' )/" "$PLUGIN_FILE"

# readme.txt stable tag.
sed -i.bak "s/^Stable tag:[[:space:]]*.*/Stable tag: ${VERSION}/" "$README_FILE"

# package.json version.
sed -i.bak "s/\"version\": \"[^\"]*\"/\"version\": \"${VERSION}\"/" "$PACKAGE_FILE"

# composer.json version.
sed -i.bak "s/\"version\": \"[^\"]*\"/\"version\": \"${VERSION}\"/" "$COMPOSER_FILE"

# phpstan-constants.php SHQF_VERSION define.
sed -i.bak "s/define( 'SHQF_VERSION', '[^']*' )/define( 'SHQF_VERSION', '${VERSION}' )/" "$PHPSTAN_FILE"

# tests/bootstrap.php SHQF_VERSION define.
sed -i.bak "s/define( 'SHQF_VERSION', '[^']*' )/define( 'SHQF_VERSION', '${VERSION}' )/" "$BOOTSTRAP_FILE"

# block.json version.
sed -i.bak "s/\"version\": \"[^\"]*\"/\"version\": \"${VERSION}\"/" "$BLOCK_JSON"

# PluginTest.php version assertion.
sed -i.bak "s/assertSame( '[^']*', SHQF_VERSION )/assertSame( '${VERSION}', SHQF_VERSION )/" "$PLUGIN_TEST"

# Clean up .bak files.
rm -f "$PLUGIN_FILE.bak" "$README_FILE.bak" "$PACKAGE_FILE.bak" "$COMPOSER_FILE.bak" \
      "$PHPSTAN_FILE.bak" "$BOOTSTRAP_FILE.bak" "$BLOCK_JSON.bak" "$PLUGIN_TEST.bak"

echo "Version bumped to ${VERSION}"
echo ""
echo "  Updated:"
echo "    $PLUGIN_FILE     — header + SHQF_VERSION define"
echo "    $README_FILE     — Stable tag"
echo "    $PACKAGE_FILE    — version"
echo "    $COMPOSER_FILE   — version"
echo "    $PHPSTAN_FILE    — SHQF_VERSION define"
echo "    $BOOTSTRAP_FILE  — SHQF_VERSION define"
echo "    $BLOCK_JSON      — version"
echo "    $PLUGIN_TEST     — version assertion"
echo ""

# Generate and apply changelog.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -x "${SCRIPT_DIR}/generate-changelog.sh" ]; then
	echo "Generating changelog..."
	bash "${SCRIPT_DIR}/generate-changelog.sh" --version "${VERSION}" --apply
	echo ""
fi

echo "  Next steps:"
echo "    1. Review the changelog in readme.txt"
echo "    2. Run: npm install --package-lock-only (sync package-lock.json)"
echo "    3. Commit: git commit -am 'release: v${VERSION}'"
echo "    4. Tag: git tag -a v${VERSION} -m 'Release ${VERSION}'"
echo "    5. Push: git push origin main && git push origin v${VERSION}"
