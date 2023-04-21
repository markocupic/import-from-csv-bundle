/*
 * This file is part of Import From CSV Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/import-from-csv-bundle
 */

class ImportFromCsvApp {
    constructor(vueElement, options) {

        const {createApp} = Vue

        const app = createApp({

            data() {
                return {
                    items: [],
                    isTestMode: false,
                    status: 'ifcb-status-preparing',
                    urlStack: [],
                    requestsDone: 0,
                    requestsNeeded: 0,
                    options: {
                        id: null,
                        csrfToken: '',
                        taskId: '',
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
                        logs: [],
                        summary: {
                            rows: 0,
                            errors: 0,
                            success: 0,
                        },
                    },
                }
            },
            watch: {
                report: {
                    handler(newValue, oldValue) {
                        const box = document.getElementById("ifcbImportReportBox");
                        if (box) {
                            window.setTimeout(() => box.scrollTop = box.scrollHeight + 100, 100);
                        }
                    },
                    deep: true
                }
            },

            // Lifecycle hooks are called at different stages
            // of a component's lifecycle.
            // This function will be called when the component is mounted.
            mounted() {
                // Override defaults
                this.options = {...this.options, ...options}
                this.$nextTick(function () {
                    // Code that will run only after the
                    // entire view has been rendered
                    this.appMountAction();
                });
            },

            methods: {
                appMountAction() {

                    fetch('contao?do=import_from_csv&key=appMountAction&id=' + this.options.id + '&taskId=' + this.options.taskId + '&token=' + this.options.csrfToken,
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

                importAction(isTestMode, isInitial = false) {

                    if (isInitial === true && this.status !== 'ifcb-status-ready') {
                        console.error('The import cannot be called twice.');
                        return;
                    }

                    this.disableButtons();

                    if (isTestMode === true) {
                        this.isTestMode = true;
                    }

                    let url = '';

                    if (this.urlStack.length) {
                        this.status = 'ifcb-status-pending';
                        url = this.urlStack.shift();
                    } else {
                        this.status = 'ifcb-status-completed';
                        return;
                    }

                    fetch(url + '&isTestMode=' + isTestMode + '&taskId=' + this.options.taskId + '&token=' + this.options.csrfToken, {
                        method: "GET",
                        headers: {
                            'x-requested-with': 'XMLHttpRequest'
                        },
                    }).then(response => {
                        if (response.status === 200) {
                            response.json().then(res => {
                                this.report.logs = [...this.report.logs, ...res.data.logs];
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

                disableButtons() {
                    let buttons = document.querySelectorAll('#ifcbImportFromCsvApp .tl_submit_container button');
                    if (buttons) {
                        buttons.forEach(function (button) {
                            button.setAttribute('disabled', 'true');
                        });
                    }
                },

                filterInsertListing(event) {
                    const button = event.target;
                    // Use button to show failed inserts only
                    button.classList.toggle('hideSuccess');
                    if (button.classList.contains('hideSuccess')) {
                        button.innerText = button.getAttribute('data-lbl-all')
                    } else {
                        button.innerText = button.getAttribute('data-lbl-error-only')
                    }

                    let rows = document.querySelectorAll('tr.ifcb-import-success,tr.ifcb-delim');
                    if (rows) {
                        rows.forEach((row) => {
                            row.classList.toggle('hiddenRow');
                        });
                    }
                },

                updateProgressBar() {
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
                },

            },
        });
        
        app.config.compilerOptions.delimiters = ['${', '}'];
        return app.mount(vueElement);

    }
}
