/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/composer/record/row-actions/store', 'views/record/row-actions/default',
    Dep => Dep.extend({

        afterRender() {
            if (this.model.get('inStoreLink')) {
                this.model.set('status', 'buyable');
            }

            if (this.model.get('status') === 'buyable') {
                let storeLink = this.model.get('inStoreLink') || 'https://store.atrocore.com';
                storeLink += '?instanceId=' + this.getConfig().get('appId');

                const titleStr = this.model.get('purchasePrice') ? `${this.translate('Purchase price:')} €${this.model.get('purchasePrice')}` : this.translate('Сlick to purchase!');
                const rentalPriceStr = this.model.get('rentalPrice') ? `€${this.model.get('rentalPrice')}/mo` : this.translate('Purchase');

                this.$el.html(`<a role="button" href="${storeLink}" target="_blank" title="${titleStr}" class="btn btn-sm btn-default" style="padding:4px 8px;border-radius:4px"><i class="ph ph-wallet"></i> <span>${rentalPriceStr}</span></a>`);
            } else if (this.model.get('status') === 'available') {
                this.$el.html(`<button class="btn btn-sm btn-default" style="padding:4px 8px;border-radius:4px"><i class="ph-fill ph-download-simple"></i> <span>${this.translate('Install')}</span></button>`);
            }

            this.$el.css('text-align', 'right').css('vertical-align', 'middle');
        },

        getActionList() {
            return [];
        },

    })
);
