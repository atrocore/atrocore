/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/stream/notes/create-association-record', 'views/stream/notes/relate', function (Dep) {

    return Dep.extend({

        messageName: 'createAssociationRecord',

        setup: function () {
            let data = this.model.get('data') || {};

            this.messageData['mainRecord'] = '<a href="#' + data.scope + '/view/' + data.mainRecordId + '">' + data.mainRecordName + '</a>';
            this.messageData['relatedProduct'] = '<a href="#' + data.scope + '/view/' + data.relatedRecordId + '">' + data.relatedRecordName + '</a>';

            this.createMessage();
        }
    });
});

