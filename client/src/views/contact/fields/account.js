

Espo.define('views/contact/fields/account', 'views/fields/link', function (Dep) {

    return Dep.extend({

        getAttributeList: function () {
            var list = Dep.prototype.getAttributeList.call(this);
            list.push('accountIsInactive');
            return list;
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if (this.mode === 'list' || this.mode === 'detail') {
                if (this.model.get('accountIsInactive')) {
                    this.$el.find('a').css('textDecoration', 'line-through');
                }
            }
        }
    });

});
