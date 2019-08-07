ARG PHP_version=7.3
ARG WP_VERSION=latest
FROM pratushnyi/ubuntu-wp-test:php-${PHP_version}-wp-${WP_VERSION}

ARG SITES="Spanish Site:es:es,French Site:fr-FR:fr,Russian Site:ru-RU:ru,Ukrainian Site:uk-UA:ua"
ARG ACFPRO_KEY
ARG GITHUB_OAUTH_TOKEN
ARG ACF_PRO_VERSION=latest

ENV SITES=${SITES}
ENV DEPENDENCIES="smartling-connector;wordpress-seo"
ENV DEBIAN_FRONTEND=noninteractive

SHELL ["/bin/bash", "-c"]

RUN echo Building image with Wordpress:${WP_VERSION} and ACF Pro plugin:${ACF_PRO_VERSION} and PHP:${PHP_version}

ENV COMPOSER_H_DIR="${HOME}/.composer"
ENV PLUGIN_DIR="${WP_PLUGINS_DIR}/smartling-connector"
ENV LOCAL_GIT_DIR="/plugin-dir"

COPY Buildplan/boot.sh /boot.sh
RUN chmod +x /boot.sh

RUN chown -R mysql:mysql /var/lib/mysql && service mysql start && \
    cd ${WP_INSTALL_DIR}
    if [ ! -d "${COMPOSER_H_DIR}" ]; then mkdir "${COMPOSER_H_DIR}"; fi; echo "{\"github-oauth\":{\"github.com\":\"$GITHUB_OAUTH_TOKEN\"}}" > "${COMPOSER_H_DIR}/auth.json" && \
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
