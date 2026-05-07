#!/bin/bash
#
# Build a production-ready distribution zip of the plugin.
# Usage: bash bin/build-zip.sh
#
# Output: build/samplehq-request-form.zip
#

set -euo pipefail

PLUGIN_SLUG="samplehq-request-form"
BUILD_DIR="build"
DIST_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"

cleanup() {
	echo "Restoring dev dependencies..."
	composer install --no-progress --prefer-dist --quiet 2>/dev/null
}
trap cleanup EXIT

echo "Building ${PLUGIN_SLUG} distribution zip..."

# Clean previous build.
rm -rf "${BUILD_DIR}"
mkdir -p "${DIST_DIR}"

# Install production PHP dependencies.
composer install --no-dev --no-progress --prefer-dist --optimize-autoloader --quiet

# Build JS/CSS assets.
npm ci --silent
npm run build

# Generate .pot file for translations.
if command -v wp &> /dev/null; then
	wp i18n make-pot . languages/${PLUGIN_SLUG}.pot --slug="${PLUGIN_SLUG}" --quiet
	echo "  .pot file generated."
else
	echo "  wp-cli not found — skipping .pot generation (using existing .pot file)."
fi

# Copy plugin files, excluding dev-only items.
rsync -rc --exclude-from=.distignore . "${DIST_DIR}/"

# Create the zip.
cd "${BUILD_DIR}"
zip -rq "${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}/"
cd ..

ZIP_SIZE=$(du -h "${BUILD_DIR}/${PLUGIN_SLUG}.zip" | cut -f1)
echo "Done: ${BUILD_DIR}/${PLUGIN_SLUG}.zip (${ZIP_SIZE})"
