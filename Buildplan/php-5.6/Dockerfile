FROM ubuntu:14.04

ENV DEBIAN_FRONTEND="noninteractive"

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

ENV WP_INSTALL_DIR=${WP_INSTALL_DIR}
ENV MYSQL_USER=${MYSQL_USER}
ENV MYSQL_PASS=${MYSQL_PASS}
ENV MYSQL_BASE=${MYSQL_BASE}
ENV WP_DB_TABLE_PREFIX=${WP_DB_TABLE_PREFIX}
ENV WP_INSTALLATION_DOMAIN=${WP_INSTALLATION_DOMAIN}
ENV SITES=${SITES}
ENV DEPENDENCIES="smartling-connector;wordpress-seo"
ENV ACFPRO_KEY=${ACFPRO_KEY}
ENV DLINK="https://connect.advancedcustomfields.com/index.php?a=download&p=pro&k=${ACFPRO_KEY}"
ENV GITHUB_OAUTH_TOKEN=${GITHUB_OAUTH_TOKEN}
ENV BUILD_FQFN="${WP_INSTALL_DIR}/advanced-custom-fields-pro.zip"
ENV WP_VERSION=${WP_VERSION}
ENV ACF_PRO_VERSION=${ACF_PRO_VERSION}

RUN echo -e "Building image with Wordpress:${WP_VERSION} and ACF Pro plugin:${ACF_PRO_VERSION}"

# install MySQL
RUN apt-get update && \
    echo "mysql-server mysql-server/root_password password ${MYSQL_PASS}" | debconf-set-selections && \
    echo "mysql-server mysql-server/root_password_again password ${MYSQL_PASS}" | debconf-set-selections && \
    apt-get install mysql-server -y --force-yes && \
    apt-get autoclean -y && \
    apt-get autoremove -y

# creating database
RUN chown -R mysql:mysql /var/lib/mysql && service mysql start && \
    echo "CREATE DATABASE ${MYSQL_BASE};" | mysql -u${MYSQL_USER} -p${MYSQL_PASS}

# install php 5.6
RUN apt-get install software-properties-common -y && \
    add-apt-repository ppa:ondrej/php && \
    apt-get update && \
    apt-get install \
        wget \
        git \
        curl \
        zip \
        mc \
        php5.6 \
        php5.6-cli \
        php5.6-xml \
        php5.6-mysql \
        php5.6-gd \
        php5.6-curl \
        php5.6-mcrypt \
        php5.6-mbstring \
        php5.6-xdebug -y --force-yes && \
        apt-get autoclean -y && apt-get autoremove -y

# install wpcli
RUN mkdir -p ${WP_INSTALL_DIR}
RUN cd ${WP_INSTALL_DIR} && curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x ./wp-cli.phar
ENV WPCLI="${WP_INSTALL_DIR}/wp-cli.phar --allow-root --path=${WP_INSTALL_DIR}"



RUN chown -R mysql:mysql /var/lib/mysql && service mysql start && \
    cd ${WP_INSTALL_DIR} && \
    if [ "latest" = "${WP_VERSION}" ]; then ${WPCLI} core download; else ${WPCLI} core download --version="${WP_VERSION}"; fi && \
    ${WPCLI} config create --dbname=${MYSQL_BASE} --dbuser=${MYSQL_USER} --dbpass=${MYSQL_PASS} --dbprefix=${WP_DB_TABLE_PREFIX} && \
    ${WPCLI} core install --url=${WP_INSTALLATION_DOMAIN} --title=Test --admin_user=wp --admin_password=wp --admin_email=test@wp.org --skip-email && \
    ${WPCLI} core multisite-convert && \
    curl ${DLINK} --output ${BUILD_FQFN} && \
    if [ ! -d "/root/.composer" ]; then mkdir "/root/.composer"; fi; echo "{\"github-oauth\":{\"github.com\":\"$GITHUB_OAUTH_TOKEN\"}}" > "/root/.composer/auth.json"

SHELL ["/bin/bash", "-c"]

# create sites
RUN chown -R mysql:mysql /var/lib/mysql && service mysql start && \
    IFS=',' read -a array <<< "${SITES}"; for site in "${array[@]}"; do ${WPCLI} site create --slug="${site##*\:}" --title="${site%%\:*}" --email=test@wp.org; done

# install plugins
RUN chown -R mysql:mysql /var/lib/mysql && service mysql start && \
    IFS=';' read -a array <<< "${DEPENDENCIES}"; for plugin_name in "${array[@]}"; do ${WPCLI} plugin install $plugin_name --activate-network; done

# install ACF Pro (latest) add &t=<ver> to download URI for customization

RUN DLINK="https://connect.advancedcustomfields.com/index.php?a=download&p=pro&k=${ACFPRO_KEY}" && \
    if [ "latest" != "${ACF_PRO_VERSION}" ]; then DLINK="${DLINK}&t=${ACF_PRO_VERSION}"; fi && \
    PPATH="${WP_INSTALL_DIR}/advanced-custom-fields-pro.zip" && \
    chown -R mysql:mysql /var/lib/mysql && service mysql start && \
    curl ${DLINK}${ACF_VER_STR} --output ${PPATH} && \
    ${WPCLI} plugin install ${PPATH} --activate-network

ENV WP_PLUGINS_DIR="${WP_INSTALL_DIR}/wp-content/plugins"
ENV PLUGIN_DIR="${WP_PLUGINS_DIR}/smartling-connector"
ENV LOCAL_GIT_DIR="/plugin-dir"

COPY Buildplan/boot.sh /boot.sh
RUN chmod +x /boot.sh

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
