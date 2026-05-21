let lastHeight = 0;

function iframeResize() {
    var height = document.body.scrollHeight;

    if (height !== lastHeight) {
        lastHeight = height;
        parent.postMessage("resize::" + height, "*");
    }
}

function scrolltotop() {
    parent.postMessage("scrollto::edh-iframe", "*");
}

// Run on load
window.onload = iframeResize;

// Auto detect size changes (BEST WAY)
new ResizeObserver(function () {
    iframeResize();
}).observe(document.body);