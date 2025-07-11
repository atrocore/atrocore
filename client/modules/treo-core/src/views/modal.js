/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/modal', 'class-replace!treo-core:views/modal', function (Dep) {

    return Dep.extend({

        init() {
            Dep.prototype.init.call(this);

            $(window).on('keydown', e => {
                if (e.keyCode === 69 && e.ctrlKey) {
                    e.preventDefault();
                }
                if (e.keyCode === 83 && e.ctrlKey) {
                    e.preventDefault();
                    if (typeof this.actionSave === 'function') {
                        this.actionSave();
                    }
                }
            });

            this.listenTo(this, 'after:render', () => {
                const headerHeight = this.$el.find('header.modal-header').outerHeight();
                const footerHeight = this.$el.find('footer.modal-footer').outerHeight();

                let diffHeight = headerHeight + footerHeight + $('.navbar .navbar-header').outerHeight();

                const adjustHeight = () => {
                    const el = this.$el.find('.modal-content')[0]
                    if (!el) {
                        return
                    }
                    const contentComputed = getComputedStyle(el);
                    const windowHeight = window.innerHeight;
                    const windowWidth = window.innerWidth;
                    const cssParams = {
                        overflow: 'auto'
                    };
                    this.dialog.$el.css('paddingLeft', 0);
                    this.dialog.$el.css('paddingRight', 0);
                    if (!this.dialog.options.fullHeight && windowHeight < 512) {
                        cssParams.maxHeight = 'none';
                        cssParams.height = 'none';
                    } else if (this.dialog.options.fullHeight) {
                        let marginTop = parseInt(contentComputed.marginTop);
                        let marginBottom = parseInt(contentComputed.marginBottom);
                        let height = windowHeight - diffHeight - marginTop - marginBottom;

                        cssParams.height = height + 'px';
                        cssParams.maxHeight = height + 'px';
                    } else {
                        if (windowWidth <= this.dialog.options.screenWidthXs) {
                            cssParams.maxHeight = 'none';
                        } else {
                            cssParams.maxHeight = (windowHeight - diffHeight) + 'px';
                        }
                    }

                    this.$el.find('div.modal-body').css(cssParams);

                    if (this.$el.find('div.modal-body').length) {
                        if (this.$el.find('div.modal-body')[0].scrollHeight <= this.$el.find('div.modal-body').innerHeight()) {
                            if (this.$el.find('input.selectized').length) {
                                this.$el.find('div.modal-body').css('overflow', 'inherit');
                            }
                        }
                    }
                };
                $(window).off('resize.adjust-modal-height').on('resize.adjust-modal-height', adjustHeight);
                adjustHeight();
            });
        },

    });
});