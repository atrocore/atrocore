/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/fields/folder', 'views/fields/link',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (this.model.get('reupload')) {
                this.setReadOnly(true);
            }

            if (this.mode === 'edit') {
                const selectedFolderId = this.getStorage().get('selectedNodeId', 'File') || null;
                if (selectedFolderId && this.model.isNew()) {
                    this.ajaxGetRequest(`Folder/${selectedFolderId}?silent=true`).success(folder => {
                        this.model.set('folderId', folder.id);
                        this.model.set('folderName', folder.name);
                    });
                }
            }
        },

    })
);
