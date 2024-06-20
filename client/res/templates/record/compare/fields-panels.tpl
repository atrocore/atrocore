
<table class="table full-table table-striped table-fixed table-bordered-inside">
    <thead>
        <tr>
            <th>{{ translate 'instance' scope='Synchronization' }}</th>
            <th>
                {{translate 'current' scope='Synchronization' category='labels'}}
            </th>
            {{#each distantModels}}
            <th>
                {{_connection}}
            </th>
            {{/each}}
        </tr>

    </thead>
    <tbody>
        {{#each fieldList}}
        <tr>
            <td colspan="3"><h5>{{translate label category='labels' scope='Global'}}</h5></td>
        </tr>
        {{#each fields}}
        <tr class="list-row {{#if  different}} danger {{/if}}" data-field="{{field}}">
            <td class="cell">{{translate label scope=../../scope category='fields'}}</td>
            <td class="cell current">
                {{{var current ../../this}}}
            </td>
            {{#each others}}
            <td class="cell other{{index}}">
                {{{var other ../../../this}}}
            </td>
            {{/each}}
        </tr>
        {{/each}}
        {{/each}}
        </tbody>
</table>
<style>
    .hidden-cell{
        display:none !important;
    }
</style>

