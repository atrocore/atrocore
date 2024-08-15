/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/markdown', ['views/fields/text', 'lib!EasyMDE'], function (Dep, EasyMDE) {
    return Dep.extend({

        listTemplate: 'fields/markdown/list',

        detailTemplate: 'fields/markdown/detail',

        editor: null,

        minHeight: 200,

        maxHeight: 400,

        setup() {
            Dep.prototype.setup.call(this);

            if (this.params.maxHeight < this.params.minHeight) {
                this.params.minHeight = this.params.maxHeight;
            }

            this.minHeight = this.params.minHeight || this.minHeight;
            this.maxHeight = this.params.maxHeight || this.maxHeight;
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            const element = this.$element.get(0);

            if (this.mode === 'edit' && element && !this.readOnly) {
                this.editor = new EasyMDE({
                    element: element,
                    minHeight: `${this.minHeight}px`,
                    forceSync: true,
                    status: false,
                    initialValue: this.default,
                    sideBySideFullscreen: false,
                    previewClass: ['editor-preview', 'complex-text'],
                    toolbar: ['undo', 'redo', '|', 'heading-1', 'heading-2', 'heading-3', '|', 'bold', 'italic', 'strikethrough', '|', 'table', 'unordered-list', 'ordered-list', 'code', 'quote', 'horizontal-rule', '|', 'link', 'image', '|', 'preview', 'guide']
                });

                const scroller = this.editor.codemirror.getScrollerElement();
                if (scroller) {
                    scroller.style.maxHeight = `${this.maxHeight}px`;
                }
            }
        }
    });
});
