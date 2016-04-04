# SSAS_webserver
Hi, and welcome to our project for the System Achitecture and Security (SSAS) course at the IT University of Copenhagen.

The website runs on PHP using a mysql database

To create the database run
mysql < datamodel.sql

Update ssas.php with the correct mysql credentials

To install dependencies run (if you don't have composer, get it with apt-get)
composer install

place the source files in your apache www root and everything sohuld work
