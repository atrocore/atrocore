

Espo.define('views/fields/user-with-avatar', 'views/fields/user', function (Dep) {

    return Dep.extend({

        listTemplate: 'fields/user-with-avatar/list',

        detailTemplate: 'fields/user-with-avatar/detail',

        data: function () {
            var o = _.extend({}, Dep.prototype.data.call(this));
            if (this.mode == 'detail') {
                o.avatar = this.getAvatarHtml();
            }
            return o;
        },

        getAvatarHtml: function () {
            return this.getHelper().getAvatarHtml(this.model.get(this.idName), 'small', 14, 'avatar-link');
        }

    });
});
