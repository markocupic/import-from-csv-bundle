document.addEventListener("DOMContentLoaded", function (event) {
    if (document.querySelector('.import-report-table')) {

        let elements = document.querySelectorAll('h2.sub_headline, .tl_message, .tl_formbody_submit');
        if (elements) {
            elements.forEach((el) => {
                el.style.display = 'none'
            });
        }

        // change href property of the header_back link
        let link = document.querySelector('a.header_back');
        if (link) {
            link.setAttribute('href', window.location);
        }

        // Use button to show failed inserts only
        let button = document.querySelector('.tl_submit.showErrorButton');
        if (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                let btn = event.target;
                btn.classList.toggle('hideSuccess');
                if (btn.classList.contains('hideSuccess')) {
                    btn.innerText = btn.getAttribute('data-lbl-all')
                } else {
                    btn.innerText = btn.getAttribute('data-lbl-error-only')
                }

                let rows = document.querySelectorAll('tr.allOk');
                if (rows) {
                    rows.forEach((row) => {
                        row.classList.toggle('hiddenRow');
                    });
                }
            });
        }
    }

});
