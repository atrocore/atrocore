/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/preview-template/record/actions/preview', 'view',
    Dep => Dep.extend({

        _template: '',

        showHtmlPreview() {
            this.notify('Loading...');
            let htmlTemplateId = this.options.button.name;
            this.ajaxGetRequest('PreviewTemplate/action/getHtmlPreview', {
                previewTemplateId: this.options.button.name,
                entityId: this.model.get('id')
            }).success(res => {
                this.createView('htmlPreviewModal' + htmlTemplateId, 'views/preview-template/record/modals/preview', {
                    htmlContent: res.htmlPreview ?? '',
                    modalTitle: this.options.button.label || null
                }, view => {
                    view.render();
                    this.notify(false);

                    view.on('after:render', e => {
                        const iframe = document.querySelector('.html-preview iframe');
                        if (iframe) {
                            const overlay = `<div style="position: fixed;top: 0;right: 0;padding: 6px 12px;background-color: #ececec;border-bottom-left-radius: 3px;font-size: 0.85em;user-select: none">${iframe.contentDocument.documentElement.scrollWidth} x ${iframe.contentWindow.innerHeight}</div>`;
                            iframe.contentDocument.body.insertAdjacentHTML('beforeend', overlay);
                        }
                    });
                });
            });
        }
    })
);
