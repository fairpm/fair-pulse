FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        composer \
        curl \
        git \
        gh \
        jq \
        php-cli \
        unzip \
        zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

COPY . .

ENV GITHUB_WORKSPACE=/app

CMD ["php", "src/actions/PublishFairAction.php"]
