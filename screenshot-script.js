const CDP = require('chrome-remote-interface');
const argv = require('minimist')(process.argv.slice(2));
const fs = require('fs');

const targetURL = argv.url || 'https://fogos.pt';
const viewport = [argv.width, argv.height];
const screenshotDelay = 6000; // ms
const fullPage = argv.fullPage || false;
const ip = argv.ip;

var opt = {
    host: 'fogos.chrome'
}

CDP(opt, async function(client){
    const {DOM, Emulation, Network, Page, Runtime} = client;

    await Page.enable();
    await DOM.enable();
    await Network.enable();

    var device = {
        width: viewport[0],
        height: viewport[1],
        deviceScaleFactor: 0,
        mobile: false,
        fitWindow: false
    };

    await Emulation.setDeviceMetricsOverride(device);
    await Emulation.setVisibleSize({width: viewport[0], height: viewport[1]});
    await Page.navigate({url: targetURL});

    await Network.setCookie({
        name: "CookieConsent",
        value: "{stamp:'m+a2sHQeOOuoPJRBktiiVf5mOGWDtiqvOKiLgCLNxxLwBBxXgfbaWQ=='%2Cnecessary:true%2Cpreferences:true%2Cstatistics:true%2Cmarketing:true%2Cver:1}",
        domain: ".fogos.pt"
    })

    Page.loadEventFired(async() => {
        if (fullPage) {
            const {root: {nodeId: documentNodeId}} = await DOM.getDocument();
            const {nodeId: bodyNodeId} = await DOM.querySelector({
                selector: 'body',
                nodeId: documentNodeId,
            });

            const {model: {height}} = await DOM.getBoxModel({nodeId: bodyNodeId});
            await Emulation.setVisibleSize({width: device.width, height: height});
            await Emulation.setDeviceMetricsOverride({width: device.width, height:height, screenWidth: device.width, screenHeight: height, deviceScaleFactor: 1, fitWindow: false, mobile: false});
            await Emulation.setPageScaleFactor({pageScaleFactor:1});
        }
    });

    setTimeout(async function() {
        const screenshot = await Page.captureScreenshot({format: "png", fromSurface: true});
        const buffer = new Buffer(screenshot.data, 'base64');
        fs.writeFile( '/var/www/html/public/screenshots/' +argv.name+'.png', buffer, 'base64', function(err) {
            if (err) {
                console.error(err);
            } else {
                console.log('Screenshot saved');
            }
        });
        client.close();
    }, screenshotDelay);

}).on('error', err => {
    console.error('Cannot connect to browser:', err);
});
