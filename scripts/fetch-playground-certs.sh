#!/bin/sh
# Downloads the shared test certificates of the EET 2.0 playground to _cert/.
#
# The certificates are published by the Financial Administration for all testers. The .p12 files
# use a legacy cipher, so they are also converted to PEM, which works with every OpenSSL version.
# Requires curl, unzip and openssl 3 (the -legacy option).
set -eu

URL='https://eet.gov.cz/assets/cs/cmsmedia/pro-vyvojare/CAEET_Playground_2026_v1.zip'
TARGET="$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)/_cert"

umask 077
mkdir -p "$TARGET"
curl --fail --silent --show-error --location --max-time 60 --output "$TARGET/playground.zip" "$URL"
unzip -o -q "$TARGET/playground.zip" -d "$TARGET"
chmod 600 "$TARGET"/*
rm -f "$TARGET/playground.zip"

for p12 in "$TARGET"/CA_EET-Playground-*.p12; do
    eic="$(basename "$p12" .p12)"
    eic="${eic#CA_EET-Playground-}"
    openssl pkcs12 -legacy -in "$p12" -passin "file:$TARGET/password_pokladni_cert_playground.txt" \
        -nocerts -nodes -out "$TARGET/$eic.key" 2>/dev/null
    openssl pkcs12 -legacy -in "$p12" -passin "file:$TARGET/password_pokladni_cert_playground.txt" \
        -nokeys -clcerts -out "$TARGET/$eic.pem" 2>/dev/null
    echo "$eic: $(openssl x509 -in "$TARGET/$eic.pem" -noout -subject -enddate | tr '\n' ' ')"
done
