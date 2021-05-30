#------------- Application Specific Stuff ----------------------------------------------------

# Install NPM and Node.js
RUN curl -sL https://deb.nodesource.com/setup_10.x | bash -
RUN apt-get install -y nodejs

#------------- Puppeteer & Chrome Headless  ---------------------------------------------------------------
# Puppeteer
RUN npm install --global chrome-remote-interface
RUN npm install --global minimist

