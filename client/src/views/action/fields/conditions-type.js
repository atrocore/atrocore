/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/fields/conditions-type', 'views/fields/enum',
    Dep => {

        return Dep.extend({

            setup() {
                Dep.prototype.setup.call(this);

                this.prepareOptionsList();
                this.listenTo(this.model, 'change:sourceEntity', () => {
                    this.model.set(this.name, null);
                    this.prepareOptionsList();
                    this.reRender();
                });
            },

            prepareOptionsList() {
                this.params.options = ['', 'basic', 'script'];
                this.translatedOptions = {
                    '': '',
                    'basic': this.translate('basic'),
                    'script': this.translate('script')
                };

                if (this.model.get('sourceEntity')) {
                    $.each(this.getMetadata().get('app.conditionsTypes') || {}, (type, item) => {
                        if (item.entityName === this.model.get('sourceEntity')) {
                            this.params.options.push(type);
                            this.translatedOptions[type] = item.label;
                        }
                    })
                }
            },

        });
    });
