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
                if(this.disabled) {
                    return;
                }
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

            this.filterList = this.options.filterList.map(item => {
                return {
                    name: item,
                    label: this.translate(item === 'posts' ? 'notes': item, 'filters', 'Note'),
                    action: "filter",
                    isActive: this.activeFilters.includes(item)
                };
            });
        },

        data: function () {
            return {
                filterList: this.filterList
            }
        },

        enableButtons: function () {
            this.disabled = false;
            $('a[data-action="filter"]').removeClass('disabled');
        },

        disableButtons: function () {
            this.disabled = true;
            $('a[data-action="filter"]').addClass('disabled');
        }
    })
});