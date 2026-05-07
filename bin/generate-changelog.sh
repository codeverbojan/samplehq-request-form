#!/bin/bash
#
# Generate a changelog from conventional commits since the last tag.
#
# Usage:
#   bash bin/generate-changelog.sh              # preview to stdout
#   bash bin/generate-changelog.sh --apply      # inject into readme.txt
#   bash bin/generate-changelog.sh --version 1.1.0        # override version
#   bash bin/generate-changelog.sh --version 1.1.0 --apply
#
# Reads commits between the most recent git tag and HEAD, groups them by
# conventional-commit prefix, and outputs wp.org readme.txt changelog format.
#
# Commit types mapped to user-facing categories:
#   feat     → Added
#   fix      → Fixed
#   perf     → Improved
#   a11y     → Accessibility
#   security → Security
#   other    → Changed  (refactor, style, etc. — only if user-facing)
#
# Skipped (not user-facing):
#   ci, test, docs, build, chore, Merge, dependabot
#

set -euo pipefail

VERSION=""
APPLY=false

while [[ $# -gt 0 ]]; do
	case "$1" in
		--apply) APPLY=true; shift ;;
		--version) VERSION="$2"; shift 2 ;;
		*) echo "Unknown option: $1"; exit 1 ;;
	esac
done

README_FILE="readme.txt"

if [ ! -f "$README_FILE" ]; then
	echo "Error: $README_FILE not found. Run from the plugin root." >&2
	exit 1
fi

# Determine version from plugin header if not specified.
if [ -z "$VERSION" ]; then
	VERSION=$(grep -oP '^\s*\*\s*Version:\s*\K[0-9]+\.[0-9]+\.[0-9]+' samplehq-request-form.php 2>/dev/null || true)
	if [ -z "$VERSION" ]; then
		echo "Error: Could not detect version. Pass --version X.Y.Z" >&2
		exit 1
	fi
fi

# Find the last tag to diff against.
LAST_TAG=$(git describe --tags --abbrev=0 2>/dev/null || true)

if [ -z "$LAST_TAG" ]; then
	echo "Error: No previous tag found. Cannot generate changelog." >&2
	exit 1
fi

# Collect commits since last tag.
COMMITS=$(git log "${LAST_TAG}..HEAD" --format="%s" --no-merges 2>/dev/null || true)

if [ -z "$COMMITS" ]; then
	echo "No new commits since ${LAST_TAG}." >&2
	exit 0
fi

# Arrays for each category.
declare -a ADDED=()
declare -a FIXED=()
declare -a IMPROVED=()
declare -a SECURITY=()
declare -a CHANGED=()

while IFS= read -r line; do
	# Skip merge commits, dependabot, and empty lines.
	if [[ "$line" =~ ^Merge ]] || [[ "$line" =~ ^build\(deps ]] || [[ -z "$line" ]]; then
		continue
	fi

	# Skip non-user-facing commits.
	SKIP_RE='^(ci|test|tests|docs|build|chore)(\(|:)'
	if [[ "$line" =~ $SKIP_RE ]]; then
		continue
	fi

	# Strip the conventional commit prefix for the bullet text.
	# e.g. "feat: add sample picker" → "Add sample picker"
	# e.g. "fix(forms): broken validation" → "Broken validation"
	CC_RE='^([a-z]+)(\([^)]*\))?:[[:space:]]*(.*)'
	if [[ "$line" =~ $CC_RE ]]; then
		TYPE="${BASH_REMATCH[1]}"
		MSG="${BASH_REMATCH[3]}"
		# Capitalize first letter.
		MSG="$(echo "${MSG:0:1}" | tr '[:lower:]' '[:upper:]')${MSG:1}"
	else
		TYPE="other"
		MSG="$line"
	fi

	case "$TYPE" in
		feat)     ADDED+=("$MSG") ;;
		fix)      FIXED+=("$MSG") ;;
		perf)     IMPROVED+=("$MSG") ;;
		a11y)     IMPROVED+=("$MSG") ;;
		security) SECURITY+=("$MSG") ;;
		refactor|style) CHANGED+=("$MSG") ;;
		*) CHANGED+=("$MSG") ;;
	esac
