/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity/fields/master-entity', 'views/fields/entity-type',
    Dep => Dep.extend({

        checkAvailability(entityType) {
            if (
                Dep.prototype.checkAvailability.call(this, entityType)
                && ['Base', 'Hierarchy'].includes(this.getMetadata().get(`scopes.${entityType}.type`))
                && !this.getMetadata().get(`scopes.${entityType}.matchingDisabled`)
            ) {
                return true;
            }
        },

        initInlineEdit() {
            Dep.prototype.initInlineEdit.call(this);

            const $cell = this.getCellElement();
            const inlineActions = this.getInlineActionsContainer();

            $cell.find('.configure-matching').parent().remove();

            if (this.model.get(this.name)) {
                const $link = $(`<a href="/#Matching/view/${this.model.id}-S2M" class="hidden" title="${this.translate('configureMatching', 'labels', 'Matching')}"><i class="ph ph-gear-six configure-matching"></i></a>`);
                if (inlineActions.size()) {
                    inlineActions.prepend($link);
                } else {
                    $cell.prepend($link);
                }

                $cell.on('mouseenter', e => {
                    e.stopPropagation();
                    if (this.disabled || this.readOnly) {
                        return;
                    }
                    if (this.mode === 'detail') {
                        $link.removeClass('hidden');
                    }
                }).on('mouseleave', e => {
                    e.stopPropagation();
                    if (this.mode === 'detail') {
                        $link.addClass('hidden');
                    }
                });
            }

        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.model.get(this.name) !== null && ['list', 'detail'].includes(this.mode)) {
                this.$el.html(`<a href="/#MasterDataEntity/view/${this.model.get(this.name)}">${this.$el.html()}</a>`);
            }
        },

    })
);

