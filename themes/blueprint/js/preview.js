$(document).ready(function() {
    if (!Array.prototype.indexOf) {
        setIndexOf();
    }
    getBookPreviews();
});

function getBookPreviews() {
    var skeys = '';
    $('.previewBibkeys').each(function(){
        skeys += $(this).attr('class');
    });
    skeys = skeys.replace(/previewBibkeys/g, '').replace(/^\s+|\s+$/g, '');
    var bibkeys = skeys.split(/\s+/);

    // fetch Google preview if enabled
    if ($('.previewGBS').length > 0) {
        // checks if query string might break URI limit - if not, run as normal
        if (!bibkeys.length > 150){
            var script = 'https://encrypted.google.com/books?jscmd=viewapi&bibkeys='
                + bibkeys.join(',') + '&callback=processGBSBookInfo';
            $.getScript(script);
        } else {
            // if so, break request into chunks of 100
            var keyString = '';
            // loop through array
            for (var i=0; i < bibkeys.length; i++){
                keyString += bibkeys[i] + ',';
                // send request when there are 100 requests ready or when there are no
                // more elements to be sent
                if ((i > 0 && i % 100 == 0) || i == bibkeys.length-1) {
                    var  script = 'https://encrypted.google.com/books?jscmd=viewapi&bibkeys='
                        + keyString + '&callback=processGBSBookInfo';
                    $.getScript(script);
                    keyString = '';
                }
            }
        }
    }

    // fetch OpenLibrary preview if enabled
    if ($('.previewOL').length > 0) {
        var script = 'http://openlibrary.org/api/books?bibkeys='
            + bibkeys.join(',') + '&callback=processOLBookInfo';
        $.getScript(script);
    }

    // fetch HathiTrust preview if enabled
    if ($('.previewHT').length > 0) {
        getHTPreviews(skeys);
    }
}
// functions to get rights codes for previews
function getHathiOptions() {
    HathiOptions = $('[class*="hathiPreviewDiv"]').attr("class").split('__')[1].split(',');
    return HathiOptions;
}
function getGoogleOptions() {
    GoogleOptions = $('[class*="googlePreviewDiv"]').attr("class").split('__')[1].split(',');
    return GoogleOptions;
}
function getOLOptions() {
    OlOptions = $('[class*="olPreviewDiv"]').attr("class").split('__')[1].split(',');
    return OlOptions;
}

function getHTPreviews(skeys) {
    skeys = skeys.replace(/(ISBN|LCCN|OCLC)/gi, '$1:').toLowerCase();
    var bibkeys = skeys.split(/\s+/);
    // fetch 20 books at time if there are more than 20
    // since hathitrust only allows 20 at a time
    // as per http://vufind.org/jira/browse/VUFIND-317
    var batch = [];
    for(i = 0; i < bibkeys.length; i++) {
        batch.push(bibkeys[i]);
        if ((i > 0 && i % 20 == 0) || i == bibkeys.length-1) {
            var script = 'http://catalog.hathitrust.org/api/volumes/brief/json/'
                + batch.join('|') + '&callback=processHTBookInfo';
            $.getScript(script);
            batch = [];
        }
    }
}

function processGBSBookInfo(booksInfo) {
    processBookInfo(booksInfo, 'previewGBS');
}

function processOLBookInfo(booksInfo) {
    processBookInfo(booksInfo, 'previewOL');
}

function processHTBookInfo(booksInfo) {
    for (b in booksInfo) {
        var bibkey = b.replace(/:/, '').toUpperCase();
        var $link = $('.previewHT.' + bibkey);
        var items = booksInfo[b].items;
        for (var i = 0; i < items.length; i++) {
            // check if items possess an eligible rights code
            if (getHathiOptions().indexOf(items[i].rightsCode) >= 0) {
                $link.attr('href', items[i].itemURL).show();
            }
        }
    }
}

function processBookInfo(booksInfo, previewClass) {
    // assign the correct rights string depending on source
    if (previewClass == 'previewGBS') {
        var viewOptions = getGoogleOptions();
    } else {
        var viewOptions = getOLOptions();
    }
    for (bibkey in booksInfo) {
        var bookInfo = booksInfo[bibkey];
        if (bookInfo) {
          if (viewOptions.indexOf(bookInfo.preview)>= 0) {
                $link = $('.' + previewClass + '.' + bibkey);
                $link.attr('href', bookInfo.preview_url).show();
            }
        }
    }
}
/**
 * Array.indexOf is not universally supported
 * We need to set it for users who don't have it.
 *
 * developer.mozilla.org/en-US/docs/JavaScript/Reference/Global_Objects/Array/indexOf
 */
function setIndexOf() {
    Array.prototype.indexOf = function (searchElement /*, fromIndex */ ) {
        "use strict";
        if (this == null) {
            throw new TypeError();
        }
        var t = Object(this);
        var len = t.length >>> 0;
        if (len === 0) {
            return -1;
        }
        var n = 0;
        if (arguments.length > 1) {
            n = Number(arguments[1]);
            if (n != n) { // shortcut for verifying if it's NaN
                n = 0;
            } else if (n != 0 && n != Infinity && n != -Infinity) {
                n = (n > 0 || -1) * Math.floor(Math.abs(n));
            }
        }
        if (n >= len) {
            return -1;
        }
        var k = n >= 0 ? n : Math.max(len - Math.abs(n), 0);
        for (; k < len; k++) {
            if (k in t && t[k] === searchElement) {
                return k;
            }
        }
        return -1;
    }
}