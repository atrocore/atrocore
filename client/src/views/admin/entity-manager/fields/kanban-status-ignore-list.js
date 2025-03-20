/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/entity-manager/fields/kanban-status-ignore-list', 'views/fields/multi-enum', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:statusField', () => {
                this.setupOptions();
                this.reRender();
            });
        },

        setupOptions() {
            const type = this.getMetadata().get(['entityDefs', this.model.get('code'), 'fields', this.model.get('statusField'), 'type'])

            if (type === 'enum') {
                this.params.options = this.getMetadata().get(['entityDefs', this.model.get('code'), 'fields', this.model.get('statusField'), 'options']) || [];
                this.translatedOptions = {'': ''};
                this.params.options.forEach(option => {
                    this.translatedOptions[option] = this.getLanguage().translateOption(option, this.model.get('statusField'), this.model.get('code'));
                });
            } else if (type === 'extensibleEnum') {
                const extensibleEnumId = this.getMetadata().get(['entityDefs', this.model.get('code'), 'fields', this.model.get('statusField'), 'extensibleEnumId'])
                if (extensibleEnumId) {
                    let key = 'extensible_enum_' + extensibleEnumId;

                    if (!Espo[key]) {
                        Espo[key] = [];
                        this.ajaxGetRequest(`ExtensibleEnum/action/getExtensibleEnumOptions`, {extensibleEnumId: extensibleEnumId}, {async: false}).then(res => {
                            Espo[key] = res;
                        });
                    }

                    this.params.options = Espo[key].map(item => item.id)
                    this.params.translatedOptions = {'': ''}
                    Espo[key].forEach(item => {
                        this.params.translatedOptions[item.id] = item.name
                    })
                }
            }
        },

    });
});
