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
                'click [data-action="refreshMatchedRecords"]': function (e) {
                    this.refreshMatchedRecords();
                },
            }, Dep.prototype.events),

            setup() {
                Dep.prototype.setup.call(this);

                this.selectedFilters = this.getStorage().get('mrFilter', this.model.name) || [];

                this.matchesList = [];
                $.each((this.getConfig().get('referenceData')?.Matching || {}), (code, item) => {
                    if (item.isActive) {
                        if (item.stagingEntity === this.model.name && this.getAcl().check(item.masterEntity, 'read')) {
                            this.matchesList.push({
                                name: code,
                                label: item.name,
                            });
                        } else if (item.masterEntity === this.model.name && this.getAcl().check(item.stagingEntity, 'read')) {
                            this.matchesList.push({
                                name: code,
                                label: item.foreignName,
                            });
                        }
                    }
                });

                this.matchesList.sort((a, b) => a.label.localeCompare(b.label));
            },

            data() {
                return {
                    matchesList: this.matchesList
                };
            },

            refreshMatchedRecords() {
                this.matchesList.forEach(item => {
                    this.createView(item.name, 'views/record/panels/side/matched-records', {
                        name: item.name,
                        model: this.model,
                        selectedFilters: this.selectedFilters,
                        el: `${this.options.el} .list-container[data-name="${item.name}"]`
                    }, view => {
                        view.render();
                    });
                })
            },

            afterRender() {
                Dep.prototype.afterRender.call(this);

                new Svelte.ContentFilter({
                    target: this.$el.find('.mr-content-filter').get(0),
                    props: {
                        scope: this.model.name,
                        allFilters: this.getMetadata().get('entityDefs.MatchedRecord.fields.status.options') || [],
                        storageKey: 'mrFilter',
                        translationScope: 'MatchedRecord',
                        translationField: 'status',
                        titleLabel: '',
                        onExecute: (evt, selectedFilters) => {
                            this.selectedFilters = selectedFilters;
                            this.refreshMatchedRecords();
                        },
                        style: "padding-bottom: 10px;"
                    }
                });

                this.refreshMatchedRecords();
            },

        });
    }
);