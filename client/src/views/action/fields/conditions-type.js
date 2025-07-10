/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/fields/conditions-type', 'views/fields/enum',
    Dep => {

        return Dep.extend({

            entityTypeField: null,

            setup() {
                Dep.prototype.setup.call(this);

                this.entityTypeField = this.model.name === 'Action' ? 'sourceEntity' : 'entityType';

                this.prepareOptionsList();
                this.listenTo(this.model, `change:${this.entityTypeField}`, () => {
                    this.model.set(this.name, null);
                    this.prepareOptionsList();
                    this.reRender();
                });
            },

            prepareOptionsList() {
                this.params.options = ['', 'basic', 'script'];
                this.translatedOptions = {
                    '': '',
                    'basic': this.translate('basic'),
                    'script': this.translate('script')
                };

                if (this.model.get(this.entityTypeField)) {
                    $.each(this.getMetadata().get('app.conditionsTypes') || {}, (type, item) => {
                        if (item.entityName === this.model.get(this.entityTypeField)) {
                            this.params.options.push(type);
                            this.translatedOptions[type] = item.label;
                        }
                    })
                }
            },

            initStatusContainer() {
                Dep.prototype.initStatusContainer.call(this);

                if (this.model.get('conditionPhpCode')) {
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
                    this.createView('dialog', 'views/modals/php-code', {model: this.model, phpCode: this.model.get('conditionPhpCode')}, dialog => {
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

        });
    });
