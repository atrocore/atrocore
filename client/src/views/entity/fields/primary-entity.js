/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity/fields/primary-entity', 'views/fields/link',
    Dep => Dep.extend({

        createDisabled: true,

        selectBoolFilterList: ['fieldsFilter'],

        boolFilterData: {
            fieldsFilter() {
                return {
                    type: ["Base", "Hierarchy"]
                };
            }
        },

        initInlineEdit() {
            Dep.prototype.initInlineEdit.call(this);

            const $cell = this.getCellElement();
            const inlineActions = this.getInlineActionsContainer();

            $cell.find('.configure-matching').parent().remove();

            if (this.model.get(this.idName)) {
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

            if (this.model.get(this.idName) !== null && ['list', 'detail'].includes(this.mode)) {
                this.$el.html(`<a href="/#MasterDataEntity/view/${this.model.get(this.idName)}">${this.model.get(this.nameName)}</a>`);
            }
        },

    })
);

