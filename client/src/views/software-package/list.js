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

                    // "url": "SoftwarePackage/install",
                    console.log('SoftwarePackage install, selected ids:', ids);
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

                    // "url": "SoftwarePackage/uninstall",
                    console.log('SoftwarePackage uninstall, selected ids:', ids);
                });
            });
        },

    })
});

