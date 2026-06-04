/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/classification-attribute/modals/edit',
    ['views/modals/edit', 'views/search/search-filter-opener'],
    (Dep, SearchFilterOpener) => Dep.extend({

        fullFormDisabled: true,

        setup() {
            Dep.prototype.setup.call(this);

            this.buttonList.push({
                title: this.translate('openSearchFilter'),
                name: 'filterButton',
                hidden: true,
                html: this.getFilterButtonHtml(),
                onClick: () => {
                    const data = this.model.get('data') || {};
                    SearchFilterOpener.prototype.open.call(
                        this,
                        this.getFilterScope(),
                        data.where,
                        ({where, whereData}) => {
                            this.model.set('data', Object.assign({}, this.model.get('data'), {
                                where,
                                whereData,
                                whereScope: this.getFilterScope()
                            }));
                            this.$el.find('button[data-name="filterButton"]').html(this.getFilterButtonHtml());
                        }
                    );
                }
            });

            this.listenTo(this.model, 'change:attributeType change:attributeEntityType', () => {
                this.checkFilterButtonVisibility();
            });

            this.listenTo(this, 'after:save', model => {
                $('.action[data-action=refresh][data-panel=classificationAttributes]').click();
                /**
                 * Show another notify message if attribute '%s' was linked not for all chosen channels
                 */
                if (model.get('channelsNames') === true) {
                    let message = this.getLanguage().translate('savedForNotAllChannels', 'messages', 'ClassificationAttribute');
                    Espo.Ui.notify(message.replace('%s', model.get('attributeName')), 'success', 1000 * 60 * 60 * 2, true);
                }
            });
        },

        getFilterButtonHtml() {
            const data = this.model.get('data') || {};
            if (Array.isArray(data.where) && data.where.length > 0) {
                return `<i class="ph-fill ph-binoculars" style="color:#06c"></i>`;
            }
            return `<i class="ph ph-binoculars"></i>`;
        },

        getFilterScope() {
            return this.model.get('attributeEntityType');
        },

        checkFilterButtonVisibility() {
            const type = this.model.get('attributeType');
            if (['link', 'linkMultiple'].includes(type) && this.getFilterScope()) {
                this.$el.find('button[data-name="filterButton"]').removeClass('hidden');
            } else {
                this.$el.find('button[data-name="filterButton"]').addClass('hidden');
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            this.$el.find('button[data-name="filterButton"]').html(this.getFilterButtonHtml());
            this.checkFilterButtonVisibility();
        }

    })
);

