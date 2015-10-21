window.addEvent('domready', function () {

    if (document.id('reportTable')) {

        if ($$('h2.sub_headline')) {
            $$('h2.sub_headline')[0].setStyle('display', 'none');
        }

        if ($$('.tl_message')) {
            $$('.tl_message')[0].setStyle('display', 'none');
        }

        // change href property of the header_back link
        if ($$('a.header_back')) {
            $$('a.header_back')[0].setProperty('href', window.location);
        }

    }
});