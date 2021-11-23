<div class="page-header"><h3>{{translate 'Administration' scope='Admin'}}</h3></div>

<div class="admin-content">
    <div class="row">
        <div class="col-md-7">
            <div class="admin-tables-container">
                {{#each panelDataList}}
                <div>
                    <h4>{{translate label scope='Admin'}}</h4>
                    <table class="table table-bordered table-admin-panel" data-name="{{name}}">
                        {{#each itemList}}
                        <tr>
                            <td>
                                <a href="{{url}}">{{translate label scope='Admin' category='labels'}}</a>
                            </td>
                            <td>{{translate description scope='Admin' category='descriptions'}}</td>
                        </tr>
                        {{/each}}
                    </table>
                </div>
                {{/each}}
            </div>
        </div>
        <div class="col-md-5 admin-right-column">
            <div class="notifications-panel-container">{{{notificationsPanel}}}</div>
            <div class="twitter-tape">
                <a class="twitter-timeline" data-lang="en" data-dnt="true" data-width="500" href="https://twitter.com/atrocore_global?ref_src=twsrc%5Etfw">Tweets by atrocore_global</a>
                <script>
                    $('#twitter-wjs').remove();
                    window.twttr = (function(d, s, id) {
                        var js, fjs = d.getElementsByTagName(s)[0],
                            t = window.twttr || {};
                        if (d.getElementById(id)) return t;
                        js = d.createElement(s);
                        js.id = id;
                        js.src = "https://platform.twitter.com/widgets.js";
                        fjs.parentNode.insertBefore(js, fjs);
                        t._e = [];
                        t.ready = function(f) {
                            t._e.push(f);
                        };
                        return t;
                    }(document, "script", "twitter-wjs"));

                    twttr.ready(function (twttr) {
                        twttr.events.bind(
                            'rendered',
                            function (event) {
                                let frame = event.target;
                                let h1 = frame.contentWindow.document.getElementsByTagName('h1');

                                if (h1) {
                                    h1[0].style.fontSize = '18px';
                                    h1[0].style.lineHeight = '18px';
                                    h1[0].style.marginTop = '8px';
                                }

                                $('.admin-right-column').css({
                                    animation: 'fadein 1s',
                                    opacity: 1
                                });
                            }
                        );
                    });
                </script>
            </div>
        </div>
    </div>
</div>