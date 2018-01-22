FROM ubuntu:14.04

ENV DEBIAN_FRONTEND="noninteractive"

# Install utilities and LAMP stack.
RUN apt-get update && \
    echo "mysql-server-5.5 mysql-server/root_password password root" | debconf-set-selections && \
    echo "mysql-server-5.5 mysql-server/root_password_again password root" | debconf-set-selections && \
    apt-get install wget git curl zip apache2 mysql-server-5.5 php5-mysql php5 php5-cli php5-gd php5-curl libapache2-mod-php5 php5-mcrypt -y && \
    apt-get autoclean -y && \
    apt-get autoremove -y

# Create temporary directory for mounted files.
WORKDIR /var/www/html

# Start container.
COPY startup.sh startup.sh
CMD ["/bin/bash", "startup.sh"]
