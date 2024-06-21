<div class="list">
    <table class="table full-table table-striped  table-fixed table-scrolled table-bordered" >
        <thead>
        <tr>
            <th >{{ translate 'instance' scope='Synchronization' }}</th>
            <th colspan="{{columnCountCurrent}}" class="text-center">
                {{translate 'current' scope='Synchronization' category='labels'}}
            </th>
            {{#each instanceNames}}
            <th colspan="{{../columnCountCurrent}}" class="text-center">
                {{name}}
            </th>
            {{/each}}
        </tr>
        </thead>
        <tbody>
        {{#each relationshipsFields}}
        <tr class="list-row" >
            <td class="cell">{{translate field scope=../scope category='fields'}}</td>
            {{#each currentViewKeys}}
            <td class="cell" data-field="{{key}}">
                {{{var key ../../this}}}
            </td>
            {{/each}}
            {{#each othersModelsKeyPerInstances}}
            {{#each this }}
            <td class="cell" data-field="{{key}}">
                {{{var key ../../../this}}}
            </td>
            {{/each}}
            {{/each}}
        </tr>
        {{/each}}
        </tbody>
    </table>
</div>

