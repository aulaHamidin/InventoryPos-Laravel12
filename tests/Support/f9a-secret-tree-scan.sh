#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "${1:-$(dirname "$0")/../..}" && pwd -P)"
scan_root="$(mktemp -d /tmp/f9a-gitleaks-tree.XXXXXX)"

cleanup() {
    case "$scan_root" in
        /tmp/f9a-gitleaks-tree.*) rm -rf -- "$scan_root" ;;
        *) echo "Refusing unsafe temporary cleanup: $scan_root" >&2 ;;
    esac
}
trap cleanup EXIT

mkdir -p "$repo_root/storage/framework/testing"
git -C "$repo_root" ls-files --cached --others --exclude-standard -z \
    | tar --null -C "$repo_root" -T - -cf - \
    | tar -C "$scan_root" -xf -

docker run --rm --user "$(id -u):$(id -g)" \
    -v "$scan_root:/scan:ro" \
    -v "$repo_root:/output" \
    ghcr.io/gitleaks/gitleaks:v8.29.0@sha256:71d3ee5990f2176f763b438298453fc37e87b119122045e176ca9d44ff00b08b \
    dir /scan --redact --verbose --report-format=json \
    --report-path=/output/storage/framework/testing/f9a-gitleaks-tree.json
