

Espo.define('treo-core:views/fields/wysiwyg', 'class-replace!treo-core:views/fields/wysiwyg',
    Dep => Dep.extend({

        listTemplate: 'treo-core:fields/wysiwyg/list',

        detailTemplate: 'treo-core:fields/wysiwyg/detail',

        detailMaxHeight: 400,

        showMoreText: false,

        showMoreDisabled: false,

        events: {
            'click a[data-action="seeMoreText"]': function (e) {
                this.showMoreText = true;
                this.reRender();
            }
        },

        setup() {
            this.once('render remove', function () {
                if (this.isDestroyed) return;
                let el = this.$el.find('.note-editor');
                if (el) {
                    el.popover('destroy');
                    this.isDestroyed = true;
                }
            });

            Dep.prototype.setup.call(this);

            this.detailMaxHeight = this.params.displayedHeight || this.detailMaxHeight;
            this.showMoreDisabled = this.showMoreDisabled || this.params.showMoreDisabled;
            this.showMoreText = false;
        },

        data() {
            let data = Dep.prototype.data.call(this);
            data.valueWithoutTags = data.value;
            return data;
        },

        removeTags(html) {
            return $('<textarea />').html((html || '').replace(/<(?:.|\n)*?>/gm, ' ').replace(/\s\s+/g, ' ').trim()).text();
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'detail' || this.mode === 'list') {
                if ((!this.model.has('isHtml') || this.model.get('isHtml')) && !this.showMoreText && !this.showMoreDisabled) {
                    this.applyFieldPartHiding(this.name);
                }
            }
        },

        fetch() {
            let data = Dep.prototype.fetch.call(this);
            return this.checkDataForDefaultTagsValue(data, this.name);
        },

        checkDataForDefaultTagsValue(data, field) {
            if (data[field] === '<p><br></p>') {
                data[field] = '';
            }

            if (data[field + 'Plain'] === '<p><br></p>') {
                data[field + 'Plain'] = ''
            }

            return data;
        },

        getValueForDisplay() {
            let text = this.model.get(this.name);
            if (this.mode === 'list') {
                text = this.removeTags(text);
            }

            if (this.mode === 'list' || (this.mode === 'detail' && (this.model.has('isHtml') && !this.model.get('isHtml')))) {
                if (text && !this.showMoreText && !this.showMoreDisabled) {
                    let isCut = false;

                    if (text.length > this.detailMaxLength) {
                        text = text.substr(0, this.detailMaxLength);
                        isCut = true;
                    }

                    let nlCount = (text.match(/\n/g) || []).length;
                    if (nlCount > this.detailMaxNewLineCount) {
                        let a = text.split('\n').slice(0, this.detailMaxNewLineCount);
                        text = a.join('\n');
                        isCut = true;
                    }

                    if (isCut) {
                        text += ' ...\n[#see-more-text]';
                    }
                }
            }

            return this.sanitizeHtml(text || '');
        },

        validateRequired: function () {
            if (this.isRequired()) {
                if (this.model.get(this.name) === '') {
                    let msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg, '.note-editor');
                    return true;
                }
            }
        },

        applyFieldPartHiding(name) {
            let showMore = $(`<a href="javascript:" data-action="seeMoreText" data-name="${name}">${this.getLanguage().translate('See more')}</a>`);
            if (!this.useIframe) {
                let htmlContainer = this.$el.find(`.html-container[data-name="${name}"]`);
                if (htmlContainer.height() > this.detailMaxHeight) {
                    htmlContainer.parent().append(showMore);
                    htmlContainer.css({maxHeight: this.detailMaxHeight + 'px', overflow: 'hidden', marginBottom: '10px'});
                }
            }
        }
    })
);