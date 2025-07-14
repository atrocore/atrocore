/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/composer/list', 'views/list',
    Dep => Dep.extend({

        template: 'treo-core:composer/list',

        createButton: false,

        searchPanel: false,

        installedCollection: null,

        storeCollection: null,

        actionsInProgress: 0,

        messageText: null,

        messageType: null,

        log: null,

        inProgress: false,

        setup() {
            Dep.prototype.setup.call(this);

            this.wait(true);
            this.getConfig().fetch({
                success: () => {
                    this.wait(false);
                }
            });

            this.listenToOnce(this, 'remove', () => {
                if (this.logCheckInterval) {
                    window.clearInterval(this.logCheckInterval);
                    this.logCheckInterval = null;
                }

                if (this.configCheckInterval) {
                    window.clearInterval(this.configCheckInterval);
                    this.configCheckInterval = null;
                }
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.ajaxPostRequest('Composer/action/check').then(response => {
                let alertEl = $('.progress-status');
                alertEl.html('');
                if (response.status) {
                    $('.composer-action').removeAttr('disabled');
                } else {
                    alertEl.html(response.message);
                    alertEl.addClass('text-alert');
                }
            });

            new Svelte.TreePanel({
                target: $(`${this.options.el} .content-wrapper`).get(0),
                anchor: $(`${this.options.el} .content-wrapper .tree-panel-anchor`).get(0),
                props: {
                    scope: 'Settings',
                    model: this.model,
                    mode: 'detail',
                    isAdminPage: true,
                    callbacks: {

                    }
                }
            });
        },

        loadList() {
            this.loadInstalledModulesList();
            this.loadStoreModulesList();
            this.loadLogList();
        },

        loadLogList() {
            this.createView('logList', 'treo-core:views/composer/record/panels/log', {
                el: `${this.options.el} .log-list-container`
            }, view => {
                view.render();
                this.listenTo(this, 'composerUpdate:started composerUpdate:failed', () => {
                    view.actionRefresh();
                });
            })
        },

        loadInstalledModulesList() {
            this.getCollectionFactory().create('Composer', collection => {
                this.installedCollection = collection;
                collection.maxSize = 200;
                collection.url = 'Composer/list';

                this.listenToOnce(collection, 'sync', () => {
                    this.createView('list', 'views/record/list', {
                        collection: collection,
                        el: `${this.options.el} .list-container.modules-installed`,
                        type: 'list',
                        searchManager: false,
                        selectable: false,
                        checkboxes: false,
                        massActionsDisabled: true,
                        buttonsDisabled: false,
                        paginationEnabled: false,
                        showCount: false,
                        showMore: false,
                        rowActionsView: 'treo-core:views/composer/record/row-actions/installed'
                    }, view => {
                        this.listenTo(view, 'after:render', () => {
                            let rows = view.nestedViews || {};
                            let showCancelAction = false;
                            collection.each(currentModel => {
                                let status = currentModel.get('status');
                                if (status) {
                                    showCancelAction = true;
                                    rows[currentModel.id].$el.addClass(`${status}-module-row`);
                                }
                            });
                            this.toggleActionButton('cancelUpdate', showCancelAction);
                        });
                        this.listenToOnce(view, 'after:render', () => {
                            this.installedCollection.trigger('disableActions', false);
                        });
                        view.render();
                    });
                });

                collection.fetch();
            });
        },

        loadStoreModulesList() {
            this.getCollectionFactory().create('Store', collection => {
                this.storeCollection = collection;
                collection.maxSize = 50;
                collection.where = [
                    {
                        field: 'status',
                        type: 'notIn',
                        value: ['installed']
                    }
                ];

                this.listenToOnce(collection, 'sync', () => {
                    this.createView('listStore', 'views/record/list', {
                        collection: collection,
                        el: `${this.options.el} .list-container.modules-store`,
                        layoutName: 'list',
                        searchManager: false,
                        selectable: false,
                        checkboxes: false,
                        massActionsDisabled: true,
                        buttonsDisabled: false,
                        paginationEnabled: false,
                        showCount: true,
                        showMore: true,
                        rowActionsView: 'treo-core:views/composer/record/row-actions/store'
                    }, view => {
                        this.listenToOnce(view, 'after:render', () => {
                            this.storeCollection.trigger('disableActions', false);
                        });
                        view.render();
                    });
                });
                collection.fetch();
            });
        },

        getBreadcrumbsItems() {
            return [
                this.getAdminBreadcrumbsItem(),
                {
                    label: this.translate('Composer', 'labels', 'Admin'),
                }
            ]
        },

        setupHeader() {
            new Svelte.BaseHeader({
                target: document.querySelector('.page-header'),
                props: {
                    breadcrumbs: this.getBreadcrumbsItems(),
                }
            })
        },

        updatePageTitle() {
            this.setPageTitle(this.getLanguage().translate('Composer', 'labels', 'Admin'));
        },

        actionRefresh(data) {
            if (data.collection === 'installed') {
                this.installedCollection.fetch();
            } else if (data.collection === 'store') {
                this.storeCollection.fetch();
            }
        },

        actionInstallModule(data) {
            if (!data.id) {
                return;
            }

            this.actionsInProgress++;
            this.notify(this.translate('settingModuleForInstalling', 'labels', 'Composer'));
            this.ajaxRequest('Composer/installModule', 'POST', JSON.stringify({id: data.id, version: "*"}), {timeout: 180000}).then(response => {
                if (response) {
                    this.notify(this.translate('setModuleForInstalling', 'labels', 'Composer'), 'success');
                    this.installedCollection.fetch();
                    this.storeCollection.fetch();
                }
            }).always(() => {
                this.actionsInProgress--;
            });
        },

        actionUpdateModule(data) {
            if (!data.id) {
                return;
            }

            this.createView('installModal', 'treo-core:views/composer/modals/update', {
                currentModel: this.installedCollection.get(data.id)
            }, view => {
                view.render();
                this.listenTo(view, 'save', saveData => {
                    this.actionsInProgress++;
                    this.notify(this.translate('settingModuleForUpdating', 'labels', 'Composer'));
                    this.ajaxRequest('Composer/updateModule', 'PUT', JSON.stringify(saveData), {timeout: 180000}).then(response => {
                        if (response) {
                            this.notify(this.translate('setModuleForUpdating', 'labels', 'Composer'), 'success');
                            this.installedCollection.fetch();
                        }
                    }).always(() => {
                        this.actionsInProgress--;
                    });
                });
            });
        },

        actionRemoveModule(data) {
            if (!data.id) {
                return;
            }

            this.actionsInProgress++;
            this.notify(this.translate('settingModuleForRemoving', 'labels', 'Composer'));
            this.ajaxRequest('Composer/deleteModule?id=' + data.id, 'DELETE').then(response => {
                if (response) {
                    Espo.Ui.notify(this.translate('setModuleForRemoving', 'labels', 'Composer'), 'info', 1000 * 60, true);
                    this.installedCollection.fetch();
                }
            }).always(() => {
                this.actionsInProgress--;
            });
        },

        actionCancelModule(data) {
            if (!data.id) {
                return;
            }

            this.actionsInProgress++;
            this.notify(this.translate('cancelingModuleInstall', 'labels', 'Composer'));
            this.ajaxPostRequest('Composer/cancel', {id: data.id}).then(response => {
                if (response) {
                    this.notify(this.translate('canceledModuleInstall', 'labels', 'Composer'), 'success');
                    this.storeCollection.fetch();
                    this.installedCollection.fetch();
                }
            }).always(() => {
                this.actionsInProgress--;
            });
        },

        actionRunUpdate() {
            if (this.actionsInProgress) {
                this.notify(this.translate('anotherActionInProgress', 'labels', 'Composer'), 'warning');
                return;
            }

            this.confirm({
                message: this.translate('confirmRun', 'labels', 'Composer'),
                confirmText: this.translate('Run Update', 'labels', 'Composer')
            }, () => {
                this.actionsInProgress++;
                this.notify(this.translate('updating', 'labels', 'Composer'));
                this.actionStarted();
                this.ajaxPostRequest('Composer/runUpdate', {}, {timeout: 180000}).then(response => {
                    this.notify(this.translate('updateStarted', 'labels', 'Composer'), 'success');
                    location.reload();
                }, error => {
                    this.actionFinished();
                }).always(() => {
                    this.actionsInProgress--;
                    this.trigger('composerUpdate:started');
                });
            });
        },

        actionCancelUpdate() {
            if (this.actionsInProgress) {
                this.notify(this.translate('anotherActionInProgress', 'labels', 'Composer'), 'warning');
                return;
            }

            this.actionsInProgress++;
            this.notify(this.translate('canceling', 'labels', 'Composer'));
            this.ajaxRequest('Composer/cancelUpdate', 'DELETE').then(response => {
                if (response) {
                    this.notify(this.translate('canceled', 'labels', 'Composer'), 'success');
                    this.storeCollection.fetch();
                    this.installedCollection.fetch();
                    this.reRender();
                }
            }).always(() => {
                this.actionsInProgress--;
            });
        },

        actionShowReleaseNotes(data) {
            this.notify(this.translate('pleaseWait', 'messages'));
            this.ajaxPostRequest('Composer/action/releaseNotes', {
                id: data.id
            }).success(response => {
                this.notify(false)
                if (!response.html) {
                    this.notify("Invalid response from server", "error")
                    return;
                }

                this.createView('dialog', 'treo-core:views/composer/modals/release-notes', {
                    scope: this.options.scope,
                    el: '[data-view="dialog"]',
                    notes: response.html,
                }, view => view.render());
            })
        },

        toggleActionButton(action, show) {
            let button = this.$el.find(`.detail-button-container button[data-action="${action}"]`);
            if (show) {
                button.show();
                button.prev().removeClass('last');
            } else {
                button.hide();
                button.prev().addClass('last');
            }
        },

        disableActionButton(action, disabled) {
            let button = this.$el.find(`.detail-button-container button[data-action="${action}"]`);
            button.prop('disabled', disabled);
        },

        actionStarted() {
            this.inProgress = true;
            this.installedCollection.trigger('disableActions', true);
            this.storeCollection.trigger('disableActions', true);

            this.disableActionButton('runUpdate', true);
            this.disableActionButton('cancelUpdate', true);
            this.$el.find('.spinner').removeClass('hidden');
        },

        actionFinished() {
            this.inProgress = false;
            this.installedCollection.trigger('disableActions', false);
            this.storeCollection.trigger('disableActions', false);

            this.disableActionButton('runUpdate', false);
            this.disableActionButton('cancelUpdate', false);
            this.$el.find('.spinner').addClass('hidden');
        },

        showCurrentStatus(text, type, hideLog) {
            if (!hideLog) {
                let log = `<a href="javascript:" class="action" data-action="showLog">${this.translate('log', 'labels', 'Admin')}</a>`;
                text = text.replace('{log}', log);
            }
            let el = this.$el.find('.progress-status');
            el.removeClass();
            el.addClass('progress-status text-' + type);
            el.html(text);
        },

        actionShowLog() {
            this.createView('progress-log', 'treo-core:views/modals/progress-log', {
                progressData: this.getProgressData()
            }, view => {
                this.listenTo(this, 'log-updated', () => {
                    view.trigger('log-updated', this.getProgressData());
                });
                view.render()
            });
        },

        getProgressData() {
            return {
                log: this.log,
                inProgress: this.inProgress,
                messageText: this.messageText,
                messageType: this.messageType
            }
        },

        shouldSetupRightSideView: function() {
            return false;
        }

    })
);
