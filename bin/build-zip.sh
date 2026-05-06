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

echo "Building ${PLUGIN_SLUG} distribution zip..."

# Clean previous build.
rm -rf "${BUILD_DIR}"
mkdir -p "${DIST_DIR}"

# Install production PHP dependencies.
composer install --no-dev --no-progress --prefer-dist --optimize-autoloader --quiet

# Build JS/CSS assets.
npm ci --silent
npm run build

# Copy plugin files, excluding dev-only items.
rsync -rc --exclude-from=.distignore . "${DIST_DIR}/"

# Create the zip.
cd "${BUILD_DIR}"
zip -rq "${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}/"
cd ..

# Restore dev dependencies.
composer install --no-progress --prefer-dist --quiet

ZIP_SIZE=$(du -h "${BUILD_DIR}/${PLUGIN_SLUG}.zip" | cut -f1)
echo "Done: ${BUILD_DIR}/${PLUGIN_SLUG}.zip (${ZIP_SIZE})"
