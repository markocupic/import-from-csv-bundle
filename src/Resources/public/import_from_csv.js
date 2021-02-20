window.addEvent('domready', function () {

    if (document.id('reportTable')) {

        if (document.querySelector('h2.sub_headline')) {
            $$('h2.sub_headline')[0].setStyle('display', 'none');
        }

        if (document.querySelector('.tl_message')) {
            $$('.tl_message')[0].setStyle('display', 'none');
        }

        // change href property of the header_back link
        if (document.querySelector('a.header_back')) {
            $$('a.header_back')[0].setProperty('href', window.location);
        }

    }

    // Use button to show failed inserts only
    let button = document.querySelector('.tl_submit.showErrorButton');
    if(button){
        button.addEventListener('click', function(event){
            event.preventDefault();
            let btn = event.target;
            btn.classList.toggle('hideSuccess');
            if(btn.classList.contains('hideSuccess'))
            {
                btn.innerText = btn.getAttribute('data-lblall')
            }else{
                btn.innerText = btn.getAttribute('data-lblerroronly')
            }

            let rows = document.querySelectorAll('tr.allOk');
            if(rows)
            {
                rows.forEach((row) => {
                    row.classList.toggle('hiddenRow');
                });
            }
        });
    }
});