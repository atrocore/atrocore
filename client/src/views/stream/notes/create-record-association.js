/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/stream/notes/create-record-association', 'views/stream/notes/relate', function (Dep) {

    return Dep.extend({

        messageName: 'createRecordAssociation',

        setup: function () {
            let data = this.model.get('data') || {};

            this.messageData['relatedRecord'] = '<a href="#' + data.scope + '/view/' + data.relatedRecordId + '">' + data.relatedRecordName + '</a>';
            this.messageData['association'] = '<a href="#Association/view/' + data.associationId + '">' + data.associationName + '</a>';

            this.createMessage();
        }
    });
});

