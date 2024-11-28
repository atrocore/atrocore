/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/linkMultiple/allow-file-types', 'views/fields/link-multiple', Dep => {

    return Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);
            this.foreignScope = 'FileType';
            this.once('after:render', ( ) => {
                    let linkDefs = this.getMetadata().get(['entityDefs', this.options.scope, 'links', this.options.field]);
                    if(linkDefs['entity'] !== 'File'){
                        this.hide();
                        return;
                    }
                    if (this.model.get('allowFileTypesIds') && this.model.get('allowFileTypesIds').length) {
                        this.ajaxGetRequest(`${this.foreignScope}`, {
                            select: 'id,name',
                            where: [
                                {
                                    "type": "in",
                                    "attribute": "id",
                                    "value": this.model.get('allowFileTypesIds')
                                }
                            ]
                        }).success(record => {
                            for (let item of record.list) {
                                let data = this.model.get('allowFileTypesNames');
                                let hasChanged = false;
                                if (this.model.get('allowFileTypesNames')[item.id] !== item.name) {
                                    data[item.id] = item.name;
                                    hasChanged = true;

                                }
                                if (hasChanged) {
                                    this.model.set('allowFileTypesNames', data);
                                    this.reRender()
                                }
                            }
                        });
                    }
            })
        }
    });
});