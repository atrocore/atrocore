/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/fields/type', 'views/fields/enum',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            $.each((this.getMetadata().get('action.typesData') || {}), (type, data) => {
                if (data.typeLabel) {
                    this.translatedOptions[type] = data.typeLabel;
                }
            });

            this.listenTo(this.model, `change:${this.name}`, () => {
                let name = this.getMetadata().get(`action.typesData.${this.model.get(this.name)}.name`);
                if (name && !this.model.get('name')) {
                    this.model.set('name', name);
                }

                let description = this.getMetadata().get(`action.typesData.${this.model.get(this.name)}.description`);
                if (description && !this.model.get('description')) {
                    this.model.set('description', description);
                }
            });

        },

        initStatusContainer() {
            Dep.prototype.initStatusContainer.call(this);

            if (this.mode !== 'list' && this.model.get('typePhpCode')) {
                this.initShowCodeModal();
            }
        },

        initShowCodeModal() {
            const $cell = this.getCellElement();
            const inlineActions = this.getInlineActionsContainer();

            $cell.find('.show-code').parent().remove();

            const $link = $(`<a href="javascript:" class="code-link hidden" title="${this.translate('phpCode')}"><i class="ph ph-code show-code"></i></a>`);

            if (inlineActions.size()) {
                inlineActions.prepend($link);
            } else {
                $cell.prepend($link);
            }

            $link.on('click', () => {
                this.notify('Loading...');
                this.createView('dialog', 'views/modals/php-code', {model: this.model, phpCode: this.model.get('typePhpCode')}, dialog => {
                    dialog.render();
                    this.notify(false);
                });
            });

            $cell.on('mouseenter', e => {
                e.stopPropagation();
                if (this.mode === 'detail') {
                    $link.removeClass('hidden');
                }
            }).on('mouseleave', e => {
                e.stopPropagation();
                if (this.mode === 'detail') {
                    $link.addClass('hidden');
                }
            });
        },

    })
);
