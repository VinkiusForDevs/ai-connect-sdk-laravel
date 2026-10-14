FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        unzip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY . /app
WORKDIR /app

RUN composer update --no-interaction --prefer-dist

CMD ["composer", "test"]
