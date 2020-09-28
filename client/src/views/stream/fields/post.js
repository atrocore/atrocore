

Espo.define('views/stream/fields/post', 'views/fields/text', function (Dep) {

    return Dep.extend({

        getValueForDisplay: function () {
            var text = Dep.prototype.getValueForDisplay.call(this);

            if (this.mode == 'detail' || this.mode == 'list') {
                var mentionData = (this.model.get('data') || {}).mentions || {};

                Object.keys(mentionData).sort(function (a, b) {
                    return a.length < b.length
                }).forEach(function (item) {
                    var part = '[' + mentionData[item].name + '](#User/view/'+mentionData[item].id + ')';
                    text = text.replace(new RegExp(item, 'g'), part);
                });
            }

            return text;
        },

    });

});
