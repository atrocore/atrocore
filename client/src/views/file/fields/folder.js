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
                if (this.model.isNew()) {
                    if (window.location.hash === '#File') {
                        const treeNodes = this.getStorage().get('treeSelectedNodes', 'File') || [];
                        const folderNode = treeNodes.find(n => n.link === 'folder');
                        if (folderNode) {
                            this.model.set('folderId', folderNode.id);
                            this.model.set('folderName', folderNode.name);
                        }
                    }
                }
            }
        },

    })
);
