<div class="list">
    <table class="table full-table table-striped table-scrolled table-bordered-inside">
        <thead>
        <tr>
            <th>{{ translate 'instance' scope='Synchronization' }}</th>
            <th>
                {{translate 'current' scope='Synchronization' category='labels'}}
            </th>
            {{#each instances}}
            <th>
                {{name}}
            </th>
            {{/each}}
            <th width="25" style="width:25px"></th>
        </tr>

        </thead>
        <tbody>
        {{#each fieldList}}
        <tr>
            <td colspan="4"><h5>{{translate label category='labels' scope='Global'}}</h5></td>
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

            {{#if showDetailsComparison }}
            <td class="cell" data-name="buttons">
                <div class="list-row-buttons btn-group pull-right">
                    <button type="button" class="btn btn-link btn-sm dropdown-toggle" data-toggle="dropdown">
                        <span class="fas fa-ellipsis-v"></span>
                    </button>
                    <ul class="dropdown-menu pull-right">
                        {{#if isLinkMultiple }}
                        <li> <a class="disabled panel-title">  {{translate 'detailsComparison' scope='Synchronization'}}</a></li>
                        {{#each values }}
                        <li>
                            <a href="#" class="action" data-action="detailsComparison"
                               data-scope="{{../foreignScope}}"
                               data-id="{{id}}">
                                {{ name }}
                            </a>
                        </li>
                        {{/each}}
                        {{else}}
                        <li><a href="#" class="action" data-action="detailsComparison" data-scope="{{foreignScope}}" data-id="{{foreignId}}">{{translate 'detailsComparison' scope='Synchronization'}}</a></li>
                        {{/if}}
                    </ul>
                </div>
            </td>
            {{else}}
            <td></td>
            {{/if}}
        </tr>
        {{/each}}
        {{/each}}
        </tbody>
    </table>
</div>
<style>
    .hidden-cell{
        display:none !important;
    }
</style>

