/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/composer/modals/install', 'views/modal',
    Dep => Dep.extend({

        template: 'treo-core:composer/modals/install',

        model: null,

        buttonList: [],

        setup() {
            Dep.prototype.setup.call(this);

            this.model = this.options.currentModel.clone();

            this.prepareAttributes();

            this.createVersionView();

            this.setupHeader();
            this.setupButtonList();
        },

        setupHeader() {
            this.header = this.translate('installModule', 'labels', 'Store') + ': ' + this.model.get('name');
        },

        setupButtonList() {
            this.buttonList = [
                {
                    name: 'save',
                    label: 'Save',
                    style: 'primary',
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];
        },

        createVersionView() {
            this.createView('settingVersion', 'views/fields/varchar', {
                el: `${this.options.el} .field[data-name="settingVersion"]`,
                model: this.model,
                mode: 'edit',
                params: {
                    required: true
                },
                defs: {
                    name: 'settingVersion',
                }
            });
        },

        prepareAttributes() {
            let settingVersion = this.model.get('settingVersion');
            if (typeof settingVersion === 'string' && settingVersion.substring(0, 1) == 'v') {
                settingVersion = settingVersion.substr(1);
            }
            if (!settingVersion) {
                settingVersion = '*';
            }

            this.model.set({
                settingVersion: settingVersion
            });
        },

        actionSave() {
            if(!this.getView('settingVersion').validate()) {
                this.trigger('save', {id: this.model.id, version: this.model.get('settingVersion')});
                this.close();
            }
        }

    })
);