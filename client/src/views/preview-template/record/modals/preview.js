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
                width: "100%",
                height: "100%",
                minWidth: "1280px",
                title: "Desktop"
            },
            tablet: {
                width: "1024px",
                height: "1366px",
                title: "IPad pro: 1024x1366"
            },
            mobile: {
                width: "390px",
                height: "844px",
                title: "Iphone 12 pro: 390x844"
            }
        },

        events: {
            'click [data-action="changeProfile"]': function (e) {
                this.changeProfile($(e.currentTarget).data());
            },
            'click [data-action="close-modal"]': function () {
                this.actionClose();
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

        loadHtmlPage() {
            if (!this.htmlContent) {
                return;
            }

            const iframe = document.querySelector('.html-preview iframe');
            iframe.contentWindow.document.open();
            iframe.contentWindow.document.write(this.htmlContent);
            iframe.contentWindow.document.close();

            const sizes = this.profiles[this.profile];
            iframe.contentDocument.documentElement.style.minWidth = sizes.minWidth || sizes.width;
        },

        loadBreadcrumbs() {
            const breadcrumbs = document.querySelector('#main .header .header-breadcrumbs');
            const modal = this.$el.get(0);
            if (!modal || !breadcrumbs || !this.options.modalTitle) {
                return;
            }

            const header = modal.querySelector('.header-container');
            if (!header) {
                return;
            }

            const modalBreadcrumbs = breadcrumbs.cloneNode(true);
            const wrapper = modalBreadcrumbs.querySelector('.breadcrumbs-wrapper');
            if (!wrapper) {
                return;
            }

            modalBreadcrumbs.classList.remove('fixed-header-breadcrumbs');

            try {
                wrapper.lastChild.classList.add('subsection');
                wrapper.lastChild.dataset.action = 'close-modal';
                wrapper.lastChild.innerHTML = `<a href="javascript:">${wrapper.lastChild.textContent}</a>`;
            } catch (e) {
            }

            const lastItem = document.createElement('span');
            lastItem.textContent = this.options.modalTitle;

            wrapper.append(lastItem);
            header.prepend(modalBreadcrumbs);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            this.loadHtmlPage();
            this.loadBreadcrumbs();
        }
    })
);