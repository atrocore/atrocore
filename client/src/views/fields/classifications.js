/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/classifications', 'views/fields/link-multiple',
    Dep => Dep.extend({

        selectBoolFilterList: ['onlyForEntity'],

        boolFilterData: {
            onlyForEntity() {
                return this.model.name
            }
        },

        inlineEditSave() {
            let data = this.fetch();

            let prev = this.initialAttributes;

            this.model.set(data, {silent: true});
            data = this.model.attributes;

            let attrs = false;
            for (let attr in data) {
                if (_.isEqual(prev[attr], data[attr])) {
                    continue;
                }
                (attrs || (attrs = {}))[attr] = data[attr];
            }

            if (!attrs) {
                this.inlineEditClose();
                return;
            }

            if (this.validate()) {
                this.notify('Not valid', 'error');
                this.model.set(prev, {silent: true});
                return;
            }

            const postData = {
                entityName: this.model.name,
                entityId: this.model.id,
                classificationsIds: attrs?.classificationsIds || null
            };

            this.notify('Saving...');
            this.ajaxPostRequest('Classification/action/relateRecords', postData)
                .success(() => {
                    this.model.fetch().then(() => {
                        this.trigger('after:save');
                        this.model.trigger('after:save');
                        this.notify('Saved', 'success');
                    });
                })
                .error(() => {
                    this.notify('Error occured', 'error');
                    this.model.set(prev, {silent: true});
                    this.render()
                });

            this.inlineEditClose(true);
        },

        hasLockedControls() {
            return false;
        }
    })
);
