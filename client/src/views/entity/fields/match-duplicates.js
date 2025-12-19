/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity/fields/match-duplicates', 'views/fields/bool',
    Dep => Dep.extend({

        initInlineEdit() {
            Dep.prototype.initInlineEdit.call(this);

            const $cell = this.getCellElement();
            const inlineActions = this.getInlineActionsContainer();

            $cell.find('.configure-matching').parent().remove();

            if (this.model.get(this.name)) {
                const $link = $(`<a href="${this.getLinkUrl()}" class="hidden" title="${this.translate('configureMatching', 'labels', 'Matching')}"><i class="ph ph-gear-six configure-matching"></i></a>`);
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

        getLinkUrl() {
            return `/#Matching/view/${this.model.id}-D2D`;
        },

    })
);

