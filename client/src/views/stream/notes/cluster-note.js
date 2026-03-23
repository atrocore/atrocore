/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/stream/notes/cluster-note', 'views/stream/note', function (Dep) {

    return Dep.extend({

        template: 'stream/notes/create-related',

        init: function () {
            var type   = this.model.get('type') || '';
            var action = (this.model.get('data') || {}).action;

            var prefix = type.replace('Activity', '');
            prefix = prefix.charAt(0).toLowerCase() + prefix.slice(1);

            if (action) {
                this.messageName = prefix + action.charAt(0).toUpperCase() + action.slice(1);
            }

            Dep.prototype.init.call(this);
        },

        setup: function () {
            var data        = this.model.get('data') || {};
            var relatedType = data.relatedType || this.model.get('relatedType');
            var relatedId   = data.relatedId   || this.model.get('relatedId');
            var relatedName = this.model.get('relatedName');

            if (relatedType && relatedId) {
                var label = Handlebars.Utils.escapeExpression(relatedName || relatedId);
                var entityStr;
                if (data.action && data.action.toLowerCase().indexOf('deleted') !== -1) {
                    entityStr = label;
                } else {
                    entityStr = '<a href="#' + relatedType + '/view/' + relatedId + '">' + label + '</a>';
                }
                if (data.entityRole) {
                    var roleLabel = this.translate(data.entityRole + 'Record', 'labels', 'Cluster');
                    entityStr = roleLabel + ' ' + entityStr;
                }
                this.messageData['relatedEntity'] = entityStr;
            }

            this.createMessage();
        }
    });
});
