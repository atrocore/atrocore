{{#each relationshipsPanels}}

 <div class="panel panel-default panel-{{name}}" >
        <div class="panel-heading">
            <h4 class="panel-title">
              {{label}}
            </h4>
        </div>
        <table class="table full-table table-striped table-fixed table-bordered" data-panel="{{name}}">
             <thead>
             <tr>
                 <th>{{ translate 'instance' scope='Synchronization' }}</th>
                 <th>
                     {{translate 'current' scope='Synchronization' category='labels'}}
                 </th>

                 <th>
                     {{translate 'Other'}}
                 </th>
                 <th width="25"></th>
             </tr>
             </thead>
             <tbody>
                  <tr class="list-row data-id="{{attributeId}}">
                    <td class="cell" colspan="4"> {{translate 'Loading...'}}</td>
                 </tr>
                  <tr class="list-row" >
                    <td class="cell" colspan="4"> {{translate 'Loading...'}}</td>
                 </tr>
             </tbody>
         </table>
        <div class="panel-scroll hidden">
            <div></div>
        </div>
 </div>
{{/each}}