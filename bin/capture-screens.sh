#!/usr/bin/env bash
# Capture the theme pages for the case study with headless Chrome.
# Desktop at 1568x743, phone at 390x844 (2x). Raw PNGs go to docs/scratch/,
# which is git-ignored; bin/build-portfolio-assets.py turns them into the
# published images.
set -euo pipefail
cd "$(dirname "$0")/.."

chrome="${CHROME:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"
base="${WP_URL:-http://localhost:${WP_PORT:-8080}}"
mkdir -p docs/scratch

for page in home:/ services:/services/ about:/about/ inventory:/inventory/ contact:/contact/; do
  name=${page%%:*}
  path=${page#*:}
  "$chrome" --headless=new --disable-gpu --hide-scrollbars --force-device-scale-factor=1 \
    --window-size=1568,743 --virtual-time-budget=15000 \
    --screenshot="docs/scratch/$name.png" "$base$path" >/dev/null 2>&1
  echo "captured $name"
done

# Phones: headless Chrome will not lay a window out narrower than about 500px,
# so a 390px window renders at 500 and crops. Frames inside a wider window do
# render at exactly 390px.
cat > docs/scratch/phones.html <<HTML
<!doctype html><html><body style="margin:0;background:#F9F8F4;display:flex;gap:56px;padding:40px 60px">
<iframe src="$base/" style="width:390px;height:844px;border:0"></iframe>
<iframe src="$base/services/" style="width:390px;height:844px;border:0"></iframe>
<iframe src="$base/about/" style="width:390px;height:844px;border:0"></iframe>
</body></html>
HTML
"$chrome" --headless=new --disable-gpu --hide-scrollbars --force-device-scale-factor=2 \
  --window-size=1402,924 --virtual-time-budget=20000 \
  --screenshot="docs/scratch/phones.png" "file://$PWD/docs/scratch/phones.html" >/dev/null 2>&1
echo "captured phones"
