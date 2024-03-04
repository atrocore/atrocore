<div class="compare">
    <table class="table table-bordered">
        <thead>
        <tr>
            <th width="20%"></th>
            {{#each dataList}}
            <th width="5%">
               {{field}}
            </th>
            <th width="{{../width}}%">
                {{name}}
            </th>
            {{/each}}
        </tr>
        </thead>
        <tbody>
        <tr>
            <td align="right">
                {{translate 'createdAt' scope=../scope category='fields'}}
            </td>
            {{#each dataList}}
            <td></td>
            <td data-id="{{id}}">
                <div class="field" data-name="createdAt">
                    {{{var createdAtViewName ../../this}}}
                </div>
            </td>
            {{/each}}
        </tr>
        {{#each rows}}
        <tr>
            <td align="right">
                {{translate name scope=../scope category='fields'}}
            </td>
            {{#each columns}}
            <td data-id="{{id}}">
                <div class="field" data-name="{{../name}}">
                    {{{var fieldVariable ../../this}}}
                </div>
            </td>
            {{/each}}
        </tr>
        {{/each}}
        </tbody>
    </table>
</div>
