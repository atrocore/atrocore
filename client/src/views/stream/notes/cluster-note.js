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
            var data   = this.model.get('data') || {};

            var prefix = type.replace('Activity', '');
            prefix = prefix.charAt(0).toLowerCase() + prefix.slice(1);

            if (action) {
                this.messageName = prefix + action.charAt(0).toUpperCase() + action.slice(1);

                // Apply Staging/Master suffix before parent appends 'This'
                if (data.stagingRecords !== undefined || data.masterRecords !== undefined) {
                    var hasSt = data.stagingRecords && data.stagingRecords.length > 0;
                    var hasMa = data.masterRecords  && data.masterRecords.length  > 0;
                    if (hasSt && !hasMa) { this.messageName += 'Staging'; }
                    if (!hasSt && hasMa) { this.messageName += 'Master';  }
                }
            }

            Dep.prototype.init.call(this);
        },

        setup: function () {
            var data        = this.model.get('data') || {};
            var relatedType = data.relatedType || this.model.get('relatedType');
            var relatedId   = data.relatedId   || this.model.get('relatedId');
            var relatedName = this.model.get('relatedName') || data.relatedName || null;

            // Bulk-move note (movedToCluster / movedFromCluster)
            if (data.stagingRecords !== undefined || data.masterRecords !== undefined) {
                var hasSt = data.stagingRecords && data.stagingRecords.length > 0;
                var hasMa = data.masterRecords  && data.masterRecords.length  > 0;
                if (!hasSt && !hasMa) { return; }

                this.messageData['stagingRecords'] = (data.stagingRecords || []).map(function (r) {
                    var label      = Handlebars.Utils.escapeExpression(r.name || r.id);
                    var entityName = Handlebars.Utils.escapeExpression(r.entityName || '');
                    var id         = Handlebars.Utils.escapeExpression(r.id || '');
                    return '<a href="#' + entityName + '/view/' + id + '">' + label + '</a>';
                }).join(', ');

                this.messageData['masterRecords'] = (data.masterRecords || []).map(function (r) {
                    var label      = Handlebars.Utils.escapeExpression(r.name || r.id);
                    var entityName = Handlebars.Utils.escapeExpression(r.entityName || '');
                    var id         = Handlebars.Utils.escapeExpression(r.id || '');
                    return '<a href="#' + entityName + '/view/' + id + '">' + label + '</a>';
                }).join(', ');

                var clusterLabel = Handlebars.Utils.escapeExpression(
                    data.clusterNumber != null ? String(data.clusterNumber) : (data.clusterId || '')
                );
                this.messageData['clusterLink'] = data.clusterId
                    ? '<a href="#Cluster/view/' + data.clusterId + '">' + clusterLabel + '</a>'
                    : clusterLabel;

                this.createMessage();
                return;
            }

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
