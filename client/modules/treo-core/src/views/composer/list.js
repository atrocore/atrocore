/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
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
                        checkAllResultDisabled: false,
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
            this.getCollectionFactory().create('TreoStore', collection => {
                this.storeCollection = collection;
                collection.maxSize = 20;
                collection.data.isInstalled = false;

                this.listenToOnce(collection, 'sync', () => {
                    this.createView('listStore', 'views/record/list', {
                        collection: collection,
                        el: `${this.options.el} .list-container.modules-store`,
                        layoutName: 'list',
                        searchManager: false,
                        selectable: false,
                        checkboxes: false,
                        massActionsDisabled: true,
                        checkAllResultDisabled: false,
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

        getHeader() {
            return  `<a href="#Admin">${this.translate('Administration')}</a>`+
                    `<span class="subsection">${this.translate('System')}</span>` +
                    this.getLanguage().translate('Composer', 'labels', 'Admin');
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
            if (!data.id || !data.mode) {
                return;
            }

            let currentModel;
            let viewName;
            let beforeSaveLabel;
            let afterSaveLabel;
            let apiUrl;
            let requestType;
            if (data.mode === 'install') {
                currentModel = this.storeCollection.get(data.id);
                viewName = 'treo-core:views/composer/modals/install';
                beforeSaveLabel = 'settingModuleForInstalling';
                afterSaveLabel = 'settedModuleForInstalling';
                apiUrl = 'Composer/installModule';
                requestType = 'POST';
            } else {
                currentModel = this.installedCollection.get(data.id);
                viewName = 'treo-core:views/composer/modals/update';
                beforeSaveLabel = 'settingModuleForUpdating';
                afterSaveLabel = 'settedModuleForUpdating';
                apiUrl = 'Composer/updateModule';
                requestType = 'PUT';
            }

            this.createView('installModal', viewName, {
                currentModel: currentModel
            }, view => {
                view.render();
                this.listenTo(view, 'save', saveData => {
                    this.actionsInProgress++;
                    this.notify(this.translate(beforeSaveLabel, 'labels', 'Composer'));
                    this.ajaxRequest(apiUrl, requestType, JSON.stringify(saveData), {timeout: 180000}).then(response => {
                        if (response) {
                            this.notify(this.translate(afterSaveLabel, 'labels', 'Composer'), 'success');
                            if (data.mode === 'install') {
                                this.storeCollection.fetch();
                            }
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
            this.ajaxRequest('Composer/deleteModule', 'DELETE', JSON.stringify({id: data.id})).then(response => {
                if (response) {
                    this.notify(this.translate('settedModuleForRemoving', 'labels', 'Composer'), 'success');
                    this.installedCollection.fetch();
                }
            }).always(() => {
                this.actionsInProgress--;
            });
        },

        actionCancelModule(data) {
            if (!data.id || !data.status) {
                return;
            }

            let beforeSaveLabel;
            let afterSaveLabel;
            if (data.status = 'install') {
                beforeSaveLabel = 'cancelingModuleUpdate';
                afterSaveLabel = 'canceledModuleUpdate';
            } else {
                beforeSaveLabel = 'cancelingModuleInstall';
                afterSaveLabel = 'canceledModuleInstall';
            }

            this.actionsInProgress++;
            this.notify(this.translate(beforeSaveLabel, 'labels', 'Composer'));
            this.ajaxPostRequest('Composer/cancel', {id: data.id}).then(response => {
                if (response) {
                    this.notify(this.translate(afterSaveLabel, 'labels', 'Composer'), 'success');
                    if (data.status = 'install') {
                        this.storeCollection.fetch();
                    }
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

        toggleActionButton(action, show) {
            let button = this.$el.find(`.detail-button-container button[data-action="${action}"]`);
            if (show) {
                button.show();
            } else {
                button.hide();
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

    })
);
