/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/foreign-name', 'views/fields/enum',
    Dep => {

        return Dep.extend({

            prohibitedEmptyValue: true,

            afterRender() {
                Dep.prototype.afterRender.call(this);

                if (!this.model.get('foreignName')) {
                    this.model.set('foreignName', 'name');
                }
            },

            setupOptions: function () {
                const foreign = this.getMetadata().get(`entityDefs.${this.model.get('entityId')}.links.${this.model.get('code')}.entity`);
                const foreignFields = this.getMetadata().get(`entityDefs.${foreign}.fields`) || {};

                this.params.options = [];
                this.translatedOptions = {};
                $.each(foreignFields, (name, data) => {
                    if (data.type === 'varchar' && !data.notStorable) {
                        this.params.options.push(name);
                        this.translatedOptions[name] = this.translate(name, 'fields', foreign);
                    } else if (data.type === 'link' && ['ownerUser', 'assignedUser'].includes(name)) {
                        let linkEntity = this.getMetadata().get(['entityDefs', foreign, 'links', name, 'entity']);
                        if (linkEntity && this.getMetadata().get(['entityDefs', linkEntity, 'fields', 'name'])) {
                            this.params.options.push(name + 'Name');
                            this.translatedOptions[name + 'Name'] = this.translate('name', 'fields', 'Global') + ': ' + this.translate(name, 'fields', foreign);
                        }
                    }
                });
            },

        });

    });
