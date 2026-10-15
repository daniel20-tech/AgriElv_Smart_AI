#!/bin/sh
# Alternative sans PHP : sh tools/install-libs.sh (nécessite curl)
set -e
cd "$(dirname "$0")/../assets/vendor"
C=https://cdn.jsdelivr.net/npm
mkdir -p bootstrap/css bootstrap/js bootstrap-icons/font/fonts
curl -fsSL $C/bootstrap@5.3.3/dist/css/bootstrap.min.css -o bootstrap/css/bootstrap.min.css
curl -fsSL $C/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css -o bootstrap-icons/font/bootstrap-icons.min.css
curl -fsSL $C/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2 -o bootstrap-icons/font/fonts/bootstrap-icons.woff2
curl -fsSL $C/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff -o bootstrap-icons/font/fonts/bootstrap-icons.woff
echo "Librairies installées en local."
