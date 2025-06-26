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

        fullHeight: true,

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
                minWidth: "768px",
                title: "IPad pro: 1024x1366"
            },
            mobile: {
                width: "390px",
                height: "844px",
                title: "Iphone 12 pro: 390x844"
            }
        },

        editorActive: false,

        selectedElement: null,

        frame: null,

        useAutosave: true,

        canChangeLanguage: false,

        languages: [],

        selectedLanguage: null,

        events: {
            'click [data-action="changeProfile"]': function (e) {
                this.changeProfile(e);
            },
            'click [data-action="close-modal"]': function () {
                this.actionClose();
            },
            'click [data-action=toggleEditor]': function (e) {
                this.toggleEditor(e);
            },
            'change select.language-selector': function (e) {
                this.selectedLanguage = this.languages.find(lang => lang.code === e.target.value);
                this.reloadFrame(this.selectedElement);
            }
        },

        toggleEditor(e) {
            const iframe = document.querySelector('.html-preview iframe');
            if (iframe) {
                iframe.contentDocument.body.classList.toggle('highlight')
                let toRemove = 'btn-primary', toAdd = 'btn-default';
                if (iframe.contentDocument.body.classList.contains('highlight')) {
                    toRemove = 'btn-default';
                    toAdd = 'btn-primary';
                }

                e.currentTarget.classList.remove(toRemove);
                e.currentTarget.classList.add(toAdd);
            }
        },

        changeProfile(e) {
            const data = e.currentTarget.dataset;
            this.profile = data.profile;
            if (this.frame) {
                const profileData = this.profiles[this.profile];
                this.frame.style.width = profileData.width;
                this.frame.style.height = profileData.height;

                this.prepareFrameDimensions(this.frame);
            }

            const btnGroup = e.currentTarget.parentElement;
            if (btnGroup) {
                for (let i = 0; i < btnGroup.children.length; i++) {
                    const el = btnGroup.children[i];

                    el.classList.remove('btn-primary');
                    el.classList.add('btn-default');
                }
            }

            e.currentTarget.classList.remove('btn-default');
            e.currentTarget.classList.add('btn-primary');
        },

        data() {
            return {
                title: this.options.modalTitle,
                size: this.profiles[this.profile],
                isTablet: this.profile === 'tablet',
                isMobile: this.profile === 'mobile',
                isDesktop: this.profile === 'desktop',
                editorActive: this.editorActive,
            };
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.languages = Object.values(this.getConfig().get('referenceData').Language)
                .map(lang => ({code: lang.code, name: lang.name, main: lang.role === 'main'}))
                .sort((x, y) => (x.main === y.main) ? 0 : x.main ? -1 : 1); // main lang should be first

            this.selectedLanguage = this.languages[0];
        },

        loadPreviewFrame(afterLoad = null) {
            if (this.htmlContent !== null) {
                this.loadHtmlPage(this.htmlContent);
                if (afterLoad instanceof Function) {
                    afterLoad();
                }

                return;
            }

            this.getPreviewRequest().success(res => {
                this.htmlContent = res.htmlPreview ?? '';

                if (typeof res.hasMultipleLanguages === 'boolean') {
                    this.canChangeLanguage = res.hasMultipleLanguages;
                }

                this.$el.find('.language-container').empty();

                if (this.canChangeLanguage && this.languages.length > 1) {
                    const selector = $('<select class="language-selector"></select>');
                    this.$el.find('.language-container').append(selector);

                    selector.selectize({
                        setFirstOptionActive: true,
                        persist: false,
                        valueField: "code",
                        labelField: "name",
                        searchField: ["name", "code"],
                        options: this.languages,
                        items: [this.selectedLanguage.code]
                    });
                }

                this.notify(false);
                this.loadHtmlPage(this.htmlContent);
                if (afterLoad instanceof Function) {
                    afterLoad();
                }
            });
        },

        getPreviewRequest() {
            const options = {};
            if (this.selectedLanguage && !this.selectedLanguage.main) {
                options.headers = {
                    language: this.selectedLanguage.code
                }
            }

            return this.ajaxGetRequest('PreviewTemplate/action/getHtmlPreview', {
                previewTemplateId: this.options.previewTemplateId,
                entityId: this.options.entityId
            }, options);
        },

        loadHtmlPage(htmlContent) {
            if (!htmlContent) {
                return;
            }

            if (!this.frame) {
                return;
            }

            this.frame.contentWindow.document.open();
            this.frame.contentWindow.document.write(htmlContent);
            this.frame.contentWindow.document.close();

            this.prepareFrameDimensions(this.frame);

            this.loadFrameCssFile('client/css/preview.css');

            this.prepareEditorElements(this.frame.contentDocument);
        },

        loadFrameCssFile(filename) {
            const link = this.frame.contentDocument.createElement("link");
            link.rel = "stylesheet";
            link.type = "text/css";
            link.href = filename;

            if (Espo?.loader?.cacheTimestamp) {
                link.href += `?cacheTimestamp=${Espo.loader.cacheTimestamp}`;
            }

            this.frame.contentDocument.head.appendChild(link);

        },

        prepareFrameDimensions(iframe) {
            const sizes = this.profiles[this.profile];
            const overlayEl = iframe.contentDocument.querySelector('#dimensions-overlay');

            iframe.contentDocument.documentElement.style.minWidth = sizes.minWidth || null;

            if (overlayEl) {
                overlayEl.textContent = `${iframe.contentDocument.documentElement.scrollWidth} x ${iframe.contentWindow.innerHeight}`;
            } else {
                const overlay = `<div id="dimensions-overlay">${iframe.contentDocument.documentElement.scrollWidth} x ${iframe.contentWindow.innerHeight}</div>`;
                iframe.contentDocument.body.insertAdjacentHTML('beforeend', overlay);
            }
        },

        prepareEditorElements(document) {
            document.querySelectorAll('[data-editor-type]').forEach(el => {
                el.addEventListener('click', () => this.prepareEditableElement(el));
            });
        },

        prepareEditableElement(el) {
            if (this.selectedElement) {
                if (this.selectedElement === el) {
                    return;
                }

                this.selectedElement.classList.remove('active');
                this.selectedElement = null;
            }

            this.selectedElement = el;
            el.classList.add('active')

            const scope = el.dataset.editorType;
            const id = el.dataset.editorId;
            const fields = el.dataset.editorFields?.split(',');

            this.displaySidePanel(scope, id, fields, el);
        },

        displaySidePanel(scope, id, fields = [], trigger = null) {
            const container = document.querySelector('.html-preview .side-container');
            if (!container) {
                return;
            }

            this.clearView('sideEdit');

            container.classList.add('active');
            this.prepareFrameDimensions(this.frame);

            this.createView('sideEdit', 'views/preview-template/record/panels/side-edit', {
                el: '.full-page-modal .html-preview .side-container',
                scope: scope,
                id: id,
                autosaveDisabled: !this.useAutosave,
                fields: fields
            }, view => {
                this.listenToOnce(view, 'cancel', () => {
                    container.classList.remove('active');
                    trigger?.classList.remove('active');

                    this.selectedElement.classList.remove('active');
                    this.selectedElement = null;

                    this.prepareFrameDimensions(this.frame);
                    view.remove();
                });

                this.listenToOnce(view, 'remove', () => {
                    this.clearView('sideEdit');
                });

                this.listenTo(view, 'record:after:save', () => {
                    this.reloadFrame(trigger)
                });

                this.listenTo(view, 'autosaveChanged', (value) => {
                    this.useAutosave = value;
                });

                view.render();
            });
        },

        reloadFrame(trigger = null) {
            this.htmlContent = null;
            let callback = null;

            // if there was a trigger element, activate it after a new render
            if (trigger) {
                const scope = trigger.dataset.editorType;
                const id = trigger.dataset.editorId;
                const fields = trigger.dataset.editorFields ?? null;

                if (scope && id) {
                    callback = () => {
                        trigger = this.frame?.contentDocument?.querySelector(`[data-editor-type="${scope}"][data-editor-id="${id}"]${fields ? `[data-editor-fields="${fields}"]` : ''}`);
                        this.selectedElement = trigger;

                        if (this.getView('sideEdit')) {
                            trigger?.classList.add('active');
                        }
                    }
                }
            }

            this.loadPreviewFrame(callback);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.frame = document.querySelector('.html-preview iframe');
            this.loadPreviewFrame(() => {
                let selector = null;
                if (this.options.selectedScope && this.options.selectedId) {
                    selector = `[data-editor-type="${this.options.selectedScope}"][data-editor-id="${this.options.selectedId}"]`;

                    if (this.options.selectedField) {
                        selector += `[data-editor-fields*="${this.options.selectedField}"]`;
                    }
                }

                if (selector) {
                    const el = this.frame?.contentDocument?.querySelector(selector);
                    if (el) {
                        el.click();

                        setTimeout(() => {
                            this.frame.contentWindow.scrollTo({
                                top: el.getBoundingClientRect().top,
                                left: 0,
                                behavior: 'smooth'
                            });
                        }, 300);
                    }
                }
            });
        }
    })
);