/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/datetime-with-user', 'views/fields/base',
    Dep => Dep.extend({

        listTemplate: 'fields/datetime-with-user',
        detailTemplate: 'fields/datetime-with-user',


        setup() {
            Dep.prototype.setup.call(this);

            const mode = ['detail', 'list'].includes(this.mode) ? this.mode : 'detail'
            let options = {
                name: this.name,
                model: this.model,
                params: {
                    required: false
                },
                inlineEditDisabled: true,
                mode: mode
            };

            this.createView('datetimeField', 'views/fields/datetime', {
                el: `${this.options.el} > [data-name="datetimeField"]`,
                ...options
            });
        },

        getUserField(){
            return this.name === 'createdAt' ? 'createdBy' : 'modifiedBy'
        },

        data() {
            let data = Dep.prototype.data.call(this);

            const auditMeta = this.model.get('_meta')?.audit?.[this.getUserField()];

            if (auditMeta) {
                data.actorIsLink = !auditMeta.actor.isSystem;
                data.actorId = auditMeta.actor.id;
                data.actorName = auditMeta.actor.name;

                data.delegatorIsLink = !auditMeta.delegator.isSystem;
                data.delegatorId = auditMeta.delegator.id;
                data.delegatorName = auditMeta.delegator.name;
            } else {
                data.actorIsLink = this.model.get(this.getUserField() + 'Id') !== this.getConfig().get('systemUserId');
                data.actorId = this.model.get(this.getUserField() + 'Id');
                data.actorName = this.model.get(this.getUserField() + 'Name');
            }

            return data;
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            if (!this.model.get(this.getUserField()+'Id')){
                $(this.$el).children('.extra').hide()
            }else{
                $(this.$el).children('.extra').show()
            }
        },


        setMode(mode) {
            Dep.prototype.setMode.call(this, mode);
            mode = ['detail', 'list'].includes(mode) ? mode : 'detail'
            let datetimeField = this.getView('datetimeField');
            let userField = this.getView('userField');
            if (datetimeField) {
                datetimeField.setMode(mode);
            }
            if (userField) {
                userField.setMode(mode);
            }
        },

    })
);
