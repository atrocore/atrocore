

Espo.define('views/email-template/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        duplicateAction: true,

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.listenToInsertField();
        },

        listenToInsertField: function () {
            var fieldView = this.getFieldView('insertField');
            if (fieldView) {
                this.listenTo(fieldView, 'insert-field', function (o) {
                    var tag = '{' + o.entityType + '.' + o.field + '}';

                    var bodyView = this.getFieldView('body');

                    if (this.model.get('isHtml')) {
                        bodyView.$summernote.summernote('insertText', tag);
                    } else {
                        var $body = bodyView.$element;
                        var text = $body.val();
                        text += tag;
                        $body.val(text);
                    }

                    var bodyView = this.getFieldView('body');


                }.bind(this));
            };
        },

    });

});
