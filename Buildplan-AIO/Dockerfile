FROM ubuntu:14.04

ENV DEBIAN_FRONTEND="noninteractive"

#install MySQL
RUN apt-get update && \
    echo "mysql-server mysql-server/root_password password root" | debconf-set-selections && \
    echo "mysql-server mysql-server/root_password_again password root" | debconf-set-selections && \
    apt-get install mysql-server -y --force-yes && \
    apt-get autoclean -y && \
    apt-get autoremove -y


#install PHP 5.6
RUN apt-get install software-properties-common -y && \
    add-apt-repository ppa:ondrej/php && \
    apt-get update && \
    apt-get install \
        wget \
        git \
        curl \
        zip \
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


# Start container.
COPY Buildplan/Docker/startup.sh startup.sh
CMD ["/bin/bash", "/startup.sh"]
