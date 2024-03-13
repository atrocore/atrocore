/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/record/row-actions/default', 'class-replace!treo-core:views/record/row-actions/default', function (Dep) {

    return Dep.extend({

        template: 'treo-core:record/row-actions/default',

        events: {
            'click .action': function (e) {
                var $el = $(e.currentTarget);
                var action = $el.data('action');
                var method = 'action' + Espo.Utils.upperCaseFirst(action);
                if (typeof this[method] === 'function') {
                    var data = $el.data();
                    this[method](data, e);
                    e.preventDefault();
                }
            }
        },

        getActionList() {
            let list = Dep.prototype.getActionList.call(this);

            let rowActions = this.getMetadata().get(['clientDefs', this.model.name, 'additionalRowActions']) || {};
            Object.keys(rowActions).forEach((item) => {
                let action = {
                    action: rowActions[item].action,
                    label: rowActions[item].label
                };
                if (rowActions[item].iconClass) {
                    let htmlLogo = `<span class="additional-action-icon ${rowActions[item].iconClass}"></span>`;
                    action.html = `${this.translate(rowActions[item].label, 'labels', this.model.name)} ${htmlLogo}`;
                }
                list.push(action);

                let method = 'action' + Espo.Utils.upperCaseFirst(rowActions[item].action);
                this[method] = function () {
                    let path = rowActions[item].actionViewPath;

                    let o = {};
                    (rowActions[item].optionsToPass || []).forEach((option) => {
                        if (option in this) {
                            o[option] = this[option];
                        }
                    });

                    this.createView(item, path, o, (view) => {
                        if (typeof view[rowActions[item].action] === 'function') {
                            view[rowActions[item].action]();
                        }
                    });
                };
            }, this);

            return list;
        },

    });
});