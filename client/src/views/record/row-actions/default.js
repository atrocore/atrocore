

Espo.define('views/record/row-actions/default', 'view', function (Dep) {

    return Dep.extend({

        template: 'record/row-actions/default',

        setup: function () {
            this.options.acl = this.options.acl || {};
        },

        afterRender: function () {
            var $dd = this.$el.find('button[data-toggle="dropdown"]').parent();

            var isChecked = false;
            $dd.on('show.bs.dropdown', function () {
                var $el = this.$el.closest('.list-row');
                isChecked = false;
                if ($el.hasClass('active')) {
                    isChecked = true;
                }
                $el.addClass('active');
            }.bind(this));
            $dd.on('hide.bs.dropdown', function () {
                if (!isChecked) {
                    this.$el.closest('.list-row').removeClass('active');
                }
            }.bind(this));
        },

        getActionList: function () {
            var list = [{
                action: 'quickView',
                label: 'View',
                data: {
                    id: this.model.id
                },
                link: '#' + this.model.name + '/view/' + this.model.id
            }];
            if (this.options.acl.edit) {
                list.push({
                    action: 'quickEdit',
                    label: 'Edit',
                    data: {
                        id: this.model.id
                    },
                    link: '#' + this.model.name + '/edit/' + this.model.id
                });
            }
            if (this.options.acl.delete) {
                list.push({
                    action: 'quickRemove',
                    label: 'Remove',
                    data: {
                        id: this.model.id
                    }
                });
            }
            return list;
        },

        data: function () {
            return {
                acl: this.options.acl,
                actionList: this.getActionList(),
                scope: this.model.name
            };
        }
    });

});
