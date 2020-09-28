

Espo.define('views/modals/image-crop', ['views/modal', 'lib!Cropper'], function (Dep, Cropper) {

    return Dep.extend({

        cssName: 'image-crop',

        template: 'modals/image-crop',

        events: {
            'click [data-action="zoomIn"]': function () {
                this.$img.cropper('zoom', 0.1);
            },
            'click [data-action="zoomOut"]': function () {
                this.$img.cropper('zoom', -0.1);
            }
        },

        setup: function () {
            this.header = null;

            this.buttonList = [
                {
                    name: 'crop',
                    label: 'Submit',
                    style: 'primary'
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.on('remove', function () {
                if (this.$img.size()) {
                    this.$img.cropper('destroy');
                    this.$img.parent().empty();
                }
            }, this);
        },

        afterRender: function () {
            var $img = this.$img = $('<img>').attr('src', this.options.contents).addClass('hidden');

            this.$el.find('.image-container').append($img);

            setTimeout(function () {
                $img.cropper({
                    aspectRatio: 1,
                    movable: true,
                    resizable: true,
                    rotatable: false,
                });
            }, 50);

        },

        actionCrop: function () {
            var dataUrl = this.$img.cropper('getDataURL', 'image/jpeg');
            this.trigger('crop', dataUrl);
            this.close();
        }

    });
});

