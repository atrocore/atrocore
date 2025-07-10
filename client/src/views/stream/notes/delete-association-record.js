/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/stream/notes/delete-association-record', 'views/stream/notes/unrelate', function (Dep) {

    return Dep.extend({

        messageName: 'deleteAssociationRecord',

        setup: function () {
            let data = this.model.get('data') || {};

            this.messageData['mainRecord'] = '<a href="#' + data.scope + '/view/' + data.mainRecordId + '">' + data.mainRecordName + '</a>';
            this.messageData['relatedRecord'] = '<a href="#' + data.scope + '/view/' + data.relatedRecordId + '">' + data.relatedRecordName + '</a>';

            this.createMessage();
        }
    });
});

