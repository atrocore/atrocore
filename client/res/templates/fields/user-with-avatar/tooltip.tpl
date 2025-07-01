<div class="user-tooltip-inner">
    <div class="top">
        {{{avatar}}}
        <div class="info">
            <div class="name">{{name}}</div>
            <div class="username">@{{username}}</div>
        </div>
    </div>
    {{#if items}}
        <table>
            {{#each items}}
                <tr>
                    <td width="30%">{{translate @key category='fields' scope='User'}}</td>
                    <td>{{./this}}</td>
                </tr>
            {{/each}}
        </table>
    {{/if}}
</div>