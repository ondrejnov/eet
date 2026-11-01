Unit tests, no network:

    vendor/bin/tester -C tests

Including real communication with the EET playground:

    sh scripts/fetch-playground-certs.sh
    EET_PLAYGROUND=1 vendor/bin/tester -C tests

The extensions curl, dom and openssl are required. Without them, run the tests in a container:

    docker run --rm -e EET_PLAYGROUND=1 -v "$PWD":/app -w /app php:8.3-cli vendor/bin/tester -C tests
