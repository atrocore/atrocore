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

            this.setFieldOptions();
            this.listenTo(this.model, 'change:statusField', () => {
                this.setFieldOptions();
                this.reRender();
            });
        },

        setFieldOptions() {
            this.params.options = this.getMetadata().get(['entityDefs', this.model.get('code'), 'fields', this.model.get('statusField'), 'options']) || [];
            this.translatedOptions = {'': ''};
            this.params.options.forEach(option => {
                this.translatedOptions[option] = this.getLanguage().translateOption(option, this.model.get('statusField'), this.model.get('code'));
            });
        },

    });
});
