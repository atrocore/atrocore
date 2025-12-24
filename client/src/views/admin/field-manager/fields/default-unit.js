/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/default-unit', 'views/fields/enum', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.prepareOptionsList();
            this.listenTo(this.model, 'change:measureId', () => {
                this.model.set('defaultUnit', null);
                this.prepareOptionsList();
                this.reRender();
            });
        },

        prepareOptionsList() {
            this.params.options = [''];
            this.translatedOptions = {'': ''};

            if (this.model.get('measureId')) {
                this.getMeasureUnits(this.model.get('measureId')).forEach(option => {
                    this.params.options.push(option.id);
                    this.translatedOptions[option.id] = option.name ? option.name : ' ';
                });
            }

            this.originalOptionList = this.params.options;
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'list') {
                return;
            }

            this.$el.parent().hide();
            if (this.model.get('measureId') && this.supportDefaultUnit()) {
                this.$el.parent().show();
            }
        },

        supportDefaultUnit() {
            let result = true;

            if (this.model.urlRoot === 'EntityField') {
                let defs = this.getMetadata().get(['fields', this.model.get('type'), 'params']) || [];

               result = defs.some(item => item.name === this.name);
            }

            return result;
        }
    });

});

