/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/search/filter', 'views/search/filter', function (Dep) {

    return Dep.extend({

        template: 'treo-core:search/filter',

        pinned: false,

        events: {
            'click a[data-action="pinFilter"]': function (e) {
                e.stopPropagation();
                e.preventDefault();

                this.pinned = !this.pinned;

                if (this.pinned) {
                    this.$el.find('.pin-filter').addClass('pinned');
                } else {
                    this.$el.find('.pin-filter').removeClass('pinned');
                }

                this.trigger('pin-filter', this.pinned);
            },
        },

        data: function () {
            let isPinEnabled = false;

            if (this.getParentView() && this.getParentView().getParentView()) {
                const parent =  this.getParentView().getParentView();

                if (('$el' in parent) && parent.$el.is('div#main')) {
                    isPinEnabled = true;
                }
            }

            return {
                generalName: this.generalName,
                name: this.name,
                scope: this.model.name,
                notRemovable: this.options.notRemovable,
                isPinEnabled: isPinEnabled,
                pinned: this.pinned
            };
        },

        setup: function () {
            var newName = this.name = this.options.name;
            this.generalName = newName.split('-')[0];
            var type = this.model.getFieldType(this.generalName);

            this.pinned = this.options.pinned;

            if (type) {
                var viewName = this.model.getFieldParam(this.generalName, 'view') || this.getFieldManager().getViewName(type);

                this.createView('field', viewName, {
                    mode: 'search',
                    model: this.model,
                    el: this.options.el + ' .field',
                    defs: {
                        name: this.generalName,
                    },
                    searchParams: this.options.params,
                });
            }
        }
    });
});