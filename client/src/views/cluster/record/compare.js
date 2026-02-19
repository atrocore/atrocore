/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/cluster/record/compare', ['views/selection/record/detail/compare', 'views/fields/colored-enum'], function (Dep, ColoredEnum) {
    return Dep.extend({

        itemScope: 'ClusterItem',

        relationName: 'clusterItems',

        isComparisonAcrossScopes() {
            return false;
        },

        actionRejectItem(e) {
            const id = $(e.currentTarget).data('selection-item-id');

            this.ajaxPostRequest(`ClusterItem/action/reject`, { id: id })
                .then(response => {
                    this.notify('Item rejected', 'success');
                    this.notify(this.translate('Loading...'));

                    const view = this.getParentView();
                    view.reloadModels(() => view.refreshContent());
                })
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            (this.getModels() || [])
                .forEach(model => {
                    const meta = model.item?.get('_meta')?.cluster || {};

                    if (meta.confirmed) {
                        this.$el.find(`th[data-id="${model.id}"]`).addClass('confirmed');
                    }

                    if (meta.golden) {
                        this.$el.find(`th[data-id="${model.id}"]`).addClass('golden');
                    }

                    if (model.item?.get('confirmedAutomatically')) {
                        this.$el.find(`th[data-id="${model.id}"]`).append('<i class="ph ph-sparkle">')
                    }
                });
        },

        getModels() {
            const models = Dep.prototype.getModels.call(this) || [];

            return models
                .sort((a, b) => {
                    const aMeta = a.item?.get('_meta')?.cluster || {};
                    const bMeta = b.item?.get('_meta')?.cluster || {};

                    if (!!aMeta.confirmed && !!!bMeta.confirmed) return -1;
                    if (!!!aMeta.confirmed && !!bMeta.confirmed) return 1;
                    return 0;
                })
                .sort((a, b) => a.item?.get('_meta')?.cluster?.golden ? -1 : 1);
        },

        getAdditionalHeaderHtml() {
            let html = `<tr class="matched-score-row">`;

            html += '<th>' + this.translate('matchedScore', 'fields', 'ClusterItem') + '</th>'

            for (let model of this.getModels()) {
                html += '<th>' + this.getMatchedScoreHtml(model.item.get('matchedScore')) + '</th>'
            }

            return html + '</tr>'
        },

        getMatchedScoreHtml(value) {
            let backgroundColor = '#CCCCCC';
            let text = ''

            if (value === null) {
                text = this.translate('N/A');
            } else {
                text = value + '%'

                if (value === 100) {
                    backgroundColor = '#CAF2C2';
                } else if (value > 74) {
                    backgroundColor = '#E0FFCC';
                } else if (value > 49) {
                    backgroundColor = '#FFF8B8';
                } else if (value > 24) {
                    backgroundColor = '#FEFFD6';
                } else {
                    backgroundColor = '#FFE7D1';
                }
            }

            let style = {
                'font-weight': 'normal',
                'background-color': backgroundColor,
                'color': ColoredEnum.prototype.getFontColor.call(this, backgroundColor),
                'border': ColoredEnum.prototype.getBorder.call(this, backgroundColor),
                'padding': '2px 5px',
                'font-size': '100%'
            };

            const styleString = Object.entries(style)
                .map(([key, value]) => `${key}: ${value}`)
                .join('; ');

            return `<span class="colored-enum label" style="${styleString}">${text}</span>`
        }
    })
})