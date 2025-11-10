/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/panels/side/matched-records', 'view', Dep => {
        return Dep.extend({

            template: "record/panels/side/matched-records",

            matches: null,

            setup() {
                Dep.prototype.setup.call(this);

                if (this.model.get("id")) {
                    this.getMatchedRecords();
                } else {
                    this.listenToOnce(this.model, "sync", () => {
                        if (this.model.get("id")) {
                            this.getMatchedRecords();
                        }
                    });
                }
            },

            afterRender() {
                Dep.prototype.afterRender.call(this);

                (this.matches || []).forEach(item => {
                    (item.list || []).forEach(record => {
                        this.createView(record.mrId, 'views/matched-record/record/row-actions/right-sidebar', {
                            name: record.mrId,
                            model: this.model,
                            status: record.status,
                            el: `${this.options.el} .matched-record-item-actions[data-name="${record.mrId}"]`
                        }, view => {
                            view.render();
                            this.listenToOnce(view, 'after:save', () => {
                                this.getMatchedRecords();
                            });
                        });
                    })
                })
            },

            data() {
                return {
                    hasMatches: this.matches !== null,
                    matches: this.matches
                };
            },

            getMatchedRecords() {
                this.ajaxGetRequest('MatchedRecord/action/matchedRecords', {
                    code: this.name,
                    entityName: this.model.name,
                    entityId: this.model.id,
                    statuses: this.options.selectedFilters || [],
                }).success(res => {
                    this.matches = [];
                    (res.matches || []).forEach(item => {
                        let row = {
                            status: this.getLanguage().translateOption(item.status, 'status', 'MatchedRecord'),
                            list: []
                        };
                        (item.list || []).forEach(record => {
                            row.list.push({
                                status: item.status,
                                mrId: record.mrId,
                                label: record.name,
                                link: `/#${res.entityName}/view/${record.id}`,
                                score: record.score
                            })
                        });

                        if (row.list.length > 0) {
                            this.matches.push(row);
                        }
                    })
                    this.reRender();
                });
            },

        });
    }
);