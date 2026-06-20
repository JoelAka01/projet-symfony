# ──────────────────────────────────────────────
# Stage 1 — Installation des dépendances
# ──────────────────────────────────────────────
FROM php:8.3-cli-alpine AS deps
# "AS deps" donne un nom à ce stage pour qu'on puisse y référer depuis le stage suivant

# On installe les libs système nécessaires à la compilation des extensions PHP
# Ces packages ne seront PAS dans l'image finale
RUN apk add --no-cache \
    ca-certificates \
    curl-dev \
    git \
    icu-dev \
    libzip-dev \
    postgresql-dev \
    unzip \
    && docker-php-ext-install \
        curl \
        intl \
        opcache \
        pdo \
        pdo_pgsql \
        zip

# On récupère Composer depuis son image officielle
# C'est plus propre que de l'installer manuellement
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# On copie UNIQUEMENT les fichiers dont Composer a besoin
# Pas tout le code source — si ton code change mais pas composer.lock,
# Docker réutilise le cache de cette couche (plus rapide)
COPY composer.json composer.lock symfony.lock ./

# Installation des dépendances SANS les dépendances de dev (--no-dev)
# --no-scripts : on n'exécute pas les scripts Composer ici (Symfony Flex etc.)
# car le code source n'est pas encore copié
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

# ──────────────────────────────────────────────
# Stage 2 — Image de production finale
# ──────────────────────────────────────────────
FROM php:8.3-cli-alpine AS prod
# On repart d'une image PHP vierge et légère
# Tout ce qui était dans "deps" est laissé derrière

# On réinstalle UNIQUEMENT les libs runtime (pas les headers de dev)
# La différence : postgresql-dev (pour compiler) vs libpq (pour exécuter)
RUN apk add --no-cache \
    ca-certificates \
    icu \
    libzip \
    libpq \
    && docker-php-ext-install \
        curl \
        intl \
        opcache \
        pdo \
        pdo_pgsql \
        zip

# Config PHP (opcache, memory_limit, etc.)
COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

# On copie le vendor/ buildé dans le stage "deps"
# C'est ici que la magie du multi-stage opère
COPY --from=deps /var/www/html/vendor ./vendor

# On copie tout le code source
# Cette couche est invalidée à chaque push — c'est normal
COPY . .

# On s'assure que les dossiers var/ existent avec les bonnes permissions
# www-data est l'utilisateur sous lequel PHP tourne
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var/

# On bascule sur un utilisateur non-root pour la sécurité
USER www-data

EXPOSE 8000

CMD ["sh", "docker/entrypoint.sh"]