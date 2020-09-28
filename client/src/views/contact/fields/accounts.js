

Espo.define('views/contact/fields/accounts', 'views/fields/link-multiple-with-columns', function (Dep) {

    return Dep.extend({

        roleType: 'varchar',

        events: {
            'click [data-action="switchPrimary"]': function (e) {
                $target = $(e.currentTarget);
                var id = $target.data('id');

                if (!$target.hasClass('active')) {
                    this.$el.find('button[data-action="switchPrimary"]').removeClass('active').children().addClass('text-muted');
                    $target.addClass('active').children().removeClass('text-muted');
                    this.setPrimaryId(id);
                }
            }
        },

        getAttributeList: function () {
            var list = Dep.prototype.getAttributeList.call(this);
            list.push('accountId');
            list.push('accountName');
            list.push('title');
            return list;
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.primaryIdFieldName = 'accountId';
            this.primaryNameFieldName = 'accountName';
            this.primaryRoleFieldName = 'title';

            this.primaryId = this.model.get(this.primaryIdFieldName);
            this.primaryName = this.model.get(this.primaryNameFieldName);

            this.listenTo(this.model, 'change:' + this.primaryIdFieldName, function () {
                this.primaryId = this.model.get(this.primaryIdFieldName);
                this.primaryName = this.model.get(this.primaryNameFieldName);
            }, this);


            if (this.mode === 'edit' || this.mode === 'detail') {
                this.events['click a[data-action="setPrimary"]'] = function (e) {
                    var id = $(e.currentTarget).data('id');
                    this.setPrimaryId(id);
                    this.reRender();
                }
            }
        },

        setPrimaryId: function (id) {
            this.primaryId = id;
            if (id) {
                this.primaryName = this.nameHash[id];
            } else {
                this.primaryName = null;
            }

            this.trigger('change');
        },

        renderLinks: function () {
            if (this.primaryId) {
                this.addLinkHtml(this.primaryId, this.primaryName);
            }
            this.ids.forEach(function (id) {
                if (id != this.primaryId) {
                    this.addLinkHtml(id, this.nameHash[id]);
                }
            }, this);
        },

        getValueForDisplay: function () {
            if (this.mode == 'detail' || this.mode == 'list') {
                var names = [];
                if (this.primaryId) {
                    names.push(this.getDetailLinkHtml(this.primaryId, this.primaryName));
                }
                this.ids.forEach(function (id) {
                    if (id != this.primaryId) {
                        names.push(this.getDetailLinkHtml(id));
                    }
                }, this);
                return names.join('');
            }
        },

        getDetailLinkHtml: function (id, name) {
            var html = Dep.prototype.getDetailLinkHtml.call(this, id, name);
            if (this.getColumnValue(id, 'isInactive')) {
                var $el = $(html);
                $el.find('a').css('text-decoration', 'line-through');
                return $el.prop('outerHTML');
            }
            return html;
        },

        afterAddLink: function (id) {
            if (this.ids.length === 1) {
                this.primaryId = id;
                this.primaryName = this.nameHash[id];
            }
            this.controlPrimaryAppearance();
        },

        afterDeleteLink: function (id) {
            if (this.ids.length === 0) {
                this.primaryId = null;
                this.primaryName = null;
                return;
            }
            if (id === this.primaryId) {
                this.primaryId = this.ids[0];
                this.primaryName = this.nameHash[this.primaryId];
            }
            this.controlPrimaryAppearance();
        },

        controlPrimaryAppearance: function () {
            this.$el.find('li.set-primary-list-item').removeClass('hidden');
            if (this.primaryId) {
                this.$el.find('li.set-primary-list-item[data-id="'+this.primaryId+'"]').addClass('hidden');
            }
        },

        addLinkHtml: function (id, name) {
            if (this.mode == 'search') {
                return Dep.prototype.addLinkHtml.call(this, id, name);
            }

            var $el = Dep.prototype.addLinkHtml.call(this, id, name);

            var isPrimary = (id == this.primaryId);

            var $a = $(
                '<a href="javascript:" data-action="setPrimary" data-id="' + id+ '"</a>' +
                this.translate('Set Primary', 'labels', 'Account') +
                '</a>'
            );

            var $li = $('<li class="set-primary-list-item" data-id="'+id+'">').append($a);

            if (isPrimary || this.ids.length === 1) {
                $li.addClass('hidden');
            }

            $el.find('ul.dropdown-menu').append($li);


            if (this.getColumnValue(id, 'isInactive')) {
                $el.find('div.link-item-name').css('text-decoration', 'line-through');
            }
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
        },

        fetch: function () {
            var data = Dep.prototype.fetch.call(this);

            data[this.primaryIdFieldName] = this.primaryId;
            data[this.primaryNameFieldName] = this.primaryName;
            data[this.primaryRoleFieldName] = (this.columns[this.primaryId] || {}).role || '';

            data.accountIsInactive = (this.columns[this.primaryId] || {}).isInactive || false;

            if (!this.primaryId) {
                data[this.primaryRoleFieldName] = null;
                data.accountIsInactive = null;
            }

            return data;
        }

    });

});
