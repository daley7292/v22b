function getElementByXpath(path) {
  return document.evaluate(path, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
}

function handleLoadFunc(e) {
  var [origin, currentHash] = window.location.href.split('#');
  if (currentHash.includes('/register') && currentHash.includes('?code=')) {
    const cssContent = `
#root > div.styles_C6Q6h > div.styles_svCqL > div.styles_2koXy > input:nth-child(4) {
      display: none;
      }
    `;

    const styleElement = document.createElement('style');
    styleElement.type = 'text/css';
    styleElement.appendChild(document.createTextNode(cssContent));
    document.head.appendChild(styleElement);
  }
}


(function () {
  var oldonload = window.onload;
  if (typeof window.onload != 'function') {
    window.onload = handleLoadFunc;
  } else {
    window.onload = function () {
      oldonload();
      handleLoadFunc();
    }
  }
})()
