/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity-field/fields/option-code', 'views/fields/varchar', Dep => {

    return Dep.extend({

        initInlineActions() {
            Dep.prototype.initInlineActions.call(this);

            this.listenTo(this, 'after:render', this.initInlineLabelEdit, this);
        },

        initInlineLabelEdit() {
            let $cell = this.getCellElement();

            this.getInlineActionsContainer().find('.ph-pencil-simple-line').parent().remove();
            let $link = $('<a href="javascript:" class="pull-right inline-label-edit-link hidden"><i class="ph ph-pencil-simple-line"></i></a>');

            if ($cell.size() === 0) {
                this.listenToOnce(this, 'after:render', this.initInlineLabelEdit, this);
                return;
            }

            this.getInlineActionsContainer().append($link);

            $link.on('click', () => {
                this.createView('modal', 'views/entity-field/modals/update-code', {code: this.model.get('code')}, view => {
                    view.render();
                    this.listenTo(view, 'save', data => {
                        this.listenToOnce(view, 'remove', () => {
                            this.clearView('modal');
                        });

                        if(data.code === this.model.get('code')) {
                            this.notify(this.translate('notModified', 'messages'), 'warning')
                            return;
                        }

                        this.notify(this.translate('Loading...', 'messages'), 'warning')

                        $.ajax({
                            url: 'EntityField/action/updateOptionCode',
                            type: 'POST',
                            data: JSON.stringify({
                                scope: this.options.fieldModel.get('entityId'),
                                field: this.options.fieldModel.get('code'),
                                oldValue: this.model.get('code'),
                                newValue: data.code
                            }),
                            success: function () {
                                this.notify(this.translate('Loading...', 'messages'), 'warning')
                                this.options.fieldModel.fetch().then(() => {
                                    this.notify(this.translate('Saved'), 'success');
                                    view.close();
                                });
                            }.bind(this),
                        });
                    })
                })
            });

            $cell.on('mouseenter', e => {
                e.stopPropagation();
                if (this.disabled) {
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
        },

        getIconTitle() {
            return this.translate('editLabel');
        },

    });
});
