/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/site/footer', 'class-replace!treo-core:views/site/footer', function (Dep) {

    return Dep.extend({

        template: 'treo-core:site/footer',

        events: {
            'click .scroll-top': function (e) {
                e.preventDefault();

                let bodyHtml = $('body, html');
                bodyHtml.animate({
                    scrollTop: bodyHtml.offset().top
                }, 500);
            }
        },

        data() {
            return {
                year: moment().format("YYYY"),
                version: this.getConfig().get('coreVersion') || ''
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            this.showScrollTop()
        },

        showScrollTop() {
            let btn = this.$el.find('.scroll-top');
            let $window = $(window);

            $window.on('scroll', function () {
                $window.scrollTop() >= 300 ? btn.removeClass('hidden') : btn.addClass('hidden');
            });
        }

    });

});


