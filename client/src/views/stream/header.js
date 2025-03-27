/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/stream/header', 'view', function (Dep) {
    return Dep.extend({

        template: 'stream/header',

        activeFilters: [],

        events: {
            'click a[data-action="filter"]': function (e) {
                e.preventDefault();
                let name = $(e.target).data('name')
                if ($(e.target).hasClass('active')) {
                    $(e.target).removeClass('active');
                    this.activeFilters = this.activeFilters.filter(v => v !== name)
                } else {
                    $(e.target).addClass('active');
                    if (!this.activeFilters.includes(name)) {
                        this.activeFilters.push(name);
                    }
                }

                this.trigger('filter-update', this.activeFilters);
            }
        },


        setup() {

            this.scope = this.options.scope;

            this.activeFilters = this.options.activeFilters ?? [];

            if(!Array.isArray(this.activeFilters)) {
                this.activeFilters = [this.activeFilters];
            }

            this.filterList = [
                {
                    name: "posts",
                    label: this.translate('notes', 'filters', 'Note'),
                    action: "filter",
                    isActive: this.activeFilters.includes('notes')

                },
                {
                    name: "updates",
                    label: this.translate('updates', 'filters', 'Note'),
                    action: "filter",
                    isActive: this.activeFilters.includes('updates')
                },
                {
                    name: "emails",
                    label: this.translate('emails', 'filters', 'Note'),
                    action: "filter",
                    isActive: this.activeFilters.includes('emails')
                }
            ];
        },

        data: function () {
            return {
                filterList: this.filterList
            }
        }
    })
});