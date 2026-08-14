/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/software-package/list', 'views/list', Dep => {

    return Dep.extend({

        actionInstallAction(data) {
            this.notify('Loading...');

            this.createView('dialog', 'views/modals/select-records', {
                scope: 'SoftwarePackage',
                multiple: true,
                createButton: false,
                massRelateEnabled: false,
                allowSelectAllResult: false,
                layoutName: 'listModulesToSelect',
                whereAdditional: [
                    {
                        type: 'isFalse',
                        attribute: 'installed'
                    }
                ]
            }, dialog => {
                dialog.render();
                this.notify(false);

                dialog.once('select', models => {
                    const ids = (Array.isArray(models) ? models : [models]).map(model => model.id);

                    this.notify(this.translate('Loading...'));
                    this.ajaxPostRequest('SoftwarePackage/install', {ids}).then(() => {
                        this.notify(false);
                        location.reload();
                    });
                });
            });
        },

        actionUninstallAction(data) {
            this.notify('Loading...');

            this.createView('dialog', 'views/modals/select-records', {
                scope: 'SoftwarePackage',
                multiple: true,
                createButton: false,
                massRelateEnabled: false,
                allowSelectAllResult: false,
                layoutName: 'listModulesToSelect',
                boolFilterList: ['onlyUninstallable'],
                whereAdditional: [
                    {
                        type: 'isTrue',
                        attribute: 'installed'
                    }
                ]
            }, dialog => {
                dialog.render();
                this.notify(false);

                dialog.once('select', models => {
                    const ids = (Array.isArray(models) ? models : [models]).map(model => model.id);

                    this.notify(this.translate('Loading...'));
                    this.ajaxRequest('SoftwarePackage/uninstall', 'DELETE', JSON.stringify({ids})).then(() => {
                        this.notify(false);
                        location.reload();
                    });
                });
            });
        },

        actionUpdateSystemAction(data) {
            this.confirm(this.translate('update', 'massActionConfirmMessages', 'SoftwarePackage'), () => {
                this.notify('Loading...');
                this.ajaxPostRequest('SoftwarePackage/updateSystem').success(() => {
                    this.notify(false);
                    location.reload();
                });
            });
        },

        actionToStoreAction(data) {
            window.open('https://store.atrocore.com/', '_blank', 'noopener');
        },

        actionShowReleaseNotes(data) {
            this.notify(this.translate('pleaseWait', 'messages'));
            this.ajaxPostRequest(`SoftwarePackage/${data.id}/releaseNotes`).success(response => {
                this.notify(false);

                if (!response.html) {
                    this.notify("Invalid response from server", "error")
                    return;
                }

                this.createView('dialog', 'views/software-package/modals/release-notes', {
                    scope: this.options.scope,
                    el: '[data-view="dialog"]',
                    notes: response.html,
                }, view => view.render());
            })
        },

        actionReadDocs(data) {
            window.open('/docs/#/' + data.id + '/', '_blank');
        },

    })
});

