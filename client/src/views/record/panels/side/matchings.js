/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/panels/side/matchings', 'view', Dep => {
    return Dep.extend({

        template: "record/panels/side/matchings",

        events: _.extend({
            'click [data-action="findMatches"]': function (e) {
                console.log($(e.currentTarget).data('name'));
            },
        }, Dep.prototype.events),

        setup() {
            Dep.prototype.setup.call(this);

            this.matchesList = [];
            $.each((this.getConfig().get('referenceData')?.Matching || {}), (code, item) => {
                if (item.isActive) {
                    if (item.stagingEntity === this.model.name) {
                        this.matchesList.push({
                            name: code,
                            label: item.name,
                        });
                    } else if (item.masterEntity === this.model.name) {
                        this.matchesList.push({
                            name: code,
                            label: item.foreignName,
                        });
                    }
                }
            })
        },

        data() {
            return {
                matchesList: this.matchesList
            };
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.matchesList.forEach(item => {
                this.createView(item.name, 'views/record/panels/side/matched-records', {
                    name: item.name,
                    model: this.model,
                    el: `${this.options.el} .list-container[data-name="${item.name}"]`
                }, view => {
                    view.render();
                });
            })
        },

    });
}
);