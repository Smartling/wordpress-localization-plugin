FROM pratushnyi/anyphp:ubuntu

ARG WP_INSTALL_DIR="/WP_INSTALL_DIR"
ARG MYSQL_USER="root"
ARG MYSQL_PASS="root"
ARG MYSQL_BASE="wp"
ARG WP_DB_TABLE_PREFIX="wp_"
ARG WP_INSTALLATION_DOMAIN="test.com"
ARG SITES="Spanish Site:es:es,French Site:fr-FR:fr,Russian Site:ru-RU:ru,Ukrainian Site:uk-UA:ua"
ARG ACFPRO_KEY
ARG GITHUB_OAUTH_TOKEN

# if not set latest is used. Value should be like "4.9.8"
ARG WP_VERSION=latest

# if not set latest is used. Value should be like "5.7.10" (before internal refactoring) or "5.7.12"
ARG ACF_PRO_VERSION=latest

ARG PHP_version=5.6

ENV WP_INSTALL_DIR=${WP_INSTALL_DIR}
ENV MYSQL_USER=${MYSQL_USER}
ENV MYSQL_PASS=${MYSQL_PASS}
ENV MYSQL_BASE=${MYSQL_BASE}
ENV WP_DB_TABLE_PREFIX=${WP_DB_TABLE_PREFIX}
ENV WP_INSTALLATION_DOMAIN=${WP_INSTALLATION_DOMAIN}
ENV SITES=${SITES}
ENV DEPENDENCIES="smartling-connector;wordpress-seo"

RUN echo Building image with Wordpress:${WP_VERSION} and ACF Pro plugin:${ACF_PRO_VERSION} and PHP:${PHP_version}

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && \
    apt-get install -y \
        git \
        nano \
        curl \
        zip \
        unzip \
        mysql-server \
        php${PHP_version} \
        php${PHP_version}-mysql \
        php${PHP_version}-cli \
        php${PHP_version}-gd \
        php${PHP_version}-curl \
        php${PHP_version}-zip \
        php${PHP_version}-dom \
        php${PHP_version}-simplexml \
        php${PHP_version}-xml \
        php${PHP_version}-mbstring \
        php${PHP_version}-xdebug && \
        apt-get autoclean -y && \
        apt-get autoremove -y

RUN chown -R mysql:mysql /var/lib/mysql && service mysql start && \
    find /var/lib/mysql -type f -exec touch {} \; && \
    sed -i 's/^bind-address\s*=.*$/bind-address = "0.0.0.0"/' /etc/mysql/my.cnf && \
    sleep 5 && \
    echo "CREATE USER '${MYSQL_USER}'@'%' IDENTIFIED BY '${MYSQL_PASS}';" | mysql && \
    echo "GRANT ALL PRIVILEGES ON *.* TO '${MYSQL_USER}'@'%' WITH GRANT OPTION;" | mysql && \
    echo "CREATE DATABASE ${MYSQL_BASE};" | mysql -u${MYSQL_USER} -p${MYSQL_PASS} && \
    service mysql stop

SHELL ["/bin/bash", "-c"]

# install wpcli
ENV WPCLI="${WP_INSTALL_DIR}/wp-cli.phar --allow-root --path=${WP_INSTALL_DIR}"
ENV COMPOSER_H_DIR="${HOME}/.composer"
ENV WP_PLUGINS_DIR="${WP_INSTALL_DIR}/wp-content/plugins"
ENV PLUGIN_DIR="${WP_PLUGINS_DIR}/smartling-connector"
ENV LOCAL_GIT_DIR="/plugin-dir"

COPY Buildplan/boot.sh /boot.sh
RUN chmod +x /boot.sh

RUN chown -R mysql:mysql /var/lib/mysql && service mysql start && \
    mkdir -p ${WP_INSTALL_DIR} && \
    cd ${WP_INSTALL_DIR} && curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x ./wp-cli.phar && \
    if [ ! -d "${COMPOSER_H_DIR}" ]; then mkdir "${COMPOSER_H_DIR}"; fi; echo "{\"github-oauth\":{\"github.com\":\"$GITHUB_OAUTH_TOKEN\"}}" > "${COMPOSER_H_DIR}/auth.json" && \
    if [ "latest" = "${WP_VERSION}" ]; then ${WPCLI} core download; else ${WPCLI} core download --version="${WP_VERSION}"; fi && \
    ${WPCLI} config create --dbname=${MYSQL_BASE} --dbuser=${MYSQL_USER} --dbpass=${MYSQL_PASS} --dbprefix=${WP_DB_TABLE_PREFIX} && \
    ${WPCLI} core install --url=${WP_INSTALLATION_DOMAIN} --title=Test --admin_user=wp --admin_password=wp --admin_email=test@wp.org --skip-email && \
    ${WPCLI} core multisite-convert && \
    IFS=',' read -a array <<< "${SITES}"; for site in "${array[@]}"; do ${WPCLI} site create --slug="${site##*\:}" --title="${site%%\:*}" --email=test@wp.org; done && \
    IFS=';' read -a array <<< "${DEPENDENCIES}"; for plugin_name in "${array[@]}"; do ${WPCLI} plugin install $plugin_name --activate-network; done && \
    if [ "latest" != "${ACF_PRO_VERSION}" ]; then DLINK="${DLINK}&t=${ACF_PRO_VERSION}"; fi && \
    DLINK="https://connect.advancedcustomfields.com/index.php?a=download&p=pro&k=${ACFPRO_KEY}" && \
    BUILD_FQFN="${WP_INSTALL_DIR}/advanced-custom-fields-pro.zip" && \
    curl ${DLINK} --output ${BUILD_FQFN} && \
    ${WPCLI} plugin install ${BUILD_FQFN} --activate-network && \
    service mysql stop

CMD WP_INSTALL_DIR=${WP_INSTALL_DIR} \
    PLUGIN_DIR=${PLUGIN_DIR} \
    WPCLI=${WPCLI} \
    WP_DB_USER=${MYSQL_USER} \
    WP_DB_PASS=${MYSQL_PASS} \
    WP_DB_NAME=${MYSQL_BASE} \
    WP_DB_TABLE_PREFIX=${WP_DB_TABLE_PREFIX} \
    WP_INSTALLATION_DOMAIN=${WP_INSTALLATION_DOMAIN} \
    SITES=${SITES} \
    LOCAL_GIT_DIR=${LOCAL_GIT_DIR} \
    /boot.sh
