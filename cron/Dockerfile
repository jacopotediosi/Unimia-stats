FROM python:3-alpine

# Copy script which should be run
COPY script.py requirements.txt /

# Setup
RUN apk update && \
    apk upgrade && \
    apk add tzdata chromium chromium-chromedriver build-base jpeg-dev zlib-dev && \
    pip3 install -r /requirements.txt && \
    chmod 755 /script.py && \
    echo '*/10  *  *  *  *    python3 /script.py' > /etc/crontabs/root && \
    rm -f /var/cache/apk/*

# Start cron
CMD [ "crond", "-l", "2", "-f" ]
