

Espo.define('views/fields/map', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'map',

        detailTemplate: 'fields/map/detail',

        addressField: null,

        provider: null,

        height: 300,

        data: function () {
            var data = Dep.prototype.data.call(this);
            return data;
        },

        setup: function () {
            this.addressField = this.name.substr(0, this.name.length - 3);

            this.provider = this.params.provider;
            this.height = this.params.height || this.height;

            var addressAttributeList = Object.keys(this.getMetadata().get('fields.address.fields') || {}).map(
                function (a) {
                    return this.addressField + Espo.Utils.upperCaseFirst(a);
                },
                this
            );

            this.listenTo(this.model, 'sync', function (model) {
                var isChanged = false;
                addressAttributeList.forEach(function (attribute) {
                    if (model.hasChanged(attribute)) {
                        isChanged = true;
                    }
                }, this);

                if (isChanged && this.isRendered()) {
                    this.reRender();
                }
            }, this);

            this.listenTo(this.model, 'after:save', function () {
                if (this.isRendered()) {
                    this.reRender();
                }
            }, this);
        },

        hasAddress: function () {
            return this.addressData.city || this.addressData.postalCode;
        },

        afterRender: function () {
            this.addressData = {
                city: this.model.get(this.addressField + 'City'),
                street: this.model.get(this.addressField + 'Street'),
                postalCode: this.model.get(this.addressField + 'PostalCode'),
                country: this.model.get(this.addressField + 'Country'),
                state: this.model.get(this.addressField + 'State')
            };

            if (this.hasAddress()) {
                var methodName = 'afterRender' + this.provider.replace(/\s+/g, '');
                if (typeof this[methodName] === 'function') {
                    this[methodName]();
                }
            }
        },

        afterRenderGoogle: function () {
            if (window.google && window.google.maps) {
                this.initMapGoogle();
            } else {
                window.mapapiloaded = function () {
                    this.initMapGoogle();
                }.bind(this);
                var src = 'https://maps.googleapis.com/maps/api/js?callback=mapapiloaded';
                var apiKey = this.getConfig().get('googleMapsApiKey');
                if (apiKey) {
                    src += '&key=' + apiKey;
                }

                var s = document.createElement('script');
                s.setAttribute('async', 'async');
                s.src = src;
                document.head.appendChild(s);
            }
        },

        initMapGoogle: function () {
            this.$el.find('.map').css('height', this.height + 'px');

            var geocoder = new google.maps.Geocoder();

            try {
                var map = new google.maps.Map(this.$el.find('.map').get(0), {
                    zoom: 15,
                    center: {lat: 0, lng: 0},
                    scrollwheel: false
                });
            } catch (e) {
                console.error(e.message);
                return;
            }

            var address = '';

            if (this.addressData.street) {
                address += this.addressData.street;
            }

            if (this.addressData.city) {
                if (address != '') {
                    address += ', ';
                }
                address += this.addressData.city;
            }

            if (this.addressData.state) {
                if (address != '') {
                    address += ', ';
                }
                address += this.addressData.state;
            }

            if (this.addressData.postalCode) {
                if (this.addressData.state || this.addressData.city) {
                    address += ' ';
                } else {
                    if (address) {
                        address += ', ';
                    }
                }
                address += this.addressData.postalCode;
            }

            if (this.addressData.country) {
                if (address != '') {
                    address += ', ';
                }
                address += this.addressData.country;
            }

            geocoder.geocode({'address': address}, function(results, status) {
                if (status === google.maps.GeocoderStatus.OK) {
                    map.setCenter(results[0].geometry.location);
                    var marker = new google.maps.Marker({
                        map: map,
                        position: results[0].geometry.location
                    });
                }
            }.bind(this));

        }

    });

});
