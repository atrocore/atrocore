/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/preview-template/record/modals/preview', 'views/modal',
    Dep => Dep.extend({
        template: 'preview-template/modals/preview',
        className: 'full-page-modal',
        htmlContent: null,
        profile: 'desktop',
        profiles: {
            desktop: {
                width: "1920px",
                height: "1080px",
                title: "1920x1080"
            },
            tablet: {
                width: "1024px",
                height: "1366px",
                "title": "IPad pro: 1024*1366"
            },
            mobile: {
                width: "390px",
                height: "844px",
                title: "Iphone 12 pro: 390x844"
            }
        },
        events: {
            'click a.close': function () {
                this.actionClose();
            },
            'click [data-action="changeProfile"]': function (e) {
                this.changeProfile($(e.currentTarget).data());
            }
        },
        setup() {
            Dep.prototype.setup.call(this);
            this.htmlContent = this.options.htmlContent;
        },
        changeProfile(data) {
            this.profile = data.profile;
            this.reRender()
        },
        data() {
            let data = {};
            data['htmlContent'] = this.htmlContent;
            data['isTablet'] = this.profile === 'tablet';
            data['isMobile'] = this.profile === 'mobile';
            data['isDesktop'] = this.profile === 'desktop';
            data['size'] = this.profiles[this.profile];

            return data;
        },
        loadHmtlPage() {
            if (!this.htmlContent) {
                return;
            }

            var iframe = document.querySelector('.html-preview iframe');
            iframe.contentWindow.document.open();
            iframe.contentWindow.document.write(this.htmlContent);
            iframe.contentWindow.document.close();

        },
        afterRender() {
            Dep.prototype.afterRender.call(this);
            this.loadHmtlPage();
        }
    })
);