done <<< "$COMMITS"

# Build the changelog block.
CHANGELOG=""
CHANGELOG+="= ${VERSION} =\n"

if [ ${#ADDED[@]} -gt 0 ]; then
	for item in "${ADDED[@]}"; do
		CHANGELOG+="* ${item}\n"
	done
fi

if [ ${#FIXED[@]} -gt 0 ]; then
	for item in "${FIXED[@]}"; do
		CHANGELOG+="* Fix: ${item}\n"
	done
fi

if [ ${#IMPROVED[@]} -gt 0 ]; then
	for item in "${IMPROVED[@]}"; do
		CHANGELOG+="* Improved: ${item}\n"
	done
fi

if [ ${#SECURITY[@]} -gt 0 ]; then
	for item in "${SECURITY[@]}"; do
		CHANGELOG+="* Security: ${item}\n"
	done
fi

if [ ${#CHANGED[@]} -gt 0 ]; then
	for item in "${CHANGED[@]}"; do
		CHANGELOG+="* ${item}\n"
	done
fi

# Check if we actually have any entries.
LINE_COUNT=$(echo -e "$CHANGELOG" | wc -l)
if [ "$LINE_COUNT" -le 1 ]; then
	echo "No user-facing changes found since ${LAST_TAG}." >&2
	exit 0
fi

# Build upgrade notice — one-line summary of the most important change.
TOTAL_CHANGES=$(( ${#ADDED[@]} + ${#FIXED[@]} + ${#IMPROVED[@]} + ${#SECURITY[@]} + ${#CHANGED[@]} ))

if [ ${#SECURITY[@]} -gt 0 ]; then
	UPGRADE_SUMMARY="Security update: ${SECURITY[0]}"
elif [ ${#FIXED[@]} -gt 0 ] && [ ${#ADDED[@]} -gt 0 ]; then
	UPGRADE_SUMMARY="${#ADDED[@]} new feature(s), ${#FIXED[@]} fix(es)."
elif [ ${#ADDED[@]} -gt 0 ]; then
	UPGRADE_SUMMARY="${ADDED[0]}"
elif [ ${#FIXED[@]} -gt 0 ]; then
	UPGRADE_SUMMARY="${FIXED[0]}"
else
	UPGRADE_SUMMARY="${TOTAL_CHANGES} improvement(s)."
fi

UPGRADE_NOTICE="= ${VERSION} =\n${UPGRADE_SUMMARY}"

echo "Changelog for v${VERSION} (since ${LAST_TAG}):"
echo "---"
echo -e "$CHANGELOG"
echo "---"
echo "Upgrade notice: ${UPGRADE_SUMMARY}"
echo ""

if [ "$APPLY" = true ]; then
	# Write blocks to temp files for safe awk insertion.
	TMPCHANGELOG=$(mktemp)
	TMPUPGRADE=$(mktemp)
	echo -e "$CHANGELOG" > "$TMPCHANGELOG"
	echo -e "$UPGRADE_NOTICE" > "$TMPUPGRADE"

	# Insert changelog right after "== Changelog ==" line.
	awk '
		/^== Changelog ==/ {
			print
			print ""
			while ((getline line < "'"$TMPCHANGELOG"'") > 0) print line
			next
		}
		{ print }
	' "$README_FILE" > "${README_FILE}.tmp" && mv "${README_FILE}.tmp" "$README_FILE"

	# Insert upgrade notice right after "== Upgrade Notice ==" line.
	awk '
		/^== Upgrade Notice ==/ {
			print
			print ""
			while ((getline line < "'"$TMPUPGRADE"'") > 0) print line
			next
		}
		{ print }
	' "$README_FILE" > "${README_FILE}.tmp" && mv "${README_FILE}.tmp" "$README_FILE"

	rm -f "$TMPCHANGELOG" "$TMPUPGRADE"
	echo "Applied changelog and upgrade notice to ${README_FILE}"
else
	echo "(dry run — pass --apply to inject into readme.txt)"
fi
