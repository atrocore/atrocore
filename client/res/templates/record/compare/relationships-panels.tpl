{{#each relationshipsPanels}}

 <div class="panel panel-default panel-{{name}}" >
        <div class="panel-heading">
            <h4 class="panel-title">
              {{label}}
            </h4>
        </div>
        <div class="panel-body">
             <div class="list-container" data-panel="{{name}}">
             <div class="list">
                 <table class="table full-table table-striped table-fixed table-scrolled table-bordered" >
                     <thead>
                     <tr>
                         {{#each ../columns}}
                        <th colspan="{{itemColumnCount}}" class="text-center">
                             {{#if link}}
                                <a href="#{{../../scope}}/view/{{name}}"> {{label}}</a>
                             {{else}}
                                {{name}}
                             {{/if}}
                        </th>
                        {{/each}}
                     </tr>
                     </thead>
                     <tbody>
                          <tr class="list-row data-id="{{attributeId}}">
                            <td class="cell" colspan="{{../columnsLength}}"> {{translate 'Loading...'}}</td>
                         </tr>
                          <tr class="list-row" >
                            <td class="cell" colspan="{{../columnsLength}}"> {{translate 'Loading...'}}</td>
                         </tr>
                     </tbody>
                 </table>
            </div>

        </div>
    </div>
 </div>
{{/each}}
