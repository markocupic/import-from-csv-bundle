/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

class importFromCsvApp {
  constructor(vueElement, options) {

    new Vue({
      el: vueElement,
      delimiters: ['${', '}'],

      data: {
        items: [],
        isTestMode: false,
        status: 'ifcb-status-preparing',
        urlStack: [],
        requestsDone: 0,
        requestsNeeded: 0,
        options: {
          id: null,
          csrfToken: '',
        },
        model: {
          urls: [],
          count: 0,
          limit: 0,
          offset: 0,
        },
        head: {
          countSuccess: 0,
          countErrors: 0,
          countTotal: 0,
        },
        report: {
          rows: '',
          summary: {
            rows: 0,
            errors: 0,
            success: 0,
          },
        },
      },

      created: function created() {
        // Override defaults
        this.options = {...this.options, ...options}
        this.$nextTick(function () {
          // Code that will run only after the
          // entire view has been rendered
          this.appMountAction();
        });
      },

      methods: {

        appMountAction: function appMount() {

          fetch('contao?do=import_from_csv&key=appMountAction&id=' + this.options.id + '&token=' + this.options.csrfToken,
            {
              method: "GET",
              headers: {
                'x-requested-with': 'XMLHttpRequest'
              },
            }).then(response => {
            if (response.status === 200) {
              response.json().then(res => {
                Object.keys(res.data.model).forEach(key => {
                  this.model[key] = res.data.model[key];
                });
                this.urlStack = res.data.urlStack;
                this.requestsNeeded = this.urlStack.length;
              });
            }
            return response;
          }).then(response => {
            setTimeout(() => this.status = 'ifcb-status-ready', 2000);
          }).catch(error => {
            console.error("There was en error: " + error);
          });
        },

        importAction: function importAction(isTestMode, isInitial = false) {

          if (isInitial === true && this.status !== 'ifcb-status-ready') {
            console.error('The import cannot be called twice.');
            return;
          }

          this.disableButtons();

          if (isTestMode === true) {
            this.isTestMode = true;
          }
console.log(this.urlStack);
          let url = '';
          if (this.urlStack.length) {
            this.status = 'ifcb-status-pending';
            url = this.urlStack.shift();
          } else {
            this.status = 'ifcb-status-completed';
            return;
          }
          fetch(url + '&isTestMode=' + isTestMode + '&token=' + this.options.csrfToken, {
            method: "GET",
            headers: {
              'x-requested-with': 'XMLHttpRequest'
            },
          }).then(response => {
            if (response.status === 200) {
              response.json().then(res => {
                this.report.rows = this.report.rows + res.data.rows;
                this.report.summary.rows = this.report.summary.rows + res.data.summary.rows;
                this.report.summary.success = this.report.summary.success + res.data.summary.success;
                this.report.summary.errors = this.report.summary.errors + res.data.summary.errors;
                this.requestsDone++;
                this.updateProgressBar();
              });
            } else {
              // Abort import request loop, if there was an exception
              this.status = 'ifcb-status-error';
              return;
            }
            return response;
          }).then(response => {
            setTimeout(() => this.importAction(isTestMode, false), 200);
          }).catch(error => {
            console.error("There was en error: " + error);
          });
        },

        disableButtons: function disableButtons() {
          let buttons = document.querySelectorAll('#ifcbImportFromCsvApp .tl_submit_container button');
          if (buttons) {
            buttons.forEach(function (button) {
              button.setAttribute('disabled', true);
            });
          }
        },

        filterInsertListing: function filterInsertListing(event) {
          const button = event.target;
          // Use button to show failed inserts only
          button.classList.toggle('hideSuccess');
          if (button.classList.contains('hideSuccess')) {
            button.innerText = button.getAttribute('data-lbl-all')
          } else {
            button.innerText = button.getAttribute('data-lbl-error-only')
          }

          let rows = document.querySelectorAll('tr.ifcb-import-success');
          if (rows) {
            rows.forEach((row) => {
              row.classList.toggle('hiddenRow');
            });
          }
        },

        updateProgressBar: function updateProgressBar() {
          const bar = document.getElementById('importProgress');
          const percentage = document.querySelector('#importProgress .ifcb-percentage');
          if (bar) {
            let perc = Math.ceil((this.requestsNeeded - this.urlStack.length) / this.requestsNeeded * 100);
            if (this.requestsNeeded === 0) {
              let perc = 100;
            }
            bar.style.width = perc + '%';
            if (percentage) {
              percentage.innerHTML = perc + ' %';
            }
          }
        }
      }
    });
  }
}
