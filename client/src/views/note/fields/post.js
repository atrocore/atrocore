

Espo.define('views/note/fields/post', ['views/fields/text', 'lib!Textcomplete'], function (Dep, Textcomplete) {

    return Dep.extend({

        rowsDefault: 1,

        seeMoreText: false,

        events: _.extend({
            'input textarea': function (e) {
                this.controlTextareaHeight();
            },
        }, Dep.prototype.events),

        setup: function () {
            Dep.prototype.setup.call(this);
        },

        controlTextareaHeight: function (lastHeight) {
            var scrollHeight = this.$element.prop('scrollHeight');
            var clientHeight = this.$element.prop('clientHeight');

            if (clientHeight === lastHeight) return;

            if (scrollHeight > clientHeight) {
                this.$element.attr('rows', this.$element.prop('rows') + 1);
                this.controlTextareaHeight(clientHeight);
            }

            if (this.$element.val().length === 0) {
                this.$element.attr('rows', 1);
            }
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.$element.attr('placeholder', this.translate('writeMessage', 'messages', 'Note'));

            this.$textarea = this.$element;
            var $textarea = this.$textarea;

            $textarea.off('drop');
            $textarea.off('dragover');
            $textarea.off('dragleave');

            this.$textarea.on('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var e = e.originalEvent;
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                    this.trigger('add-files', e.dataTransfer.files);
                }
                this.$textarea.attr('placeholder', originalPlaceholderText);
            }.bind(this));

            var originalPlaceholderText = this.$textarea.attr('placeholder');

            this.$textarea.on('dragover', function (e) {
                e.preventDefault();
                this.$textarea.attr('placeholder', this.translate('dropToAttach', 'messages'));
            }.bind(this));
            this.$textarea.on('dragleave', function (e) {
                e.preventDefault();
                this.$textarea.attr('placeholder', originalPlaceholderText);
            }.bind(this));

            var assignmentPermission = this.getAcl().get('assignmentPermission');

            var buildUserListUrl = function (term) {
                var url = 'User?orderBy=name&limit=7&q=' + term + '&' + $.param({'primaryFilter': 'active'});
                if (assignmentPermission == 'team') {
                    url += '&' + $.param({'boolFilterList': ['onlyMyTeam']})
                }
                return url;
            }.bind(this);

            if (assignmentPermission !== 'no') {
                this.$element.textcomplete([{
                    match: /(^|\s)@(\w*)$/,
                    search: function (term, callback) {
                        if (term.length == 0) {
                            callback([]);
                            return;
                        }
                        $.ajax({
                            url: buildUserListUrl(term)
                        }).done(function (data) {
                            callback(data.list)
                        });
                    },
                    template: function (mention) {
                        return mention.name + ' <span class="text-muted">@' + mention.userName + '</span>';
                    },
                    replace: function (o) {
                        return '$1@' + o.userName + '';
                    }
                }],{
                    zIndex: 1100
                });

                this.once('remove', function () {
                    if (this.$element.size()) {
                        this.$element.textcomplete('destroy');
                    }
                }, this);
            }
        },

        validateRequired: function () {
            if (this.isRequired()) {
                if ((this.model.get('attachmentsIds') || []).length) {
                    return false;
                }
            }
            return Dep.prototype.validateRequired.call(this);
        },


    });

});