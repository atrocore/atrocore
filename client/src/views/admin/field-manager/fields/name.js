/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/name', ['views/fields/varchar', 'lib!MD5'], (Dep, MD5) => {

    return Dep.extend({

        initInlineActions() {
            Dep.prototype.initInlineActions.call(this);

            this.listenTo(this, 'after:render', this.initInlineLabelEdit, this);
        },

        initInlineLabelEdit() {
            let $cell = this.getCellElement();

            $cell.find('.fa-globe').parent().remove();

            let $link = $('<a href="javascript:" class="pull-right inline-label-edit-link hidden" style="margin-left: 7px"><span class="fas fa-globe fa-sm"></span></a>');

            if ($cell.size() === 0) {
                this.listenToOnce(this, 'after:render', this.initInlineLabelEdit, this);
                return;
            }

            $cell.prepend($link);

            $link.on('click', () => {
                Espo.Ui.notify(this.translate('pleaseWait', 'messages'));
                let scope = 'Translation';
                let viewName = this.getMetadata().get(`clientDefs.${scope}.modalViews.edit`) || 'views/modals/edit';
                let id = MD5(`${this.model.get('entityId')}.fields.${this.model.get('code')}`);

                this.ajaxGetRequest(`${scope}/${id}`).then(res => {
                    this.getModelFactory().create(scope, model => {
                        model.set(res);
                        let options = {
                            scope: scope,
                            id: res.id,
                            model: model,
                            fullFormDisabled: this.getMetadata().get('clientDefs.' + scope + '.modalFullFormDisabled') || false,
                        };

                        this.createView('modal', viewName, options, view => {
                            Espo.Ui.notify(false);
                            view.render();

                            this.listenToOnce(view, 'remove', () => {
                                this.clearView('modal');
                            });

                            this.listenToOnce(view, 'after:save', m => {
                                this.model.fetch();
                            });
                        });
                    });
                });
            });

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
        },

    });
});
