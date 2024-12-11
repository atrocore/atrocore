<div class="list">
    <table class="table full-table table-striped  table-fixed table-scrolled table-bordered" >
        <thead>
        <tr>
            <th style="width: 20%">{{ translate 'instance' scope='Synchronization' }}</th>
            <th style="width: 40%;" colspan="{{columnCountCurrent}}" class="text-center">
                {{translate 'current' scope='Synchronization' category='labels'}}
            </th>
            {{#each colummns}}
            <th style="width: 40%" colspan="{{itemColumnCount}}" class="text-center">
                {{name}}
                {{#if _error}}
                <br>
                <span class="danger"> ({{_error}})</span>
                {{/if}}
            </th>
            {{/each}}
        </tr>
        </thead>
        <tbody>
        {{#each relationshipsFields}}
        <tr class="list-row" >
            <td class="cell">{{translate field scope=../scope category='fields'}}</td>
            {{#if currentViewKeys }}
            {{#each currentViewKeys}}
            <td class="cell" data-field="{{key}}">
                {{{var key ../../this }}}
            </td>
            {{/each}}
            {{else}}
            <td class="cell"></td>
            {{/if}}
            {{#each othersModelsKeyPerInstances}}
            {{#if this }}
            {{#each this }}
            <td class="cell" data-field="{{key}}">
                {{{var key ../../../this}}}
            </td>
            {{/each}}
            {{else}}
                <td class="cell"></td>
            {{/if}}
            {{/each}}
        </tr>
        {{/each}}
        </tbody>
    </table>
</div>

