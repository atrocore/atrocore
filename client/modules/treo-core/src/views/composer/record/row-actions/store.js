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

        setup() {
            Dep.prototype.setup.call(this);

            const locales = this.getConfig().get('locales');
            let localeId = this.getUser() ? this.getUser().get('localeId') : null;
            if (!localeId || !locales[localeId]) {
                localeId = this.getConfig().get('locale');
                if (!locales[localeId]) {
                    localeId = 'main';
                }
            }

            const locale = locales[localeId];
            if (locale) {
                this.thousandSeparator = locale['thousandSeparator'] || '';
                this.decimalMark = locale['decimalMark']
            }
        },

        afterRender() {
            if (this.model.get('status') === 'buyable') {
                let storeLink = this.model.get('inStoreLink') || 'https://store.atrocore.com';
                storeLink += '?instanceId=' + this.getConfig().get('appId');

                const titleStr = this.model.get('purchasePrice') ? `Shown price is the rental price for 3 months. Purchase price is €${this.formatNumber(this.model.get('purchasePrice'))}.` : this.translate('Click to purchase!');
                const rentalPriceStr = this.model.get('rentalPrice') ? `€${this.formatNumber(this.model.get('rentalPrice'))}` : this.translate('Purchase');

                this.$el.html(`<a role="button" href="${storeLink}" target="_blank" title="${titleStr}" class="btn btn-sm btn-default" style="padding:4px 8px;border-radius:4px"><i class="ph ph-wallet"></i> <span>${rentalPriceStr}</span></a>`);
            } else if (this.model.get('status') === 'available') {
                const $button = $(`<button class="btn btn-sm btn-default" style="padding:4px 8px;border-radius:4px"><i class="ph-fill ph-download-simple"></i> <span>${this.translate('Install')}</span></button>`);
                this.$el.html($button);
                $button.on('click', () => {
                    const listView = this.getParentView().getParentView().getParentView();
                    $button.addClass('disabled')
                    listView.actionInstallModule({id: this.model.id});
                });
            }

            this.$el.css('text-align', 'right').css('vertical-align', 'middle');
        },

        getActionList() {
            return [];
        },

        formatNumber(value) {
            let parts = value.toString().split(".");

            if (this.mode !== 'edit') {
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.thousandSeparator);
            }

            return parts.join(this.decimalMark);
        },

    })
);
