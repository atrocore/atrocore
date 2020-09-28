

Espo.define('views/attachment/fields/name', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        detailTemplate: 'attachment/fields/name/detail',

        data: function () {
            var data = Dep.prototype.data.call(this);

            var url = this.getBasePath() + '?entryPoint=download&id=' + this.model.id;
            if (this.getUser().get('portalId')) {
                url += '&portalId=' + this.getUser().get('portalId');
            }

            data.url = url;
            return data;
        }

    });
});
