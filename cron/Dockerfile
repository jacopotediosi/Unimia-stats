FROM python:3-alpine

# Copy script which should be run
COPY script.py requirements.txt /

# Setup
RUN apk update && \
    apk upgrade && \
    apk add tzdata chromium chromium-chromedriver build-base jpeg-dev zlib-dev mariadb-client && \
    pip3 install -r /requirements.txt && \
    chmod 755 /script.py && \
    echo '*/10  *  *  *  * python3 /script.py' > /etc/crontabs/root && \
    echo '0 7 */15 * * mysqldump -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASSWORD $MYSQL_DATABASE -r /tmp/$MYSQL_DATABASE.sql && GZIP=-9 tar -czf /downloads/latest_dump.tar.gz -C /tmp $MYSQL_DATABASE.sql /screenshot' >> /etc/crontabs/root && \
    rm -f /var/cache/apk/*

# Start cron
CMD [ "crond", "-l", "2", "-f" ]
