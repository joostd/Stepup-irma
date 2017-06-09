Install
===

Install php5, apache2

eg

sudo apt-get install sqlite3
sudo apt-get install php5-sqlite

sudo apt-get install mysql-server
sudo apt-get install php5-mysql


Use composer to install php dependencies:

	curl -sS https://getcomposer.org/installer | php
	./composer.phar install

	openssl req -new -subj '/CN=SAML signing' -x509 -days 3652 -nodes -out cert.pem -keyout key.pem

Run
===

Use the built-in web server of php 5.4+ to run from the command line:

        php -S ip:port -t www
