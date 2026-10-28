/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/stream/notes/erase', 'views/stream/note',
    Dep => Dep.extend({

        template: 'stream/notes/erase',

        messageName: 'erase',

        isEditable: false,

        isRemovable: false,

        setup() {
            this.messageData['datetime'] = this.getDateTime().toDisplay(this.model.get('createdAt'));

            const auditMeta = this.model.getMeta('audit', 'createdBy');
            if (auditMeta) {
                this.messageData['user'] = this.buildUserHtml(auditMeta);
            }

            this.createMessage();
        },

    })
);
