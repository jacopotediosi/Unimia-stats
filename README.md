# Unimia stats
Unimia stats is a fully dockerized software, composed by 3 containers (php-fpm, mariadb and a python script launched via crontab) to check (simulating a real user login every N minutes) when unimia.unimi.it (the student career portal of the University of Milan) is down or slow and keep its health history, representing it through useful charts (a lot) and screenshots on a web interface.

This project was made by students of computer science faculties to verify what Unimia's actual SLA is and to be able to possibly understand what are the difficulties of the portal and the errors most commonly encountered by users.

It may be that it is easily adaptable to similar contexts or other websites.

Live demo available at https://unimia.webctf.it/

# Features
- Automatically check every N minutes if Unimia is working properly and if the page meets certain criteria (e.g. it does not contain the word "error" or "sqlexception").
- It represents uptime percentages and response times through various types of charts (hourly during days, daily during weeks, in last n months etc)
- Save HTML pages (in a private folder not reachable from the website container) for N days for each time it has found Unimia down (for debugging purposes and further analysis and improvements to the script)
- Save screenshots showing what the page was like when Unimia was down and allow users to see them
- Redact personal informations before to take screenshots using a two-pass system: first it eliminates certain elements based on their position on the page, and then iterating a words blocklist

# Installation
Prerequisites: Nginx (with virtualhosts already configured), Docker (remember to add your user to the docker group with `sudo groupadd docker; sudo usermod -aG docker $USER`), Docker-compose.
You can install them via your favorite packet manager (e.g. `sudo apt install nginx docker docker-compose`) and follow some online tutorials to configure essential things (feel free to trust Digitalocean Blog).

1. cd to your favorite project path (e.g. I sometimes use /opt/ or /var/docker/)
2. git clone https://github.com/jacopotediosi/Unimia-stats.git
3. cd Unimia-stats-master
4. create a folder named "sock" and double check it has the right permissions: `sudo chown $USER:docker sock && 2775`
5. edit the docker-compose.yml file by entering your Unimia credentials and completing settings where necessary
6. edit the install/unimia-stats.vhost configuration file, place it inside your nginx virtualhost folder (e.g. /etc/nginx/sites-available) and run `ln -s /etc/nginx/sites-available/unimia-stats.vhost /etc/nginx/sites-enabled && sudo service nginx reload`
7. if you want you can populate your db with initial test data with `mv install/unimia_test_dummy_data.sql to mysql/sql/`
8. launch `docker-compose build && docker-compose up -d` and check 
