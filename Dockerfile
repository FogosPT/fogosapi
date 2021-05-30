#------------- Application Specific Stuff ----------------------------------------------------

# Install NPM and Node.js
RUN curl -sL https://deb.nodesource.com/setup_10.x | bash -
RUN apt-get install -y nodejs

#------------- Supervisor Process Manager ----------------------------------------------------

# Install supervisor
RUN apt-get install -y supervisor
RUN mkdir -p /var/log/supervisor
ADD assets/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

#------------- Puppeteer & Chrome Headless  ---------------------------------------------------------------
# Puppeteer
RUN npm install --global chrome-remote-interface
RUN npm install --global minimist

# Set supervisor to manage container processes
ENTRYPOINT ["/usr/bin/supervisord"]
