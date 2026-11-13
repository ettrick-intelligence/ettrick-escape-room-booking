#!/usr/bin/env bash
# Build a test zip of Escape Room Booking (Lite).
#
#   tools/build.sh
#
# Produces dist/ettrick-escape-room-booking-<version>.zip containing ONLY the files that make up the plugin
# (including readme.txt), under a top folder called ettrick-escape-room-booking/. This plugin is public and
# holds no secrets, so nothing is injected. Publishing to WordPress.org is NOT done here (it is still done
# with SVN); this zip is for installing on a test site.
set -euo pipefail
cd "$(dirname "$0")/.."

# The three version numbers must agree: plugin header, EERB_VERSION, and readme.txt "Stable tag".
header_ver=$(grep -m1 -E '^ \* Version:' escape-room-booking.php | sed -E 's/.*Version:[[:space:]]*//; s/[[:space:]]+$//')
const_ver=$(grep -m1 "define( 'EERB_VERSION'" escape-room-booking.php | sed -E "s/.*,[[:space:]]*'([^']+)'.*/\1/")
stable_ver=$(grep -m1 -E '^Stable tag:' readme.txt | sed -E 's/Stable tag:[[:space:]]*//; s/[[:space:]]+$//')
if [ -z "$header_ver" ] || [ "$header_ver" != "$const_ver" ] || [ "$header_ver" != "$stable_ver" ]; then
  echo "ERROR: version mismatch: header '$header_ver', EERB_VERSION '$const_ver', readme.txt Stable tag '$stable_ver'." >&2
  exit 1
fi

stage=$(mktemp -d)
trap 'rm -rf "$stage"' EXIT
mkdir -p "$stage/ettrick-escape-room-booking" dist
cp -r admin includes languages public escape-room-booking.php readme.txt uninstall.php "$stage/ettrick-escape-room-booking/"

out="dist/ettrick-escape-room-booking-${header_ver}.zip"
rm -f "$out"
(cd "$stage" && zip -qr "$OLDPWD/$out" ettrick-escape-room-booking)
echo "Built $out"
unzip -l "$out" | tail -1
