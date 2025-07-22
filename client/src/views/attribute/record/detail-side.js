/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/attribute/record/detail-side', 'views/record/detail-side',
    Dep => Dep.extend({

        multiLangOwnershipPanel: {
            name: 'default',
            label: '',
            view: 'views/attribute/record/panels/default-side',
            isForm: true,
            options: {
                fieldList: [
                    {
                        name: 'ownerUser'
                    },
                    {
                        name: 'assignedUser'
                    },
                    {
                        name: 'teams'
                    }
                ]
            }
        },

        setup() {
            this.createMultilangOwnershipPanel();
            this.listenTo(this.model, 'after:save', function () {
                this.panelList.forEach(function (panel) {
                    if (this.model.get('isMultilang')) {
                        this.showPanel(panel.name);
                    } else if (panel.name !== 'default') {
                        this.hidePanel(panel.name);
                    }
                }, this)
            }, this);

            Dep.prototype.setup.call(this);
        },

        createMultilangOwnershipPanel() {
            let config = this.getConfig(),
                inputLanguageList = config.get('inputLanguageList') || [];

            if (config.get('assignedUserAttributeOwnership') && config.get('ownerUserAttributeOwnership')
                && config.get('teamsAttributeOwnership')) {
                if (this.model.get('isMultilang') && config.get('isMultilangActive') && inputLanguageList.length) {
                    inputLanguageList.forEach(lang => {
                        let panel = Espo.Utils.cloneDeep(this.multiLangOwnershipPanel);

                        panel.options.fieldList.forEach((item, index) => {
                            let name = lang.split('_').reduce((prev, curr) => prev + Espo.Utils.upperCaseFirst(curr.toLocaleLowerCase()), item.name);

                            if (item.name === 'teams') {
                                this.model.defs.fields[name] = this.model.defs.fields[item.name];
                            }
                            this.model.defs.links[name] = this.model.defs.links[item.name];

                            panel.options.fieldList[index].name = name;
                        });
                        panel.name = panel.name + '_' + lang.toLowerCase();
                        panel.label = this.translate('Ownership Information', 'labels', 'Global') + ' ' + lang.toUpperCase();

                        this.panelList.push(panel);
                    });
                }
            }
        }
    })
);
