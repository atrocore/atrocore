/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/fields/storage', 'views/fields/link',
    Dep => Dep.extend({

        selectBoolFilterList: ['onlyActive'],

        setup() {
            Dep.prototype.setup.call(this);

            this.setDefaultStorage(this.model.get('folderId') || '');
            this.listenTo(this.model, 'change:folderId', () => {
                this.setDefaultStorage(this.model.get('folderId') || '');
            });
        },

        setDefaultStorage(folderId) {
            if (this.mode === 'edit' && this.model.isNew()) {
                this.ajaxGetRequest(`Folder/action/defaultStorage?id=${folderId}`).success(storage => {
                    if (storage) {
                        this.model.set('storageId', storage.id);
                        this.model.set('storageName', storage.name);
                    }
                });
            }
        },
    })
);
