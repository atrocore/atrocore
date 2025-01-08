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

            setup() {
                Dep.prototype.setup.call(this);

                this.prepareOptionsList();
                this.listenTo(this.model, 'change:foreignEntityId', () => {
                    this.prepareOptionsList();
                    this.reRender();
                });
            },

            prepareOptionsList() {
                this.params.options = [];
                this.translatedOptions = {};

                let foreignEntity = this.model.get('foreignEntityId');

                if (!foreignEntity) {
                    return;
                }

                let foreignFields = this.getMetadata().get(`entityDefs.${foreignEntity}.fields`) || {};

                $.each(foreignFields, (name, data) => {
                    if (name === 'name' && !this.model.get('foreignName')) {
                        this.model.set('foreignName', 'name');
                    }
                    if (data.type === 'varchar' && !data.notStorable) {
                        this.params.options.push(name);
                        this.translatedOptions[name] = this.translate(name, 'fields', foreignEntity);
                    } else if (data.type === 'link' && ['ownerUser', 'assignedUser'].includes(name)) {
                        let linkEntity = this.getMetadata().get(['entityDefs', foreignEntity, 'links', name, 'entity']);
                        if (linkEntity && this.getMetadata().get(['entityDefs', linkEntity, 'fields', 'name'])) {
                            this.params.options.push(name + 'Name');
                            this.translatedOptions[name + 'Name'] = this.translate('name', 'fields', 'Global') + ': ' + this.translate(name, 'fields', foreignEntity);
                        }
                    }
                });
            },

            afterRender() {
                Dep.prototype.afterRender.call(this);

                if (this.mode === 'edit') {
                    if (!this.model.get('foreignEntityId')) {
                        this.$el.parent().hide();
                    } else {
                        this.$el.parent().show();
                    }
                }

            },

        });

    });
