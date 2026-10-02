# Browser-test targets for this module, sourced by the shared runner
# (~/Sites/0_ss-mods-maintenance/tools/browser/run.sh) and by .github/workflows/browser-tests.yml.
# Plain bash assignments only. CI tests only the targets with an empty SS<n>_SRC_REF (= this
# checkout): on this branch that is both.
# Ports are assigned in ~/Sites/0_ss-mods-maintenance/tools/browser/PORTS.md; take new ones there.

BROWSER_PACKAGE="restruct/silverstripe-latlongfield"
BROWSER_TARGETS="ss5 ss6"

# This branch (main, 2.x) requires framework ^5 || ^6, so it serves BOTH majors (empty ref = the
# checkout the runner was given). The v1 line (1.x, framework ^4 || ^5) is the only line serving
# Silverstripe 4; it has no browser target yet (no ss4 port is assigned in PORTS.md).
SS5_RECIPE="^5"
SS5_PHP="8.3"
SS5_PORT="8887"
SS5_SRC_REF=""

SS6_RECIPE="^6"
SS6_PHP="8.3"
SS6_PORT="8888"
SS6_SRC_REF=""
