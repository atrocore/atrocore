<div class="detail" id="{{id}}">
    <div class="detail-button-container button-container record-buttons clearfix">
        <div class="btn-group pull-left" role="group">
            {{#each buttonList}}{{button name scope=../../entityType label=label style=style hidden=hidden html=html}}{{/each}}
        </div>
        <div class="panel-navigation panel-left pull-left">{{{panelDetailNavigation}}}</div>
        <div class="clearfix"></div>
    </div>

    <div class="row">
        <div class="overview list col-md-12">
            <table class="table full-table table-striped table-fixed table-bordered-inside">
                <thead>
                   <tr>
                       <th></th>
                       <th>
                           Entity(system)
                       </th>
                       <th>
                           Entity (Connection: atrocore local)
                       </th>
                   </tr>

                </thead>
                <tbody>
                    <tr>
                        <td>Name</td>
                        <td>Iphone</td>
                        <td>Iphone 12</td>
                    </tr>
                    <tr>
                        <td>Name</td>
                        <td>Iphone</td>
                        <td>Iphone 12</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>