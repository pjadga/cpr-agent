# Builds CPR Agent into a single self-contained binary (FrankenPHP embed).
# Build from the repo root:
#   docker build -t cpr-agent-static -f standalone/static-build.Dockerfile .
#   docker cp $(docker create --name cpr-tmp cpr-agent-static):/go/src/app/dist/frankenphp-linux-x86_64 cpr-agent
#   docker rm cpr-tmp

# The static-builder image has no php/composer, so resolve dependencies in a
# dedicated stage first.
FROM composer:2 AS deps
WORKDIR /app
COPY . .
RUN rm -rf tests/ base/ node_modules/ standalone/bin/ .ddev/ .git/
RUN composer install --ignore-platform-reqs --no-dev -a

FROM dunglas/frankenphp:static-builder-gnu

WORKDIR /go/src/app/dist/app
COPY --from=deps /app .

# Embedded default env: production settings, secrets and paths supplied at
# runtime via real environment variables (they take precedence over .env).
RUN cp standalone/.env.embed .env

WORKDIR /go/src/app/
# Explicit extension list: the auto-detected set omits pdo_sqlite (SQLite
# runtime) and pcntl (Reverb signal handling). ext-curl is deliberately
# ABSENT: the current static-builder's prebuilt libcurl.a references symbols
# (ldap, libssh2, zstd, ngtcp2...) that spc's dependency resolution fails to
# assemble into the final link. Guzzle transparently falls back to the PHP
# stream handler (TLS via openssl), which covers all HTTP this app performs.
# Optional BuildKit secret raises GitHub's API rate limit for source downloads:
#   docker build --secret id=github-token,env=GITHUB_TOKEN ...
RUN --mount=type=secret,id=github-token,required=false \
    if [ -f /run/secrets/github-token ]; then export GITHUB_TOKEN=$(cat /run/secrets/github-token); fi; \
    EMBED=dist/app/ \
    PHP_EXTENSIONS=ctype,dom,fileinfo,filter,iconv,libxml,mbstring,mbregex,phar,zlib,openssl,session,tokenizer,xml,xmlwriter,xmlreader,simplexml,pdo,pdo_sqlite,sqlite3,pcntl,sockets,bcmath,opcache \
    ./build-static.sh
